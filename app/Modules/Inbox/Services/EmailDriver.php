<?php

namespace App\Modules\Inbox\Services;

use App\Models\SmtpConfiguration;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\Message;
use App\Services\Mail\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailDriver implements ChannelDriverInterface
{
    public function __construct(private readonly MailService $mailService) {}

    /**
     * Send an outbound email message
     */
    public function send(Message $message): string
    {
        $conversation = $message->conversation;
        $contact = $conversation?->contact;
        $toEmail = $contact?->email;

        if (empty($toEmail)) {
            throw new \InvalidArgumentException("Contact does not have a valid email address.");
        }

        $subject = "Re: Conversation #{$conversation->id} - Growbridge Connect";
        $body = $message->body ?? '';

        try {
            $this->mailService->sendRaw(
                to: $toEmail,
                subject: $subject,
                htmlBody: nl2br(e($body)),
                textBody: strip_tags($body)
            );

            return 'email_' . Str::uuid();
        } catch (\Throwable $e) {
            Log::error('EmailDriver send failed', ['error' => $e->getMessage(), 'message_id' => $message->id]);
            throw $e;
        }
    }

    /**
     * Handle an inbound email webhook
     */
    public function receiveWebhook(Request $request): array
    {
        // Handled by inbound mail webhook parser if enabled
        return [];
    }

    /**
     * Verify SMTP / Mail credentials
     */
    public function verifyCreds(): bool
    {
        return SmtpConfiguration::isConfigured();
    }
}
