<?php

namespace App\Models;

use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PrescricaoSemana extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_semanas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'numero',
        'data_prevista',
        'sem_aplicacao',
        'status',
        'liberado_por_user_id',
        'liberado_em',
        'chegada_em',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_prevista' => 'date',
        'sem_aplicacao' => 'boolean',
        'status' => StatusSemana::class,
        'liberado_em' => 'datetime',
        'chegada_em' => 'datetime',
    ];

    /**
     * Prescrição da semana.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Medicamentos/combos da semana.
     */
    public function itens()
    {
        return $this->hasMany(PrescricaoSemanaItem::class, 'prescricao_semana_id');
    }

    /**
     * Parcelas geradas a partir desta semana.
     */
    public function parcelas()
    {
        return $this->hasMany(FinanceiroParcela::class, 'prescricao_semana_id');
    }

    /**
     * Administrador que liberou a semana sem a parcela paga.
     */
    public function liberadoPor()
    {
        return $this->belongsTo(User::class, 'liberado_por_user_id');
    }

    /**
     * Atendimentos da semana: o paciente pode vir mais de uma vez quando a
     * aplicação fica parcial.
     */
    public function atendimentos()
    {
        return $this->hasMany(PrescricaoSemanaAtendimento::class, 'prescricao_semana_id')->orderBy('iniciado_em');
    }

    /**
     * Atendimento aberto — é ele que mantém a semana em "Em Atendimento".
     */
    public function atendimentoAberto()
    {
        return $this->hasOne(PrescricaoSemanaAtendimento::class, 'prescricao_semana_id')
            ->whereNull('finalizado_em');
    }

    /**
     * A semana pode iniciar um atendimento agora? (primeira vez na fila ou
     * volta do paciente depois de uma aplicação parcial)
     */
    public function getPodeIniciarAtendimentoAttribute(): bool
    {
        return in_array($this->status, [StatusSemana::FilaAplicacao, StatusSemana::AplicacaoParcial], true)
            && $this->atendimentoAberto === null;
    }

    /**
     * O usuário logado é quem está com o atendimento aberto desta semana?
     */
    public function getAtendimentoEmAbertoDoUsuarioAttribute(): bool
    {
        $atendimento = $this->atendimentoAberto;

        return $atendimento !== null
            && (int) $atendimento->iniciado_por_user_id === (int) auth()->id();
    }

    /**
     * O atendimento desta semana está livre para o usuário logado mexer?
     * (não tem atendimento aberto ou o atendimento aberto é dele)
     */
    public function getAtendimentoLivreParaMimAttribute(): bool
    {
        return $this->atendimentoAberto === null || $this->atendimento_em_aberto_do_usuario;
    }

    /**
     * Chegada do paciente formatada (d/m/Y H:i).
     */
    public function getChegadaEmFormatadaAttribute(): ?string
    {
        return $this->chegada_em?->format('d/m/Y H:i');
    }

    /**
     * Tempo de espera desde a chegada — só enquanto a semana está na fila.
     */
    public function getTempoDeEsperaAttribute(): ?string
    {
        if (! $this->chegada_em || $this->status !== StatusSemana::FilaAplicacao) {
            return null;
        }

        $minutos = (int) $this->chegada_em->diffInMinutes(now());

        return $minutos < 60
            ? $minutos.' min'
            : intdiv($minutos, 60).'h '.($minutos % 60).'min';
    }

    /**
     * A semana foi liberada para a fila sem a parcela paga?
     */
    public function getFoiLiberadaAttribute(): bool
    {
        return $this->liberado_por_user_id !== null;
    }

    /**
     * Texto da liberação: "Liberada sem pagamento por Fulano em 15/09/2026 14:30".
     */
    public function getLiberacaoDescricaoAttribute(): ?string
    {
        if (! $this->foi_liberada) {
            return null;
        }

        return 'Liberada sem pagamento por '.($this->liberadoPor?->nome ?? 'usuário removido')
            .($this->liberado_em ? ' em '.$this->liberado_em->format('d/m/Y H:i') : '');
    }

    /**
     * Valor total da semana (quantidade cobrada x valor de cada item).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->itens->sum(fn (PrescricaoSemanaItem $item) => $item->valor_total);
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }

    /**
     * A semana possui algum item que gera aplicação?
     */
    public function getTemAplicacaoAttribute(): bool
    {
        return $this->itens->contains(fn (PrescricaoSemanaItem $item) => $item->gera_aplicacao);
    }

    /**
     * A semana pode ser alterada (editar/excluir)? Só enquanto não existe
     * aplicação: semanas sem nada a aplicar ou ainda "Agendada".
     * Qualquer outro status (fila, atendimento, aplicada, aplicação parcial
     * e um futuro "cancelada") bloqueia a alteração.
     */
    public function getPodeSerAlteradaAttribute(): bool
    {
        return ! $this->tem_aplicacao || $this->status === StatusSemana::Agendada;
    }

    /**
     * Explicação do bloqueio, para exibir no tooltip da tela.
     */
    public function getMotivoBloqueioAttribute(): ?string
    {
        if ($this->pode_ser_alterada) {
            return null;
        }

        return 'Semana '.$this->status->label().' — só pode ser alterada enquanto não há aplicação.';
    }

    /**
     * Semanas ANTERIORES a esta que ainda estão "Agendada".
     *
     * A aplicação é sequencial, mas dá para mandar mais de uma semana para a
     * fila em sequência: basta que a semana anterior NÃO esteja mais
     * "Agendada" (pode estar na fila, em atendimento ou já aplicada).
     * Semana "sem aplicação" já nasce Aplicada e por isso não bloqueia nada.
     *
     * @return Collection<int, PrescricaoSemana>
     */
    public function getSemanasAnterioresPendentesAttribute(): Collection
    {
        return $this->prescricao->semanas
            ->filter(fn (PrescricaoSemana $semana) => $semana->numero < $this->numero
                && $semana->status === StatusSemana::Agendada)
            ->values();
    }

    /**
     * A semana pode ser enviada para a fila de aplicação? Só se nenhuma
     * semana anterior estiver ainda "Agendada".
     */
    public function getPodeIrParaFilaAttribute(): bool
    {
        return $this->semanas_anteriores_pendentes->isEmpty();
    }

    /**
     * Explicação do bloqueio do envio para a fila, para exibir na tela.
     */
    public function getMotivoBloqueioFilaAttribute(): ?string
    {
        $pendentes = $this->semanas_anteriores_pendentes;

        if ($pendentes->isEmpty()) {
            return null;
        }

        if ($pendentes->count() === 1) {
            $numero = $pendentes->first()->numero;

            return 'Aplicação sequencial: a semana '.$numero
                .' ainda está "Agendada". Envie a semana '.$numero
                .' para a fila antes da semana '.$this->numero.'.';
        }

        // Lista curta: não vale poluir o tooltip com 9 semanas
        $numeros = $pendentes->take(4)->pluck('numero')->implode(', ')
            .($pendentes->count() > 4 ? ' e mais '.($pendentes->count() - 4) : '');

        return 'Aplicação sequencial: as semanas '.$numeros
            .' ainda estão "Agendadas". Envie elas para a fila antes da semana '.$this->numero.'.';
    }

    /**
     * A semana pode voltar para "Agendada"? Só enquanto está na fila de
     * aplicação ou em atendimento e nenhuma aplicação foi registrada — é o
     * caminho de volta do paciente que não compareceu.
     *
     * Item que não gera aplicação (consulta, procedimento) nasce "Aplicado"
     * e NÃO conta: ele nunca foi aplicado de verdade.
     */
    public function getPodeVoltarParaAgendadaAttribute(): bool
    {
        if (! in_array($this->status, [StatusSemana::FilaAplicacao, StatusSemana::Atendimento], true)) {
            return false;
        }

        return ! $this->itens->contains(fn (PrescricaoSemanaItem $item) => $item->gera_aplicacao
            && $item->status === StatusSemanaItem::Aplicado);
    }

    /**
     * Data prevista formatada (d/m/Y).
     */
    public function getDataPrevistaFormatadaAttribute(): ?string
    {
        return $this->data_prevista?->format('d/m/Y');
    }

    /**
     * Resumo da semana para o histórico (chave => valor).
     *
     * @return array<string, string>
     */
    public function resumoParaLog(): array
    {
        $itens = $this->itensParaLog();

        return [
            'Data prevista' => $this->data_prevista_formatada ?? '—',
            'Sem aplicação' => $this->sem_aplicacao ? 'Sim' : 'Não',
            'Status' => $this->status->label(),
            'Itens' => $itens ? implode(' · ', $itens) : 'Nenhum item',
            'Valor' => $this->valor_total_formatado,
        ];
    }

    /**
     * Itens da semana em texto, na ordem — usado para comparar antes/depois.
     *
     * @return array<int, string>
     */
    public function itensParaLog(): array
    {
        return $this->itens
            ->map(fn (PrescricaoSemanaItem $item) => $item->quantidade_formatada.'× '.$item->nome
                .' ('.$item->valor_formatado.')')
            ->values()
            ->all();
    }
}
