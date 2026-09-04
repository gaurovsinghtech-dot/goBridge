<?php

namespace App\Modules\Voice;

use App\Modules\Voice\Services\VoiceDriverManager;
use Illuminate\Support\ServiceProvider;

class VoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VoiceDriverManager::class, function () {
            return new VoiceDriverManager;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
