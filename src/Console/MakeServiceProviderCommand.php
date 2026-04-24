<?php

namespace Wapkit\LaravelBot\Console;

use Illuminate\Console\Command;

class MakeServiceProviderCommand extends Command
{
    protected $signature   = 'wapkit:make-provider';
    protected $description = 'Generate WhatsAppServiceProvider and register it in bootstrap/providers.php';

    public function handle(): int
    {
        $providerPath    = app_path('Providers/WhatsAppServiceProvider.php');
        $bootstrapPath   = base_path('bootstrap/providers.php');
        $providerEntry   = 'App\\Providers\\WhatsAppServiceProvider::class';

        // 1. Generate the provider file
        if (file_exists($providerPath)) {
            $this->warn("WhatsAppServiceProvider already exists at app/Providers/WhatsAppServiceProvider.php — skipped.");
        } else {
            $stub = file_get_contents(__DIR__.'/../../stubs/service-provider.stub');
            file_put_contents($providerPath, $stub);
            $this->info('  ✔ app/Providers/WhatsAppServiceProvider.php created');
        }

        // 2. Register in bootstrap/providers.php
        if (! file_exists($bootstrapPath)) {
            $this->error('bootstrap/providers.php not found. Are you using Laravel 11+?');
            return self::FAILURE;
        }

        $bootstrap = file_get_contents($bootstrapPath);

        if (str_contains($bootstrap, $providerEntry)) {
            $this->warn('  WhatsAppServiceProvider already registered in bootstrap/providers.php — skipped.');
        } else {
            $bootstrap = str_replace(
                'App\\Providers\\AppServiceProvider::class,',
                "App\\Providers\\AppServiceProvider::class,\n    {$providerEntry},",
                $bootstrap
            );
            file_put_contents($bootstrapPath, $bootstrap);
            $this->info('  ✔ Registered in bootstrap/providers.php');
        }

        $this->info('');
        $this->info(' ✅ WhatsAppServiceProvider ready!');
        $this->info('');
        $this->info(' Next steps:');
        $this->info('');
        $this->line('  1. Generate your handlers:');
        $this->line('     <fg=cyan>php artisan wapkit:make-handler MenuHandler</>');
        $this->line('     <fg=cyan>php artisan wapkit:make-context MyContextBuilder</>');
        $this->info('');
        $this->line('  2. Uncomment and register them in:');
        $this->line('     <fg=yellow>app/Providers/WhatsAppServiceProvider.php</>');
        $this->info('');

        return self::SUCCESS;
    }
}
