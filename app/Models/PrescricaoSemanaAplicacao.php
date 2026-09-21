<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Medicamento aplicado em um atendimento: guarda o momento exato da aplicação
 * e a cópia do código de barras / lote / vencimento que saiu do estoque.
 */
class PrescricaoSemanaAplicacao extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_semana_aplicacoes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_semana_atendimento_id',
        'prescricao_semana_item_id',
        'entrada_item_id',
        'medicamento_id',
        'codigo_barras',
        'lote',
        'vencimento',
        'quantidade',
        'aplicado_em',
        'user_id',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'vencimento' => 'date',
        'aplicado_em' => 'datetime',
        'quantidade' => 'decimal:3',
    ];

    /**
     * Atendimento ao qual a aplicação pertence.
     */
    public function atendimento()
    {
        return $this->belongsTo(PrescricaoSemanaAtendimento::class, 'prescricao_semana_atendimento_id');
    }

    /**
     * Item da semana que foi aplicado.
     */
    public function item()
    {
        return $this->belongsTo(PrescricaoSemanaItem::class, 'prescricao_semana_item_id');
    }

    /**
     * Lote do estoque de onde saiu o medicamento.
     */
    public function entradaItem()
    {
        return $this->belongsTo(EntradaItem::class, 'entrada_item_id');
    }

    /**
     * Medicamento aplicado (no combo, qual dos componentes).
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Quem aplicou.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Aplicação formatada (d/m/Y H:i).
     */
    public function getAplicadoEmFormatadoAttribute(): ?string
    {
        return $this->aplicado_em?->format('d/m/Y H:i');
    }

    /**
     * Vencimento formatado (d/m/Y).
     */
    public function getVencimentoFormatadoAttribute(): ?string
    {
        return $this->vencimento?->format('d/m/Y');
    }

    /**
     * Quantidade formatada, sem zeros desnecessários (ex.: 0,5 / 2).
     */
    public function getQuantidadeFormatadaAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantidade, 3, ',', '.'), '0'), ',');
    }

    /**
     * O lote estava vencido no momento da aplicação?
     */
    public function getLoteVencidoAttribute(): bool
    {
        return $this->vencimento && $this->aplicado_em
            && $this->vencimento->startOfDay()->lt($this->aplicado_em->copy()->startOfDay());
    }
}
