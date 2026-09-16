<?php

namespace App\Models;

use App\Enums\StatusParcela;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceiroParcela extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'financeiro_parcelas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'financeiro_id',
        'prescricao_semana_id',
        'valor_bruto',
        'valor_desconto',
        'valor_adicional',
        'numero',
        'vencimento',
        'valor',
        'valor_pago',
        'status',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor_bruto' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_adicional' => 'decimal:2',
        'vencimento' => 'date',
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'status' => StatusParcela::class,
    ];

    /**
     * Financeiro (mestre) da parcela.
     */
    public function financeiro()
    {
        return $this->belongsTo(Financeiro::class, 'financeiro_id');
    }

    /**
     * Semana que originou a parcela.
     */
    public function semana()
    {
        return $this->belongsTo(PrescricaoSemana::class, 'prescricao_semana_id');
    }

    /**
     * Valor formatado (R$ 0,00).
     */
    public function getValorFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor, 2, ',', '.');
    }

    /**
     * Vencimento formatado (d/m/Y).
     */
    public function getVencimentoFormatadoAttribute(): ?string
    {
        return $this->vencimento?->format('d/m/Y');
    }

    /**
     * Número formatado (1ª, 2ª...).
     */
    public function getNumeroFormatadoAttribute(): string
    {
        return $this->numero.'ª';
    }

    /**
     * Valor bruto (valor da semana, antes do desconto/adicional) formatado.
     */
    public function getValorBrutoFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_bruto, 2, ',', '.');
    }

    /**
     * Parte do desconto rateada nesta parcela, formatada.
     */
    public function getValorDescontoFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_desconto, 2, ',', '.');
    }

    /**
     * Parte do adicional rateada nesta parcela, formatada.
     */
    public function getValorAdicionalFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_adicional, 2, ',', '.');
    }

    /**
     * A parcela tem desconto e/ou adicional?
     */
    public function getTemAjustesAttribute(): bool
    {
        return (float) $this->valor_desconto > 0 || (float) $this->valor_adicional > 0;
    }

    /**
     * Valor já recebido por esta parcela, formatado.
     */
    public function getValorPagoFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_pago, 2, ',', '.');
    }

    /**
     * Quanto falta receber desta parcela.
     */
    public function getValorEmAbertoAttribute(): float
    {
        return round(max((float) $this->valor - (float) $this->valor_pago, 0), 2);
    }

    /**
     * Valor em aberto formatado (R$ 0,00).
     */
    public function getValorEmAbertoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_em_aberto, 2, ',', '.');
    }

    /**
     * Parcela quitada.
     */
    public function getEstaPagaAttribute(): bool
    {
        return (float) $this->valor > 0 && (float) $this->valor_pago >= (float) $this->valor;
    }

    /**
     * Parcela com pagamento parcial.
     */
    public function getEstaParcialAttribute(): bool
    {
        return (float) $this->valor_pago > 0 && (float) $this->valor_pago < (float) $this->valor;
    }
}
