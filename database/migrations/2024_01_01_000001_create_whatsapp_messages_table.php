<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')
                ->nullable()
                ->constrained('whatsapp_conversations')
                ->nullOnDelete();
            $table->string('phone')->index();
            $table->enum('direccion', ['entrante', 'saliente']);
            $table->string('tipo')->default('text');
            $table->text('contenido')->nullable();
            $table->string('whatsapp_message_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
