<?php

namespace App\Models;

use App\Enums\StatusSemana;
use App\Enums\TipoAtendimento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescricao extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricoes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'paciente_id',
        'medico_id',
        'medico_nome',
        'clinica_id',
        'tipo_atendimento',
        'agendamento',
        'observacoes',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tipo_atendimento' => TipoAtendimento::class,
    ];

    /**
     * Paciente da prescrição.
     */
    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    /**
     * Clínica onde a prescrição foi feita.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Usuário que cadastrou.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Semanas da prescrição (ordenadas pela numeração, que segue a data).
     */
    public function semanas()
    {
        return $this->hasMany(PrescricaoSemana::class, 'prescricao_id')->orderBy('numero');
    }

    /**
     * Financeiro (mestre) gerado pela prescrição.
     */
    public function financeiro()
    {
        return $this->hasOne(Financeiro::class, 'prescricao_id');
    }

    /**
     * Anexos da prescrição.
     */
    public function anexos()
    {
        return $this->hasMany(PrescricaoAnexo::class, 'prescricao_id')->latest('id');
    }

    /**
     * Valor total da prescrição (soma das semanas com a quantidade cobrada).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->semanas->sum(fn (PrescricaoSemana $semana) => $semana->valor_total);
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }

    /**
     * Quantidade de semanas da prescrição.
     */
    public function getQuantidadeSemanasAttribute(): int
    {
        return $this->semanas->count();
    }

    /**
     * Quantidade de semanas que possuem aplicação.
     */
    public function getSemanasComAplicacaoAttribute(): int
    {
        return $this->semanas
            ->filter(fn (PrescricaoSemana $semana) => ! $semana->sem_aplicacao && $semana->valor_total > 0)
            ->count();
    }

    /**
     * Quantidade de semanas com aplicação que já foram aplicadas.
     */
    public function getSemanasAplicadasAttribute(): int
    {
        return $this->semanas
            ->filter(fn (PrescricaoSemana $semana) => ! $semana->sem_aplicacao
                && $semana->valor_total > 0
                && $semana->status === StatusSemana::Aplicada)
            ->count();
    }

    /**
     * Progresso da aplicação: "Não iniciada" enquanto nenhuma semana foi
     * aplicada, "1/9" durante o processo e "Finalizado" quando todas as
     * semanas com aplicação já foram aplicadas.
     */
    public function getProgressoAplicacaoAttribute(): string
    {
        if ($this->semanas_aplicadas === 0) {
            return 'Não iniciada';
        }

        if ($this->semanas_aplicadas >= $this->semanas_com_aplicacao) {
            return 'Finalizado';
        }

        return $this->semanas_aplicadas.'/'.$this->semanas_com_aplicacao;
    }

    /**
     * Cor (badge) do progresso: cinza não iniciada, amarelo em andamento
     * e verde finalizada.
     */
    public function getProgressoAplicacaoCorAttribute(): string
    {
        if ($this->semanas_aplicadas === 0) {
            return 'bg-label-secondary';
        }

        if ($this->semanas_aplicadas >= $this->semanas_com_aplicacao) {
            return 'bg-label-success';
        }

        return 'bg-label-warning';
    }

    /**
     * Situação do procedimento: "Não iniciado" enquanto nenhuma semana foi
     * aplicada, "1/9", "2/9"... durante o processo e "Finalizado" quando
     * todas as semanas com aplicação já foram aplicadas.
     */
    public function getSituacaoProcedimentoAttribute(): string
    {
        if ($this->semanas_com_aplicacao === 0) {
            return 'Sem aplicação';
        }

        if ($this->semanas_aplicadas === 0) {
            return 'Não iniciado';
        }

        if ($this->semanas_aplicadas >= $this->semanas_com_aplicacao) {
            return 'Finalizado';
        }

        return $this->semanas_aplicadas.'/'.$this->semanas_com_aplicacao;
    }

    /**
     * Cor (badge) da situação do procedimento.
     */
    public function getSituacaoProcedimentoCorAttribute(): string
    {
        if ($this->semanas_com_aplicacao === 0) {
            return 'bg-label-secondary';
        }

        return $this->progresso_aplicacao_cor;
    }
}
