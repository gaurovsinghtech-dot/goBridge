<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmTask;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\Crm\CrmAuditService;
use App\Services\CustomerJourney\CustomerJourneyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmTaskController extends Controller
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService,
        private readonly CrmAuditService $auditService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $bucket = $request->query('bucket', 'all'); // all, overdue, today, upcoming

        $tasksQuery = CrmTask::where('workspace_id', $workspace->id)
            ->with(['contact', 'deal', 'assignedUser', 'createdBy'])
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('contact', fn ($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->priority, fn ($q) => $q->where('priority', $request->priority))
            ->when($request->owner_id, fn ($q) => $q->where('assigned_user_id', $request->owner_id));

        if ($bucket === 'overdue') {
            $tasksQuery->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->where('due_at', '<', Carbon::now());
        } elseif ($bucket === 'today') {
            $tasksQuery->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->whereDate('due_at', Carbon::today());
        } elseif ($bucket === 'upcoming') {
            $tasksQuery->where('status', '!=', 'completed')
                ->where('status', '!=', 'cancelled')
                ->where('due_at', '>', Carbon::today()->endOfDay());
        }

        $tasks = $tasksQuery
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'in_progress' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->paginate(25)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $tasks->items(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'total' => $tasks->total(),
                ],
            ]);
        }

        // Summary counts for Follow-up dashboard
        $followUpSummary = $this->getFollowUpSummary($workspace->id);
        $teamMembers = User::where('workspace_id', $workspace->id)->where('status', 'active')->get(['id', 'name', 'email']);

        return Inertia::render('Crm/Tasks', [
            'tasks' => $tasks,
            'teamMembers' => $teamMembers,
            'summary' => $followUpSummary,
            'filters' => $request->only('search', 'status', 'priority', 'owner_id', 'bucket'),
        ]);
    }

    public function followUps(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $summary = $this->getFollowUpSummary($workspace->id);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    private function getFollowUpSummary(int $workspaceId): array
    {
        $now = Carbon::now();
        $today = Carbon::today();

        $overdue = CrmTask::where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('due_at', '<', $now)
            ->with(['contact', 'assignedUser'])
            ->orderBy('due_at')
            ->take(15)
            ->get();

        $dueToday = CrmTask::where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('due_at', $today)
            ->with(['contact', 'assignedUser'])
            ->orderBy('due_at')
            ->take(15)
            ->get();

        $upcoming = CrmTask::where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('due_at', '>', $today->copy()->endOfDay())
            ->with(['contact', 'assignedUser'])
            ->orderBy('due_at')
            ->take(15)
            ->get();

        return [
            'overdue_count' => CrmTask::where('workspace_id', $workspaceId)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', $now)->count(),
            'due_today_count' => CrmTask::where('workspace_id', $workspaceId)->whereNotIn('status', ['completed', 'cancelled'])->whereDate('due_at', $today)->count(),
            'upcoming_count' => CrmTask::where('workspace_id', $workspaceId)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '>', $today->copy()->endOfDay())->count(),
            'overdue_tasks' => $overdue,
            'due_today_tasks' => $dueToday,
            'upcoming_tasks' => $upcoming,
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'deal_id' => ['nullable', 'exists:crm_deals,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:pending,in_progress,completed,overdue,cancelled'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $task = CrmTask::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $validated['contact_id'] ?? null,
            'lead_id' => $validated['lead_id'] ?? null,
            'deal_id' => $validated['deal_id'] ?? null,
            'created_by_id' => $request->user()->id,
            'assigned_user_id' => $validated['assigned_user_id'] ?? $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'] ?? 'pending',
        ]);

        if ($task->contact_id) {
            $this->journeyService->recordEvent(
                contactId: $task->contact_id,
                workspaceId: $workspace->id,
                eventType: 'crm_task_created',
                channel: 'crm',
                title: "Task Scheduled: {$task->title}",
                description: 'Task due on '.($task->due_at ? $task->due_at->toFormattedDateString() : 'N/A'),
                metadata: ['task_id' => $task->id, 'priority' => $task->priority]
            );

            if ($task->due_at) {
                Contact::where('id', $task->contact_id)->update(['next_follow_up_at' => $task->due_at]);
            }
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'task_created',
            entityType: 'task',
            entityId: $task->id,
            newValues: $task->toArray(),
            description: "Task '{$task->title}' scheduled."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task scheduled successfully.',
                'data' => $task->load(['contact', 'deal', 'assignedUser']),
            ], 201);
        }

        return back()->with('success', __('Task scheduled successfully.'));
    }

    public function show(Request $request, CrmTask $task): JsonResponse|Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 403);

        $task->load(['contact', 'deal', 'assignedUser', 'createdBy']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $task,
            ]);
        }

        return Inertia::render('Crm/Tasks/Show', ['task' => $task]);
    }

    public function update(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:pending,in_progress,completed,overdue,cancelled'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'deal_id' => ['nullable', 'exists:crm_deals,id'],
        ]);

        $oldValues = $task->toArray();
        $task->update($validated);

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'task_updated',
            entityType: 'task',
            entityId: $task->id,
            oldValues: $oldValues,
            newValues: $task->toArray(),
            description: "Task '{$task->title}' updated."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully.',
                'data' => $task->fresh(['contact', 'deal', 'assignedUser']),
            ]);
        }

        return back()->with('success', 'Task updated successfully.');
    }

    public function updateStatus(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,overdue,cancelled'],
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        if ($task->contact_id && $validated['status'] === 'completed') {
            $this->journeyService->recordEvent(
                contactId: $task->contact_id,
                workspaceId: $workspace->id,
                eventType: 'crm_task_completed',
                channel: 'crm',
                title: "Task Completed: {$task->title}",
                description: "Task was marked completed by {$request->user()->name}",
                metadata: ['task_id' => $task->id]
            );
        }

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'task_status_updated',
            entityType: 'task',
            entityId: $task->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $validated['status']],
            description: "Task '{$task->title}' status changed to {$validated['status']}."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task status updated.',
                'data' => $task->fresh(),
            ]);
        }

        return back()->with('success', __('Task updated successfully.'));
    }

    public function destroy(Request $request, CrmTask $task): RedirectResponse|JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless((int) $task->workspace_id === (int) $workspace->id, 403);

        $title = $task->title;
        $id = $task->id;
        $task->delete();

        $this->auditService->log(
            workspace: $workspace,
            actor: $request->user(),
            action: 'task_deleted',
            entityType: 'task',
            entityId: $id,
            description: "Task '{$title}' deleted."
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully.',
            ]);
        }

        return back()->with('success', 'Task deleted successfully.');
    }
}
