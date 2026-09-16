<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaixaItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'baixa_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'baixa_id',
        'entrada_item_id',
        'medicamento_id',
        'quantidade',
        'motivo',
    ];

    /**
     * Baixa à qual o item pertence.
     */
    public function baixa()
    {
        return $this->belongsTo(Baixa::class, 'baixa_id');
    }

    /**
     * Lote / código de barras baixado.
     */
    public function entradaItem()
    {
        return $this->belongsTo(EntradaItem::class, 'entrada_item_id');
    }

    /**
     * Medicamento baixado.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }
}
