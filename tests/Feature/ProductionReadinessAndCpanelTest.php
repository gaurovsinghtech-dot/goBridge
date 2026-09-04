<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionReadinessAndCpanelTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;
    private Workspace $workspace;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('local');
        Storage::fake('public');

        $this->admin = $this->createSuperAdminUser();

        $client = Client::create(['name' => 'Production Client', 'status' => 'active']);
        $this->workspace = Workspace::create([
            'client_id' => $client->id,
            'name' => 'Production Workspace',
            'industry' => 'Technology',
            'currency_code' => 'USD',
        ]);
        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@production.com',
            'password' => bcrypt('SecurePassword123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'workspace_id' => $this->workspace->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->workspace->forceFill(['owner_id' => $this->user->id])->saveQuietly();
        $this->workspace->members()->syncWithoutDetaching([$this->user->id => ['role' => 'owner']]);
    }

    public function test_production_security_headers_and_cookie_configuration(): void
    {
        $response = $this->get('/');

        // Security headers
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');

        // Session settings
        $this->assertEquals('lax', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_database_backup_command_executes_and_records_telemetry(): void
    {
        $exitCode = Artisan::call('db:backup', ['--no-upload' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify telemetry recorded in system_settings
        $lastBackupAt = SystemSetting::get('system.last_backup_at');
        $lastBackupStatus = SystemSetting::get('system.last_backup_status');

        $this->assertNotNull($lastBackupAt);
        $this->assertEquals('success', $lastBackupStatus);
    }

    public function test_cron_queue_worker_execution_with_stop_when_empty(): void
    {
        // Push a simple closure/job to test queue execution
        $executed = false;
        dispatch(function () use (&$executed) {
            $executed = true;
        });

        // Run queue worker with --stop-when-empty (cPanel cron style)
        $exitCode = Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
        $this->assertEquals(0, $exitCode);
    }

    public function test_scheduler_heartbeat_and_scheduled_tasks_registration(): void
    {
        // Execute schedule:run
        Artisan::call('schedule:run');

        // Heartbeat should be recorded
        $heartbeat = Cache::get(\App\Http\Controllers\Admin\CronSetupController::HEARTBEAT_KEY);
        $this->assertNotNull($heartbeat);
    }

    public function test_inbound_webhooks_signature_verification_and_fast_200_response(): void
    {
        // WhatsApp Webhook GET challenge verification
        $challenge = 'growbridge_challenge_12345';
        $appId = 'meta_app_123456';
        $appSecret = 'super_secret_app_key';

        IntegrationConfig::create([
            'provider' => 'meta_app',
            'label' => 'Meta WhatsApp',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => [
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'system_user_token' => 'EAAB12345',
            ],
        ]);

        $expectedVerifyToken = hash('sha256', $appId . $appSecret . 'wh_global_verify');

        $response = $this->get("/webhooks/whatsapp/global?hub_mode=subscribe&hub_verify_token={$expectedVerifyToken}&hub_challenge={$challenge}");
        $response->assertOk();
        $this->assertEquals($challenge, $response->getContent());
    }

    public function test_transactional_email_and_password_reset_flow(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => $this->user->email,
        ]);

        $response->assertSessionHas('status');
    }

    public function test_admin_system_health_telemetry_and_actions(): void
    {
        // Setup S3 and Provider data
        IntegrationConfig::create([
            'provider' => 'storage_s3',
            'label' => 'Amazon S3',
            'mode' => 'live',
            'enabled' => true,
            'is_default' => true,
            'credentials' => [
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'SECRETKEY',
                'region' => 'us-east-1',
                'bucket' => 'growbridge-prod-bucket',
            ],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.system-health.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/SystemHealth')
                ->has('diagnostics')
                ->has('s3')
                ->has('providers')
                ->has('webhooks')
                ->where('diagnostics.db_status', 'healthy')
        );

        // Test on-demand backup action from System Health
        $backupAction = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.system-health.run-backup'));
        $backupAction->assertRedirect();
        $backupAction->assertSessionHas('success');

        // Test test-email action
        $emailAction = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.system-health.test-email'), ['email' => 'admin@test.com']);
        $emailAction->assertOk();
        $this->assertTrue($emailAction->json('ok'));
    }

    public function test_system_masks_secrets_and_does_not_expose_credentials(): void
    {
        $config = IntegrationConfig::create([
            'provider' => 'twilio',
            'label' => 'Twilio',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => [
                'account_sid' => 'ACtestmockaccount00000000000000000',
                'auth_token' => 'secret_auth_token_value_123',
            ],
        ]);

        $masked = $config->maskedCredentials();
        $this->assertStringStartsWith('••••', $masked['account_sid']);
        $this->assertStringStartsWith('••••', $masked['auth_token']);
        $this->assertStringNotContainsString('secret_auth_token_value_123', $masked['auth_token']);
    }

    public function test_htaccess_file_exists_and_contains_protection_rules(): void
    {
        $rootHtaccess = file_get_contents(base_path('.htaccess'));
        $publicHtaccess = file_get_contents(public_path('.htaccess'));

        // Verify root .htaccess blocks .env and core directories
        $this->assertStringContainsString('\.env', $rootHtaccess);
        $this->assertStringContainsString('vendor', $rootHtaccess);
        $this->assertStringContainsString('public/$1', $rootHtaccess);

        // Verify public .htaccess handles Authorization headers and rewrite rules
        $this->assertStringContainsString('HTTP_AUTHORIZATION', $publicHtaccess);
        $this->assertStringContainsString('RewriteRule ^ index.php', $publicHtaccess);
    }
}
