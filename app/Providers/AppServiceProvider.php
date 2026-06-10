<?php

namespace App\Providers;

use App\Channels\SmsChannel;
use App\Services\SmsService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Notification::extend('sms', fn ($app) => new SmsChannel($app->make(SmsService::class)));
    }
}
