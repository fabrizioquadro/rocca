<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComboItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'combo_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'combo_id',
        'medicamento_id',
        'quantidade',
        'valor',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantidade' => 'decimal:3',
        'valor' => 'decimal:2',
    ];

    /**
     * Combo ao qual o item pertence.
     */
    public function combo()
    {
        return $this->belongsTo(Combo::class, 'combo_id');
    }

    /**
     * Medicamento do item.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Quantidade formatada, sem zeros desnecessários (ex.: 6,25).
     */
    public function getQuantidadeFormatadaAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantidade, 3, ',', '.'), '0'), ',');
    }

    /**
     * Valor formatado (R$ 0,00).
     */
    public function getValorFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor, 2, ',', '.');
    }
}
