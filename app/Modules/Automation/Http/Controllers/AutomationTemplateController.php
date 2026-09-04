<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Services\AutomationTemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = AutomationTemplateRegistry::all();

        return Inertia::render('Automations/Templates', [
            'templates' => $templates,
        ]);
    }

    public function install(Request $request, string $key): RedirectResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $templates = collect(AutomationTemplateRegistry::all());
        $template = $templates->firstWhere('key', $key);

        if (! $template) {
            return back()->with('error', __('Automation template not found.'));
        }

        if ($key === 'unresponsive_lead_drip') {
            $nodes = [
                [
                    'id' => 'node_trigger',
                    'type' => 'trigger',
                    'data' => ['trigger' => 'contact.created', 'label' => 'New Lead Ingestion'],
                    'position' => ['x' => 250, 'y' => 50],
                ],
                [
                    'id' => 'node_check_reply',
                    'type' => 'condition',
                    'data' => [
                        'field' => 'no_reply',
                        'operator' => 'equals',
                        'value' => 'true',
                        'label' => 'Check Customer Response',
                    ],
                    'position' => ['x' => 250, 'y' => 160],
                ],
                [
                    'id' => 'node_wait_2h',
                    'type' => 'wait',
                    'data' => ['amount' => 2, 'unit' => 'hours', 'label' => 'Wait 2 Hours'],
                    'position' => ['x' => 250, 'y' => 280],
                ],
                [
                    'id' => 'node_followup_whatsapp',
                    'type' => 'send_whatsapp',
                    'data' => [
                        'body' => 'Hi {{contact.first_name}}, we noticed you reached out earlier. Did you have any questions about our solutions?',
                        'label' => 'Follow-up WhatsApp',
                    ],
                    'position' => ['x' => 250, 'y' => 400],
                ],
                [
                    'id' => 'node_wait_1d',
                    'type' => 'wait',
                    'data' => ['amount' => 1, 'unit' => 'days', 'label' => 'Wait 1 Day'],
                    'position' => ['x' => 250, 'y' => 520],
                ],
                [
                    'id' => 'node_send_email',
                    'type' => 'send_email',
                    'data' => [
                        'subject' => 'Following up on your Growbridge inquiry - {{contact.first_name}}',
                        'body' => 'Hi {{contact.first_name}}, just checking in to see if you would like to schedule a quick 10-minute demo this week.',
                        'label' => 'Follow-up Nurture Email',
                    ],
                    'position' => ['x' => 250, 'y' => 640],
                ],
                [
                    'id' => 'node_create_task',
                    'type' => 'create_task',
                    'data' => [
                        'title' => 'Direct Sales Outreach: Call {{contact.full_name}}',
                        'description' => 'Lead has been sent WhatsApp and Email follow-ups without response. Please initiate direct phone call.',
                        'priority' => 'urgent',
                        'due_in_minutes' => 30,
                        'label' => 'Create Salesperson Task',
                    ],
                    'position' => ['x' => 250, 'y' => 760],
                ],
            ];

            $edges = [
                ['id' => 'e1', 'source' => 'node_trigger', 'target' => 'node_check_reply'],
                ['id' => 'e2', 'source' => 'node_check_reply', 'target' => 'node_wait_2h', 'sourceHandle' => 'true'],
                ['id' => 'e3', 'source' => 'node_wait_2h', 'target' => 'node_followup_whatsapp'],
                ['id' => 'e4', 'source' => 'node_followup_whatsapp', 'target' => 'node_wait_1d'],
                ['id' => 'e5', 'source' => 'node_wait_1d', 'target' => 'node_send_email'],
                ['id' => 'e6', 'source' => 'node_send_email', 'target' => 'node_create_task'],
            ];
        } else {
            // Generate sample nodes & edges based on template definition
            $nodes = [
                [
                    'id' => 'trigger_1',
                    'type' => 'trigger',
                    'data' => ['trigger' => $template['trigger'], 'label' => 'Start Flow'],
                    'position' => ['x' => 250, 'y' => 50],
                ],
                [
                    'id' => 'action_ai_1',
                    'type' => 'ai_reply',
                    'data' => ['prompt' => 'Welcome customer and identify intent.', 'label' => 'AI Response'],
                    'position' => ['x' => 250, 'y' => 180],
                ],
                [
                    'id' => 'action_delay_1',
                    'type' => 'wait',
                    'data' => ['amount' => 1, 'unit' => 'days', 'label' => 'Smart Delay (1 Day)'],
                    'position' => ['x' => 250, 'y' => 310],
                ],
                [
                    'id' => 'action_followup_1',
                    'type' => 'ai_followup',
                    'data' => ['objective' => 'Check in regarding their inquiry', 'label' => 'Contextual AI Follow-up'],
                    'position' => ['x' => 250, 'y' => 440],
                ],
            ];

            $edges = [
                ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'action_ai_1'],
                ['id' => 'e2', 'source' => 'action_ai_1', 'target' => 'action_delay_1'],
                ['id' => 'e3', 'source' => 'action_delay_1', 'target' => 'action_followup_1'],
            ];
        }

        $automation = Automation::create([
            'workspace_id' => $workspaceId,
            'name' => $template['title'],
            'description' => $template['description'],
            'trigger_type' => $template['trigger'],
            'nodes' => $nodes,
            'edges' => $edges,
            'status' => 'draft',
        ]);

        return redirect()->route('client.automations.builder', $automation->uuid)
            ->with('success', __('Template installed successfully. You can now customize nodes and activate.'));
    }
}
