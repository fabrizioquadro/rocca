<?php

namespace App\Http\Controllers;

use App\Models\Prescricao;
use App\Services\OmieNotaFiscalService;
use App\Services\OmiePacienteService;
use App\Services\OmieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Aba Fiscal da prescrição: integração do paciente (tomador) com a Omie e
 * emissão das notas fiscais.
 *
 * A integração acontece em duas chamadas para a tela conseguir mostrar cada
 * etapa: primeiro a busca na Feegow, depois o cadastro na Omie.
 */
class PrescricaoFiscalController extends Controller
{
    public function __construct(
        private OmiePacienteService $omiePaciente,
        private OmieService $omie,
        private OmieNotaFiscalService $notas,
    ) {
    }

    /**
     * Emite a NFS-e da prescrição (IncluirOS + FaturarOS na Omie).
     */
    public function emitirNota(Request $request, Prescricao $prescricao): JsonResponse
    {
        $dados = $request->validate([
            'n_cod_servico' => ['required', 'integer', 'min:1'],
            'quantidade' => ['required', 'numeric', 'min:0.01'],
            'valor_unitario' => ['required', 'string'],
            'descricao' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [
            'n_cod_servico.required' => 'Selecione o serviço da nota.',
            'quantidade.min' => 'Informe uma quantidade maior que zero.',
            'valor_unitario.required' => 'Informe o valor unitário.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        try {
            $nota = $this->notas->emitir($prescricao, $dados);
        } catch (RuntimeException $e) {
            return $this->falha($e->getMessage());
        }

        return response()->json([
            'ok' => true,
            'mensagem' => 'Nota '.$nota->numero.' faturada na Omie.',
            'nota' => [
                'numero' => $nota->numero,
                'n_cod_os' => $nota->n_cod_os,
                'valor_total' => $nota->valor_total_formatado,
            ],
        ]);
    }

    /**
     * Serviços cadastrados na Omie (opções do select da emissão).
     */
    public function servicos(): JsonResponse
    {
        if (! $this->omie->configurado()) {
            return $this->falha('Integração com a Omie não está configurada.');
        }

        try {
            $retorno = $this->omie->listarServicos(1, 200);
        } catch (RuntimeException $e) {
            return $this->falha($e->getMessage());
        }

        $servicos = collect($retorno['cadastros'] ?? [])
            ->map(fn (array $servico) => [
                'codigo' => (int) ($servico['intListar']['nCodServ'] ?? 0),
                'codigo_exibicao' => $servico['cabecalho']['cCodigo'] ?? null,
                'descricao' => $servico['cabecalho']['cDescricao'] ?? ($servico['descricao']['cDescrCompleta'] ?? null),
                'preco' => (float) ($servico['cabecalho']['nPrecoUnit'] ?? 0),
                'inativo' => mb_strtoupper((string) ($servico['info']['inativo'] ?? 'N')) === 'S',
            ])
            ->filter(fn (array $servico) => $servico['codigo'] > 0 && ! $servico['inativo'])
            ->values();

        return response()->json(['ok' => true, 'servicos' => $servicos]);
    }

    /**
     * Passo 1: busca os dados fiscais do paciente na Feegow.
     */
    public function buscarPacienteFeegow(Prescricao $prescricao): JsonResponse
    {
        $paciente = $prescricao->paciente;

        if (! $paciente) {
            return $this->falha('A prescrição não tem paciente vinculado.');
        }

        if (! $this->omiePaciente->configurado()) {
            return $this->falha('As integrações com a Omie e com a Feegow precisam estar configuradas.');
        }

        try {
            $dados = $this->omiePaciente->dadosFiscais($paciente);
        } catch (RuntimeException $e) {
            return $this->falha($e->getMessage());
        }

        if ($dados['faltando'] !== []) {
            return $this->falha('Faltam dados na Feegow: '.implode(', ', $dados['faltando']).'.');
        }

        return response()->json([
            'ok' => true,
            'mensagem' => 'Dados do paciente carregados da Feegow.',
        ]);
    }

    /**
     * Passo 2: cria/atualiza o cliente na Omie e vincula ao paciente.
     */
    public function registrarPacienteOmie(Prescricao $prescricao): JsonResponse    {
        $paciente = $prescricao->paciente;

        if (! $paciente) {
            return $this->falha('A prescrição não tem paciente vinculado.');
        }

        if (! $this->omiePaciente->configurado()) {
            return $this->falha('As integrações com a Omie e com a Feegow precisam estar configuradas.');
        }

        try {
            $resultado = $this->omiePaciente->registrar($paciente);
        } catch (RuntimeException $e) {
            return $this->falha($e->getMessage());
        }

        return response()->json([
            'ok' => true,
            'mensagem' => $resultado['criado']
                ? 'Paciente cadastrado na Omie.'
                : 'Paciente vinculado ao cadastro que já existia na Omie.',
            'codigo_cliente_omie' => $resultado['codigo'],
        ]);
    }

    private function falha(string $mensagem): JsonResponse
    {
        return response()->json(['ok' => false, 'mensagem' => $mensagem], 422);
    }
}
