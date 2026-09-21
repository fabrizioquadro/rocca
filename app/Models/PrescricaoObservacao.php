<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Observação registrada na prescrição: guarda o texto, quem escreveu e quando
 * (é uma linha do tempo, não um campo editável).
 */
class PrescricaoObservacao extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_observacoes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'user_id',
        'observacao',
    ];

    /**
     * Prescrição da observação.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Usuário que registrou a observação.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Data/hora do registro formatada (d/m/Y H:i).
     */
    public function getCriadaEmFormatadaAttribute(): ?string
    {
        return $this->created_at?->format('d/m/Y H:i');
    }
}
