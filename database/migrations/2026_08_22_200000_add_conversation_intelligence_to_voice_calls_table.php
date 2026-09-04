<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            $table->string('intent', 64)->default('unknown')->index(); // sales, support, complaint, appointment, information, unknown
            $table->string('lead_interest', 32)->default('unknown')->index(); // high, medium, low, unknown
            $table->string('conversation_signal', 32)->default('unknown')->index(); // positive, neutral, negative, unknown
            $table->json('topics')->nullable(); // ["WhatsApp API", "Pricing", "Demo"]
            $table->json('important_moments')->nullable(); // [{"timestamp": "00:42", "text": "Pricing question"}]
            $table->string('next_action', 128)->nullable(); // sales_callback, send_whatsapp, none
            $table->dateTime('analyzed_at')->nullable()->index();
            $table->unsignedSmallInteger('recording_retention_days')->default(30);
            $table->unsignedSmallInteger('transcript_retention_days')->default(90);
        });
    }

    public function down(): void
    {
        Schema::table('voice_calls', function (Blueprint $table) {
            $table->dropColumn([
                'intent',
                'lead_interest',
                'conversation_signal',
                'topics',
                'important_moments',
                'next_action',
                'analyzed_at',
                'recording_retention_days',
                'transcript_retention_days',
            ]);
        });
    }
};
