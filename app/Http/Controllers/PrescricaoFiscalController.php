<?php

namespace App\Http\Controllers;

use App\Models\Prescricao;
use App\Services\OmiePacienteService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Aba Fiscal da prescrição: integração do paciente (tomador) com a Omie.
 *
 * A integração acontece em duas chamadas para a tela conseguir mostrar cada
 * etapa: primeiro a busca na Feegow, depois o cadastro na Omie.
 */
class PrescricaoFiscalController extends Controller
{
    public function __construct(private OmiePacienteService $omiePaciente)
    {
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
    public function registrarPacienteOmie(Prescricao $prescricao): JsonResponse
    {
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
