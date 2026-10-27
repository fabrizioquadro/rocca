<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\FeegowService;
use App\Services\OmiePacienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class PacienteController extends Controller
{
    /**
     * Quantos pacientes a integração em lote processa por vez.
     *
     * Cada paciente custa uma consulta à Feegow e outra à Omie: processar a
     * lista inteira numa requisição estouraria o tempo do navegador. O botão
     * repete o lote até zerar os pendentes.
     */
    public const LOTE_OMIE = 10;

    public function __construct(
        private FeegowService $feegow,
        private OmiePacienteService $omiePaciente,
    ) {
    }

    /**
     * Lista os pacientes trazidos da Feegow.
     */
    public function index()
    {
        $pacientes = Paciente::query()
            ->orderBy('nome')
            ->get();

        $pendentesOmie = $pacientes->reject(fn (Paciente $paciente) => $paciente->integrado_omie)->count();

        return view('pacientes.index', compact('pacientes', 'pendentesOmie'));
    }

    /**
     * Importa/atualiza os pacientes da Feegow.
     */
    public function sincronizar()
    {
        try {
            $lista = $this->feegow->listarPacientes();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($lista)) {
            return back()->with('error', 'A Feegow não retornou pacientes.');
        }

        $novos = 0;
        $atualizados = 0;

        foreach ($lista as $dados) {
            $pacienteId = $dados['patient_id'] ?? null;

            if (! $pacienteId) {
                continue;
            }

            $paciente = Paciente::firstOrNew(['paciente_id' => $pacienteId]);
            $jaExiste = $paciente->exists;

            $paciente->fill($this->mapear($dados, $paciente));
            $paciente->save();

            $jaExiste ? $atualizados++ : $novos++;
        }

        return redirect()
            ->route('pacientes.index')
            ->with('success', $novos.' paciente(s) importado(s) e '.$atualizados.' atualizado(s) da Feegow.');
    }

    /**
     * Detalhes de um paciente.
     */
    public function show(Paciente $paciente)
    {
        return view('pacientes.show', compact('paciente'));
    }

    /**
     * Integra um paciente com a Omie (cria ou vincula o cliente pelo CPF).
     */
    public function integrarOmie(Paciente $paciente)
    {
        if (! $this->omiePaciente->configurado()) {
            return back()->with('error', 'As integrações com a Omie e com a Feegow precisam estar configuradas.');
        }

        if ($paciente->integrado_omie) {
            return back()->with('success', $paciente->nome.' já está integrado com a Omie.');
        }

        try {
            $resultado = $this->omiePaciente->registrar($paciente);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Não foi possível integrar '.$paciente->nome.': '.$e->getMessage());
        }

        return back()->with('success', $resultado['criado']
            ? $paciente->nome.' integrado com a Omie (código '.$resultado['codigo'].').'
            : $paciente->nome.' vinculado ao cadastro que já existia na Omie (código '.$resultado['codigo'].').');
    }

    /**
     * Integra com a Omie os pacientes que ainda não têm código de cliente.
     *
     * Processa um lote por vez (cada paciente custa uma consulta à Feegow e
     * até duas à Omie). A tela chama esta rota em sequência, mostrando o
     * progresso: assim nenhuma requisição passa do tempo limite do servidor.
     *
     * Os pacientes que falham (dados fiscais incompletos na Feegow, por
     * exemplo) voltam na lista `falhas` e o navegador os envia em `ignorar`
     * na chamada seguinte, para o lote não travar nos mesmos casos.
     */
    public function integrarOmieLote(Request $request)
    {
        if (! $this->omiePaciente->configurado()) {
            $erro = 'As integrações com a Omie e com a Feegow precisam estar configuradas.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'mensagem' => $erro], 422)
                : back()->with('error', $erro);
        }

        // O lote faz duas chamadas externas por paciente.
        @set_time_limit(300);

        $ignorar = collect($request->input('ignorar', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $pendentes = $this->pendentesOmie($ignorar);
        $total = (clone $pendentes)->count();

        if ($total === 0) {
            $mensagem = 'Todos os pacientes já estão integrados com a Omie.';

            return $request->expectsJson()
                ? response()->json(['ok' => true, 'integrados' => 0, 'falhas' => [], 'restantes' => 0, 'total' => 0, 'mensagem' => $mensagem])
                : back()->with('success', $mensagem);
        }

        $integrados = 0;
        $falhas = [];

        foreach ((clone $pendentes)->limit(self::LOTE_OMIE)->get() as $paciente) {
            try {
                $this->omiePaciente->registrar($paciente);
                $integrados++;
            } catch (RuntimeException $e) {
                $falhas[] = [
                    'id' => $paciente->id,
                    'nome' => $paciente->nome,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        $restantes = $this->pendentesOmie($ignorar)->count();

        $mensagem = $integrados.' paciente(s) integrado(s) com a Omie.';

        if ($restantes > 0) {
            $mensagem .= ' Restam '.$restantes.' pendente(s).';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'integrados' => $integrados,
                'falhas' => $falhas,
                'restantes' => $restantes,
                'total' => $total,
                'mensagem' => $mensagem,
            ]);
        }

        if ($falhas !== []) {
            return back()
                ->with('success', $mensagem)
                ->with('error', count($falhas).' paciente(s) sem os dados fiscais necessários: '
                    .implode(' | ', array_slice(array_map(fn ($falha) => $falha['nome'].': '.$falha['motivo'], $falhas), 0, 5)));
        }

        return back()->with('success', $mensagem);
    }

    /**
     * Pacientes sem código de cliente na Omie.
     *
     * @param  array<int, int>  $ignorar  Pacientes que já falharam nesta rodada.
     */
    private function pendentesOmie(array $ignorar = [])
    {
        return Paciente::query()
            ->whereNull('codigo_cliente_omie')
            ->when($ignorar !== [], fn ($query) => $query->whereNotIn('id', $ignorar))
            ->orderBy('nome');
    }

    /**
     * Busca os dados completos do paciente na Feegow (CPF, telefone e etc.).
     */
    public function atualizarDados(Paciente $paciente)
    {
        try {
            $dados = $this->feegow->buscarPaciente((int) $paciente->paciente_id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($dados)) {
            return back()->with('error', 'A Feegow não retornou os dados deste paciente.');
        }

        $paciente->fill($this->mapear($dados, $paciente));

        $paciente->save();

        return back()->with('success', 'Dados do paciente atualizados pela Feegow.');
    }

    /**
     * Grava a observação interna do paciente (a Feegow não manda este dado).
     */
    public function atualizarObservacao(Request $request, Paciente $paciente)
    {
        $dados = $request->validate([
            'observacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'observacao.max' => 'A observação deve ter no máximo 2000 caracteres.',
        ]);

        $paciente->update(['observacao' => $dados['observacao'] ?? null]);

        return back()->with('success', 'Observação do paciente atualizada.');
    }

    /**
     * Converte o JSON da Feegow nos campos do paciente.
     * Mantém o que já existe quando a Feegow não traz o dado.
     *
     * A observação é campo interno: fica FORA deste mapeamento de propósito,
     * para a sincronização nunca apagar o que foi digitado na tela.
     */
    private function mapear(array $dados, ?Paciente $atual = null): array
    {
        return [
            'nome' => $this->primeiroValor($dados['nome'] ?? null) ?? $atual?->nome ?? 'Sem nome',
            'nascimento' => $this->converterData($this->primeiroValor($dados['nascimento'] ?? null))
                ?? $atual?->nascimento?->format('Y-m-d'),
            'cpf' => $this->somenteDigitos($this->primeiroValor($dados['cpf'] ?? ($dados['documento'] ?? null)))
                ?? $atual?->cpf,
            'telefone' => $this->somenteDigitos($this->primeiroValor(
                $dados['telefone'] ?? ($dados['telefones'] ?? ($dados['fone'] ?? null))
            )) ?? $atual?->telefone,
            'celular' => $this->somenteDigitos($this->primeiroValor($dados['celular'] ?? ($dados['celulares'] ?? null)))
                ?? $atual?->celular,
            'email' => $this->primeiroValor($dados['email'] ?? null) ?? $atual?->email,
            'dados' => array_merge($atual?->dados ?? [], $dados),
        ];
    }

    /**
     * Pega o primeiro valor preenchido (a Feegow devolve alguns campos como array/objeto).
     */
    private function primeiroValor($valor): ?string
    {
        if (is_array($valor)) {
            foreach ($valor as $item) {
                if (is_scalar($item) && filled($item)) {
                    return (string) $item;
                }
            }

            return null;
        }

        return is_scalar($valor) && filled($valor) ? (string) $valor : null;
    }

    /**
     * Converte a data recebida da Feegow (d/m/Y, d-m-Y ou Y-m-d) para o banco.
     */
    private function converterData($valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $valor = trim((string) $valor);

        try {
            if (preg_match('/^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', str_replace('-', '/', $valor))->format('Y-m-d');
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mantém apenas os dígitos de um valor (CPF/telefone).
     */
    private function somenteDigitos($valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', (string) $valor);

        return $digitos === '' ? null : $digitos;
    }
}
