<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferenciaItem extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'transferencia_itens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transferencia_id',
        'entrada_item_id',
        'medicamento_id',
        'quantidade',
    ];

    /**
     * Transferência à qual o item pertence.
     */
    public function transferencia()
    {
        return $this->belongsTo(Transferencia::class, 'transferencia_id');
    }

    /**
     * Lote / código de barras transferido.
     */
    public function entradaItem()
    {
        return $this->belongsTo(EntradaItem::class, 'entrada_item_id');
    }

    /**
     * Medicamento transferido.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }
}
