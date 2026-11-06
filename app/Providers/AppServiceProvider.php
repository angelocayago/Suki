<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Services\Sms\HttpSmsSender;
use App\Services\Sms\NullSmsSender;
use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => config('services.sms.driver') === 'http'
            ? new HttpSmsSender()
            : new NullSmsSender());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        \Illuminate\Support\Facades\Storage::extend('chat_private_s3',fn($app,$config)=>
            \App\Filesystem\PrivateChatS3Filesystem::make($app['filesystem'],$config));
    }
}
