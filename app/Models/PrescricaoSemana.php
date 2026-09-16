<?php

namespace App\Models;

use App\Enums\StatusSemana;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
     * Data prevista formatada (d/m/Y).
     */
    public function getDataPrevistaFormatadaAttribute(): ?string
    {
        return $this->data_prevista?->format('d/m/Y');
    }
}
