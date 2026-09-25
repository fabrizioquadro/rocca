<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de envio de uma aplicação para a Feegow.
 *
 * Um registro por atendimento finalizado (a aplicação parcial gera mais de um
 * atendimento na mesma semana e, portanto, mais de um agendamento).
 */
class FeegowFila extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'feegow_filas';

    public const PENDENTE = 'pendente';

    public const ENVIADO = 'enviado';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'prescricao_semana_id',
        'prescricao_semana_atendimento_id',
        'evento',
        'situacao',
        'tentativas',
        'proxima_tentativa',
        'ultima_tentativa',
        'enviado_em',
        'agendamento_id',
        'erro',
        'payload',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'tentativas' => 'integer',
        'proxima_tentativa' => 'datetime',
        'ultima_tentativa' => 'datetime',
        'enviado_em' => 'datetime',
    ];

    /**
     * Prescrição do envio.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Semana aplicada.
     */
    public function semana()
    {
        return $this->belongsTo(PrescricaoSemana::class, 'prescricao_semana_id');
    }

    /**
     * Atendimento que gerou o envio.
     */
    public function atendimento()
    {
        return $this->belongsTo(PrescricaoSemanaAtendimento::class, 'prescricao_semana_atendimento_id');
    }

    /**
     * Registros que ainda precisam ser enviados (respeitando o backoff).
     */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('situacao', self::PENDENTE)
            ->where(function (Builder $query) {
                $query->whereNull('proxima_tentativa')->orWhere('proxima_tentativa', '<=', now());
            });
    }

    /**
     * O envio já foi aceito pela Feegow?
     */
    public function getEnviadoAttribute(): bool
    {
        return $this->situacao === self::ENVIADO;
    }

    /**
     * Rótulo da situação para a tela.
     */
    public function getSituacaoLabelAttribute(): string
    {
        if ($this->enviado) {
            return 'Enviado para a Feegow';
        }

        return $this->tentativas > 0 ? 'Com erro (tentando de novo)' : 'Aguardando envio';
    }

    /**
     * Classe de badge do template para a situação.
     */
    public function getSituacaoCorAttribute(): string
    {
        if ($this->enviado) {
            return 'bg-label-success';
        }

        return $this->tentativas > 0 ? 'bg-label-danger' : 'bg-label-warning';
    }
}
