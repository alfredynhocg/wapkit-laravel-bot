<?php

namespace Wapkit\LaravelBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeContextCommand extends Command
{
    protected $signature   = 'wapkit:make-context {name : ContextBuilder class name (e.g. TiendaContextBuilder)}';
    protected $description = 'Generate a new WapKit ContextBuilder class';

    public function handle(): int
    {
        $name      = Str::studly($this->argument('name'));
        $namespace = 'App\\WhatsApp';
        $dir       = app_path('WhatsApp');
        $path      = "{$dir}/{$name}.php";

        if (file_exists($path)) {
            $this->error("ContextBuilder [{$name}] already exists.");
            return self::FAILURE;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stub = file_get_contents(__DIR__.'/../../stubs/context.stub');
        $stub = str_replace(['{{ namespace }}', '{{ class }}'], [$namespace, $name], $stub);

        file_put_contents($path, $stub);

        $this->info("ContextBuilder [{$name}] created at <fg=cyan>app/WhatsApp/{$name}.php</>");
        $this->info('');
        $this->line('Bind it in your <fg=yellow>WhatsAppServiceProvider</>:');
        $this->info('');
        $this->line("  <fg=cyan>use Wapkit\\LaravelBot\\Contracts\\ContextBuilderInterface;</>");
        $this->line('');
        $this->line("  <fg=cyan>\$this->app->bind(ContextBuilderInterface::class, {$name}::class);</>");
        $this->info('');

        return self::SUCCESS;
    }
}
