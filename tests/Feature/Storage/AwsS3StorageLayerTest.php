<?php

namespace Tests\Feature\Storage;

use App\Models\StoredFile;
use App\Models\User;
use App\Models\Workspace;
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
use Tests\TestCase;

class AwsS3StorageLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_s3_filesystem_disk_configuration_is_loaded(): void
    {
        $s3Config = config('filesystems.disks.s3');

        $this->assertNotNull($s3Config);
        $this->assertEquals('s3', $s3Config['driver']);
        $this->assertEquals('private', $s3Config['visibility']);
        $this->assertTrue($s3Config['throw']);
    }

    public function test_secure_upload_service_validates_mime_and_size(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $uploadService = app(SecureUploadService::class);

        // 1. Invalid MIME type (PHP file uploaded as logo)
        $invalidFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $this->expectException(InvalidArgumentException::class);
        $uploadService->upload($invalidFile, $workspace->id, $user->id, 'logos');
    }

    public function test_secure_upload_enforces_workspace_scoped_path(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $uploadService = app(SecureUploadService::class);

        $image = UploadedFile::fake()->image('company_logo.png', 400, 400);

        $storedFile = $uploadService->upload($image, $workspace->id, $user->id, 'logos', [
            'description' => 'Official Brand Logo',
        ]);

        $this->assertInstanceOf(StoredFile::class, $storedFile);
        $this->assertEquals($workspace->id, $storedFile->workspace_id);
        $this->assertEquals($user->id, $storedFile->user_id);
        $this->assertEquals('logos', $storedFile->category);
        $this->assertEquals('private', $storedFile->visibility);

        // S3 key pattern: workspaces/{workspace_id}/{category}/{filename}
        $expectedPrefix = "workspaces/{$workspace->id}/logos/";
        $this->assertStringStartsWith($expectedPrefix, $storedFile->key);
        $this->assertStringEndsWith('.png', $storedFile->key);

        // Verify stored in S3 disk
        Storage::disk($storedFile->disk)->assertExists($storedFile->key);

        // Verify checksum exists
        $this->assertNotEmpty($storedFile->checksum);
        $this->assertEquals(64, strlen($storedFile->checksum)); // SHA-256 length
    }

    public function test_temporary_signed_url_generation_for_private_s3_objects(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create(['workspace_id' => $workspace->id]);
        $uploadService = app(SecureUploadService::class);

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');
        $storedFile = $uploadService->upload($file, $workspace->id, $user->id, 'crm_attachments');

        $signedUrl = $storedFile->temporarySignedUrl(45);

        $this->assertNotEmpty($signedUrl);
        $this->assertIsString($signedUrl);
    }

    public function test_workspace_isolation_prevents_cross_tenant_access(): void
    {
        $workspaceA = Workspace::factory()->create(['name' => 'Workspace A']);
        $workspaceB = Workspace::factory()->create(['name' => 'Workspace B']);

        $userA = User::factory()->create(['workspace_id' => $workspaceA->id]);
        $userB = User::factory()->create(['workspace_id' => $workspaceB->id]);

        $uploadService = app(SecureUploadService::class);
        $downloadService = app(SecureDownloadService::class);

        $file = UploadedFile::fake()->create('private_financial_report.pdf', 300, 'application/pdf');
        $storedFileA = $uploadService->upload($file, $workspaceA->id, $userA->id, 'reports');

        // User A (owner workspace) can access
        $downloadService->authorizeAccess($storedFileA, $userA);
        $signedUrl = $downloadService->getSignedUrl($storedFileA, $userA);
        $this->assertNotEmpty($signedUrl);

        // User B (different workspace) MUST be blocked
        $this->expectException(AuthorizationException::class);
        $downloadService->authorizeAccess($storedFileA, $userB);
    }

    public function test_s3_connection_tester_logic(): void
    {
        $service = app(StorageService::class);

        // Missing credentials check
        $resIncomplete = $service->testConnection([
            'key' => '',
            'secret' => '',
            'bucket' => '',
        ]);
        $this->assertFalse($resIncomplete['ok']);
        $this->assertEquals('Not Connected', $resIncomplete['status']);

        // Valid credentials check against fake S3
        $resValid = $service->testConnection([
            'key' => 'AKIATESTKEY123',
            'secret' => 'SecretKey456789Test',
            'region' => 'ap-south-1',
            'bucket' => 'growbridge-s3-production',
        ]);
        $this->assertTrue($resValid['ok']);
        $this->assertEquals('Connected', $resValid['status']);
        $this->assertEquals('growbridge-s3-production', $resValid['bucket']);
        $this->assertEquals('ap-south-1', $resValid['region']);
    }

    public function test_admin_storage_metrics_and_workspace_breakdown(): void
    {
        $workspace1 = Workspace::factory()->create(['name' => 'Acme Corp']);
        $workspace2 = Workspace::factory()->create(['name' => 'Globex Inc']);

        $uploadService = app(SecureUploadService::class);
        $storageService = app(StorageService::class);

        $file1 = UploadedFile::fake()->create('sheet1.csv', 50, 'text/csv');
        $file2 = UploadedFile::fake()->image('banner.jpg', 800, 600);
        $file3 = UploadedFile::fake()->create('doc.pdf', 120, 'application/pdf');

        $uploadService->upload($file1, $workspace1->id, null, 'exports');
        $uploadService->upload($file2, $workspace1->id, null, 'campaign_media');
        $uploadService->upload($file3, $workspace2->id, null, 'ai_knowledge');

        $stats = $storageService->getStorageStats();

        $this->assertEquals(3, $stats['total_objects']);
        $this->assertGreaterThan(0, $stats['total_bytes']);
        $this->assertArrayHasKey('exports', $stats['categories']);
        $this->assertArrayHasKey('campaign_media', $stats['categories']);
        $this->assertArrayHasKey('ai_knowledge', $stats['categories']);

        $this->assertNotEmpty($stats['top_workspaces']);
    }

    public function test_failed_upload_rollback_and_orphan_cleanup_job(): void
    {
        $workspace = Workspace::factory()->create();
        $cleanupService = app(FileCleanupService::class);
        $uploadService = app(SecureUploadService::class);

        $file = UploadedFile::fake()->create('temp_doc.pdf', 200, 'application/pdf');
        $stored = $uploadService->upload($file, $workspace->id, null, 'general');

        // Soft delete the file and set deleted_at to 10 days ago
        $stored->delete();
        StoredFile::withTrashed()->where('id', $stored->id)->update(['deleted_at' => now()->subDays(10)]);

        $prunedCount = $cleanupService->pruneOrphanedFiles(7);

        $this->assertEquals(1, $prunedCount);
        $this->assertDatabaseMissing('stored_files', ['id' => $stored->id]);
    }
}
