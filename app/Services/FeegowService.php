<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integração com a API da Feegow.
 */
class FeegowService
{
    private string $baseUrl;

    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.feegow.base_url'), '/');
        $this->token = config('services.feegow.token');
    }

    /**
     * A integração está configurada?
     */
    public function configurado(): bool
    {
        return $this->baseUrl !== '' && ! empty($this->token);
    }

    /**
     * Executa um GET na Feegow e devolve o corpo decodificado.
     *
     * @throws RuntimeException
     */
    public function get(string $endpoint, array $parametros = []): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Integração com a Feegow não está configurada (FEEGOW_BASE_URL / FEEGOW_TOKEN).');
        }

        $resposta = Http::withHeaders([
            'X-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(120)
            ->connectTimeout(30)
            ->get($this->baseUrl.'/'.ltrim($endpoint, '/'), $parametros);

        if (! $resposta->successful()) {
            throw new RuntimeException('A Feegow retornou erro: HTTP '.$resposta->status().'.');
        }

        return $resposta->json() ?? [];
    }

    /**
     * Executa um POST na Feegow e devolve o corpo decodificado.
     *
     * A API dos agendamentos recebe os parâmetros na query string (e aceita
     * também no corpo): mandamos nos dois para não depender da versão da API.
     *
     * @throws RuntimeException
     */
    public function post(string $endpoint, array $parametros = []): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Integração com a Feegow não está configurada (FEEGOW_BASE_URL / FEEGOW_TOKEN).');
        }

        $url = $this->baseUrl.'/'.ltrim($endpoint, '/').'?'.http_build_query($parametros);

        $resposta = Http::withHeaders([
            'X-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('services.feegow.timeout', 30))
            ->connectTimeout(15)
            ->post($url, $parametros);

        if (! $resposta->successful()) {
            throw new RuntimeException(
                'HTTP '.$resposta->status().' — '.mb_substr(trim($resposta->body()), 0, 300)
            );
        }

        $corpo = $resposta->json();

        return is_array($corpo) ? $corpo : [];
    }

    /**
     * Garante que a Feegow respondeu com sucesso e devolve o "content".
     *
     * @throws RuntimeException
     */
    public function exigirSucesso(array $retorno, string $contexto): array
    {
        if (! ($retorno['success'] ?? false)) {
            $mensagem = $retorno['message'] ?? ($retorno['error'] ?? 'resposta sem sucesso');

            throw new RuntimeException($contexto.': '.(is_array($mensagem) ? json_encode($mensagem) : $mensagem));
        }

        $conteudo = $retorno['content'] ?? [];

        return is_array($conteudo) ? $conteudo : [];
    }

    /**
     * Lista os locais (agendas) disponíveis na licença.
     */
    public function listarLocais(): array
    {
        $retorno = $this->get('company/list-local');

        return $retorno['content'] ?? [];
    }

    /**
     * Lista os procedimentos disponíveis na licença.
     */
    public function listarProcedimentos(): array
    {
        $retorno = $this->get('procedures/list');

        return $retorno['content'] ?? [];
    }

    /**
     * Lista todos os pacientes da Feegow (percorrendo a paginação).
     */
    public function listarPacientes(int $limitePorPagina = 500, int $maximoPaginas = 200): array
    {
        $pacientes = [];
        $offset = 0;

        for ($pagina = 1; $pagina <= $maximoPaginas; $pagina++) {
            $conteudo = $this->get('patient/list', [
                'limit' => $limitePorPagina,
                'offset' => $offset,
            ]);

            $itens = $conteudo['content'] ?? [];

            if (! is_array($itens) || $itens === []) {
                break;
            }

            foreach ($itens as $item) {
                $pacientes[] = $item;
            }

            // Última página
            if (count($itens) < $limitePorPagina) {
                break;
            }

            $offset += $limitePorPagina;
        }

        return $pacientes;
    }

    /**
     * Busca os dados completos de um paciente na Feegow.
     */
    public function buscarPaciente(int $pacienteId): ?array
    {
        $retorno = $this->get('patient/search', ['paciente_id' => $pacienteId]);

        return $retorno['content'] ?? null;
    }

    /**
     * Lista os médicos (profissionais) da Feegow.
     */
    public function listarMedicos(): array
    {
        $conteudo = $this->get('professional/list');

        $itens = $conteudo['content'] ?? [];

        if (! is_array($itens)) {
            return [];
        }

        return collect($itens)
            ->map(fn (array $medico) => [
                'id' => $medico['profissional_id'] ?? ($medico['professional_id'] ?? ($medico['id'] ?? null)),
                'nome' => $medico['nome'] ?? null,
                'conselho' => $medico['conselho'] ?? null,
            ])
            ->filter(fn (array $medico) => $medico['id'] && $medico['nome'])
            ->values()
            ->all();
    }
}
