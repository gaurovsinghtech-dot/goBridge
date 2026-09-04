<?php

namespace Tests\Feature\Storage;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\Plan;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Services\Storage\FileCleanupService;
use App\Services\Storage\SecureDownloadService;
use App\Services\Storage\SecureUploadService;
use App\Services\Storage\StorageService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class AwsS3StorageArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private User $userB;
    private AdminUser $admin;
    private StorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('public');

        // Setup tenant A
        $clientA = Client::create(['name' => 'Acme Corp', 'status' => 'active']);
        $this->workspaceA = Workspace::create([
            'client_id' => $clientA->id,
            'name' => 'Acme Primary',
            'industry' => 'Technology',
            'currency_code' => 'USD',
        ]);
        $this->userA = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice@acme.com',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $clientA->id,
            'workspace_id' => $this->workspaceA->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->workspaceA->forceFill(['owner_id' => $this->userA->id])->saveQuietly();
        $this->workspaceA->members()->syncWithoutDetaching([$this->userA->id => ['role' => 'owner']]);

        // Setup tenant B
        $clientB = Client::create(['name' => 'Beta Logistics', 'status' => 'active']);
        $this->workspaceB = Workspace::create([
            'client_id' => $clientB->id,
            'name' => 'Beta Warehouse',
            'industry' => 'Logistics',
            'currency_code' => 'USD',
        ]);
        $this->userB = User::create([
            'name' => 'Bob User',
            'email' => 'bob@beta.com',
            'password' => bcrypt('Password123!'),
            'role' => User::ROLE_CLIENT,
            'client_id' => $clientB->id,
            'workspace_id' => $this->workspaceB->id,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->workspaceB->forceFill(['owner_id' => $this->userB->id])->saveQuietly();
        $this->workspaceB->members()->syncWithoutDetaching([$this->userB->id => ['role' => 'owner']]);

        // Setup Admin
        $this->admin = $this->createSuperAdminUser();

        // Setup S3 Integration Config in DB
        IntegrationConfig::create([
            'provider' => 'storage_s3',
            'label' => 'Amazon S3',
            'mode' => 'live',
            'enabled' => true,
            'is_default' => true,
            'credentials' => [
                'key' => 'AKIAIOSFODNN7EXAMPLE',
                'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region' => 'ap-south-1',
                'bucket' => 'growbridge-production-storage',
                'directory_prefix' => '',
            ],
            'last_test_status' => 'ok',
            'last_test_message' => 'Successfully connected to S3 bucket [growbridge-production-storage].',
            'last_tested_at' => now(),
        ]);

        $this->storageService = app(StorageService::class);
    }

    public function test_upload_file_creates_record_and_writes_to_s3_with_workspace_prefix(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 1024, 'application/pdf');

        $storedFile = $this->storageService->upload(
            $this->workspaceA,
            $file,
            'crm_attachments',
            $this->userA,
            ['customer_id' => 45]
        );

        $this->assertInstanceOf(StoredFile::class, $storedFile);
        $this->assertEquals($this->workspaceA->id, $storedFile->workspace_id);
        $this->assertEquals($this->userA->id, $storedFile->user_id);
        $this->assertEquals('crm_attachments', $storedFile->category);
        $this->assertEquals('contract.pdf', $storedFile->original_name);
        $this->assertEquals('application/pdf', $storedFile->mime_type);
        $this->assertEquals('private', $storedFile->visibility);

        // Verify Workspace-Scoped path structure: workspaces/{workspace_id}/{category}/{uuid}.{ext}
        $expectedPrefix = "workspaces/{$this->workspaceA->id}/crm_attachments/";
        $this->assertStringStartsWith($expectedPrefix, $storedFile->key);

        // Verify S3 object exists in fake disk
        Storage::disk($storedFile->disk)->assertExists($storedFile->key);
    }

    public function test_download_authorization_and_temporary_signed_url(): void
    {
        $file = UploadedFile::fake()->create('sales_deck.pdf', 500, 'application/pdf');
        $storedFile = $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        $downloadService = app(SecureDownloadService::class);

        // Authorized user from Workspace A can obtain temporary signed URL
        $url = $downloadService->getSignedUrl($storedFile, $this->userA, 30);
        $this->assertNotEmpty($url);

        // Direct download / streaming route check
        $streamResponse = $downloadService->streamDownload($storedFile, $this->userA);
        $this->assertEquals(200, $streamResponse->getStatusCode());
        $this->assertEquals('application/pdf', $streamResponse->headers->get('Content-Type'));
        $this->assertEquals('nosniff', $streamResponse->headers->get('X-Content-Type-Options'));
    }

    public function test_cross_workspace_access_is_strictly_forbidden(): void
    {
        $file = UploadedFile::fake()->create('private_audit.pdf', 300, 'application/pdf');
        $storedFile = $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        $downloadService = app(SecureDownloadService::class);

        // User B from Workspace B must be denied access
        $this->expectException(AuthorizationException::class);
        $downloadService->authorizeAccess($storedFile, $this->userB);
    }

    public function test_file_deletion_removes_s3_object_and_db_record(): void
    {
        $file = UploadedFile::fake()->create('invoice_delete.pdf', 200, 'application/pdf');
        $storedFile = $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        $key = $storedFile->key;
        $disk = $storedFile->disk;

        Storage::disk($disk)->assertExists($key);

        // Perform delete via StorageService
        $deleted = $this->storageService->delete($storedFile);
        $this->assertTrue($deleted);

        // S3 object deleted
        Storage::disk($disk)->assertMissing($key);

        // StoredFile model soft-deleted
        $this->assertSoftDeleted('stored_files', ['id' => $storedFile->id]);
    }

    public function test_invalid_mime_type_rejection(): void
    {
        $invalidFile = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File type [application/x-msdownload] is not permitted');

        $this->storageService->upload($this->workspaceA, $invalidFile, 'crm_attachments', $this->userA);
    }

    public function test_file_size_limit_rejection(): void
    {
        // 10 MB image exceeds 5 MB limit for logos
        $oversizedLogo = UploadedFile::fake()->create('giant_logo.png', 10 * 1024, 'image/png');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds the allowable limit');

        $this->storageService->upload($this->workspaceA, $oversizedLogo, 'logos', $this->userA);
    }

    public function test_workspace_storage_quota_enforcement(): void
    {
        // Attach a Plan with 1 MB storage quota to Client A
        $plan = Plan::factory()->create([
            'name' => 'Starter 1MB Tier',
            'limits' => ['storage_mb' => 1],
            'enabled' => true,
        ]);
        $this->attachPlanToClient($this->workspaceA->client, $plan);

        // Upload 800 KB file (succeeds)
        $file1 = UploadedFile::fake()->create('file1.pdf', 800, 'application/pdf');
        $this->storageService->upload($this->workspaceA, $file1, 'crm_attachments', $this->userA);

        // Second 500 KB file exceeds 1 MB total quota (fails)
        $file2 = UploadedFile::fake()->create('file2.pdf', 500, 'application/pdf');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Workspace storage quota exceeded');

        $this->storageService->upload($this->workspaceA, $file2, 'crm_attachments', $this->userA);
    }

    public function test_storage_service_exists_size_move_and_copy(): void
    {
        $file = UploadedFile::fake()->create('sample_doc.pdf', 400, 'application/pdf');
        $storedFile = $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        // 1. Exists
        $this->assertTrue($this->storageService->exists($storedFile));
        $this->assertTrue($this->storageService->exists($storedFile->key, $storedFile->disk));

        // 2. Size
        $this->assertGreaterThan(0, $this->storageService->size($storedFile));

        // 3. Copy
        $copyKey = "workspaces/{$this->workspaceA->id}/crm_attachments/copied_doc.pdf";
        $copied = $this->storageService->copy($storedFile, $copyKey);
        $this->assertTrue($copied);
        $this->assertTrue($this->storageService->exists($copyKey, $storedFile->disk));

        // 4. Move
        $moveKey = "workspaces/{$this->workspaceA->id}/crm_attachments/moved_doc.pdf";
        $moved = $this->storageService->move($copyKey, $moveKey, $storedFile->disk);
        $this->assertTrue($moved);
        $this->assertFalse($this->storageService->exists($copyKey, $storedFile->disk));
        $this->assertTrue($this->storageService->exists($moveKey, $storedFile->disk));
    }

    public function test_s3_connection_test_success_and_failure_handling(): void
    {
        // 1. Success test with fake S3 disk
        $successResult = $this->storageService->testConnection([
            'key' => 'VALID_KEY',
            'secret' => 'VALID_SECRET',
            'bucket' => 'valid-bucket',
            'region' => 'us-east-1',
        ]);
        $this->assertTrue($successResult['ok']);
        $this->assertEquals('Connected', $successResult['status']);

        // 2. Incomplete configuration fails immediately
        $missingCredsResult = $this->storageService->testConnection([
            'key' => '',
            'secret' => '',
            'bucket' => '',
        ]);
        $this->assertFalse($missingCredsResult['ok']);
        $this->assertEquals('Not Connected', $missingCredsResult['status']);
    }

    public function test_orphan_file_cleanup_command_and_service(): void
    {
        $file = UploadedFile::fake()->create('orphan_candidate.pdf', 150, 'application/pdf');
        $storedFile = $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        // Soft delete the file with past timestamp
        $storedFile->delete();
        StoredFile::withTrashed()->where('id', $storedFile->id)->update(['deleted_at' => now()->subDays(10)]);

        $cleanupService = app(FileCleanupService::class);
        $orphanStats = $cleanupService->getOrphanStats();
        $this->assertEquals(1, $orphanStats['trashed_count']);

        // Run artisan command
        $this->artisan('storage:prune-orphans', ['--days' => 7])
            ->expectsOutputToContain('Successfully pruned 1 orphaned/expired files from storage.')
            ->assertExitCode(0);

        // Verify permanent removal
        $this->assertDatabaseMissing('stored_files', ['id' => $storedFile->id]);
        Storage::disk($storedFile->disk)->assertMissing($storedFile->key);
    }

    public function test_admin_storage_dashboard_renders_metrics(): void
    {
        $file = UploadedFile::fake()->create('metric_file.pdf', 250, 'application/pdf');
        $this->storageService->upload($this->workspaceA, $file, 'crm_attachments', $this->userA);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.storage.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Admin/Storage/Index')
                ->has('stats')
                ->has('orphanStats')
                ->has('recentFiles')
                ->has('workspaces')
                ->where('stats.provider', 'AWS S3')
        );
    }
}
