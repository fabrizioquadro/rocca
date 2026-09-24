<?php

namespace App\Enums;

/**
 * Tipos de evento registrados no histórico da prescrição.
 */
enum TipoLogPrescricao: string
{
    case Criacao = 'criacao';
    case SemanaCriada = 'semana_criada';
    case SemanaEditada = 'semana_editada';
    case SemanaExcluida = 'semana_excluida';
    case EnvioFila = 'envio_fila';
    case Devolucao = 'devolucao';
    case AtendimentoIniciado = 'atendimento_iniciado';
    case AtendimentoFinalizado = 'atendimento_finalizado';
    case Aplicacao = 'aplicacao';
    case VasilhameAberto = 'vasilhame_aberto';
    case ItemPendente = 'item_pendente';
    case PagamentoRegistrado = 'pagamento_registrado';
    case PagamentoRemovido = 'pagamento_removido';
    case FinanceiroAjustado = 'financeiro_ajustado';
    case AnexoEnviado = 'anexo_enviado';
    case AnexoRemovido = 'anexo_removido';
    case Observacao = 'observacao';
    case Exclusao = 'exclusao';

    public function label(): string
    {
        return match ($this) {
            self::Criacao => 'Prescrição criada',
            self::SemanaCriada => 'Semana criada',
            self::SemanaEditada => 'Semana editada',
            self::SemanaExcluida => 'Semana excluída',
            self::EnvioFila => 'Enviada para a fila',
            self::Devolucao => 'Devolvida para agendamento',
            self::AtendimentoIniciado => 'Atendimento iniciado',
            self::AtendimentoFinalizado => 'Atendimento finalizado',
            self::Aplicacao => 'Medicamento aplicado',
            self::VasilhameAberto => 'Vasilhame aberto',
            self::ItemPendente => 'Medicamento pendente',
            self::PagamentoRegistrado => 'Pagamento registrado',
            self::PagamentoRemovido => 'Pagamento removido',
            self::FinanceiroAjustado => 'Financeiro ajustado',
            self::AnexoEnviado => 'Anexo enviado',
            self::AnexoRemovido => 'Anexo removido',
            self::Observacao => 'Observação registrada',
            self::Exclusao => 'Prescrição excluída',
        };
    }

    /**
     * Classe de badge do template para exibir o tipo do evento.
     */
    public function corBadge(): string
    {
        return match ($this) {
            self::Criacao, self::SemanaCriada, self::AnexoEnviado, self::Observacao => 'bg-label-primary',
            self::Aplicacao, self::PagamentoRegistrado, self::AtendimentoFinalizado => 'bg-label-success',
            self::SemanaEditada, self::FinanceiroAjustado, self::ItemPendente => 'bg-label-warning',
            self::SemanaExcluida, self::Exclusao, self::PagamentoRemovido, self::AnexoRemovido => 'bg-label-danger',
            self::EnvioFila, self::AtendimentoIniciado, self::VasilhameAberto => 'bg-label-info',
            self::Devolucao => 'bg-label-secondary',
        };
    }

    /**
     * Ícone (Remixicon) do evento.
     */
    public function icone(): string
    {
        return match ($this) {
            self::Criacao => 'ri-file-add-line',
            self::SemanaCriada => 'ri-calendar-event-line',
            self::SemanaEditada => 'ri-edit-line',
            self::SemanaExcluida, self::Exclusao => 'ri-delete-bin-7-line',
            self::EnvioFila => 'ri-play-list-add-line',
            self::Devolucao => 'ri-arrow-go-back-line',
            self::AtendimentoIniciado => 'ri-play-circle-line',
            self::AtendimentoFinalizado => 'ri-flag-2-line',
            self::Aplicacao => 'ri-syringe-line',
            self::VasilhameAberto => 'ri-archive-2-line',
            self::ItemPendente => 'ri-error-warning-line',
            self::PagamentoRegistrado => 'ri-money-dollar-circle-line',
            self::PagamentoRemovido => 'ri-refund-2-line',
            self::FinanceiroAjustado => 'ri-percent-line',
            self::AnexoEnviado => 'ri-attachment-2',
            self::AnexoRemovido => 'ri-attachment-2',
            self::Observacao => 'ri-chat-1-line',
        };
    }

    /**
     * Opções para popular selects/menus.
     *
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tipo) => [$tipo->value => $tipo->label()])
            ->all();
    }
}
