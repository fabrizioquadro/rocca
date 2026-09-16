<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transferencia extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'transferencias';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'clinica_id',
        'clinica_destino_id',
        'user_id',
        'observacao',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'date',
    ];

    /**
     * Clínica de origem.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Clínica de destino.
     */
    public function clinicaDestino()
    {
        return $this->belongsTo(Clinica::class, 'clinica_destino_id');
    }

    /**
     * Usuário que lançou a transferência.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Medicamentos transferidos.
     */
    public function itens()
    {
        return $this->hasMany(TransferenciaItem::class, 'transferencia_id');
    }

    /**
     * Quantidade total de unidades transferidas.
     */
    public function getQuantidadeTotalAttribute(): int
    {
        return (int) $this->itens->sum('quantidade');
    }
}
