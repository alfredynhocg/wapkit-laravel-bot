<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->text('input');
            $table->text('prompt');
            $table->text('output')->nullable();
            $table->string('modelo')->default('unknown');
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->boolean('error')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_ai_logs');
    }
};
