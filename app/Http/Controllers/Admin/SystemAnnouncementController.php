<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemAnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $announcements = json_decode(SystemSetting::get('system.announcements', '[]'), true) ?: [];
        $plans = Plan::where('enabled', true)->get(['id', 'name']);

        return Inertia::render('Admin/Announcements', [
            'announcements' => $announcements,
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:info,warning,danger,success'],
            'target' => ['required', 'in:all,plan,organization'],
            'target_id' => ['nullable'],
        ]);

        $announcements = json_decode(SystemSetting::get('system.announcements', '[]'), true) ?: [];

        $newAnnouncement = [
            'id' => 'ann_'.uniqid(),
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'target' => $validated['target'],
            'target_id' => $validated['target_id'] ?? null,
            'active' => true,
            'created_at' => now()->toIso8601String(),
        ];

        array_unshift($announcements, $newAnnouncement);
        SystemSetting::set('system.announcements', json_encode($announcements));

        return back()->with('success', __('System announcement published successfully.'));
    }

    public function toggle(Request $request, string $id): RedirectResponse
    {
        $announcements = json_decode(SystemSetting::get('system.announcements', '[]'), true) ?: [];

        foreach ($announcements as &$ann) {
            if ($ann['id'] === $id) {
                $ann['active'] = ! ($ann['active'] ?? true);
                break;
            }
        }

        SystemSetting::set('system.announcements', json_encode($announcements));

        return back()->with('success', __('Announcement status updated.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $announcements = json_decode(SystemSetting::get('system.announcements', '[]'), true) ?: [];
        $filtered = array_values(array_filter($announcements, fn ($a) => $a['id'] !== $id));

        SystemSetting::set('system.announcements', json_encode($filtered));

        return back()->with('success', __('Announcement deleted.'));
    }
}
