<?php

namespace VinkiusLabs\WatchdogDiscord\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\WatchdogDiscord\DiscordNotifier;

class TestLogLevelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog:test-log-levels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all log levels to diagnose filtering issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Teste de Diagnóstico dos Log Levels ===');
        $this->newLine();

        // Teste 1: Verificar configurações
        $this->info('1. Verificando configurações:');
        $this->line('   - Enabled: ' . (config('watchdog-discord.enabled') ? 'SIM' : 'NÃO'));
        $this->line('   - Webhook URL: ' . (config('watchdog-discord.webhook_url') ? 'CONFIGURADO' : 'NÃO CONFIGURADO'));
        $this->line('   - Log Levels: ' . json_encode(config('watchdog-discord.log_levels', [])));
        $this->line('   - Min Severity: ' . config('watchdog-discord.min_severity', 'não definido'));
        $this->newLine();

        // Teste 2: Verificar método shouldReportLogLevel
        $this->info('2. Testando shouldReportLogLevel:');
        $notifier = app(DiscordNotifier::class);
        $reflection = new \ReflectionClass($notifier);
        $method = $reflection->getMethod('shouldReportLogLevel');
        $method->setAccessible(true);

        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        foreach ($levels as $level) {
            try {
                $shouldReport = $method->invoke($notifier, $level);
                $this->line("   - $level: " . ($shouldReport ? 'SIM' : 'NÃO'));
            } catch (\Exception $e) {
                $this->line("   - $level: ERRO - " . $e->getMessage());
            }
        }
        $this->newLine();

        // Teste 3: Testar método sendLog diretamente
        $this->info('3. Testando método sendLog diretamente:');
        foreach (['info', 'warning', 'error'] as $level) {
            try {
                $this->line("   Testando $level...");
                $notifier->sendLog($level, "Teste direto do comando: $level", ['test' => true]);
                $this->line("   - $level: ENVIADO");
            } catch (\Exception $e) {
                $this->line("   - $level: ERRO - " . $e->getMessage());
            }
        }
        $this->newLine();

        // Teste 4: Testar Log::listen manualmente
        $this->info('4. Testando Log::listen manual:');
        $captured = [];

        Log::listen(function ($logEntry) use (&$captured) {
            $captured[] = $logEntry->level . ': ' . $logEntry->message;
        });

        Log::info('Teste manual info');
        Log::warning('Teste manual warning');
        Log::error('Teste manual error');

        $this->line('   Logs capturados: ' . count($captured));
        foreach ($captured as $log) {
            $this->line("   - $log");
        }
        $this->newLine();

        // Teste 5: Verificar se o ServiceProvider está registrando o listener
        $this->info('5. Verificando ServiceProvider:');
        $providers = app()->getLoadedProviders();
        $watchdogProvider = null;
        foreach ($providers as $provider => $loaded) {
            if (strpos($provider, 'WatchdogDiscord') !== false) {
                $watchdogProvider = $provider;
                break;
            }
        }

        if ($watchdogProvider) {
            $this->line("   - ServiceProvider encontrado: $watchdogProvider");
            $this->line('   - Status: ' . ($providers[$watchdogProvider] ? 'CARREGADO' : 'NÃO CARREGADO'));
        } else {
            $this->line('   - ServiceProvider: NÃO ENCONTRADO');
        }

        $this->newLine();
        $this->info('=== Resultado do Diagnóstico ===');
        $this->line('Se "info" e "warning" mostrarem "NÃO" no teste 2, o problema está na configuração log_levels.');
        $this->line('Se mostrarem "SIM" no teste 2 mas não enviarem no teste 3, o problema pode ser webhook URL.');
        $this->line('Se o teste 4 não capturar logs, o Log::listen não está funcionando.');
    }
}
