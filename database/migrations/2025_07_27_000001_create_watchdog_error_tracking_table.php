<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create('watchdog_error_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('error_hash', 64)->index(); // Hash único para agrupar erros similares
            $table->string('exception_class')->index();
            $table->text('message');
            $table->string('file')->nullable(); // Make file nullable for log messages
            $table->integer('line')->nullable(); // Make line nullable for log messages
            $table->string('environment', 20)->index();
            $table->string('level', 20)->index(); // error, warning, critical, etc.
            $table->integer('severity_score')->default(1); // 1-10 (calculado automaticamente)
            $table->json('context')->nullable(); // Request data, user info, etc.
            $table->json('stack_trace')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_id', 255)->nullable()->index(); // Support both integer and UUID user IDs
            $table->timestamp('first_occurred_at')->index();
            $table->timestamp('last_occurred_at')->index();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->unsignedInteger('hourly_count')->default(1); // Contagem na última hora
            $table->unsignedInteger('daily_count')->default(1); // Contagem no último dia
            $table->boolean('is_resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('last_notification_at')->nullable();
            $table->timestamps();

            // Índices compostos para performance
            $table->index(['error_hash', 'environment']);
            $table->index(['exception_class', 'environment']);
            $table->index(['level', 'environment', 'created_at']);
            $table->index(['severity_score', 'environment']);
            $table->index(['occurrence_count', 'environment']);
            $table->index(['last_occurred_at', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('watchdog_error_tracking');
    }

    public function getConnection(): ?string
    {
        return config('watchdog-discord.database.connection');
    }
};
