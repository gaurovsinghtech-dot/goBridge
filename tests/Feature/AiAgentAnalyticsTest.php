<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiDailyStat;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiUnknownQuestion;
use App\Services\AI\AiAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private Workspace $workspaceA;
    private User $userB;
    private Workspace $workspaceB;
    private AiChatbot $agentA;
    private AiAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A Tech',
            'slug' => 'workspace-a-tech',
            'status' => 'active',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'role' => 'client',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B Retail',
            'slug' => 'workspace-b-retail',
            'status' => 'active',
        ]);

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'role' => 'client',
        ]);

        $this->analyticsService = app(AiAnalyticsService::class);

        // Setup Agent
        $this->agentA = AiChatbot::create([
            'workspace_id' => $this->workspaceA->id,
            'name' => 'Sales Assistant',
            'status' => 'active',
            'enabled' => true,
        ]);

        // Seed 7 days of daily stats for Workspace A
        for ($i = 0; $i < 7; $i++) {
            $date = Carbon::now()->subDays($i)->toDateString();
            AiDailyStat::create([
                'workspace_id' => $this->workspaceA->id,
                'date' => $date,
                'ai_agent_id' => $this->agentA->id,
                'channel' => 'whatsapp',
                'conversations' => 100,
                'ai_messages' => 250,
                'resolved' => 75,
                'handoffs' => 20,
                'failed' => 5,
                'avg_response_ms' => 1200,
                'positive_feedback' => 80,
                'negative_feedback' => 8,
                'input_tokens' => 50000,
                'output_tokens' => 30000,
            ]);
        }

        // Seed an unknown question
        AiUnknownQuestion::create([
            'workspace_id' => $this->workspaceA->id,
            'ai_agent_id' => $this->agentA->id,
            'question' => 'Do you provide delivery to Dubai?',
            'occurrences' => 15,
            'category_suggested' => 'shipping',
            'status' => 'pending',
            'last_asked_at' => now(),
        ]);
    }

    public function test_analytics_dashboard_renders_with_metrics(): void
    {
        $res = $this->actingAs($this->userA)->get(route('client.ai.analytics.index'));
        $res->assertOk();
        $res->assertInertia(fn ($page) => 
            $page->component('AI/Analytics/Index')
                ->has('overview')
                ->has('timeseries')
                ->has('channelPerformance')
                ->has('handoffs')
                ->has('feedback')
                ->has('failedQuestions')
                ->has('usage')
        );
    }

    public function test_overview_resolution_and_handoff_rates_calculation(): void
    {
        $overview = $this->analyticsService->getOverview($this->workspaceA->id, '7d');

        $this->assertEquals(700, $overview['total_conversations']);
        $this->assertEquals(525, $overview['ai_resolved']);
        $this->assertEquals(140, $overview['human_handoffs']);
        $this->assertEquals(75.0, $overview['resolution_rate']);
        $this->assertEquals(20.0, $overview['handoff_rate']);
        $this->assertEquals(1.2, $overview['avg_response_sec']);
    }

    public function test_channel_performance_breakdown(): void
    {
        $channels = $this->analyticsService->getChannelPerformance($this->workspaceA->id, '7d');

        $this->assertNotEmpty($channels);
        $whatsapp = collect($channels)->firstWhere('channel', 'whatsapp');
        $this->assertNotNull($whatsapp);
        $this->assertEquals(700, $whatsapp['conversations']);
        $this->assertEquals(75.0, $whatsapp['resolution_rate']);
    }

    public function test_user_feedback_helpful_rate_calculation(): void
    {
        $feedback = $this->analyticsService->getFeedbackAnalytics($this->workspaceA->id, '7d');

        $this->assertEquals(560, $feedback['helpful']); // 80 * 7
        $this->assertEquals(56, $feedback['not_helpful']); // 8 * 7
        $this->assertEquals(90.9, $feedback['helpful_rate']);
        $this->assertEquals('User Feedback', $feedback['label']);
    }

    public function test_failed_questions_listing_and_resolve_action(): void
    {
        $questions = $this->analyticsService->getFailedQuestions($this->workspaceA->id);
        $this->assertCount(1, $questions);
        $this->assertEquals('Do you provide delivery to Dubai?', $questions[0]['question']);
        $this->assertEquals(15, $questions[0]['occurrences']);

        $qModel = AiUnknownQuestion::where('workspace_id', $this->workspaceA->id)->first();

        // Resolve question
        $res = $this->actingAs($this->userA)->post(route('client.ai.analytics.question.resolve', $qModel->id));
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $this->assertEquals('resolved', $qModel->fresh()->status);
    }

    public function test_usage_and_token_tracking_with_no_invented_cost(): void
    {
        $usage = $this->analyticsService->getUsageAndCost($this->workspaceA->id, '7d');

        $this->assertEquals(1750, $usage['ai_requests']); // 250 * 7
        $this->assertEquals(350000, $usage['input_tokens']); // 50000 * 7
        $this->assertEquals(210000, $usage['output_tokens']); // 30000 * 7
        $this->assertEquals(560000, $usage['total_tokens']);
        $this->assertEquals('Cost data unavailable', $usage['cost_display']);
    }

    public function test_api_overview_json_endpoint(): void
    {
        $res = $this->actingAs($this->userA)->getJson(route('client.ai.analytics.overview', ['period' => '7d']));
        $res->assertOk();
        $res->assertJsonStructure([
            'ok',
            'data' => [
                'total_conversations',
                'ai_resolved',
                'human_handoffs',
                'resolution_rate',
            ],
        ]);
    }

    public function test_workspace_isolation_prevents_unauthorized_analytics_access(): void
    {
        // User B has no stats
        $overviewB = $this->analyticsService->getOverview($this->workspaceB->id, '7d');
        $this->assertEquals(0, $overviewB['total_conversations']);

        // User B cannot resolve User A's unknown question
        $qA = AiUnknownQuestion::where('workspace_id', $this->workspaceA->id)->first();
        $res = $this->actingAs($this->userB)->post(route('client.ai.analytics.question.resolve', $qA->id));
        $res->assertForbidden();
    }
}
