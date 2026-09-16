<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Baixa extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'baixas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'clinica_id',
        'user_id',
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
     * Clínica onde a baixa foi feita.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Usuário que lançou a baixa.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Medicamentos baixados.
     */
    public function itens()
    {
        return $this->hasMany(BaixaItem::class, 'baixa_id');
    }

    /**
     * Quantidade total de unidades baixadas.
     */
    public function getQuantidadeTotalAttribute(): int
    {
        return (int) $this->itens->sum('quantidade');
    }
}
