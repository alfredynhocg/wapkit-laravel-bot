<?php

namespace Wapkit\LaravelBot;

use Wapkit\LaravelBot\Console\InstallCommand;
use Wapkit\LaravelBot\Console\MakeContextCommand;
use Wapkit\LaravelBot\Console\MakeHandlerCommand;
use Wapkit\LaravelBot\Contracts\ContextBuilderInterface;
use Wapkit\LaravelBot\Contracts\IntentResolverInterface;
use Wapkit\LaravelBot\Core\HandlerRegistry;
use Wapkit\LaravelBot\Facades\WapBot;
use Wapkit\LaravelBot\Support\KeywordIntentResolver;
use Wapkit\LaravelBot\Support\NullContextBuilder;
use Illuminate\Support\ServiceProvider;

class WhatsAppBotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/whatsapp-bot.php', 'whatsapp-bot');

        // HandlerRegistry is a singleton so all registrations (done in boot)
        // are visible throughout the request lifecycle.
        $this->app->singleton(HandlerRegistry::class);

        // Default implementations — override in your app's service provider:
        //   $this->app->bind(ContextBuilderInterface::class, MyContextBuilder::class);
        //   $this->app->bind(IntentResolverInterface::class, MyIntentResolver::class);
        $this->app->bind(ContextBuilderInterface::class, NullContextBuilder::class);
        $this->app->bind(IntentResolverInterface::class, KeywordIntentResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/whatsapp-bot.php' => config_path('whatsapp-bot.php'),
            ], 'whatsapp-bot-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'whatsapp-bot-migrations');

            $this->publishes([
                __DIR__.'/../stubs' => base_path('stubs/wapkit'),
            ], 'whatsapp-bot-stubs');

            $this->commands([
                InstallCommand::class,
                MakeHandlerCommand::class,
                MakeContextCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('whatsapp-bot.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/whatsapp.php');
        }
    }

    /**
     * Get the services provided by the provider.
     * Enables IDE autocompletion for the WapBot facade.
     */
    public function provides(): array
    {
        return [WapBot::class];
    }
}
