<?php

namespace Tests\Feature;

use App\Modules\Voice\Models\TelephonyApiLog;
use App\Modules\Voice\Models\TelephonyWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProductionDeploymentAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_health_check_endpoint_returns_status_and_app_name(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'app',
            'timestamp',
            'storage',
        ]);
        $this->assertEquals('healthy', $response->json('status'));
    }

    public function test_cleanup_ephemeral_data_command_prunes_expired_logs(): void
    {
        // Create an expired log (40 days old)
        $oldLog = TelephonyApiLog::create([
            'workspace_id' => 1,
            'provider' => 'twilio',
            'endpoint' => '/v1/calls',
            'http_method' => 'POST',
        ]);
        $oldLog->timestamps = false;
        $oldLog->created_at = now()->subDays(40);
        $oldLog->save();

        // Create a recent log (2 days old)
        TelephonyApiLog::create([
            'workspace_id' => 1,
            'provider' => 'twilio',
            'endpoint' => '/v1/calls',
            'http_method' => 'POST',
        ]);

        $this->assertEquals(2, TelephonyApiLog::count());

        // Run cleanup with 30 days retention
        Artisan::call('app:cleanup-ephemeral-data', ['--days' => 30]);

        $this->assertEquals(1, TelephonyApiLog::count());
    }

    public function test_env_example_contains_all_critical_production_categories(): void
    {
        $envPath = base_path('.env.example');
        $this->assertFileExists($envPath);

        $content = file_get_contents($envPath);

        $this->assertStringContainsString('APP_NAME', $content);
        $this->assertStringContainsString('DB_CONNECTION', $content);
        $this->assertStringContainsString('QUEUE_CONNECTION', $content);
        $this->assertStringContainsString('LICENSE_VERIFY', $content);
    }
}
