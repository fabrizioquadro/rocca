<?php

namespace App\Models;

use App\Support\Numero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Item da baixa de medicamento aberto: quanto (em mg) saiu de cada vasilhame.
 */
class BaixaVasilhameItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'baixas_vasilhames_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'baixa_vasilhame_id',
        'vasilhame_aberto_id',
        'medicamento_id',
        'mg_baixa',
        'motivo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mg_baixa' => 'decimal:3',
    ];

    /**
     * Baixa à qual o item pertence.
     */
    public function baixa()
    {
        return $this->belongsTo(BaixaVasilhame::class, 'baixa_vasilhame_id');
    }

    /**
     * Vasilhame aberto de onde saiu a baixa.
     */
    public function vasilhameAberto()
    {
        return $this->belongsTo(VasilhameAberto::class, 'vasilhame_aberto_id');
    }

    /**
     * Medicamento do vasilhame.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Mg baixados formatados (ex.: 45,75).
     */
    public function getMgBaixaFormatadoAttribute(): string
    {
        return Numero::formatar($this->mg_baixa);
    }

    /**
     * Código de barras do vasilhame.
     */
    public function getCodigoBarrasAttribute(): ?string
    {
        return $this->vasilhameAberto?->codigo_barras;
    }

    /**
     * Lote do vasilhame.
     */
    public function getLoteAttribute(): ?string
    {
        return $this->vasilhameAberto?->lote;
    }

    /**
     * Vencimento formatado (d/m/Y).
     */
    public function getVencimentoFormatadoAttribute(): ?string
    {
        return $this->vasilhameAberto?->vencimento_formatado;
    }
}
