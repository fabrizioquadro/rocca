<?php

namespace App\Console\Commands;

use App\Services\FeegowAplicacaoService;
use Illuminate\Console\Command;

/**
 * Processa a fila de envio das aplicações para a Feegow.
 *
 * Rodar a cada minuto no cron/scheduler:
 *   php artisan feegow:fila
 */
class FeegowFilaProcessar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feegow:fila {--limite=20 : Quantidade máxima de registros por execução}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reenvia para a Feegow as aplicações que ainda não foram registradas';

    /**
     * Execute the console command.
     */
    public function handle(FeegowAplicacaoService $feegow): int
    {
        if (! $feegow->configurado()) {
            $this->warn('Integração com a Feegow não configurada (FEEGOW_BASE_URL / FEEGOW_TOKEN).');

            return self::SUCCESS;
        }

        $total = $feegow->processarFila((int) $this->option('limite'));

        $this->info("Feegow: {$total} registro(s) processado(s).");

        return self::SUCCESS;
    }
}
