{{--
  Conteúdo do "Imprimir cadastro": a prescrição detalhada numa página só.
  É usado tanto pela tela de impressão quanto pelo PDF (dompdf), então fica
  com HTML/CSS simples, que os dois renderizam igual.
--}}
@php
  $financeiro = $prescricao->financeiro;
  $rotulo = fn ($valor) => filled($valor) ? $valor : '—';
@endphp

<div class="impressao">
  {{-- Cabeçalho do documento --}}
  <table class="imp-tabela imp-cabecalho">
    <tr>
      <td class="imp-clinica">
        <strong>{{ $prescricao->clinica?->nome ?? '—' }}</strong>
        @if ($prescricao->clinica?->cnpj)
          <div>CNPJ {{ $prescricao->clinica->cnpj_formatado }}</div>
        @endif
      </td>
      <td class="imp-titulo">
        <strong>Prescrição #{{ $prescricao->id }}</strong>
        <div>{{ $prescricao->situacao }} · emitido em {{ now()->format('d/m/Y H:i') }}</div>
      </td>
    </tr>
  </table>

  {{-- Paciente --}}
  <table class="imp-tabela imp-campos">
    <tr>
      <td class="imp-rotulo">Paciente</td>
      <td colspan="3">{{ $rotulo($prescricao->paciente?->nome) }}</td>
      <td class="imp-rotulo">Nascimento</td>
      <td>
        {{ $rotulo($prescricao->paciente?->nascimento_formatado) }}
        @if ($prescricao->paciente?->idade !== null)
          ({{ $prescricao->paciente->idade }} anos)
        @endif
      </td>
    </tr>
    <tr>
      <td class="imp-rotulo">CPF</td>
      <td>{{ $rotulo($prescricao->paciente?->cpf_formatado) }}</td>
      <td class="imp-rotulo">Telefone</td>
      <td>{{ $rotulo($prescricao->paciente?->celular_formatado ?? $prescricao->paciente?->telefone_formatado) }}</td>
      <td class="imp-rotulo">E-mail</td>
      <td>{{ $rotulo($prescricao->paciente?->email) }}</td>
    </tr>
    <tr>
      <td class="imp-rotulo">Médico</td>
      <td>{{ $rotulo($prescricao->medico_nome) }}</td>
      <td class="imp-rotulo">Tipo de atendimento</td>
      <td>{{ $rotulo($prescricao->tipo_atendimento?->label()) }}</td>
      <td class="imp-rotulo">Agendamento</td>
      <td>{{ $rotulo($prescricao->agendamento) }}</td>
    </tr>
    <tr>
      <td class="imp-rotulo">Semanas</td>
      <td>{{ $prescricao->quantidade_semanas }} semana(s) — {{ $prescricao->semanas_concluidas }} concluída(s)</td>
      <td class="imp-rotulo">Cadastro</td>
      <td>{{ $rotulo($prescricao->created_at?->format('d/m/Y H:i')) }}</td>
      <td class="imp-rotulo">Cadastrada por</td>
      <td>{{ $rotulo($prescricao->user?->nome) }}</td>
    </tr>
    @if ($prescricao->observacoes)
      <tr>
        <td class="imp-rotulo">Observações</td>
        <td colspan="5">{{ $prescricao->observacoes }}</td>
      </tr>
    @endif
  </table>

  {{-- Semanas: itens previstos e o que foi aplicado --}}
  @foreach ($prescricao->semanas->sortBy('numero') as $semana)
    <div class="imp-bloco">
      <table class="imp-tabela imp-titulo-bloco">
        <tr>
          <td>
            <strong>Semana {{ $semana->numero }}/{{ $prescricao->quantidade_semanas }}</strong>
            <span class="imp-espaco"></span>
            Prevista: <strong>{{ $rotulo($semana->data_prevista_formatada) }}</strong>
            <span class="imp-espaco"></span>
            Aplicação: <strong>{{ $rotulo($semana->data_aplicacao_formatada) }}</strong>
          </td>
          <td class="imp-situacao">
            {{ $semana->sem_aplicacao ? 'Sem aplicação' : $semana->status->label() }}
          </td>
        </tr>
      </table>

      @if ($semana->itens->isEmpty())
        <table class="imp-tabela">
          <tr><td class="imp-vazio">Nenhum item nesta semana.</td></tr>
        </table>
      @else
        <table class="imp-tabela">
          <thead>
            <tr>
              <th style="width: 46%;">Item</th>
              <th style="width: 14%;" class="imp-centro">Quantidade</th>
              <th style="width: 14%;" class="imp-direita">Valor unitário</th>
              <th style="width: 14%;" class="imp-direita">Valor total</th>
              <th style="width: 12%;" class="imp-centro">Situação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($semana->itens as $item)
              <tr>
                <td>
                  {{ $item->nome }}
                  @if ($item->tipo === 'combo')
                    <span class="imp-obs">(combo)</span>
                  @endif
                </td>
                <td class="imp-centro">{{ $item->quantidade_formatada }}</td>
                <td class="imp-direita">{{ $item->valor_formatado }}</td>
                <td class="imp-direita">{{ $item->valor_total_formatado }}</td>
                <td class="imp-centro">
                  {{ $item->gera_aplicacao ? $item->status->label() : 'Não se aplica' }}
                </td>
              </tr>

              {{-- Componentes do combo --}}
              @if ($item->tipo === 'combo' && $item->combo)
                <tr>
                  <td colspan="5" class="imp-obs">
                    Composição: {{ $item->combo->itens->map(fn ($componente) => $componente->medicamento?->nome)->filter()->implode(' · ') ?: '—' }}
                  </td>
                </tr>
              @endif
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3" class="imp-direita">Total da semana</th>
              <th class="imp-direita">{{ $semana->valor_total_formatado }}</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      @endif

      @php
        // Aplicações da semana: o que saiu, quando, de qual lote e quem aplicou
        $aplicacoes = $semana->atendimentos
            ->flatMap(fn ($atendimento) => $atendimento->aplicacoes
                ->sortBy('aplicado_em')
                ->map(fn ($aplicacao) => ['atendimento' => $atendimento, 'aplicacao' => $aplicacao]))
            ->sortBy(fn ($linha) => $linha['aplicacao']->aplicado_em)
            ->values();
      @endphp

      @if ($aplicacoes->isNotEmpty())
        <table class="imp-tabela">
          <thead>
            <tr>
              <th style="width: 13%;">Aplicado em</th>
              <th style="width: 24%;">Medicamento aplicado</th>
              <th style="width: 10%;" class="imp-centro">Quantidade</th>
              <th style="width: 13%;">Lote</th>
              <th style="width: 12%;">Código de barras</th>
              <th style="width: 11%;">Vencimento</th>
              <th style="width: 17%;">Quem aplicou</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($aplicacoes as $linha)
              @php $aplicacao = $linha['aplicacao']; @endphp

              <tr>
                <td>{{ $aplicacao->aplicado_em_formatado ?? '—' }}</td>
                <td>
                  {{ $aplicacao->medicamento?->nome ?? $aplicacao->item?->nome ?? '—' }}
                  @if ($aplicacao->item && $aplicacao->item->tipo === 'combo')
                    <span class="imp-obs">(combo {{ $aplicacao->item->nome }})</span>
                  @endif
                </td>
                <td class="imp-centro">
                  {{ $aplicacao->quantidade === null ? '—' : $aplicacao->quantidade_com_unidade }}
                </td>
                <td>{{ $rotulo($aplicacao->lote) }}</td>
                <td>{{ $rotulo($aplicacao->codigo_barras) }}</td>
                <td>{{ $rotulo($aplicacao->vencimento_formatado) }}</td>
                <td>{{ $rotulo($aplicacao->user?->nome) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

      {{-- Chegada, atendimento e quem conduziu --}}
      @php
        $atendimentosDaSemana = $semana->atendimentos->sortBy('iniciado_em');
      @endphp

      @if ($atendimentosDaSemana->isNotEmpty())
        <table class="imp-tabela">
          <thead>
            <tr>
              <th style="width: 16%;">Chegada</th>
              <th style="width: 22%;">Atendimento iniciado</th>
              <th style="width: 22%;">Atendimento finalizado</th>
              <th style="width: 40%;">Observação do atendimento</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($atendimentosDaSemana as $atendimento)
              <tr>
                <td>{{ $rotulo($atendimento->chegada_em_formatada) }}</td>
                <td>
                  {{ $rotulo($atendimento->iniciado_em_formatado) }}
                  @if ($atendimento->iniciadoPor)
                    <span class="imp-obs">{{ $atendimento->iniciadoPor->nome }}</span>
                  @endif
                </td>
                <td>
                  {{ $rotulo($atendimento->finalizado_em_formatada) }}
                  @if ($atendimento->finalizadoPor)
                    <span class="imp-obs">{{ $atendimento->finalizadoPor->nome }}</span>
                  @endif
                </td>
                <td>{{ $rotulo($atendimento->observacao) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  @endforeach

  {{-- Financeiro --}}
  @if ($financeiro)
    <div class="imp-bloco">
      <table class="imp-tabela imp-titulo-bloco">
        <tr>
          <td><strong>Financeiro</strong></td>
          <td class="imp-situacao">
            {{ (float) $financeiro->valor_aberto <= 0 ? 'Quitado' : 'Em aberto: '.$financeiro->valor_aberto_formatado }}
          </td>
        </tr>
      </table>

      <table class="imp-tabela">
        <tr>
          <td class="imp-rotulo">Valor bruto</td>
          <td>{{ $financeiro->valor_bruto_formatado }}</td>
          <td class="imp-rotulo">Desconto</td>
          <td>
            @if ((float) $financeiro->valor_desconto > 0)
              {{-- Em valor o rótulo repete o número: só a porcentagem acrescenta informação --}}
              - {{ $financeiro->valor_desconto_formatado }}@if ($financeiro->desconto_descricao !== $financeiro->valor_desconto_formatado)
                ({{ $financeiro->desconto_descricao }})@endif
            @else
              —
            @endif
          </td>
          <td class="imp-rotulo">Adicional</td>
          <td>{{ (float) $financeiro->adicional_valor > 0 ? '+ '.$financeiro->valor_adicional_formatado : '—' }}</td>
        </tr>
        <tr>
          <td class="imp-rotulo">Valor total</td>
          <td><strong>{{ $financeiro->valor_total_formatado }}</strong></td>
          <td class="imp-rotulo">Recebido</td>
          <td>{{ $financeiro->valor_recebido_formatado }}</td>
          <td class="imp-rotulo">Em aberto</td>
          <td><strong>{{ $financeiro->valor_aberto_formatado }}</strong></td>
        </tr>
        @if ($financeiro->observacao)
          <tr>
            <td class="imp-rotulo">Observação</td>
            <td colspan="5">{{ $financeiro->observacao }}</td>
          </tr>
        @endif
      </table>

      <table class="imp-tabela">
        <thead>
          <tr>
            <th style="width: 10%;">Parcela</th>
            <th style="width: 14%;">Vencimento</th>
            <th style="width: 14%;" class="imp-direita">Valor</th>
            <th style="width: 14%;" class="imp-direita">Pago</th>
            <th style="width: 14%;" class="imp-direita">Em aberto</th>
            <th style="width: 34%;">Situação</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($financeiro->parcelas->sortBy('numero') as $parcela)
            <tr>
              <td class="imp-centro">{{ $parcela->numero_formatado }}</td>
              <td>{{ $rotulo($parcela->vencimento_formatado) }}</td>
              <td class="imp-direita">{{ $parcela->valor_formatado }}</td>
              <td class="imp-direita">{{ $parcela->valor_pago_formatado }}</td>
              <td class="imp-direita">{{ $parcela->valor_em_aberto_formatado }}</td>
              <td>{{ $parcela->status->label() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      @if ($financeiro->pagamentos->isNotEmpty())
        <table class="imp-tabela">
          <thead>
            <tr>
              <th style="width: 13%;">Recebido em</th>
              <th style="width: 13%;" class="imp-direita">Valor</th>
              <th style="width: 16%;">Forma</th>
              <th style="width: 15%;">ID</th>
              <th style="width: 28%;">Observação</th>
              <th style="width: 15%;">Registrado por</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($financeiro->pagamentos->sortBy('data_pagamento') as $pagamento)
              <tr>
                <td>{{ $rotulo($pagamento->data_formatada) }}</td>
                <td class="imp-direita">{{ $pagamento->valor_formatado }}</td>
                <td>{{ $pagamento->forma_descricao }}</td>
                <td>{{ $pagamento->identificador_label }}</td>
                <td>{{ $rotulo($pagamento->observacao) }}</td>
                <td>{{ $rotulo($pagamento->user?->nome) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  @endif

  {{-- Anotações registradas na prescrição --}}
  <div class="imp-bloco">
    <table class="imp-tabela imp-titulo-bloco">
      <tr><td><strong>Anotações / textos</strong></td></tr>
    </table>

    @if ($prescricao->observacoesRegistradas->isEmpty())
      <table class="imp-tabela">
        <tr><td class="imp-vazio">Nenhuma anotação registrada.</td></tr>
      </table>
    @else
      <table class="imp-tabela">
        <thead>
          <tr>
            <th style="width: 16%;">Data</th>
            <th style="width: 24%;">Autor</th>
            <th>Anotação</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($prescricao->observacoesRegistradas->sortBy('created_at') as $anotacao)
            <tr>
              <td>{{ $rotulo($anotacao->created_at?->format('d/m/Y H:i')) }}</td>
              <td>{{ $rotulo($anotacao->user?->nome) }}</td>
              <td>{{ $anotacao->observacao }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- Anexos --}}
  @if ($prescricao->anexos->isNotEmpty())
    <div class="imp-bloco">
      <table class="imp-tabela imp-titulo-bloco">
        <tr><td><strong>Anexos</strong></td></tr>
      </table>

      <table class="imp-tabela">
        <thead>
          <tr>
            <th>Arquivo</th>
            <th style="width: 14%;" class="imp-centro">Tamanho</th>
            <th style="width: 18%;">Enviado em</th>
            <th style="width: 22%;">Enviado por</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($prescricao->anexos as $anexo)
            <tr>
              <td>{{ $anexo->nome }}</td>
              <td class="imp-centro">{{ $anexo->tamanho_formatado }}</td>
              <td>{{ $rotulo($anexo->created_at?->format('d/m/Y H:i')) }}</td>
              <td>{{ $rotulo($anexo->user?->nome) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  {{-- Histórico: tudo o que aconteceu na prescrição --}}
  @if ($prescricao->logs->isNotEmpty())
    <div class="imp-bloco">
      <table class="imp-tabela imp-titulo-bloco">
        <tr>
          <td><strong>Histórico</strong></td>
          <td class="imp-situacao">{{ $prescricao->logs->count() }} registro(s)</td>
        </tr>
      </table>

      <table class="imp-tabela">
        <thead>
          <tr>
            <th style="width: 14%;">Data</th>
            <th style="width: 16%;">Usuário</th>
            <th style="width: 17%;">Evento</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($prescricao->logs->sortByDesc('created_at') as $log)
            <tr>
              <td>{{ $rotulo($log->created_at?->format('d/m/Y H:i')) }}</td>
              <td>{{ $rotulo($log->user?->nome) }}</td>
              <td>
                {{ $log->acao->label() }}
                @if ($log->semana)
                  <span class="imp-obs">(semana {{ $log->semana->numero }})</span>
                @endif
              </td>
              <td>
                {{ $log->descricao }}

                @foreach ($log->alteracoes as $alteracao)
                  <div class="imp-obs">
                    {{ $alteracao['campo'] }}: {{ $alteracao['de'] }} → {{ $alteracao['para'] }}
                  </div>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <p class="imp-rodape">
    Documento gerado pelo sistema em {{ now()->format('d/m/Y H:i') }} —
    prescrição #{{ $prescricao->id }} ({{ $prescricao->paciente?->nome ?? '—' }}).
  </p>
</div>
