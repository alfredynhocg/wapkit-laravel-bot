<?php

namespace Wapkit\LaravelBot\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'wapkit:install';
    protected $description = 'Install WapKit — publish config and run migrations';

    public function handle(): int
    {
        $this->info('');
        $this->info(' ██╗    ██╗ █████╗ ██████╗ ██╗  ██╗██╗████████╗');
        $this->info(' ██║    ██║██╔══██╗██╔══██╗██║ ██╔╝██║╚══██╔══╝');
        $this->info(' ██║ █╗ ██║███████║██████╔╝█████╔╝ ██║   ██║   ');
        $this->info(' ██║███╗██║██╔══██║██╔═══╝ ██╔═██╗ ██║   ██║   ');
        $this->info(' ╚███╔███╔╝██║  ██║██║     ██║  ██╗██║   ██║   ');
        $this->info('  ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝   ╚═╝  ');
        $this->info('');
        $this->info(' WhatsApp Bot Engine for Laravel');
        $this->info(' ─────────────────────────────────');
        $this->info('');

        // 1. Publish config
        $this->comment('→ Publishing config...');
        $this->callSilent('vendor:publish', [
            '--tag'   => 'whatsapp-bot-config',
            '--force' => false,
        ]);
        $this->info('  ✔ config/whatsapp-bot.php published');

        // 2. Run migrations
        $this->comment('→ Running migrations...');
        $this->callSilent('migrate', ['--path' => 'vendor/wapkit/laravel-bot/database/migrations']);
        $this->info('  ✔ Database tables created');

        // 3. Generate WhatsAppServiceProvider
        $this->comment('→ Generating WhatsAppServiceProvider...');
        $this->call('wapkit:make-provider');

        $this->info('');
        $this->info(' ✅ WapKit installed successfully!');
        $this->info('');
        $this->info(' Next steps:');
        $this->info('');
        $this->info('  1. Add your WhatsApp credentials to .env:');
        $this->info('');
        $this->line('     <fg=yellow>WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id</>');
        $this->line('     <fg=yellow>WHATSAPP_ACCESS_TOKEN=your_access_token</>');
        $this->line('     <fg=yellow>WHATSAPP_APP_SECRET=your_app_secret</>');
        $this->line('     <fg=yellow>WHATSAPP_VERIFY_TOKEN=your_verify_token</>');
        $this->info('');
        $this->info('  2. Send your first message:');
        $this->info('');
        $this->line('     <fg=cyan>use Wapkit\LaravelBot\Facades\WapBot;</>');
        $this->line('');
        $this->line('     <fg=cyan>WapBot::sendText(\'+1234567890\', \'Hello from Laravel! 👋\');</>');
        $this->info('');
        $this->info('  3. Build your bot:');
        $this->info('');
        $this->line('     <fg=cyan>php artisan wapkit:make-context MyContextBuilder</>');
        $this->line('     <fg=cyan>php artisan wapkit:make-handler MyMenuHandler</>');
        $this->info('');
        $this->info('  📖 Full docs: https://github.com/wapkit/laravel-bot');
        $this->info('');

        return self::SUCCESS;
    }
}
