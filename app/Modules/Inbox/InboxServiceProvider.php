<?php

namespace App\Modules\Inbox;

use App\Modules\Inbox\Services\Adapters\EmailAdapter;
use App\Modules\Inbox\Services\Adapters\InstagramAdapter;
use App\Modules\Inbox\Services\Adapters\MessengerAdapter;
use App\Modules\Inbox\Services\Adapters\TwilioAdapter;
use App\Modules\Inbox\Services\Adapters\WhatsAppAdapter;
use App\Modules\Inbox\Services\EmailDriver;
use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Inbox\Services\MessengerDriver;
use App\Modules\Shared\Services\ChannelAdapterManager;
use App\Modules\Shared\Services\ChannelManager;
use App\Services\Conversation\ConversationService;
use Illuminate\Support\ServiceProvider;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelAdapterManager::class, function ($app) {
            $manager = new ChannelAdapterManager();
            $manager->register('whatsapp', WhatsAppAdapter::class);
            $manager->register('instagram', InstagramAdapter::class);
            $manager->register('messenger', MessengerAdapter::class);
            $manager->register('email', EmailAdapter::class);
            $manager->register('phone', TwilioAdapter::class);
            return $manager;
        });

        $this->app->singleton(ConversationService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Register legacy drivers
        if ($this->app->bound(ChannelManager::class)) {
            $manager = $this->app->make(ChannelManager::class);
            $manager->register('messenger', MessengerDriver::class);
            $manager->register('instagram', InstagramDriver::class);
            $manager->register('email', EmailDriver::class);
        }
    }
}
