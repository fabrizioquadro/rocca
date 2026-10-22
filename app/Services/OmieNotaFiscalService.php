<?php

namespace App\Services;

use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoNotaFiscal;
use RuntimeException;

/**
 * Emissão da NFS-e de uma prescrição na Omie.
 *
 * A nota nasce do faturamento de uma Ordem de Serviço:
 *   1. IncluirOS   (etapa 50 = Faturar)
 *   2. FaturarOS   (a Omie fatura e emite a NFS-e conforme a configuração fiscal)
 *
 * Cada nota tem o código de integração "{prescricao_id}/{numero}", que também
 * identifica a OS na Omie.
 */
class OmieNotaFiscalService
{
    public function __construct(private OmieService $omie)
    {
    }

    /**
     * Emite a nota da prescrição e devolve o registro salvo.
     *
     * @param  array{n_cod_servico: int|string, quantidade: mixed, valor_unitario: mixed, descricao?: ?string, email?: ?string}  $dados
     *
     * @throws RuntimeException
     */
    public function emitir(Prescricao $prescricao, array $dados): PrescricaoNotaFiscal
    {
        if (! $this->omie->configurado()) {
            throw new RuntimeException('Integração com a Omie não está configurada (OMIE_APP_KEY / OMIE_APP_SECRET).');
        }

        $paciente = $prescricao->paciente;

        if (! $paciente || ! $paciente->codigo_cliente_omie) {
            throw new RuntimeException('Integre o paciente com a Omie antes de emitir a nota.');
        }

        $this->validarConfiguracao();

        $servico = (int) ($dados['n_cod_servico'] ?? 0);
        $quantidade = $this->numero($dados['quantidade'] ?? 0);
        $valorUnitario = $this->numero($dados['valor_unitario'] ?? 0);

        if ($servico <= 0) {
            throw new RuntimeException('Selecione o serviço da nota.');
        }

        if ($quantidade <= 0) {
            throw new RuntimeException('Informe uma quantidade maior que zero.');
        }

        if ($valorUnitario <= 0) {
            throw new RuntimeException('Informe um valor unitário maior que zero.');
        }

        $numero = (int) $prescricao->notasFiscais()->max('numero') + 1;
        $codigoIntegracao = $prescricao->id.'/'.$numero;

        $nota = PrescricaoNotaFiscal::create([
            'prescricao_id' => $prescricao->id,
            'numero' => $numero,
            'codigo_integracao' => $codigoIntegracao,
            'n_cod_servico' => $servico,
            'descricao_servico' => $dados['descricao'] ?? null,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => round($quantidade * $valorUnitario, 2),
            'cidade_prestacao' => (string) config('services.omie.cidade_prestacao'),
            'email_envio' => $dados['email'] ?? $paciente->email,
            'status' => 'processando',
        ]);

        try {
            $os = $this->omie->chamar('servicos/os', 'IncluirOS', $this->payload($prescricao, $paciente, $nota));

            $nCodOS = (int) ($os['nCodOS'] ?? 0);

            if ($nCodOS <= 0) {
                throw new RuntimeException('A Omie não retornou o código da Ordem de Serviço.');
            }

            $nota->update([
                'n_cod_os' => $nCodOS,
                'status' => 'faturando',
            ]);

            $this->omie->chamar('servicos/osp', 'FaturarOS', ['cCodIntOS' => $codigoIntegracao]);

            $nota->update([
                'status' => 'faturada',
                'emitida_em' => now(),
            ]);
        } catch (RuntimeException $e) {
            $nota->update([
                'status' => 'erro',
                'mensagem_erro' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }

        return $nota->fresh();
    }

    /**
     * Corpo da OS enviado à Omie.
     *
     * @return array<string, mixed>
     */
    private function payload(Prescricao $prescricao, Paciente $paciente, PrescricaoNotaFiscal $nota): array
    {
        $item = [
            'nCodServico' => (int) $nota->n_cod_servico,
            'nQtde' => (float) $nota->quantidade,
            'nValUnit' => round((float) $nota->valor_unitario, 2),
        ];

        if (filled($nota->descricao_servico)) {
            $item['cDadosAdicItem'] = $nota->descricao_servico;
        }

        return [
            'Cabecalho' => [
                'cCodIntOS' => $nota->codigo_integracao,
                'nCodCli' => (int) $paciente->codigo_cliente_omie,
                'dDtPrevisao' => now()->format('d/m/Y'),
                'cEtapa' => '50',
                'cCodParc' => (string) config('services.omie.condicao_pagamento', '000'),
                'nQtdeParc' => 1,
            ],
            'ServicosPrestados' => [$item],
            'InformacoesAdicionais' => [
                'cCidPrestServ' => (string) config('services.omie.cidade_prestacao'),
                'cCodCateg' => (string) config('services.omie.categoria'),
                'nCodCC' => (int) config('services.omie.conta_corrente'),
                'cDadosAdicNF' => 'Prescrição '.$prescricao->id.' - Nota '.$nota->numero,
            ],
        ];
    }

    /**
     * A OS exige categoria e conta corrente da conta Omie.
     */
    private function validarConfiguracao(): void
    {
        if (! config('services.omie.categoria')) {
            throw new RuntimeException('Configure OMIE_CATEGORIA (categoria da receita) para emitir a nota.');
        }

        if (! config('services.omie.conta_corrente')) {
            throw new RuntimeException('Configure OMIE_CONTA_CORRENTE (conta do recebimento) para emitir a nota.');
        }
    }

    /**
     * Aceita "1.234,56" (máscara) ou 1234.56.
     */
    private function numero(mixed $valor): float
    {
        if (is_string($valor) && str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return round((float) $valor, 2);
    }
}
