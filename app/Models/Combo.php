<?php

namespace App\Models;

use App\Enums\StatusAtivoInativo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Combo extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'combos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => StatusAtivoInativo::class,
    ];

    /**
     * Medicamentos que compõem o combo.
     */
    public function itens()
    {
        return $this->hasMany(ComboItem::class, 'combo_id');
    }

    /**
     * Valor total do combo (soma dos itens).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->itens->sum('valor');
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }

    /**
     * O combo exige anexar a prescrição médica? Sim quando algum medicamento
     * dele é ampola/miligrama com aplicação.
     */
    public function getExigeAnexoAttribute(): bool
    {
        return $this->itens->contains(fn (ComboItem $item) => (bool) $item->medicamento?->exige_anexo);
    }
}
