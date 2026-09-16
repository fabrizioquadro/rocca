<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntradaItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'entrada_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entrada_id',
        'medicamento_id',
        'lote',
        'codigo_barras',
        'vencimento',
        'quantidade',
        'valor_unitario',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'vencimento' => 'date',
        'valor_unitario' => 'decimal:2',
    ];

    /**
     * Entrada à qual o item pertence.
     */
    public function entrada()
    {
        return $this->belongsTo(Entrada::class, 'entrada_id');
    }

    /**
     * Medicamento do item.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Movimentações de estoque geradas por este item.
     */
    public function movimentacoes()
    {
        return $this->hasMany(EstoqueMovimentacao::class, 'entrada_item_id');
    }

    /**
     * Vencimento formatado (d/m/Y).
     */
    public function getVencimentoFormatadoAttribute(): ?string
    {
        return $this->vencimento?->format('d/m/Y');
    }

    /**
     * Saldo deste lote (código de barras) em uma clínica.
     */
    public function saldoNaClinica(int $clinicaId): int
    {
        return (int) $this->movimentacoes()->where('clinica_id', $clinicaId)->sum('quantidade');
    }

    /**
     * Lotes de um código de barras com saldo disponível na clínica,
     * em ordem de vencimento (o que vence primeiro sai primeiro).
     */
    public static function lotesComSaldo(string $codigo, int $clinicaId)
    {
        return static::with('medicamento')
            ->where('codigo_barras', $codigo)
            ->whereHas('entrada')
            ->get()
            ->each(function (self $item) use ($clinicaId) {
                $item->saldo_clinica = $item->saldoNaClinica($clinicaId);
            })
            ->filter(fn (self $item) => $item->saldo_clinica > 0)
            ->sortBy(fn (self $item) => [$item->vencimento?->format('Y-m-d') ?? '9999-12-31', $item->id])
            ->values();
    }

    /**
     * Saldo total de um código de barras na clínica.
     */
    public static function saldoPorCodigo(string $codigo, int $clinicaId): int
    {
        return (int) static::lotesComSaldo($codigo, $clinicaId)->sum('saldo_clinica');
    }

    /**
     * Distribui a quantidade entre os lotes disponíveis do código (FIFO).
     * Retorna [entrada_item_id => quantidade].
     */
    public static function distribuirPorCodigo(string $codigo, int $clinicaId, int $quantidade): array
    {
        $restante = $quantidade;
        $distribuicao = [];

        foreach (static::lotesComSaldo($codigo, $clinicaId) as $item) {
            if ($restante <= 0) {
                break;
            }

            $usar = min($restante, $item->saldo_clinica);

            $distribuicao[$item->id] = $usar;
            $restante -= $usar;
        }

        return $distribuicao;
    }

    /**
     * Valor total do item (valor unitário × quantidade).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->valor_unitario * (int) $this->quantidade;
    }

    /**
     * Valor unitário formatado (R$ 0,00).
     */
    public function getValorUnitarioFormatadoAttribute(): ?string
    {
        return $this->valor_unitario === null
            ? null
            : 'R$ '.number_format((float) $this->valor_unitario, 2, ',', '.');
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): ?string
    {
        return $this->valor_unitario === null
            ? null
            : 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }
}
