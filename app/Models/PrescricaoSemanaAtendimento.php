<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Atendimento da enfermagem: uma "sessão" em que o paciente veio aplicar.
 * A aplicação parcial faz a mesma semana ter mais de um atendimento.
 */
class PrescricaoSemanaAtendimento extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_semana_atendimentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_semana_id',
        'chegada_em',
        'iniciado_em',
        'iniciado_por_user_id',
        'finalizado_em',
        'finalizado_por_user_id',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'chegada_em' => 'datetime',
        'iniciado_em' => 'datetime',
        'finalizado_em' => 'datetime',
    ];

    /**
     * Semana do atendimento.
     */
    public function semana()
    {
        return $this->belongsTo(PrescricaoSemana::class, 'prescricao_semana_id');
    }

    /**
     * Quem iniciou o atendimento.
     */
    public function iniciadoPor()
    {
        return $this->belongsTo(User::class, 'iniciado_por_user_id');
    }

    /**
     * Quem finalizou o atendimento.
     */
    public function finalizadoPor()
    {
        return $this->belongsTo(User::class, 'finalizado_por_user_id');
    }

    /**
     * Medicamentos aplicados neste atendimento.
     */
    public function aplicacoes()
    {
        return $this->hasMany(PrescricaoSemanaAplicacao::class, 'prescricao_semana_atendimento_id');
    }

    /**
     * O atendimento ainda está aberto?
     */
    public function getEmAndamentoAttribute(): bool
    {
        return $this->finalizado_em === null;
    }

    /**
     * Só quem iniciou o atendimento pode conduzi-lo (registrar a aplicação e
     * finalizar). Os outros usuários só visualizam.
     */
    public function podeSerConduzidoPor(?User $user): bool
    {
        return $user !== null && $this->iniciado_por_user_id === $user->id;
    }

    /**
     * Recado para quem tentou mexer no atendimento de outro usuário.
     */
    public function getBloqueioDeOutroUsuarioAttribute(): string
    {
        return 'Atendimento iniciado por '.($this->iniciadoPor?->nome ?? 'outro usuário')
            .' — só quem iniciou pode registrar a aplicação e finalizar.';
    }

    /**
     * Início formatado (d/m/Y H:i).
     */
    public function getIniciadoEmFormatadoAttribute(): ?string
    {
        return $this->iniciado_em?->format('d/m/Y H:i');
    }

    /**
     * Chegada do paciente na sessão (d/m/Y H:i).
     */
    public function getChegadaEmFormatadaAttribute(): ?string
    {
        return $this->chegada_em?->format('d/m/Y H:i');
    }

    /**
     * Fim formatado (d/m/Y H:i).
     */
    public function getFinalizadoEmFormatadoAttribute(): ?string
    {
        return $this->finalizado_em?->format('d/m/Y H:i');
    }
}
