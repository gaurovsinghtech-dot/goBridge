<?php

namespace App\Modules\Voice\Services;

use App\Modules\Voice\Contracts\VoiceDriverInterface;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Voice\Services\Drivers\ExotelVoiceDriver;
use App\Modules\Voice\Services\Drivers\PlivoVoiceDriver;
use App\Modules\Voice\Services\Drivers\TwilioVoiceDriver;

class VoiceDriverManager
{
    /**
     * Resolve the voice driver for a given agent or provider name.
     */
    public function driverForAgent(VoiceAgent $agent): VoiceDriverInterface
    {
        $provider = $agent->provider ?? 'twilio';
        $workspaceId = (int) $agent->workspace_id;

        return app(TelephonyService::class)->driver($provider, $workspaceId);
    }

    public function driver(string $provider, int $workspaceId = 1): VoiceDriverInterface
    {
        return app(TelephonyService::class)->driver($provider, $workspaceId);
    }
}
