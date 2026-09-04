<?php

namespace App\Services;

use App\Models\OnboardingStep;
use App\Models\PhoneNumber;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiDocument;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Voice\Models\TelephonyPhoneNumber;
use App\Modules\Voice\Models\VoiceAgent;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Support\Facades\Schema;

class OnboardingService
{
    /**
     * Complete step registry for WhatsApp-only and WhatsApp+Voice services.
     */
    public const ALL_STEPS = [
        'account' => [
            'id' => 1,
            'title' => 'Create Account',
            'subtitle' => 'Account created & verified',
            'required' => true,
        ],
        'choose_service' => [
            'id' => 2,
            'title' => 'Choose Service',
            'subtitle' => 'Select WhatsApp or Voice capability',
            'required' => true,
        ],
        'phone' => [
            'id' => 3,
            'title' => 'Choose Phone Number',
            'subtitle' => 'Twilio virtual phone line',
            'required' => true,
        ],
        'whatsapp' => [
            'id' => 4,
            'title' => 'Connect WhatsApp',
            'subtitle' => 'Meta WhatsApp Business API',
            'required' => true,
        ],
        'calling' => [
            'id' => 5,
            'title' => 'Enable Calling',
            'subtitle' => 'Twilio Voice configuration',
            'required' => true,
        ],
        'ai_agent' => [
            'id' => 6,
            'title' => 'Configure AI',
            'subtitle' => 'OpenAI / Gemini / Claude assistant',
            'required' => true,
        ],
        'crm' => [
            'id' => 7,
            'title' => 'Connect Existing CRM',
            'subtitle' => 'HubSpot, Salesforce, Zoho, Pipedrive, or Custom',
            'required' => false,
        ],
        'business' => [
            'id' => 8,
            'title' => 'Business Profile',
            'subtitle' => 'Company profile, timezone & hours',
            'required' => true,
        ],
        'launch' => [
            'id' => 9,
            'title' => 'Complete',
            'subtitle' => 'Account ready for launch',
            'required' => true,
        ],
    ];

    /**
     * Backward-compatible static STEPS list.
     */
    public const STEPS = self::ALL_STEPS;

    /**
     * Resolve steps specific to the selected service type.
     */
    public function getStepsForService(string $serviceType = 'whatsapp_only'): array
    {
        if ($serviceType === 'whatsapp_voice') {
            return [
                'account' => [
                    'id' => 1,
                    'title' => 'Create Account',
                    'subtitle' => 'Account created & verified',
                    'required' => true,
                ],
                'choose_service' => [
                    'id' => 2,
                    'title' => 'Choose Service',
                    'subtitle' => 'WhatsApp + Voice & Calling',
                    'required' => true,
                ],
                'phone' => [
                    'id' => 3,
                    'title' => 'Choose Phone Number',
                    'subtitle' => 'Twilio virtual phone line',
                    'required' => true,
                ],
                'whatsapp' => [
                    'id' => 4,
                    'title' => 'Connect WhatsApp',
                    'subtitle' => 'Meta WhatsApp Business API',
                    'required' => true,
                ],
                'calling' => [
                    'id' => 5,
                    'title' => 'Enable Calling',
                    'subtitle' => 'Twilio Voice configuration',
                    'required' => true,
                ],
                'ai_agent' => [
                    'id' => 6,
                    'title' => 'Configure AI',
                    'subtitle' => 'OpenAI / Gemini / Claude assistant',
                    'required' => true,
                ],
                'crm' => [
                    'id' => 7,
                    'title' => 'Connect Existing CRM',
                    'subtitle' => 'HubSpot, Salesforce, Zoho, or Custom',
                    'required' => false,
                ],
                'business' => [
                    'id' => 8,
                    'title' => 'Business Profile',
                    'subtitle' => 'Company profile, timezone & hours',
                    'required' => true,
                ],
                'launch' => [
                    'id' => 9,
                    'title' => 'Complete',
                    'subtitle' => 'Account ready for launch',
                    'required' => true,
                ],
            ];
        }

        // WhatsApp API Only (6 Steps) — Phone & Calling are excluded
        return [
            'account' => [
                'id' => 1,
                'title' => 'Create Account',
                'subtitle' => 'Account created & verified',
                'required' => true,
            ],
            'choose_service' => [
                'id' => 2,
                'title' => 'Choose Service',
                'subtitle' => 'WhatsApp API (Core Platform)',
                'required' => true,
            ],
            'whatsapp' => [
                'id' => 3,
                'title' => 'Connect WhatsApp',
                'subtitle' => 'Meta WhatsApp Business API',
                'required' => true,
            ],
            'ai_agent' => [
                'id' => 4,
                'title' => 'Configure AI',
                'subtitle' => 'OpenAI / Gemini / Claude assistant',
                'required' => true,
            ],
            'business' => [
                'id' => 5,
                'title' => 'Business Profile',
                'subtitle' => 'Company profile, timezone & hours',
                'required' => true,
            ],
            'launch' => [
                'id' => 6,
                'title' => 'Complete',
                'subtitle' => 'Account ready for launch',
                'required' => true,
            ],
        ];
    }

    /**
     * Get detailed onboarding progress for a user and workspace.
     */
    public function getProgress(User $user): array
    {
        $workspace = ($user->workspace_id ? Workspace::find($user->workspace_id) : null)
            ?? $user->workspace
            ?? $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first()
            ?? ($user->client_id ? Workspace::where('client_id', $user->client_id)->first() : null)
            ?? Workspace::first();
        $workspaceId = $workspace?->id;
        $serviceType = $workspace?->service_type ?: 'whatsapp_only';

        $dbSteps = OnboardingStep::where('user_id', $user->id)
            ->get()
            ->keyBy('step');

        $activeStepDefinitions = $this->getStepsForService($serviceType);

        $steps = [];
        $currentStepKey = null;
        $doneCount = 0;
        $stepIndex = 1;

        foreach ($activeStepDefinitions as $key => $meta) {
            $dbStep = $dbSteps->get($key);
            $realCompleted = $this->verifyStep($user, $workspace, $key, $dbStep);

            $status = OnboardingStep::STATUS_PENDING;
            $lastError = $dbStep?->last_error;
            $payload = $dbStep?->payload_json ?? [];

            if ($realCompleted) {
                $status = OnboardingStep::STATUS_COMPLETED;
                $doneCount++;

                if (! $dbStep || ! $dbStep->completed) {
                    OnboardingStep::updateOrCreate(
                        ['user_id' => $user->id, 'step' => $key],
                        [
                            'workspace_id' => $workspaceId,
                            'status' => OnboardingStep::STATUS_COMPLETED,
                            'completed' => true,
                            'completed_at' => $dbStep?->completed_at ?? now(),
                            'last_error' => null,
                        ]
                    );
                }
            } elseif ($dbStep && $dbStep->status === OnboardingStep::STATUS_SKIPPED && ! $meta['required']) {
                $status = OnboardingStep::STATUS_SKIPPED;
            } elseif ($dbStep && $dbStep->status === OnboardingStep::STATUS_BLOCKED) {
                $status = OnboardingStep::STATUS_BLOCKED;
            }

            $isCompleted = ($status === OnboardingStep::STATUS_COMPLETED);

            // The first incomplete required step is the current active step
            if (! $isCompleted && $status !== OnboardingStep::STATUS_SKIPPED && $currentStepKey === null) {
                $currentStepKey = $key;
                if ($status === OnboardingStep::STATUS_PENDING) {
                    $status = OnboardingStep::STATUS_IN_PROGRESS;
                }
            }

            $steps[] = [
                'id' => $stepIndex++,
                'key' => $key,
                'title' => $meta['title'],
                'subtitle' => $meta['subtitle'],
                'required' => $meta['required'],
                'status' => $status,
                'completed' => $isCompleted,
                'is_current' => false,
                'last_error' => $lastError,
                'payload' => $payload,
            ];
        }

        // If all steps are complete, currentStep defaults to 'launch'
        if ($currentStepKey === null) {
            $currentStepKey = 'launch';
        }

        foreach ($steps as &$stepItem) {
            if ($stepItem['key'] === $currentStepKey) {
                $stepItem['is_current'] = true;
                if ($stepItem['status'] === OnboardingStep::STATUS_PENDING) {
                    $stepItem['status'] = OnboardingStep::STATUS_IN_PROGRESS;
                }
            }
        }
        unset($stepItem);

        $totalCount = count($steps);
        $percent = $totalCount > 0 ? (int) round(($doneCount / $totalCount) * 100) : 0;
        $isComplete = ($workspace?->onboarding_completed === true) || ($doneCount >= $totalCount);
        $nextStep = collect($steps)->firstWhere('completed', false);

        return [
            'steps' => $steps,
            'service_type' => $serviceType,
            'current_step_key' => $currentStepKey,
            'next_step' => $nextStep,
            'done' => $doneCount,
            'total' => $totalCount,
            'percent' => $percent,
            'is_complete' => $isComplete,
            'workspace' => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'service_type' => $serviceType,
                'industry' => $workspace->industry,
                'website' => $workspace->website,
                'country' => $workspace->country,
                'timezone' => $workspace->timezone ?? $user->timezone ?? 'Asia/Kolkata',
                'currency_code' => $workspace->currency_code ?? 'INR',
                'onboarding_completed' => (bool) $workspace->onboarding_completed,
            ] : null,
        ];
    }

    /**
     * Real-world backend verification for each step.
     */
    public function verifyStep(User $user, ?Workspace $workspace, string $step, ?OnboardingStep $dbStep = null): bool
    {
        $workspace = $workspace
            ?? ($user->workspace_id ? Workspace::find($user->workspace_id) : null)
            ?? $user->workspace
            ?? ($user->client_id ? Workspace::where('client_id', $user->client_id)->first() : null)
            ?? Workspace::first();
        $workspaceId = $workspace?->id;

        return match ($step) {
            'account' => $user->exists && $user->id > 0,

            'verify_email' => ! empty($user->email_verified_at),

            'choose_service' => ($dbStep && $dbStep->completed) || ($workspace !== null && filled($workspace->service_type)),

            'phone' => $workspaceId !== null && (
                (Schema::hasTable('telephony_phone_numbers') && TelephonyPhoneNumber::where('workspace_id', $workspaceId)->where('status', 'connected')->exists())
                || (Schema::hasTable('phone_numbers') && PhoneNumber::where('workspace_id', $workspaceId)->where('status', 'active')->exists())
                || (Schema::hasTable('contacts') && \App\Modules\Shared\Models\Contact::where('workspace_id', $workspaceId)->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'whatsapp', 'connect_first_channel' => $workspaceId !== null && (
                (Schema::hasTable('channel_accounts') && ChannelAccount::where('workspace_id', $workspaceId)->where('status', 'active')->exists())
                || (Schema::hasTable('whatsapp_business_accounts') && WhatsappBusinessAccount::where('workspace_id', $workspaceId)->whereNotNull('phone_number_id')->exists())
                || (Schema::hasTable('phone_numbers') && PhoneNumber::where('workspace_id', $workspaceId)->where('whatsapp_status', 'connected')->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'calling' => $workspaceId !== null && (
                (Schema::hasTable('telephony_phone_numbers') && TelephonyPhoneNumber::where('workspace_id', $workspaceId)->where('status', 'connected')->exists())
                || (Schema::hasTable('phone_numbers') && PhoneNumber::where('workspace_id', $workspaceId)->where('voice_enabled', true)->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'ai_agent', 'train_first_chatbot' => $workspaceId !== null && (
                (Schema::hasTable('ai_chatbots') && AiChatbot::where('workspace_id', $workspaceId)->exists())
                || (Schema::hasTable('voice_agents') && VoiceAgent::where('workspace_id', $workspaceId)->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'crm' => $workspaceId !== null && (
                (Schema::hasTable('crm_connections') && \App\Models\CrmConnection::where('workspace_id', $workspaceId)->where('status', 'active')->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'business' => $workspace !== null && (
                ! empty($workspace->industry) || ! empty($workspace->timezone) || ! empty($workspace->website) || ($dbStep && $dbStep->completed)
            ),

            'import_first_contacts' => $workspaceId !== null && (
                (Schema::hasTable('contacts') && \App\Modules\Shared\Models\Contact::where('workspace_id', $workspaceId)->exists())
                || ($dbStep && $dbStep->completed)
            ),

            'launch' => $workspace?->onboarding_completed === true || ($dbStep && $dbStep->completed),

            default => false,
        };
    }

    /**
     * Mark a step status with optional payload or error.
     */
    public function setStepStatus(
        User $user,
        string $step,
        string $status,
        array $payload = [],
        ?string $error = null
    ): OnboardingStep {
        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
        $isCompleted = ($status === OnboardingStep::STATUS_COMPLETED);

        return OnboardingStep::updateOrCreate(
            ['user_id' => $user->id, 'step' => $step],
            [
                'workspace_id' => $workspaceId,
                'status' => $status,
                'completed' => $isCompleted,
                'completed_at' => $isCompleted ? now() : null,
                'payload_json' => ! empty($payload) ? $payload : null,
                'last_error' => $error,
            ]
        );
    }

    public function markStepCompleted(User $user, string $step, array $payload = []): OnboardingStep
    {
        return $this->setStepStatus($user, $step, OnboardingStep::STATUS_COMPLETED, $payload);
    }

    /**
     * Compatibility helper for marking a step.
     */
    public function markStep(User $user, string $step, bool $verify = true): bool
    {
        $validSteps = array_merge(array_keys(self::ALL_STEPS), ['verify_email', 'connect_first_channel', 'import_first_contacts', 'train_first_chatbot']);
        if (! in_array($step, $validSteps, true)) {
            return false;
        }

        $workspaceId = $user->current_workspace_id ?? $user->workspace_id;
        $workspace = $workspaceId ? Workspace::find($workspaceId) : null;

        if ($verify && ! $this->verifyStep($user, $workspace, $step)) {
            return false;
        }

        $this->setStepStatus($user, $step, OnboardingStep::STATUS_COMPLETED);

        return true;
    }
}
