<?php

namespace App\Models;

use App\Enums\StatusSemanaItem;
use App\Enums\TipoMedicamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescricaoSemanaItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_semana_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_semana_id',
        'tipo',
        'medicamento_id',
        'combo_id',
        'quantidade',
        'valor',
        'gera_aplicacao',
        'status',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor' => 'decimal:2',
        'gera_aplicacao' => 'boolean',
        'status' => StatusSemanaItem::class,
    ];

    /**
     * Semana do item.
     */
    public function semana()
    {
        return $this->belongsTo(PrescricaoSemana::class, 'prescricao_semana_id');
    }

    /**
     * Medicamento do item (quando o tipo é medicamento).
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Combo do item (quando o tipo é combo).
     */
    public function combo()
    {
        return $this->belongsTo(Combo::class, 'combo_id');
    }

    /**
     * Quantidade que será cobrada: ampola não tem fração (0,5 cobra 1).
     * Miligrama, procedimento e combo cobram a quantidade exata.
     */
    public function getQuantidadeCobrancaAttribute(): float
    {
        $quantidade = (float) $this->quantidade;

        if ($this->tipo === 'medicamento' && $this->medicamento?->tipo === TipoMedicamento::Ampola) {
            return (float) ceil($quantidade);
        }

        return $quantidade;
    }

    /**
     * Valor total do item (quantidade cobrada x valor).
     */
    public function getValorTotalAttribute(): float
    {
        return $this->quantidade_cobranca * (float) $this->valor;
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }

    /**
     * Quantidade formatada, sem zeros desnecessários (ex.: 0,5 / 2).
     */
    public function getQuantidadeFormatadaAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantidade, 3, ',', '.'), '0'), ',');
    }

    /**
     * Valor unitário formatado (R$ 0,00).
     */
    public function getValorFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor, 2, ',', '.');
    }

    /**
     * Nome do medicamento ou do combo do item.
     */
    public function getNomeAttribute(): string
    {
        return $this->medicamento?->nome
            ?? $this->combo?->nome
            ?? '—';
    }
}
