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
     * Observações registradas na prescrição (linha do tempo).
     * O nome não pode ser "observacoes" porque a prescrição já tem uma coluna
     * com esse nome (anotação do cadastro).
     */
    public function observacoesRegistradas()
    {
        return $this->hasMany(PrescricaoObservacao::class, 'prescricao_id')->latest('id');
    }

    /**
     * Histórico de eventos da prescrição (mais recentes primeiro).
     */
    public function logs()
    {
        return $this->hasMany(PrescricaoLog::class, 'prescricao_id')->latest('id');
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
     * Quantidade de semanas que passam pelo fluxo de aplicação. As semanas
     * marcadas como "sem aplicação" ficam de fora: elas nunca são aplicadas
     * e por isso não bloqueiam a finalização da prescrição.
     */
    public function getSemanasComAplicacaoAttribute(): int
    {
        return $this->semanas
            ->reject(fn (PrescricaoSemana $semana) => $semana->sem_aplicacao)
            ->count();
    }

    /**
     * Quantidade de semanas com aplicação que já foram aplicadas.
     */
    public function getSemanasAplicadasAttribute(): int
    {
        return $this->contarSemanasComStatus(StatusSemana::Aplicada);
    }

    /**
     * Quantidade de semanas com aplicação parcialmente aplicadas.
     */
    public function getSemanasParciaisAttribute(): int
    {
        return $this->contarSemanasComStatus(StatusSemana::AplicacaoParcial);
    }

    /**
     * Número da última semana aplicada (aplicada ou com aplicação parcial).
     *
     * É a base do progresso exibido na listagem ("0/9", "1/9", "2/9"...):
     * como a aplicação é sequencial (não se manda a semana N sem a anterior),
     * o número da última semana aplicada é a quantidade de semanas já
     * realizadas. Assim uma semana atrasada/adiantada não bagunça a conta.
     *
     * Semanas "sem aplicação" ficam de fora: elas já nascem com status
     * "Aplicada" (não passam pelo fluxo) e não servem de referência.
     */
    public function getUltimaSemanaAplicadaAttribute(): ?int
    {
        return $this->semanas
            ->reject(fn (PrescricaoSemana $semana) => $semana->sem_aplicacao)
            ->filter(fn (PrescricaoSemana $semana) => in_array($semana->status, [
                StatusSemana::Aplicada,
                StatusSemana::AplicacaoParcial,
            ], true))
            ->max('numero');
    }

    /**
     * Quantidade de semanas com aplicação esperando na fila de aplicação.
     */
    public function getSemanasNaFilaAttribute(): int
    {
        return $this->contarSemanasComStatus(StatusSemana::FilaAplicacao);
    }

    /**
     * Quantidade de semanas com aplicação em atendimento.
     */
    public function getSemanasEmAtendimentoAttribute(): int
    {
        return $this->contarSemanasComStatus(StatusSemana::Atendimento);
    }

    /**
     * Quantidade de semanas com aplicação ainda agendadas.
     */
    public function getSemanasAgendadasAttribute(): int
    {
        return $this->contarSemanasComStatus(StatusSemana::Agendada);
    }

    /**
     * Semanas com aplicação já encerradas (aplicadas ou com aplicação parcial).
     */
    public function getSemanasConcluidasAttribute(): int
    {
        return $this->semanas_aplicadas + $this->semanas_parciais;
    }

    /**
     * Situação da prescrição, DERIVADA das semanas (nunca gravada no banco):
     * mostra o estágio mais avançado que ainda está em andamento.
     *
     * agendada -> fila de aplicação -> atendimento -> em andamento ->
     * finalizado com pendência -> finalizado
     */
    public function getSituacaoAttribute(): string
    {
        return match ($this->situacaoChave()) {
            'sem_aplicacao' => 'Sem aplicação',
            'fila' => StatusSemana::FilaAplicacao->label(),
            'atendimento' => StatusSemana::Atendimento->label(),
            'andamento' => 'Em andamento',
            'finalizado_pendencia' => 'Finalizado com pendência',
            'finalizado' => 'Finalizado',
            default => StatusSemana::Agendada->label(),
        };
    }

    /**
     * Cor (badge) da situação.
     */
    public function getSituacaoCorAttribute(): string
    {
        return match ($this->situacaoChave()) {
            'sem_aplicacao' => 'bg-label-secondary',
            'fila' => StatusSemana::FilaAplicacao->corBadge(),
            'atendimento' => StatusSemana::Atendimento->corBadge(),
            'finalizado' => 'bg-label-success',
            'finalizado_pendencia', 'andamento' => 'bg-label-warning',
            default => StatusSemana::Agendada->corBadge(),
        };
    }

    /**
     * Peso da situação, usado no data-order da listagem (ordena na mesma
     * sequência do fluxo de aplicação).
     */
    public function getSituacaoOrdemAttribute(): int
    {
        return match ($this->situacaoChave()) {
            'sem_aplicacao' => 1,
            'fila' => 3,
            'atendimento' => 4,
            'andamento' => 5,
            'finalizado_pendencia' => 6,
            'finalizado' => 7,
            default => 2,
        };
    }

    /**
     * Chave da situação — a regra de precedência fica concentrada aqui.
     */
    private function situacaoChave(): string
    {
        if ($this->semanas_com_aplicacao === 0) {
            return 'sem_aplicacao';
        }

        // Todas as semanas com aplicação já saíram do atendimento
        if ($this->semanas_concluidas >= $this->semanas_com_aplicacao) {
            return $this->semanas_parciais > 0 ? 'finalizado_pendencia' : 'finalizado';
        }

        if ($this->semanas_em_atendimento > 0) {
            return 'atendimento';
        }

        if ($this->semanas_na_fila > 0) {
            return 'fila';
        }

        if ($this->semanas_concluidas > 0) {
            return 'andamento';
        }

        return 'agendada';
    }

    /**
     * Conta as semanas que passam pelo fluxo com um status específico.
     */
    private function contarSemanasComStatus(StatusSemana $status): int
    {
        return $this->semanas
            ->filter(fn (PrescricaoSemana $semana) => ! $semana->sem_aplicacao && $semana->status === $status)
            ->count();
    }
}
