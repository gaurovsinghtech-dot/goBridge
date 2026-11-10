<?php

namespace App\Modules\Automation\Services;

use App\Models\Crm\CrmPipeline;
use App\Models\Crm\CrmPipelineStage;
use App\Modules\Automation\Models\Automation;

/**
 * Sets up the "Contact Journey" feature: a dedicated CRM pipeline with four
 * stages (Fresh Contacts -> Product Section -> Completed, with Query as a
 * side-branch reachable from any stage) plus the three automations that move
 * contacts between them. Everything here is idempotent — safe to call again
 * after the admin has edited the automations in the Builder, since existing
 * rows (matched by name) are left untouched.
 */
class ContactJourneyService
{
    public const PIPELINE_NAME = 'Contact Journey';

    public const STAGES = [
        ['name' => 'Fresh Contacts', 'color' => 'neutral', 'probability' => 10],
        ['name' => 'Product Section', 'color' => 'blue', 'probability' => 40],
        ['name' => 'Completed', 'color' => 'emerald', 'probability' => 100],
        ['name' => 'Query', 'color' => 'rose', 'probability' => 20],
    ];

    public const DEFAULT_QUERY_KEYWORDS = [
        'problem', 'issue', 'not working', 'refund', 'complaint', 'help', 'support',
    ];

    public function setup(int $workspaceId, string $welcomeTemplateName): array
    {
        $pipeline = CrmPipeline::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => self::PIPELINE_NAME],
            ['is_default' => false],
        );

        $stages = [];
        foreach (self::STAGES as $i => $stageData) {
            $stages[$stageData['name']] = CrmPipelineStage::firstOrCreate(
                ['workspace_id' => $workspaceId, 'pipeline_id' => $pipeline->id, 'name' => $stageData['name']],
                [
                    'color' => $stageData['color'],
                    'probability' => $stageData['probability'],
                    'position' => $i,
                    'is_won' => $stageData['name'] === 'Completed',
                    'is_lost' => false,
                ],
            );
        }

        $created = [];

        $created['welcome'] = $this->createIfMissing($workspaceId, 'Contact Journey: Welcome New Contact', [
            'description' => 'New contact starts in Fresh Contacts, gets the welcome WhatsApp template, then moves to Product Section once it sends successfully.',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'trigger_config' => [],
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'triggerNode', 'position' => ['x' => 250, 'y' => 50], 'data' => ['triggerType' => 'contact.created', 'label' => 'Trigger']],
                ['id' => 'cj-fresh', 'type' => 'move_pipeline_stage', 'position' => ['x' => 250, 'y' => 180], 'data' => ['nodeType' => 'move_pipeline_stage', 'stage_id' => $stages['Fresh Contacts']->id, 'pipeline_id' => $pipeline->id, 'label' => 'Move to Fresh Contacts', 'configured' => true]],
                ['id' => 'cj-welcome', 'type' => 'send_template', 'position' => ['x' => 250, 'y' => 310], 'data' => ['nodeType' => 'send_template', 'template_name' => $welcomeTemplateName, 'language' => 'en', 'configured' => true]],
                ['id' => 'cj-product', 'type' => 'move_pipeline_stage', 'position' => ['x' => 250, 'y' => 440], 'data' => ['nodeType' => 'move_pipeline_stage', 'stage_id' => $stages['Product Section']->id, 'pipeline_id' => $pipeline->id, 'label' => 'Move to Product Section', 'configured' => true]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'cj-fresh'],
                ['id' => 'e2', 'source' => 'cj-fresh', 'target' => 'cj-welcome'],
                ['id' => 'e3', 'source' => 'cj-welcome', 'target' => 'cj-product'],
            ],
        ]);

        $created['query'] = $this->createIfMissing($workspaceId, 'Contact Journey: Query Detected', [
            'description' => 'Any inbound WhatsApp message matching a query/complaint keyword moves the contact to the Query stage, from whichever stage it is currently in. Edit the keyword list on the trigger node.',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'trigger_config' => ['keywords' => self::DEFAULT_QUERY_KEYWORDS],
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'triggerNode', 'position' => ['x' => 250, 'y' => 50], 'data' => ['triggerType' => 'message.received', 'keywords' => self::DEFAULT_QUERY_KEYWORDS, 'label' => 'Trigger']],
                ['id' => 'cj-query', 'type' => 'move_pipeline_stage', 'position' => ['x' => 250, 'y' => 180], 'data' => ['nodeType' => 'move_pipeline_stage', 'stage_id' => $stages['Query']->id, 'pipeline_id' => $pipeline->id, 'label' => 'Move to Query', 'configured' => true]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'cj-query'],
            ],
        ]);

        $created['purchase'] = $this->createIfMissing($workspaceId, 'Contact Journey: Purchase Confirmed', [
            'description' => 'AI reads every inbound WhatsApp message and, if the customer confirms they completed a purchase/payment, moves the contact to Completed.',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'trigger_config' => [],
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'triggerNode', 'position' => ['x' => 250, 'y' => 50], 'data' => ['triggerType' => 'message.received', 'label' => 'Trigger']],
                ['id' => 'cj-intent', 'type' => 'ai_detect_intent', 'position' => ['x' => 250, 'y' => 180], 'data' => ['nodeType' => 'ai_detect_intent', 'intent' => "the customer's message confirms they completed a purchase or payment (e.g. \"I paid\", \"payment done\", \"order confirmed\", \"I bought it\")", 'configured' => true]],
                ['id' => 'cj-completed', 'type' => 'move_pipeline_stage', 'position' => ['x' => 150, 'y' => 320], 'data' => ['nodeType' => 'move_pipeline_stage', 'stage_id' => $stages['Completed']->id, 'pipeline_id' => $pipeline->id, 'label' => 'Move to Completed', 'configured' => true]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'cj-intent'],
                ['id' => 'e2', 'source' => 'cj-intent', 'target' => 'cj-completed', 'sourceHandle' => 'true'],
            ],
        ]);

        return [
            'pipeline' => $pipeline,
            'stages' => $stages,
            'automations' => $created,
        ];
    }

    private function createIfMissing(int $workspaceId, string $name, array $attributes): array
    {
        $existing = Automation::where('workspace_id', $workspaceId)->where('name', $name)->first();
        if ($existing) {
            return ['automation' => $existing, 'created' => false];
        }

        $automation = Automation::create(array_merge($attributes, [
            'workspace_id' => $workspaceId,
            'name' => $name,
        ]));

        return ['automation' => $automation, 'created' => true];
    }
}
