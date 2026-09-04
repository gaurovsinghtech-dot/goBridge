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
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('s3');
            $table->string('bucket', 128)->nullable();
            $table->string('region', 64)->nullable();
            $table->string('key', 512)->index();
            $table->string('filename', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('category', 64)->default('general')->index();
            $table->string('visibility', 16)->default('private');
            $table->string('checksum', 64)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'category']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
