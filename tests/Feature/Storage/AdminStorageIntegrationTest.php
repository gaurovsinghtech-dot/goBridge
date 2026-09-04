<?php

namespace Tests\Feature\Storage;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStorageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_admin_can_view_aws_s3_configuration_page_with_masked_secrets(): void
    {
        $admin = $this->createSuperAdmin();

        IntegrationConfig::create([
            'provider' => 'storage_s3',
            'label' => 'Amazon S3',
            'enabled' => true,
            'is_default' => false,
            'mode' => 'live',
            'credentials' => [
                'key' => 'AKIAEXAMPLEROOTKEY1',
                'secret' => 'super-secret-aws-key-9988',
                'region' => 'us-east-1',
                'bucket' => 'growbridge-s3-test-bucket',
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.integrations.edit', 'storage_s3'));

        $response->assertStatus(200);

        // Verify credentials passed to view have masked secret key
        $props = $response->original->getData()['page']['props'];
        $this->assertEquals('storage_s3', $props['provider']);
        $this->assertNotNull($props['storageStats']);
        $this->assertEquals('growbridge-s3-test-bucket', $props['storageStats']['bucket']);
        $this->assertEquals('••••••••••••', $props['config']['credentials']['secret']);
        $this->assertStringNotContainsString('super-secret-aws-key-9988', json_encode($props['config']['credentials']));
    }

    public function test_admin_can_update_s3_credentials_without_overwriting_masked_secret(): void
    {
        $admin = $this->createSuperAdmin();

        $config = IntegrationConfig::create([
            'provider' => 'storage_s3',
            'label' => 'Amazon S3',
            'enabled' => true,
            'is_default' => false,
            'mode' => 'live',
            'credentials' => [
                'key' => 'AKIAORIGINALKEY123',
                'secret' => 'preserved-original-secret-key',
                'region' => 'us-east-1',
                'bucket' => 'my-original-bucket',
            ],
        ]);

        // Submit update with masked secret preserved (••••••••••••)
        $response = $this->actingAs($admin, 'admin')->put(route('admin.integrations.update', 'storage_s3'), [
            'enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'key' => 'AKIAUPDATEDKEY456',
                'secret' => '••••••••••••', // Masked indicator from UI
                'region' => 'ap-south-1',
                'bucket' => 'my-updated-bucket',
            ],
        ]);

        $response->assertRedirect();

        $config->refresh();
        $creds = $config->credentials;

        $this->assertEquals('AKIAUPDATEDKEY456', $creds['key']);
        $this->assertEquals('preserved-original-secret-key', $creds['secret']); // Original preserved
        $this->assertEquals('ap-south-1', $creds['region']);
        $this->assertEquals('my-updated-bucket', $creds['bucket']);
    }

    public function test_admin_can_run_live_connection_test(): void
    {
        $admin = $this->createSuperAdmin();

        IntegrationConfig::create([
            'provider' => 'storage_s3',
            'label' => 'Amazon S3',
            'enabled' => true,
            'is_default' => false,
            'mode' => 'live',
            'credentials' => [
                'key' => 'AKIATESTKEY123',
                'secret' => 'SecretKey456789Test',
                'region' => 'ap-south-1',
                'bucket' => 'growbridge-s3-production',
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')->postJson(route('admin.integrations.test', 'storage_s3'));

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'status' => 'Connected',
            'bucket' => 'growbridge-s3-production',
            'region' => 'ap-south-1',
        ]);
    }
}
