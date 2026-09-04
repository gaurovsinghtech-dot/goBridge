<?php

namespace Database\Seeders;

use App\Models\AppRelease;
use Illuminate\Database\Seeder;

class AppReleaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppRelease::updateOrCreate(
            ['platform' => 'android', 'version_code' => 100],
            [
                'platform' => 'android',
                'version' => '1.0.0',
                'version_code' => 100,
                'min_supported_version' => '1.0.0',
                'file_size_mb' => 28.50,
                'release_notes' => "• Unified WhatsApp Chat & Live Omnichannel Inbox\n• Business VoIP Calling with In-App Dialpad\n• Real-Time AI Suggested Replies & Human Handoff\n• 360° Customer Profile (WhatsApp + Calls + CRM)",
                'force_update_required' => false,
                'is_active' => true,
                'download_count' => 142,
                'published_at' => now(),
            ]
        );
    }
}
