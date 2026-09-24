<?php

namespace App\Services;

use App\Enums\TipoMovimentacaoEstoque;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Medicamento;
use App\Models\VasilhameAberto;
use App\Support\Numero;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Vasilhames de medicamentos do tipo miligrama.
 *
 * O vasilhame nasce lacrado, dentro do estoque fechado (unidades). Ao ser
 * aberto, ele sai do estoque fechado (movimentação de "abertura") e passa a
 * ter saldo em mg — as aplicações vão consumindo esse saldo até zerar, e aí
 * ele não pode mais ser usado.
 */
class EstoqueVasilhameService
{
    /**
     * Abre o vasilhame de um código de barras na clínica informada.
     */
    public function abrirPorCodigo(
        string $codigo,
        int $medicamentoId,
        int $clinicaId,
        ?string $observacao = null
    ): VasilhameAberto {
        $codigo = trim($codigo);
        $medicamento = Medicamento::find($medicamentoId);

        if (! $medicamento) {
            throw ValidationException::withMessages([
                'vasilhame' => 'Medicamento não encontrado.',
            ]);
        }

        if (! $medicamento->eh_miligrama) {
            throw ValidationException::withMessages([
                'vasilhame' => 'Só medicamento do tipo miligrama tem vasilhame para abrir.',
            ]);
        }

        $item = $this->loteParaAbrir($codigo, $medicamento, $clinicaId);

        // O vasilhame aberto é do medicamento do PRÓPRIO lote: pelo grupo, um
        // item de Mounjaro 90MG pode usar vasilhame de Mounjaro 60MG e o saldo
        // (e o tamanho em mg) é o do frasco que foi aberto.
        $medicamentoDoLote = $item->medicamento;

        if ((float) $medicamentoDoLote->tamanho_vasilhame <= 0) {
            throw ValidationException::withMessages([
                'vasilhame' => 'O medicamento '.$medicamentoDoLote->nome
                    .' está sem o tamanho do vasilhame cadastrado.',
            ]);
        }

        $vasilhame = VasilhameAberto::create([
            'entrada_item_id' => $item->id,
            'medicamento_id' => $medicamentoDoLote->id,
            'clinica_id' => $clinicaId,
            'mg_restantes' => $medicamentoDoLote->tamanho_vasilhame,
            'aberto_em' => now(),
            'aberto_por_user_id' => auth()->id(),
            'observacao' => $observacao,
        ]);

        // Sai do estoque fechado: daqui pra frente o controle é em mg
        EstoqueMovimentacao::create([
            'medicamento_id' => $medicamentoDoLote->id,
            'entrada_item_id' => $item->id,
            'clinica_id' => $clinicaId,
            'tipo' => TipoMovimentacaoEstoque::Abertura,
            'quantidade' => -1,
            'user_id' => auth()->id(),
            'observacao' => 'Vasilhame aberto com '.Numero::formatar($medicamentoDoLote->tamanho_vasilhame)
                .' mg — código '.$codigo,
        ]);

        return $vasilhame;
    }

    /**
     * Consome mg de um vasilhame aberto. Ao zerar, ele é encerrado e não pode
     * mais ser usado.
     */
    public function consumir(VasilhameAberto $vasilhame, float $mg): void
    {
        if ($mg <= 0) {
            return;
        }

        $restante = round((float) $vasilhame->mg_restantes - $mg, 3);

        $vasilhame->update([
            'mg_restantes' => max($restante, 0),
            'esgotado_em' => $restante <= 0 ? now() : null,
        ]);
    }

    /**
     * Vasilhame em uso deste lote na clínica.
     */
    public function emUso(EntradaItem $item, int $clinicaId): ?VasilhameAberto
    {
        return VasilhameAberto::emUso()
            ->where('entrada_item_id', $item->id)
            ->where('clinica_id', $clinicaId)
            ->orderBy('aberto_em')
            ->first();
    }

    /**
     * Vasilhame ABERTO de um código de barras na clínica (o mais antigo em uso).
     * Aceita mais de um medicamento: o grupo reúne o mesmo produto.
     *
     * @param  array<int, int>  $medicamentoIds
     */
    public function abertoPorCodigo(string $codigo, int $clinicaId, array $medicamentoIds = []): ?VasilhameAberto
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        return VasilhameAberto::with(['entradaItem', 'medicamento'])
            ->emUso()
            ->where('clinica_id', $clinicaId)
            ->when($medicamentoIds !== [], fn ($query) => $query->whereIn('medicamento_id', $medicamentoIds))
            ->whereHas('entradaItem', fn ($query) => $query->where('codigo_barras', $codigo))
            ->orderBy('aberto_em')
            ->first();
    }

    /**
     * Situação de um código de barras para a aplicação: diz se o vasilhame
     * está aberto (e com quanto), fechado, esgotado, vencido e etc.
     *
     * Aceita mais de um medicamento permitido: o grupo reúne o mesmo produto
     * com vasilhames de tamanhos diferentes.
     *
     * @param  array<int, int>  $medicamentoIds
     * @return array<string, mixed>
     */
    public function situacaoDoCodigo(string $codigo, int $clinicaId, array $medicamentoIds = []): array
    {
        $codigo = trim($codigo);

        $lotes = $this->lotesDoCodigo($codigo);

        if ($lotes->isEmpty()) {
            return $this->resposta('nao_encontrado', 'Código de barras '.$codigo.' não encontrado.', $clinicaId);
        }

        $doMedicamento = $lotes->filter(fn (EntradaItem $item) => $medicamentoIds === []
            || in_array((int) $item->medicamento_id, $medicamentoIds, true));

        if ($doMedicamento->isEmpty()) {
            return $this->resposta(
                'medicamento_diferente',
                'O código de barras '.$codigo.' é de '.($lotes->first()->medicamento?->nome ?? 'outro medicamento').'.',
                $clinicaId,
                $lotes->first()
            );
        }

        $emUso = $this->emUsoDoCodigo($doMedicamento, $clinicaId);

        if ($emUso) {
            return $this->resposta('aberto', null, $clinicaId, $emUso->entradaItem, $emUso);
        }

        $vencidos = $doMedicamento->filter(fn (EntradaItem $item) => $item->esta_vencido);

        if ($vencidos->count() === $doMedicamento->count()) {
            $lote = $vencidos->first();

            return $this->resposta(
                'vencido',
                'O lote '.$lote->lote.' do código '.$codigo.' está vencido (venc. '
                    .($lote->vencimento_formatado ?? '—').') e não pode ser aplicado.',
                $clinicaId,
                $lote
            );
        }

        $comSaldo = $doMedicamento
            ->reject(fn (EntradaItem $item) => $item->esta_vencido)
            ->filter(fn (EntradaItem $item) => $item->saldoNaClinica($clinicaId) > 0);

        if ($comSaldo->isNotEmpty()) {
            return $this->resposta(
                'fechado',
                'O vasilhame '.$codigo.' está fechado — abra o vasilhame antes de aplicar.',
                $clinicaId,
                $comSaldo->first()
            );
        }

        return $this->resposta(
            'esgotado',
            'O vasilhame '.$codigo.' já foi esgotado e não pode mais ser usado.',
            $clinicaId,
            $doMedicamento->first()
        );
    }

    /**
     * Lote que pode ser aberto: existe, é do mesmo produto do medicamento
     * informado (o próprio ou um do mesmo grupo), não está vencido, não tem
     * vasilhame aberto e ainda tem unidade fechada na clínica.
     */
    private function loteParaAbrir(string $codigo, Medicamento $medicamento, int $clinicaId): EntradaItem
    {
        $lotes = $this->lotesDoCodigo($codigo);

        if ($lotes->isEmpty()) {
            throw ValidationException::withMessages([
                'vasilhame' => 'Código de barras '.$codigo.' não encontrado.',
            ]);
        }

        $permitidos = $medicamento->idsDoMesmoProduto();

        $doMedicamento = $lotes->filter(fn (EntradaItem $item) => $permitidos->contains((int) $item->medicamento_id));

        if ($doMedicamento->isEmpty()) {
            throw ValidationException::withMessages([
                'vasilhame' => 'O código de barras '.$codigo.' é de '
                    .($lotes->first()->medicamento?->nome ?? 'outro medicamento')
                    .', que não é o mesmo produto de '.$medicamento->nome.'.',
            ]);
        }

        $emUso = $this->emUsoDoCodigo($doMedicamento, $clinicaId);

        if ($emUso) {
            throw ValidationException::withMessages([
                'vasilhame' => 'O vasilhame '.$codigo.' já está aberto (restam '
                    .Numero::formatar($emUso->mg_restantes).' mg).',
            ]);
        }

        $validos = $doMedicamento->reject(fn (EntradaItem $item) => $item->esta_vencido);

        if ($validos->isEmpty()) {
            $lote = $doMedicamento->first();

            throw ValidationException::withMessages([
                'vasilhame' => 'O lote '.$lote->lote.' do código '.$codigo.' está vencido (venc. '
                    .($lote->vencimento_formatado ?? '—').') e não pode ser aberto.',
            ]);
        }

        // Mais antigo primeiro: o que vence primeiro é o que deve ser aberto
        $lote = $validos
            ->filter(fn (EntradaItem $item) => $item->saldoNaClinica($clinicaId) > 0)
            ->sortBy(fn (EntradaItem $item) => [$item->vencimento?->format('Y-m-d') ?? '9999-12-31', $item->id])
            ->first();

        if (! $lote) {
            throw ValidationException::withMessages([
                'vasilhame' => 'Não há vasilhame fechado do código '.$codigo.' nesta clínica.',
            ]);
        }

        return $lote;
    }

    /**
     * Lotes (código de barras) cadastrados, com o medicamento carregado.
     *
     * @return Collection<int, EntradaItem>
     */
    private function lotesDoCodigo(string $codigo): Collection
    {
        if ($codigo === '') {
            return collect();
        }

        return EntradaItem::with('medicamento')
            ->where('codigo_barras', $codigo)
            ->whereHas('entrada')
            ->get();
    }

    /**
     * Vasilhame em uso de um dos lotes informados.
     *
     * @param  Collection<int, EntradaItem>  $lotes
     */
    private function emUsoDoCodigo(Collection $lotes, int $clinicaId): ?VasilhameAberto
    {
        return VasilhameAberto::with('entradaItem')
            ->emUso()
            ->whereIn('entrada_item_id', $lotes->pluck('id'))
            ->where('clinica_id', $clinicaId)
            ->orderBy('aberto_em')
            ->first();
    }

    /**
     * Monta a resposta da consulta do código de barras.
     *
     * @return array<string, mixed>
     */
    private function resposta(
        string $estado,
        ?string $mensagem,
        int $clinicaId,
        ?EntradaItem $item = null,
        ?VasilhameAberto $vasilhame = null
    ): array {
        return [
            'estado' => $estado,
            'mensagem' => $mensagem,
            'entrada_item_id' => $item?->id ?? $vasilhame?->entrada_item_id,
            'vasilhame_id' => $vasilhame?->id,
            'medicamento_id' => $item?->medicamento_id ?? $vasilhame?->medicamento_id,
            'medicamento' => $item?->medicamento?->nome ?? $vasilhame?->medicamento?->nome,
            'codigo_barras' => $item?->codigo_barras ?? $vasilhame?->codigo_barras,
            'lote' => $item?->lote ?? $vasilhame?->lote,
            'vencimento' => $item?->vencimento_formatado ?? $vasilhame?->vencimento_formatado,
            'mg_restantes' => $vasilhame ? (float) $vasilhame->mg_restantes : null,
            'mg_restantes_formatado' => $vasilhame ? Numero::formatar($vasilhame->mg_restantes).' mg' : null,
            'saldo_fechado' => $item?->saldoNaClinica($clinicaId) ?? 0,
        ];
    }
}
