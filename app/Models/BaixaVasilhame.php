<?php

namespace App\Models;

use App\Support\Numero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baixa de medicamentos ABERTOS: retira o saldo em mg que ainda estava em um ou
 * mais vasilhames em uso (perda, quebra, vencimento, sobra descartada...).
 */
class BaixaVasilhame extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'baixas_vasilhames';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'clinica_id',
        'user_id',
        'data',
        'observacao',
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
     * Clínica onde a baixa foi lançada.
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
     * Vasilhames baixados.
     */
    public function itens()
    {
        return $this->hasMany(BaixaVasilhameItem::class, 'baixa_vasilhame_id');
    }

    /**
     * Total de mg baixados.
     */
    public function getTotalMgAttribute(): float
    {
        return round((float) $this->itens->sum('mg_baixa'), 3);
    }

    /**
     * Total de mg formatado (ex.: 45,75).
     */
    public function getTotalMgFormatadoAttribute(): string
    {
        return Numero::formatar($this->total_mg);
    }

    /**
     * Data formatada (d/m/Y).
     */
    public function getDataFormatadaAttribute(): ?string
    {
        return $this->data?->format('d/m/Y');
    }
}
