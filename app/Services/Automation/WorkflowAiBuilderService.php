<?php

namespace App\Services\Automation;

use Illuminate\Support\Str;

class WorkflowAiBuilderService
{
    /**
     * Generate structured automation graph from a natural-language description.
     */
    public function generateFromPrompt(string $prompt, int $workspaceId): array
    {
        $promptLower = strtolower($prompt);

        // Detect channel
        $channel = 'whatsapp';
        if (str_contains($promptLower, 'instagram')) {
            $channel = 'instagram';
        } elseif (str_contains($promptLower, 'messenger') || str_contains($promptLower, 'facebook')) {
            $channel = 'messenger';
        } elseif (str_contains($promptLower, 'email')) {
            $channel = 'email';
        }

        // Detect triggers
        $triggerType = 'message.received';
        if (str_contains($promptLower, 'new lead') || str_contains($promptLower, 'created')) {
            $triggerType = 'contact.created';
        } elseif (str_contains($promptLower, 'stage') || str_contains($promptLower, 'pipeline')) {
            $triggerType = 'lead.stage_changed';
        } elseif (str_contains($promptLower, 'campaign reply')) {
            $triggerType = 'campaign.reply';
        }

        $includeVoice = str_contains($promptLower, 'call') || str_contains($promptLower, 'voice') || str_contains($promptLower, 'heyo');
        $includeAi = str_contains($promptLower, 'ai') || str_contains($promptLower, 'intent') || str_contains($promptLower, 'pricing') || str_contains($promptLower, 'support');
        $includeWait = str_contains($promptLower, 'wait') || str_contains($promptLower, 'hour') || str_contains($promptLower, 'day');

        // Extract wait duration if specified
        $waitHours = 2;
        if (preg_match('/wait\s+(\d+)\s+hour/i', $prompt, $m)) {
            $waitHours = (int) $m[1];
        } elseif (preg_match('/wait\s+(\d+)\s+day/i', $prompt, $m)) {
            $waitHours = ((int) $m[1]) * 24;
        }

        // Build nodes array
        $nodes = [];
        $edges = [];
        $y = 50;

        // 1. Trigger
        $nodes[] = [
            'id' => 'trigger-1',
            'type' => 'trigger',
            'position' => ['x' => 250, 'y' => $y],
            'data' => [
                'label' => ucfirst($channel).' Trigger',
                'event' => $triggerType,
                'channel' => $channel,
            ],
        ];

        $prevId = 'trigger-1';

        // 2. AI Intent node
        if ($includeAi) {
            $y += 100;
            $aiNodeId = 'ai-'.Str::random(6);
            $nodes[] = [
                'id' => $aiNodeId,
                'type' => 'ai_action',
                'position' => ['x' => 250, 'y' => $y],
                'data' => [
                    'label' => 'AI Intent & Sentiment Classifier',
                    'confidence_threshold' => 70,
                ],
            ];
            $edges[] = ['id' => "e-{$prevId}-{$aiNodeId}", 'source' => $prevId, 'target' => $aiNodeId];
            $prevId = $aiNodeId;
        }

        // 3. Response Message node
        $y += 100;
        $msgNodeId = 'msg-'.Str::random(6);
        $nodes[] = [
            'id' => $msgNodeId,
            'type' => "send_{$channel}",
            'position' => ['x' => 250, 'y' => $y],
            'data' => [
                'label' => 'Send '.ucfirst($channel).' Response',
                'message' => 'Hello {{contact.first_name}}, thanks for reaching out! Here is the information you requested.',
            ],
        ];
        $edges[] = ['id' => "e-{$prevId}-{$msgNodeId}", 'source' => $prevId, 'target' => $msgNodeId];
        $prevId = $msgNodeId;

        // 4. Wait node
        if ($includeWait) {
            $y += 100;
            $waitNodeId = 'wait-'.Str::random(6);
            $nodes[] = [
                'id' => $waitNodeId,
                'type' => 'wait_delay',
                'position' => ['x' => 250, 'y' => $y],
                'data' => [
                    'label' => "Wait {$waitHours} Hours",
                    'hours' => $waitHours,
                ],
            ];
            $edges[] = ['id' => "e-{$prevId}-{$waitNodeId}", 'source' => $prevId, 'target' => $waitNodeId];
            $prevId = $waitNodeId;

            // 5. Condition node
            $y += 100;
            $condNodeId = 'cond-'.Str::random(6);
            $nodes[] = [
                'id' => $condNodeId,
                'type' => 'branch_if_else',
                'position' => ['x' => 250, 'y' => $y],
                'data' => [
                    'label' => 'Has Customer Replied?',
                    'field' => 'customer.replied',
                    'operator' => 'equals',
                    'value' => true,
                ],
            ];
            $edges[] = ['id' => "e-{$prevId}-{$condNodeId}", 'source' => $prevId, 'target' => $condNodeId];

            // 6. Voice Call on NO branch
            if ($includeVoice) {
                $voiceNodeId = 'voice-'.Str::random(6);
                $nodes[] = [
                    'id' => $voiceNodeId,
                    'type' => 'voice_call',
                    'position' => ['x' => 450, 'y' => $y + 100],
                    'data' => [
                        'label' => 'AI Voice Call',
                        'prompt' => 'Friendly follow-up call regarding previous inquiry',
                    ],
                ];
                $edges[] = ['id' => "e-{$condNodeId}-{$voiceNodeId}", 'source' => $condNodeId, 'sourceHandle' => 'no', 'target' => $voiceNodeId];
            }

            // 7. Goal / AI Reply on YES branch
            $goalNodeId = 'goal-'.Str::random(6);
            $nodes[] = [
                'id' => $goalNodeId,
                'type' => 'goal_reached',
                'position' => ['x' => 50, 'y' => $y + 100],
                'data' => [
                    'label' => 'Lead Engaged / Goal Reached',
                    'goal_name' => 'Lead Qualified',
                ],
            ];
            $edges[] = ['id' => "e-{$condNodeId}-{$goalNodeId}", 'source' => $condNodeId, 'sourceHandle' => 'yes', 'target' => $goalNodeId];
        }

        $title = 'AI Workflow: '.Str::headline(Str::limit($prompt, 40));

        return [
            'name' => $title,
            'trigger_type' => $triggerType,
            'trigger_config' => ['channel' => $channel],
            'nodes' => $nodes,
            'edges' => $edges,
            'explanation' => [
                'summary' => "When triggered by a {$triggerType} event on {$channel}, the workflow classifies customer intent, delivers an instant personalized response, pauses for {$waitHours} hours, and follows up autonomously via AI voice if no response is detected.",
                'estimated_messages_per_contact' => 1,
                'estimated_calls_per_contact' => $includeVoice ? 1 : 0,
                'estimated_cost_note' => 'Estimated costs depend on your WhatsApp Meta Tier and Twilio voice minutes.',
            ],
        ];
    }
}
