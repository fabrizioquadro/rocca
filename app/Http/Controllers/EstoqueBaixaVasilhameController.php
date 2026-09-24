<?php

namespace App\Http\Controllers;

use App\Enums\TipoMedicamento;
use App\Models\BaixaVasilhame;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Services\EstoqueVasilhameService;
use App\Support\Numero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Baixa de medicamentos ABERTOS (vasilhames em uso): retira o saldo em mg que
 * ainda estava no frasco. A baixa é parcial — informa-se quantos mg saem e o
 * que sobrar continua no vasilhame. Não existe exclusão: a baixa é definitiva.
 */
class EstoqueBaixaVasilhameController extends Controller
{
    public function __construct(private EstoqueVasilhameService $vasilhames)
    {
    }

    /**
     * Lista as baixas de medicamentos abertos.
     */
    public function index()
    {
        $baixas = BaixaVasilhame::with(['clinica', 'user', 'itens.medicamento', 'itens.vasilhameAberto.entradaItem'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->get();

        return view('estoque.baixas-abertos.index', compact('baixas'));
    }

    /**
     * Formulário da baixa: pode lançar vários medicamentos abertos de uma vez.
     */
    public function create()
    {
        $clinicas = Clinica::orderBy('nome')->get();

        $medicamentos = Medicamento::query()
            ->where('tipo', TipoMedicamento::Miligrama->value)
            ->orderBy('nome')
            ->get();

        // IDs equivalentes (mesmo grupo) por medicamento: usado para aceitar o
        // vasilhame de um frasco de tamanho diferente do mesmo produto
        $grupos = $medicamentos
            ->mapWithKeys(fn (Medicamento $medicamento) => [
                $medicamento->id => $medicamento->idsDoMesmoProduto()->implode(','),
            ])
            ->all();

        $itensIniciais = old('itens', []);

        return view('estoque.baixas-abertos.create', compact('clinicas', 'medicamentos', 'grupos', 'itensIniciais'));
    }

    /**
     * Lança a baixa: cada linha retira mg do vasilhame aberto informado.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'clinica_id' => ['required', 'integer', 'exists:clinicas,id'],
            'data' => ['nullable', 'date'],
            'observacao' => ['nullable', 'string', 'max:1000'],

            'itens' => ['required', 'array', 'min:1'],
            'itens.*.medicamento_id' => ['required', 'integer', 'exists:medicamentos,id'],
            'itens.*.codigo_barras' => ['required', 'string', 'max:100'],
            'itens.*.mg_baixa' => ['required'],
            'itens.*.motivo' => ['nullable', 'string', 'max:255'],
        ], [
            'clinica_id.required' => 'Escolha a clínica onde está o estoque.',
            'itens.required' => 'Informe pelo menos um medicamento aberto.',
            'itens.min' => 'Informe pelo menos um medicamento aberto.',
            'itens.*.medicamento_id.required' => 'Escolha o medicamento em todas as linhas.',
            'itens.*.codigo_barras.required' => 'Informe o código de barras em todas as linhas.',
            'itens.*.mg_baixa.required' => 'Informe a quantidade (mg) a dar baixa em todas as linhas.',
        ]);

        $baixa = DB::transaction(function () use ($dados) {
            $baixa = BaixaVasilhame::create([
                'clinica_id' => $dados['clinica_id'],
                'user_id' => auth()->id(),
                'data' => $dados['data'] ?? now()->toDateString(),
                'observacao' => $dados['observacao'] ?? null,
            ]);

            foreach ($dados['itens'] as $linha) {
                $this->baixarLinha($baixa, $linha, (int) $dados['clinica_id']);
            }

            if ($baixa->itens()->count() === 0) {
                throw ValidationException::withMessages([
                    'itens' => 'Nenhum vasilhame aberto foi encontrado com os dados informados.',
                ]);
            }

            return $baixa;
        });

        return redirect()
            ->route('estoque.baixas-abertos.show', $baixa)
            ->with('success', 'Baixa de medicamentos abertos lançada com sucesso.');
    }

    /**
     * Detalhes de uma baixa.
     */
    public function show(BaixaVasilhame $baixaVasilhame)
    {
        $baixaVasilhame->load([
            'clinica',
            'user',
            'itens.medicamento',
            'itens.vasilhameAberto.entradaItem',
            'itens.vasilhameAberto.abertoPor',
        ]);

        return view('estoque.baixas-abertos.show', ['baixa' => $baixaVasilhame]);
    }

    /**
     * Dá baixa em uma linha: encontra o vasilhame ABERTO do código informado e
     * retira os mg solicitados (parcial ou o frasco todo).
     *
     * @param  array<string, mixed>  $linha
     */
    private function baixarLinha(BaixaVasilhame $baixa, array $linha, int $clinicaId): void
    {
        $medicamento = Medicamento::find($linha['medicamento_id']);

        if (! $medicamento) {
            throw ValidationException::withMessages([
                'itens' => 'Medicamento não encontrado.',
            ]);
        }

        if (! $medicamento->eh_miligrama) {
            throw ValidationException::withMessages([
                'itens' => 'Só medicamento do tipo miligrama (vasilhame) tem baixa de aberto.',
            ]);
        }

        $codigo = trim((string) $linha['codigo_barras']);
        $mg = round(Numero::paraFloat($linha['mg_baixa'] ?? null), 3);

        if ($mg <= 0) {
            throw ValidationException::withMessages([
                'itens' => 'Informe uma quantidade maior que zero para '.$medicamento->nome.'.',
            ]);
        }

        // Vale o vasilhame do medicamento escolhido ou de um equivalente do grupo
        $vasilhame = $this->vasilhames->abertoPorCodigo(
            $codigo,
            $clinicaId,
            $medicamento->idsDoMesmoProduto()->all()
        );

        if (! $vasilhame) {
            throw ValidationException::withMessages([
                'itens' => 'Não há vasilhame ABERTO do código '.$codigo.' para '.$medicamento->nome
                    .' nesta clínica. Confira o código e o medicamento.',
            ]);
        }

        if ($mg > (float) $vasilhame->mg_restantes + 0.001) {
            throw ValidationException::withMessages([
                'itens' => 'O vasilhame '.$codigo.' tem apenas '
                    .Numero::formatar($vasilhame->mg_restantes).' mg disponíveis — não dá para baixar '
                    .Numero::formatar($mg).' mg.',
            ]);
        }

        $baixa->itens()->create([
            'vasilhame_aberto_id' => $vasilhame->id,
            'medicamento_id' => $vasilhame->medicamento_id,
            'mg_baixa' => $mg,
            'motivo' => filled($linha['motivo'] ?? null) ? $linha['motivo'] : null,
        ]);

        // Consome o saldo: zerando, o vasilhame é encerrado e não pode mais ser usado
        $this->vasilhames->consumir($vasilhame, $mg);
    }
}
