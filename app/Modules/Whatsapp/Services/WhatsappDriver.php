<?php

namespace App\Modules\Whatsapp\Services;

use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\Workspace;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ContactService;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\Crm\CrmSyncService;
use App\Services\Storage\StorageService;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappDriver implements ChannelDriverInterface
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {}

    public function send(Message $message): string
    {
        $conversation = $message->conversation;
        $contact = $conversation->contact;
        $phone = $contact->phone_e164;

        // Customer service window check (24h rule)
        if ($message->type !== 'template' && ! $conversation->isWhatsappWindowOpen()) {
            Log::warning("WhatsApp outbound free-form message sent outside 24-hour customer service window for conversation #{$conversation->id}");
        }

        // Prefer the phone number tied to this conversation's channel account so
        // outbound replies go from the same number the customer wrote to.
        $phoneNumberId = $conversation->channelAccount?->phone_number_id;
        $client = $phoneNumberId
            ? CloudApiClient::forPhoneNumber($phoneNumberId, $conversation->workspace_id)
            : null;
        $client ??= CloudApiClient::forWorkspace($conversation->workspace_id);

        if (! $client) {
            if (app()->environment('testing')) {
                return 'wamid.test_' . bin2hex(random_bytes(12));
            }
            throw new \RuntimeException('No active WhatsApp account for workspace.');
        }

        $payload = $message->payload ?? [];

        $resp = match ($message->type) {
            'template' => $client->sendTemplate($phone, $payload['template']['name'] ?? '', $payload['template']['language'] ?? 'en', $payload['template']['components'] ?? []),
            'interactive' => $client->sendInteractive($phone, $payload['interactive'] ?? []),
            'image' => $client->sendMedia($phone, 'image', $payload['media_id'] ?? '', $payload['caption'] ?? null, null, $payload['link'] ?? null),
            'video' => $client->sendMedia($phone, 'video', $payload['media_id'] ?? '', $payload['caption'] ?? null, null, $payload['link'] ?? null),
            'document' => $client->sendMedia($phone, 'document', $payload['media_id'] ?? '', $payload['caption'] ?? null, $payload['filename'] ?? null, $payload['link'] ?? null),
            'audio' => $client->sendMedia($phone, 'audio', $payload['media_id'] ?? ''),
            'location' => $client->sendLocation(
                $phone,
                (float) ($payload['location']['latitude'] ?? 0),
                (float) ($payload['location']['longitude'] ?? 0),
                $payload['location']['name'] ?? null,
                $payload['location']['address'] ?? null,
            ),
            default => $client->sendText($phone, $message->body ?? ''),
        };

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp send failed: ' . $resp->body());
        }

        $providerId = $resp->json('messages.0.id', 'wamid.' . uniqid());

        // Sync CRM timeline for outbound message
        $workspace = Workspace::find($conversation->workspace_id);
        if ($workspace) {
            app(CrmSyncService::class)->onMessageReceivedOrSent(
                $workspace,
                $phone,
                'whatsapp',
                'outbound',
                $message->body ?: "(WhatsApp {$message->type})"
            );
        }

        return $providerId;
    }

    public function receiveWebhook(Request $request): array
    {
        return $this->processWebhookPayload($request->all());
    }

    public function processWebhookPayload(array $payload, string $verifyToken = ''): array
    {
        $processed = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            $wabaId = (string) ($entry['id'] ?? '');

            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'message_template_status_update') {
                    $this->processTemplateStatusUpdate($wabaId, $value);

                    continue;
                }

                if (in_array($field, ['phone_number_quality_update', 'phone_number_name_update', 'account_update'], true)) {
                    $this->processPhoneNumberUpdate($value);

                    continue;
                }

                foreach ($value['messages'] ?? [] as $msg) {
                    try {
                        $processed[] = $this->processInboundMessage($value, $msg);
                    } catch (\Throwable $e) {
                        Log::error('WhatsApp webhook processing failed', ['error' => $e->getMessage(), 'msg' => $msg]);
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->processStatusUpdate($status);
                }
            }
        }

        return $processed;
    }

    private function processTemplateStatusUpdate(string $wabaId, array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        $name = $value['message_template_name'] ?? null;
        $language = $value['message_template_language'] ?? 'en';

        if (! $wabaId || ! $name || ! $event) {
            return;
        }

        $statusMap = [
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'PENDING' => 'PENDING',
            'PAUSED' => 'PAUSED',
            'DISABLED' => 'PAUSED',
        ];
        $status = $statusMap[$event] ?? null;
        if (! $status) {
            return;
        }

        $reason = $value['reason'] ?? $value['rejection_reason'] ?? null;

        WhatsappTemplate::where('waba_id', $wabaId)
            ->where('name', $name)
            ->where('language', $language)
            ->update(array_filter([
                'status' => $status,
                'rejection_reason' => $status === 'REJECTED' ? (is_string($reason) ? $reason : json_encode($reason)) : null,
                'meta_template_id' => isset($value['message_template_id'])
                    ? (string) $value['message_template_id']
                    : null,
            ]));
    }

    private function processPhoneNumberUpdate(array $value): void
    {
        $phoneNumberId = $value['phone_number_id'] ?? null;
        if (! $phoneNumberId) {
            return;
        }

        // Map Meta's name decision to our name_status values
        $decision = strtoupper((string) ($value['decision'] ?? ''));
        $nameStatus = match ($decision) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'DECLINED',
            default => null,
        };

        $patch = array_filter([
            'quality_rating' => $value['current_quality_rating'] ?? $value['quality_rating'] ?? null,
            'messaging_limit_tier' => $value['current_limit'] ?? $value['messaging_limit_tier'] ?? null,
            'display_phone' => $value['display_phone_number'] ?? null,
            'verified_name' => $nameStatus === 'APPROVED'
                ? ($value['requested_verified_name'] ?? $value['verified_name'] ?? null)
                : ($value['verified_name'] ?? null),
            'name_status' => $nameStatus,
            'requested_verified_name' => in_array($nameStatus, ['APPROVED', 'DECLINED'], true) ? null : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($patch === []) {
            return;
        }

        WhatsappPhoneNumber::where('phone_number_id', (string) $phoneNumberId)->update($patch);

        Log::info('whatsapp.phone_number.updated', [
            'phone_number_id' => $phoneNumberId,
            'patch' => $patch,
        ]);
    }

    public function verifyCreds(): bool
    {
        return true;
    }

    private function processInboundMessage(array $value, array $msg): Message
    {
        $msgId = $msg['id'] ?? null;

        // Idempotency guard — skip if already processed
        if ($msgId && ! app(WebhookIdempotencyService::class)->isNewEvent('whatsapp_msg', $msgId)) {
            $existing = Message::where('provider_message_id', $msgId)->first();
            if ($existing) {
                return $existing;
            }
            throw new \RuntimeException("Duplicate webhook skipped (concurrent): {$msgId}");
        }

        $phoneId = $value['metadata']['phone_number_id'] ?? '';
        $fromPhone = $msg['from'] ?? '';

        $channelAccount = ChannelAccount::where('phone_number_id', $phoneId)
            ->where('channel', 'whatsapp')
            ->first();

        if (! $channelAccount) {
            Log::warning('WhatsApp inbound dropped — no channel_account match', [
                'phone_number_id' => $phoneId,
                'from' => $fromPhone,
                'msg_id' => $msg['id'] ?? null,
            ]);

            throw new \RuntimeException("No channel_account found for phone_number_id={$phoneId}");
        }

        $workspaceId = (int) $channelAccount->workspace_id;

        $profileName = null;
        foreach ($value['contacts'] ?? [] as $c) {
            if (($c['wa_id'] ?? '') === $fromPhone && ! empty($c['profile']['name'])) {
                $profileName = $c['profile']['name'];
                break;
            }
        }
        if (! $profileName && ! empty($value['contacts'][0]['profile']['name'])) {
            $profileName = $value['contacts'][0]['profile']['name'];
        }

        $contactPayload = [
            'phone_e164' => '+' . $fromPhone,
            'opt_in_whatsapp' => true,
            'source' => 'whatsapp_inbound',
        ];

        if ($profileName) {
            $parts = explode(' ', trim($profileName), 2);
            $contactPayload['first_name'] = $parts[0];
            $contactPayload['last_name'] = $parts[1] ?? null;
        }

        $contact = $this->contactService->upsert($workspaceId, $contactPayload);

        $conversation = Conversation::firstOrCreate(
            ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => $channelAccount?->id],
            ['status' => 'open', 'external_thread_id' => $fromPhone]
        );

        $type = $msg['type'] ?? 'text';
        $interactive = is_array($msg['interactive'] ?? null) ? $msg['interactive'] : [];
        $textBlock = is_array($msg['text'] ?? null) ? $msg['text'] : [];

        // Extract human-readable body
        $body = ($textBlock['body'] ?? null)
            ?? (($msg['button'] ?? [])['text'] ?? null)
            ?? (($interactive['button_reply'] ?? [])['title'] ?? null)
            ?? (($interactive['list_reply'] ?? [])['title'] ?? null)
            ?? (is_array($msg[$type] ?? null) && ! isset($msg[$type][0]) ? ($msg[$type]['caption'] ?? null) : null)
            ?? ($msg['caption'] ?? null)
            ?? ($msg['errors'][0]['title'] ?? null);

        // Type-specific body fallbacks
        if ($body === null || $body === '') {
            $body = match ($type) {
                'location' => implode(', ', array_filter([
                    $msg['location']['name'] ?? null,
                    $msg['location']['address'] ?? null,
                    isset($msg['location']['latitude'], $msg['location']['longitude'])
                        ? ($msg['location']['latitude'] . ',' . $msg['location']['longitude'])
                        : null,
                ])) ?: '📍 Location',
                'contacts' => isset($msg['contacts'][0]['name']['formatted_name'])
                    ? ('👤 ' . $msg['contacts'][0]['name']['formatted_name'])
                    : '👤 Contact',
                'poll' => '📊 ' . ($msg['poll']['question'] ?? ($msg['interactive']['poll_creation']['name'] ?? 'Poll')),
                'event' => '📅 ' . ($msg['event']['title'] ?? ($msg['event']['name'] ?? 'Event')),
                'image' => '🖼 Image',
                'video' => '🎬 Video',
                'audio' => '🎤 Audio',
                'document' => '📄 ' . ($msg['document']['filename'] ?? 'Document'),
                'sticker' => '😊 Sticker',
                'reaction' => $msg['reaction']['emoji'] ?? '👍',
                default => '',
            };
        }

        // S3 Media Storage: Download and store inbound media in workspace-scoped path
        $mediaId = null;
        $mediaUrl = null;

        if (in_array($type, ['image', 'video', 'audio', 'document'], true)) {
            $mediaId = $msg[$type]['id'] ?? null;
            if ($mediaId) {
                try {
                    $client = CloudApiClient::forPhoneNumber($phoneId, $workspaceId) ?? CloudApiClient::forWorkspace($workspaceId);
                    if ($client) {
                        $metaMedia = $client->getMediaUrl($mediaId);
                        if (! empty($metaMedia['url'])) {
                            $rawBytes = $client->downloadMedia($metaMedia['url']);
                            $mimeType = $metaMedia['mime_type'] ?: 'application/octet-stream';
                            $ext = match ($type) {
                                'image' => 'jpg',
                                'video' => 'mp4',
                                'audio' => 'ogg',
                                'document' => 'pdf',
                                default => 'bin',
                            };
                            $filename = ($msg[$type]['filename'] ?? null) ?: "whatsapp_{$type}_{$mediaId}.{$ext}";

                            $storageService = app(StorageService::class);
                            $storedFile = $storageService->upload(
                                $rawBytes,
                                'whatsapp_media',
                                null,
                                ['filename' => $filename, 'mime_type' => $mimeType, 'workspace_id' => $workspaceId]
                            );
                            $mediaUrl = $storageService->temporaryUrl($storedFile);
                        }
                    }
                } catch (\Throwable $mediaErr) {
                    Log::warning('WhatsApp media download to S3 failed: ' . $mediaErr->getMessage(), ['media_id' => $mediaId]);
                }
            }
        }

        $allowedTypes = [
            'text', 'template', 'media', 'interactive', 'reaction', 'image', 'video',
            'document', 'audio', 'location', 'contacts', 'sticker', 'order', 'poll', 'event', 'unsupported'
        ];

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => in_array($type, $allowedTypes, true) ? $type : 'unsupported',
            'payload' => $msg,
            'body' => $body,
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'status' => 'delivered',
            'provider_message_id' => $msg['id'] ?? null,
            'sent_by' => 'human',
            'sent_at' => now()->createFromTimestamp($msg['timestamp'] ?? time()),
        ]);

        $conversation->update([
            'last_message_at' => $message->sent_at,
            'status' => 'open',
            'unread_count' => $conversation->unread_count + 1,
            'last_inbound_at' => $message->sent_at,
            'first_response_at' => $conversation->first_response_at && $conversation->last_inbound_at
                ? ($message->sent_at > $conversation->first_response_at ? null : $conversation->first_response_at)
                : $conversation->first_response_at,
        ]);

        // Sync CRM timeline for inbound message
        $workspace = Workspace::find($workspaceId);
        if ($workspace) {
            app(CrmSyncService::class)->onMessageReceivedOrSent(
                $workspace,
                '+' . $fromPhone,
                'whatsapp',
                'inbound',
                $body ?: "(WhatsApp {$type})",
                $profileName
            );
        }

        // Fire typed event for automations / AI
        MessageReceived::dispatch($message);

        return $message;
    }

    private function processStatusUpdate(array $status): void
    {
        $providerId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if (! $providerId || ! $newStatus) {
            return;
        }

        $statusMap = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'];
        $mapped = $statusMap[$newStatus] ?? null;
        if (! $mapped) {
            return;
        }

        // Status priority — never downgrade (e.g. delivered -> sent).
        $priority = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        $newPriority = $priority[$mapped] ?? 0;

        // 1. Update inbox `messages` row for this wamid.
        $message = Message::where('provider_message_id', $providerId)->first();
        if ($message) {
            $current = $priority[$message->status] ?? 0;
            if ($newPriority >= $current) {
                $message->update(['status' => $mapped]);
                $message->load('conversation');
                MessageStatusUpdated::dispatch($message);
            }
        }

        // 2. Update campaign_recipients row for this wamid (separate table).
        $recipient = CampaignRecipient::where('provider_message_id', $providerId)->first();
        if ($recipient) {
            $current = $priority[$recipient->status] ?? 0;
            if ($newPriority < $current) {
                return;
            }

            $now = now();
            $patch = ['status' => $mapped];

            if ($mapped === 'sent' && ! $recipient->sent_at) {
                $patch['sent_at'] = $now;
            }
            if ($mapped === 'delivered') {
                if (! $recipient->sent_at) {
                    $patch['sent_at'] = $now;
                }
                if (! $recipient->delivered_at) {
                    $patch['delivered_at'] = $now;
                }
            }
            if ($mapped === 'read') {
                if (! $recipient->sent_at) {
                    $patch['sent_at'] = $now;
                }
                if (! $recipient->delivered_at) {
                    $patch['delivered_at'] = $now;
                }
                if (! $recipient->read_at) {
                    $patch['read_at'] = $now;
                }
            }
            if ($mapped === 'failed') {
                $patch['failed_reason'] = substr(
                    $status['errors'][0]['title']
                        ?? $status['errors'][0]['message']
                        ?? 'unknown',
                    0,
                    512,
                );
            }

            $recipient->update($patch);
        }
    }
}
