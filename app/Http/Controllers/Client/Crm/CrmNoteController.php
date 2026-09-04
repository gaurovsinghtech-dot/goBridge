<?php

namespace App\Http\Controllers\Client\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmNote;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\Contact;
use App\Services\CustomerJourney\CustomerJourneyService;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrmNoteController extends Controller
{
    public function __construct(
        private readonly CustomerJourneyService $journeyService,
        private readonly NotificationCenterService $notificationService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace ?? $user?->workspace ?? $user?->client?->workspaces()->first() ?? Workspace::first();

        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'content' => ['required', 'string', 'max:2000'],
            'is_private' => ['boolean'],
        ]);

        $contact = Contact::where('workspace_id', $workspace->id)->findOrFail($validated['contact_id']);

        // Extract @mentions
        preg_match_all('/@([a-zA-Z0-9_\.\-]+)/', $validated['content'], $matches);
        $mentionedUsernames = $matches[1] ?? [];
        $mentions = [];

        if (! empty($mentionedUsernames)) {
            $users = User::where('workspace_id', $workspace->id)
                ->where(function ($q) use ($mentionedUsernames) {
                    foreach ($mentionedUsernames as $un) {
                        $q->orWhere('name', 'like', "%{$un}%")->orWhere('email', 'like', "%{$un}%");
                    }
                })->get();

            foreach ($users as $u) {
                $mentions[] = ['id' => $u->id, 'name' => $u->name];

                // Send notification to mentioned user
                if ($u->id !== $request->user()->id) {
                    $this->notificationService->notify(
                        workspace: $workspace,
                        type: 'mention',
                        title: "💬 {$request->user()->name} mentioned you on {$contact->full_name}",
                        message: substr($validated['content'], 0, 120),
                        data: ['url' => "/app/crm/leads/{$contact->uuid}"],
                        user: $u,
                        priority: 'high'
                    );
                }
            }
        }

        $note = CrmNote::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'is_private' => $validated['is_private'] ?? true,
            'mentions' => $mentions,
        ]);

        $this->journeyService->recordEvent(
            contactId: $contact->id,
            workspaceId: $workspace->id,
            eventType: 'crm_internal_note',
            channel: 'crm',
            title: "Internal Note Added by {$request->user()->name}",
            description: substr($note->content, 0, 150),
            metadata: ['note_id' => $note->id, 'author' => $request->user()->name]
        );

        return back()->with('success', __('Note saved successfully.'));
    }
}
