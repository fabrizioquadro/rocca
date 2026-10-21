<?php

namespace App\Console\Commands;

use App\Services\OmieService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Valida as credenciais da Omie consultando cadastros básicos.
 *
 *   php artisan omie:testar
 */
class OmieTestar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'omie:testar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa as credenciais da Omie consultando empresas, serviços e formas de pagamento';

    /**
     * Execute the console command.
     */
    public function handle(OmieService $omie): int
    {
        if (! $omie->configurado()) {
            $this->warn('Integração com a Omie não configurada (OMIE_APP_KEY / OMIE_APP_SECRET).');

            return self::FAILURE;
        }

        $falhou = false;

        $consultas = [
            'Empresas' => fn () => $omie->listarEmpresas(),
            'Serviços' => fn () => $omie->listarServicos(),
            'Formas de pagamento' => fn () => $omie->listarFormasPagamento(),
        ];

        foreach ($consultas as $rotulo => $consulta) {
            try {
                $retorno = $consulta();
            } catch (RuntimeException $e) {
                $this->error($rotulo.': '.$e->getMessage());
                $falhou = true;

                continue;
            }

            $total = $retorno['total_de_registros'] ?? null;

            $this->info($rotulo.': OK'.($total !== null ? " ({$total} registro(s))" : ''));
        }

        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}
