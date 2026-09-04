<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32)->default('android'); // 'android', 'ios'
            $table->string('version', 32)->default('1.0.0'); // Semantic version e.g. '1.0.0'
            $table->unsignedInteger('version_code')->default(100); // Build number e.g. 100
            $table->string('min_supported_version', 32)->default('1.0.0');
            $table->string('file_path')->nullable(); // Storage path if uploaded to server
            $table->string('download_url')->nullable(); // External or public URL
            $table->decimal('file_size_mb', 6, 2)->default(28.00);
            $table->text('release_notes')->nullable();
            $table->boolean('force_update_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'is_active', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
