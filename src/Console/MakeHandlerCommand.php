<?php

namespace Wapkit\LaravelBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeHandlerCommand extends Command
{
    protected $signature   = 'wapkit:make-handler {name : Handler class name (e.g. MenuHandler)}';
    protected $description = 'Generate a new WapKit bot handler class';

    public function handle(): int
    {
        $name      = Str::studly($this->argument('name'));
        $namespace = 'App\\WhatsApp\\Handlers';
        $dir       = app_path('WhatsApp/Handlers');
        $path      = "{$dir}/{$name}.php";

        if (file_exists($path)) {
            $this->error("Handler [{$name}] already exists.");
            return self::FAILURE;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stub = file_get_contents(__DIR__.'/../../stubs/handler.stub');
        $stub = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $name], $stub);

        file_put_contents($path, $stub);

        $this->info("Handler [{$name}] created at <fg=cyan>app/WhatsApp/Handlers/{$name}.php</>");
        $this->info('');
        $this->line('Register it in your <fg=yellow>WhatsAppServiceProvider</>:');
        $this->info('');
        $this->line("  <fg=cyan>\$registry->intent('my_intent', {$name}::class);</>");
        $this->line("  <fg=cyan>\$registry->button('btn_id',    {$name}::class);</>");
        $this->info('');

        return self::SUCCESS;
    }
}
