<?php

namespace App\Services;

use App\Models\Paciente;
use RuntimeException;

/**
 * Integração do paciente (tomador da NFS-e) com a Omie.
 *
 * O Rocca não guarda o endereço fiscal: os dados são buscados na Feegow no
 * momento da integração. O cliente é criado/atualizado pelo CPF
 * (UpsertClienteCpfCnpj), então repetir a integração não duplica cadastros.
 */
class OmiePacienteService
{
    /**
     * Campos que a nota fiscal precisa (com o nome usado na mensagem de erro).
     *
     * @var array<string, string>
     */
    private const CAMPOS_OBRIGATORIOS = [
        'cnpj_cpf' => 'CPF',
        'razao_social' => 'nome',
        'endereco' => 'endereço',
        'endereco_numero' => 'número do endereço',
        'bairro' => 'bairro',
        'cidade' => 'cidade',
        'estado' => 'UF',
        'cep' => 'CEP',
    ];

    public function __construct(
        private FeegowService $feegow,
        private OmieService $omie,
    ) {
    }

    /**
     * As duas integrações necessárias estão configuradas?
     */
    public function configurado(): bool
    {
        return $this->omie->configurado() && $this->feegow->configurado();
    }

    /**
     * Dados fiscais do paciente: o que será enviado à Omie e o que está faltando.
     *
     * @return array{payload: array<string, string>, faltando: array<int, string>}
     */
    public function dadosFiscais(Paciente $paciente): array
    {
        $payload = $this->montarPayload($paciente, $this->buscarNaFeegow($paciente));

        $faltando = [];

        foreach (self::CAMPOS_OBRIGATORIOS as $campo => $rotulo) {
            if (empty($payload[$campo])) {
                $faltando[] = $rotulo;
            }
        }

        return ['payload' => $payload, 'faltando' => $faltando];
    }

    /**
     * Garante o paciente como cliente na Omie e grava o código no cadastro.
     *
     * Se o CPF já existe na Omie, apenas vincula o cadastro existente (não
     * sobrescreve os dados que foram preenchidos lá).
     *
     * @return array{codigo: int, criado: bool}
     */
    public function registrar(Paciente $paciente): array
    {
        if ($paciente->codigo_cliente_omie) {
            return ['codigo' => (int) $paciente->codigo_cliente_omie, 'criado' => false];
        }

        $dados = $this->dadosFiscais($paciente);

        if ($dados['faltando'] !== []) {
            throw new RuntimeException(
                'Faltam dados na Feegow para emitir a nota: '.implode(', ', $dados['faltando']).'.'
            );
        }

        $payload = $dados['payload'];

        $cliente = $this->omie->buscarClientePorCpf($payload['cnpj_cpf']);
        $criado = $cliente === null;

        if ($criado) {
            $cliente = $this->omie->incluirClientePorCpf($payload);
        }

        $codigo = (int) ($cliente['codigo_cliente_omie'] ?? 0);

        if ($codigo <= 0) {
            throw new RuntimeException('A Omie não retornou o código do cliente.');
        }

        $paciente->forceFill([
            'codigo_cliente_omie' => $codigo,
            'omie_integrado_em' => now(),
            // Completa o cadastro local com o que só existia na Feegow
            'cpf' => $paciente->cpf ?: $payload['cnpj_cpf'],
            'email' => $paciente->email ?: ($payload['email'] ?? null),
        ])->save();

        return ['codigo' => $codigo, 'criado' => $criado];
    }

    /**
     * Busca o cadastro completo do paciente na Feegow.
     *
     * @return array<string, mixed>
     */
    private function buscarNaFeegow(Paciente $paciente): array
    {
        if (! $this->feegow->configurado()) {
            throw new RuntimeException('Integração com a Feegow não está configurada (FEEGOW_BASE_URL / FEEGOW_TOKEN).');
        }

        if (! $paciente->paciente_id) {
            throw new RuntimeException('O paciente não tem cadastro na Feegow (código do paciente vazio).');
        }

        $dados = $this->feegow->buscarPaciente((int) $paciente->paciente_id);

        if (empty($dados)) {
            throw new RuntimeException('A Feegow não retornou os dados deste paciente.');
        }

        return $dados;
    }

    /**
     * Converte o paciente da Feegow no cadastro de cliente da Omie.
     *
     * @param  array<string, mixed>  $feegow
     * @return array<string, string>
     */
    private function montarPayload(Paciente $paciente, array $feegow): array
    {
        $documentos = is_array($feegow['documentos'] ?? null) ? $feegow['documentos'] : [];

        $cpf = $this->digitos($documentos['cpf'] ?? null)
            ?? $this->digitos($feegow['documento'] ?? null)
            ?? $this->digitos($paciente->cpf);

        $nome = $this->texto($feegow['nome'] ?? null) ?: (string) $paciente->nome;

        $telefone = $this->digitos($feegow['celular'] ?? null)
            ?? $this->digitos($feegow['celulares'] ?? null)
            ?? $this->digitos($feegow['telefones'] ?? null)
            ?? $this->digitos($paciente->celular)
            ?? $this->digitos($paciente->telefone);

        $payload = [
            'razao_social' => $nome,
            'nome_fantasia' => $nome,
            'cnpj_cpf' => (string) $cpf,
            // A Feegow devolve o e-mail como lista ("email": ["...", ""]).
            'email' => $this->texto($feegow['email'] ?? null) ?: (string) $paciente->email,
            'pessoa_fisica' => 'S',
            'contribuinte' => 'N',
            'codigo_pais' => '1058',
            'endereco' => $this->texto($feegow['endereco'] ?? null),
            'endereco_numero' => $this->texto($feegow['numero'] ?? null),
            'complemento' => $this->texto($feegow['complemento'] ?? null),
            'bairro' => $this->texto($feegow['bairro'] ?? null),
            'cep' => (string) $this->digitos($feegow['cep'] ?? null),
            'cidade' => $this->texto($feegow['cidade'] ?? null),
            'estado' => mb_strtoupper($this->texto($feegow['estado'] ?? null)),
        ];

        if ($telefone !== null && strlen($telefone) >= 10) {
            $payload['telefone1_ddd'] = substr($telefone, 0, 2);
            $payload['telefone1_numero'] = substr($telefone, 2);
        }

        // A Omie recusa campos vazios: só envia o que foi preenchido.
        return array_filter($payload, fn ($valor) => $valor !== null && $valor !== '');
    }

    /**
     * Primeiro valor de texto preenchido (a Feegow devolve alguns campos como
     * lista, por exemplo "email": ["contato@exemplo.com", ""]).
     */
    private function texto(mixed $valor): string
    {
        if (is_array($valor)) {
            foreach ($valor as $item) {
                $texto = $this->texto($item);

                if ($texto !== '') {
                    return $texto;
                }
            }

            return '';
        }

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * Deixa só os dígitos (CPF, CEP e telefone podem vir formatados da Feegow).
     */
    private function digitos(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D+/', '', $this->texto($valor));

        return $digitos !== '' ? $digitos : null;
    }
}
