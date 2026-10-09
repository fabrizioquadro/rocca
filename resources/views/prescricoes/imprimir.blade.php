@extends('layouts.app')

@section('title', 'Prescrição #'.$prescricao->id.' — '.($prescricao->paciente?->nome ?? 'cadastro completo'))

@section('content')
  @php
    $financeiro = $prescricao->financeiro;
    $semCancelar = fn ($valor) => filled($valor) ? $valor : '—';

    // Semanas na ordem (o número pode vir como texto do banco)
    $semanas = $prescricao->semanas->sortBy(fn ($semana) => (int) $semana->numero)->values();

    // IDs dos recebimentos que caíram em cada parcela (mesma cascata do service)
    $idsPorParcela = $financeiro
        ? collect(\App\Services\FinanceiroPagamentoService::recebimentosPorParcela(
            $financeiro->pagamentos,
            $financeiro->parcelas
        ))->map(fn ($recebimentos) => collect($recebimentos)
            ->pluck('identificador')
            ->map(fn ($identificador) => filled($identificador) ? $identificador : 'sem ID')
            ->unique()
            ->implode(', ') ?: null)->all()
        : [];
  @endphp

  {{-- Barra de ações da página --}}
  <div class="card mb-4 no-print">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Prescrição #{{ $prescricao->id }}</h4>
        <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
        <span class="text-muted">{{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('prescricoes.imprimir.pdf', $prescricao) }}" class="btn btn-outline-danger">
          <i class="ri-file-pdf-2-line me-1"></i>Gerar PDF
        </a>

        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
          <i class="ri-printer-line me-1"></i>Imprimir
        </button>

        <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>
      </div>
    </div>
  </div>

  @if (session('success') || session('error'))
    <div class="card mb-4 no-print">
      <div class="card-body">
        @if (session('success'))
          <div class="alert alert-success mb-0" role="alert">{{ session('success') }}</div>
        @endif

        @if (session('error'))
          <div class="alert alert-danger mb-0" role="alert">{{ session('error') }}</div>
        @endif
      </div>
    </div>
  @endif

  {{-- 1. Dados da prescrição --}}
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0"><i class="ri-file-list-3-line me-1"></i>Dados da prescrição</h6>

      <button
        type="button"
        class="btn btn-sm btn-primary no-print"
        data-bs-toggle="modal"
        data-bs-target="#modal-editar-prescricao">
        <i class="ri-edit-line me-1"></i>Editar dados
      </button>
    </div>

    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <small class="text-muted d-block">Paciente</small>
          <span class="fw-semibold">{{ $semCancelar($prescricao->paciente?->nome) }}</span>
          <small class="text-body-secondary d-block">
            @if ($prescricao->paciente?->nascimento)
              Nasc. {{ $prescricao->paciente->nascimento_formatado }}
              @if ($prescricao->paciente->idade !== null)
                · {{ $prescricao->paciente->idade }} anos
              @endif
            @endif
            @if ($prescricao->paciente?->cpf_formatado)
              · CPF {{ $prescricao->paciente->cpf_formatado }}
            @endif
          </small>
          @if ($prescricao->paciente?->celular_formatado || $prescricao->paciente?->telefone_formatado)
            <small class="text-body-secondary d-block">
              {{ $prescricao->paciente->celular_formatado ?? $prescricao->paciente->telefone_formatado }}
            </small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Médico</small>
          <span class="fw-semibold">{{ $semCancelar($prescricao->medico_nome) }}</span>
          @if ($prescricao->medico_id)
            <small class="text-body-secondary d-block">cód. {{ $prescricao->medico_id }}</small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Clínica</small>
          <span class="fw-semibold">{{ $semCancelar($prescricao->clinica?->nome) }}</span>
          @if ($prescricao->clinica?->cnpj)
            <small class="text-body-secondary d-block">CNPJ {{ $prescricao->clinica->cnpj_formatado }}</small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Tipo de atendimento</small>
          <span class="fw-semibold">{{ $semCancelar($prescricao->tipo_atendimento?->label()) }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Agendamento</small>
          <span class="fw-semibold">{{ $semCancelar($prescricao->agendamento) }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Cadastro</small>
          <span class="fw-semibold">{{ $prescricao->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
          <small class="text-body-secondary d-block">{{ $semCancelar($prescricao->user?->nome) }}</small>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Semanas</small>
          <span class="fw-semibold">
            {{ $prescricao->semanas_concluidas }}/{{ $prescricao->quantidade_semanas }} concluída(s)
          </span>
          @if ($prescricao->dias_de_atraso > 0)
            <span class="badge bg-label-danger ms-1">{{ $prescricao->dias_de_atraso }} dia(s) em atraso</span>
          @endif
        </div>

        @if ($prescricao->observacoes)
          <div class="col-12">
            <small class="text-muted d-block">Observações</small>
            <span style="white-space: pre-line;">{{ $prescricao->observacoes }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- 2. Financeiro: parcelas e recebimentos --}}
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0"><i class="ri-money-dollar-circle-line me-1"></i>Financeiro</h6>

      @if ($financeiro)
        <div class="d-flex flex-wrap align-items-center gap-3">
          <span class="text-muted small">Total: <strong>{{ $financeiro->valor_total_formatado }}</strong></span>
          <span class="text-muted small">Pago: <strong>{{ $financeiro->valor_pago_formatado }}</strong></span>
          <span class="text-muted small">
            Em aberto:
            <strong class="{{ (float) $financeiro->valor_aberto > 0 ? 'text-danger' : 'text-success' }}">
              {{ $financeiro->valor_aberto_formatado }}
            </strong>
          </span>
        </div>
      @endif
    </div>

    <div class="card-body">
      @if (! $financeiro)
        <p class="text-muted mb-0">Nenhuma parcela gerada (sem semanas com aplicação).</p>
      @else
        @php
          $temDesconto = (float) $financeiro->valor_desconto > 0;
          $temAdicional = (float) $financeiro->adicional_valor > 0;
          $temAjustes = $temDesconto || $temAdicional;
        @endphp

        @if ($financeiro->observacao)
          <p class="text-muted small mb-3">
            <i class="ri-sticky-note-line me-1"></i>{{ $financeiro->observacao }}
          </p>
        @endif

        {{-- Parcelas --}}
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 90px;">Parcela</th>
                <th style="width: 120px;">Vencimento</th>
                <th style="width: 110px;">Semana</th>
                <th style="width: 120px;" class="text-end">Valor bruto</th>
                <th style="width: 120px;" class="text-end {{ $temDesconto ? '' : 'd-none' }}">Desconto</th>
                <th style="width: 120px;" class="text-end {{ $temAdicional ? '' : 'd-none' }}">Adicional</th>
                <th style="width: 120px;" class="text-end">Valor</th>
                <th style="width: 120px;" class="text-end">Pago</th>
                <th style="width: 120px;" class="text-end">Aberto</th>
                <th style="min-width: 130px;">ID do recebimento</th>
                <th style="width: 110px;">Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($financeiro->parcelas->sortBy('numero') as $parcela)
                <tr>
                  <td class="fw-semibold">{{ $parcela->numero_formatado }}</td>
                  <td>{{ $parcela->vencimento_formatado ?? '—' }}</td>
                  <td class="text-muted">{{ $parcela->semana ? 'Semana '.$parcela->semana->numero : '—' }}</td>
                  <td class="text-end">{{ $parcela->valor_bruto_formatado }}</td>
                  <td class="text-end text-danger {{ $temDesconto ? '' : 'd-none' }}">
                    {{ (float) $parcela->valor_desconto > 0 ? '- '.$parcela->valor_desconto_formatado : '—' }}
                  </td>
                  <td class="text-end text-success {{ $temAdicional ? '' : 'd-none' }}">
                    {{ (float) $parcela->valor_adicional > 0 ? '+ '.$parcela->valor_adicional_formatado : '—' }}
                  </td>
                  <td class="text-end fw-semibold">{{ $parcela->valor_formatado }}</td>
                  <td class="text-end {{ (float) $parcela->valor_pago > 0 ? 'text-success' : 'text-muted' }}">
                    {{ (float) $parcela->valor_pago > 0 ? $parcela->valor_pago_formatado : '—' }}
                  </td>
                  <td class="text-end {{ $parcela->valor_em_aberto > 0 ? 'text-danger' : 'text-muted' }}">
                    {{ $parcela->valor_em_aberto > 0 ? $parcela->valor_em_aberto_formatado : '—' }}
                  </td>
                  <td class="small text-body-secondary">{{ $idsPorParcela[$parcela->id] ?? '—' }}</td>
                  <td class="text-center">
                    <span class="badge {{ $parcela->status->corBadge() }}">{{ $parcela->status->label() }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total</th>
                <th class="text-end">{{ $financeiro->valor_bruto_formatado }}</th>
                <th class="text-end text-danger {{ $temDesconto ? '' : 'd-none' }}">
                  - {{ $financeiro->valor_desconto_formatado }}
                </th>
                <th class="text-end text-success {{ $temAdicional ? '' : 'd-none' }}">
                  + {{ $financeiro->valor_adicional_formatado }}
                </th>
                <th class="text-end">{{ $financeiro->valor_total_formatado }}</th>
                <th class="text-end text-success">{{ $financeiro->valor_pago_formatado }}</th>
                <th class="text-end text-danger">{{ $financeiro->valor_aberto_formatado }}</th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>

        @if ($temAjustes)
          <p class="text-muted small mb-0 mt-2">
            @if ($financeiro->desconto_descricao)
              Desconto: <strong>{{ $financeiro->desconto_descricao }}</strong> ·
            @endif
            @if ($temAdicional)
              Adicional: <strong>{{ $financeiro->valor_adicional_formatado }}</strong> ·
            @endif
            desconto e adicional divididos igualmente entre as parcelas.
          </p>
        @endif

        {{-- Recebimentos: cada um pode ser corrigido no modal --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 mb-3">
          <h6 class="fw-semibold mb-0">Pagamentos recebidos</h6>

          <div class="d-flex flex-wrap gap-2 no-print">
            <button
              type="button"
              class="btn btn-sm btn-primary"
              data-bs-toggle="collapse"
              data-bs-target="#painel-receber">
              <i class="ri-money-dollar-circle-line me-1"></i>Registrar recebimento
            </button>

            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              data-bs-toggle="collapse"
              data-bs-target="#painel-financeiro-imprimir">
              <i class="ri-edit-line me-1"></i>Editar desconto/adicional
            </button>
          </div>
        </div>

        @if ($financeiro->pagamentos->isEmpty())
          <p class="text-muted mb-0">Nenhum pagamento registrado.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 120px;">Data</th>
                  <th style="width: 140px;" class="text-end">Valor</th>
                  <th style="width: 180px;">Forma</th>
                  <th style="width: 150px;">ID</th>
                  <th>Observação</th>
                  <th style="width: 180px;">Registrado por</th>
                  <th style="width: 110px;" class="text-center no-print">Ações</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($financeiro->pagamentos->sortBy('data_pagamento') as $pagamento)
                  <tr>
                    <td>{{ $pagamento->data_formatada }}</td>
                    <td class="text-end fw-semibold">{{ $pagamento->valor_formatado }}</td>
                    <td>
                      @if ($pagamento->forma_pagamento)
                        <span class="badge {{ $pagamento->forma_pagamento->corBadge() }}">
                          {{ $pagamento->forma_descricao }}
                        </span>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td class="text-body-secondary">{{ $pagamento->identificador_label }}</td>
                    <td class="text-muted">{{ $pagamento->observacao ?? '—' }}</td>
                    <td class="text-muted">{{ $pagamento->user?->nome ?? '—' }}</td>
                    <td class="text-center no-print">
                      <div class="d-flex justify-content-center gap-1">
                        <button
                          type="button"
                          class="btn btn-sm btn-icon btn-text-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modal-editar-pagamento-{{ $pagamento->id }}"
                          title="Editar pagamento">
                          <i class="ri-edit-line"></i>
                        </button>

                        <form
                          method="POST"
                          action="{{ route('prescricoes.pagamentos.destroy', [$prescricao, $pagamento]) }}"
                          data-confirmar="Remover este pagamento? As parcelas serão recalculadas.">
                          @csrf
                          @method('DELETE')
                          <input type="hidden" name="origem" value="imprimir" />
                          <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                            <i class="ri-delete-bin-7-line"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <th class="text-end">Recebido</th>
                  <th class="text-end">{{ $financeiro->valor_recebido_formatado }}</th>
                  <th colspan="5"></th>
                </tr>
              </tfoot>
            </table>
          </div>

          @if ($financeiro->valor_nao_alocado > 0)
            <div class="alert alert-info mt-3 mb-0" role="alert">
              <i class="ri-information-line me-1"></i>
              Há <strong>{{ $financeiro->valor_nao_alocado_formatado }}</strong> recebido que não coube em nenhuma
              parcela (crédito a favor do cliente).
            </div>
          @endif
        @endif

        {{-- Registrar recebimento --}}
        <div class="collapse no-print" id="painel-receber">
          <form
            method="POST"
            action="{{ route('prescricoes.pagamentos.store', $prescricao) }}"
            class="border rounded p-4 mt-4">
            @csrf
            <input type="hidden" name="origem" value="imprimir" />

            <div class="row g-3 align-items-end">
              <div class="col-md-2">
                <label class="form-label" for="receber_valor">Valor *</label>
                <input
                  type="text"
                  id="receber_valor"
                  name="valor"
                  class="form-control"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  required />
              </div>

              <div class="col-md-3">
                <label class="form-label" for="receber_forma">Forma de pagamento *</label>
                <select
                  id="receber_forma"
                  name="forma_pagamento"
                  class="form-select"
                  data-forma-receber
                  required>
                  <option value="">Selecione...</option>
                  @foreach ($formasPagamento as $valorForma => $rotuloForma)
                    <option value="{{ $valorForma }}">{{ $rotuloForma }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-2 d-none" data-wrap-parcelas-receber>
                <label class="form-label" for="receber_parcelas">Parcelas *</label>
                <select id="receber_parcelas" name="parcelas" class="form-select" data-parcelas-receber>
                  @foreach ($parcelasDisponiveis as $numeroParcelas)
                    <option value="{{ $numeroParcelas }}">{{ $numeroParcelas }}x</option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-2">
                <label class="form-label" for="receber_data">Data *</label>
                <input
                  type="date"
                  id="receber_data"
                  name="data_pagamento"
                  class="form-control"
                  value="{{ now()->toDateString() }}"
                  required />
              </div>

              <div class="col-md-3">
                <label class="form-label" for="receber_identificador">ID</label>
                <input
                  type="text"
                  id="receber_identificador"
                  name="identificador"
                  class="form-control"
                  maxlength="100"
                  placeholder="NSU/autorização" />
              </div>

              <div class="col-md-12">
                <label class="form-label" for="receber_observacao">Observação</label>
                <input type="text" id="receber_observacao" name="observacao" class="form-control" maxlength="255" />
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
              <small class="text-muted">
                O valor é alocado da 1ª parcela para a última. Quem registra fica no histórico.
              </small>

              <button type="submit" class="btn btn-primary">
                <i class="ri-money-dollar-circle-line me-1"></i>Receber
              </button>
            </div>
          </form>
        </div>

        {{-- Editar desconto/adicional --}}
        <div class="collapse no-print" id="painel-financeiro-imprimir">
          <form
            method="POST"
            action="{{ route('prescricoes.financeiro.update', $prescricao) }}"
            class="border rounded p-4 mt-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="origem" value="imprimir" />

            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label" for="imprimir_desconto">Desconto (R$)</label>
                <input
                  type="text"
                  id="imprimir_desconto"
                  name="desconto_valor"
                  class="form-control"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  value="{{ (float) $financeiro->valor_desconto > 0 ? number_format((float) $financeiro->valor_desconto, 2, ',', '.') : '' }}" />
              </div>

              <div class="col-md-3">
                <label class="form-label" for="imprimir_adicional">Adicional (R$)</label>
                <input
                  type="text"
                  id="imprimir_adicional"
                  name="adicional_valor"
                  class="form-control"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  value="{{ (float) $financeiro->adicional_valor > 0 ? number_format((float) $financeiro->adicional_valor, 2, ',', '.') : '' }}" />
              </div>

              <div class="col-md-4">
                <label class="form-label" for="imprimir_observacao_financeiro">Observação</label>
                <input
                  type="text"
                  id="imprimir_observacao_financeiro"
                  name="observacao"
                  class="form-control"
                  maxlength="255"
                  value="{{ $financeiro->observacao }}" />
              </div>

              <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                  <i class="ri-save-3-line me-1"></i>Salvar
                </button>
              </div>
            </div>

            <small class="text-muted d-block mt-2">
              O desconto é aplicado em valor (R$) e dividido entre as parcelas.
            </small>
          </form>
        </div>
      @endif
    </div>
  </div>

  {{-- 3. Semanas: previsto, aplicado e o histórico de quem aplicou --}}
  <div class="card mb-4">
    <div class="card-header">
      <h6 class="fw-semibold mb-0"><i class="ri-calendar-check-line me-1"></i>Semanas e aplicações</h6>
    </div>

    <div class="card-body">
      @forelse ($semanas as $semana)
        @php
          $itens = $semana->itens;
          $atendimentos = $semana->atendimentos->sortBy('iniciado_em');
          $aplicacoes = $atendimentos
              ->flatMap(fn ($atendimento) => $atendimento->aplicacoes
                  ->sortBy('aplicado_em')
                  ->map(fn ($aplicacao) => ['atendimento' => $atendimento, 'aplicacao' => $aplicacao]))
              ->sortBy(fn ($linha) => $linha['aplicacao']->aplicado_em)
              ->values();
        @endphp

        <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-3' }}">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
              <span class="fw-semibold">Semana {{ $semana->numero }}/{{ $prescricao->quantidade_semanas }}</span>

              @if ($semana->sem_aplicacao)
                <span class="badge bg-dark">Sem aplicação</span>
              @else
                <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>
              @endif

              <span class="text-muted small">
                Prevista: <strong>{{ $semana->data_prevista_formatada ?? '—' }}</strong>
              </span>

              <span class="text-muted small">
                Aplicação: <strong>{{ $semana->data_aplicacao_formatada ?? '—' }}</strong>
              </span>
            </div>

            <div class="d-flex align-items-center gap-3">
              <span class="text-muted small">Valor: <strong>{{ $semana->valor_total_formatado }}</strong></span>

              <a
                href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}"
                class="btn btn-sm btn-outline-primary no-print">
                Abrir semana
              </a>
            </div>
          </div>

          {{-- Chegada, atendimento e quem conduziu --}}
          @if ($atendimentos->isNotEmpty())
            <div class="row g-3 mb-3">
              @foreach ($atendimentos as $atendimento)
                <div class="col-md-6 col-xl-4">
                  <div class="border rounded p-2 h-100 bg-body-secondary bg-opacity-10">
                    <small class="text-muted d-block">
                      Atendimento {{ $loop->iteration }}
                      @if ($atendimento->em_andamento)
                        <span class="badge bg-label-warning ms-1">em andamento</span>
                      @endif
                    </small>
                    <small class="d-block">Chegada: <strong>{{ $atendimento->chegada_em_formatada ?? '—' }}</strong></small>
                    <small class="d-block">
                      Início: <strong>{{ $atendimento->iniciado_em_formatado ?? '—' }}</strong>
                      @if ($atendimento->iniciadoPor)
                        — {{ $atendimento->iniciadoPor->nome }}
                      @endif
                    </small>
                    <small class="d-block">
                      Fim: <strong>{{ $atendimento->finalizado_em_formatada ?? '—' }}</strong>
                      @if ($atendimento->finalizadoPor)
                        — {{ $atendimento->finalizadoPor->nome }}
                      @endif
                    </small>
                    @if ($atendimento->observacao)
                      <small class="d-block text-body-secondary" style="white-space: pre-line;">
                        {{ $atendimento->observacao }}
                      </small>
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif

          {{-- Itens previstos --}}
          @if ($itens->isEmpty())
            <p class="text-muted small mb-0">Nenhum item nesta semana.</p>
          @else
            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Item</th>
                    <th style="width: 140px;" class="text-center">Quantidade</th>
                    <th style="width: 130px;" class="text-end">Valor unit.</th>
                    <th style="width: 130px;" class="text-end">Valor total</th>
                    <th style="width: 130px;">Situação</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($itens as $item)
                    <tr>
                      <td>
                        <span class="fw-semibold">{{ $item->nome }}</span>
                        <small class="text-body-secondary d-block">
                          {{ $item->tipo === 'combo' ? 'Combo' : 'Medicamento' }}
                        </small>
                        @if ($item->tipo === 'combo' && $item->combo)
                          <small class="text-body-secondary d-block">
                            {{ $item->combo->itens->map(fn ($componente) => $componente->medicamento?->nome)->filter()->implode(' · ') }}
                          </small>
                        @endif
                      </td>
                      <td class="text-center">{{ $item->quantidade_formatada }}</td>
                      <td class="text-end">{{ $item->valor_formatado }}</td>
                      <td class="text-end fw-semibold">{{ $item->valor_total_formatado }}</td>
                      <td>
                        @if ($item->gera_aplicacao)
                          <span class="badge {{ $item->status->corBadge() }}">{{ $item->status->label() }}</span>
                        @else
                          <span class="badge bg-label-secondary">Não se aplica</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-end">Total da semana</th>
                    <th class="text-end">{{ $semana->valor_total_formatado }}</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          @endif

          {{-- Histórico da aplicação: quem aplicou, horário, lote, código e vasilhame --}}
          @if ($aplicacoes->isNotEmpty())
            <div class="table-responsive mt-3">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 140px;">Aplicado em</th>
                    <th style="min-width: 190px;">Medicamento aplicado</th>
                    <th style="width: 110px;" class="text-center">Quantidade</th>
                    <th style="width: 130px;">Lote</th>
                    <th style="width: 130px;">Código de barras</th>
                    <th style="width: 110px;">Vencimento</th>
                    <th style="width: 160px;">Quem aplicou</th>
                    <th style="min-width: 160px;">Vasilhame</th>
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
                          <small class="text-body-secondary d-block">do combo {{ $aplicacao->item->nome }}</small>
                        @endif
                      </td>
                      <td class="text-center">
                        {{ $aplicacao->quantidade === null ? '—' : $aplicacao->quantidade_com_unidade }}
                      </td>
                      <td>{{ $aplicacao->lote ?? '—' }}</td>
                      <td class="font-monospace">{{ $aplicacao->codigo_barras ?? '—' }}</td>
                      <td>
                        {{ $aplicacao->vencimento_formatado ?? '—' }}
                        @if ($aplicacao->lote_vencido)
                          <span class="badge bg-label-danger ms-1">vencido</span>
                        @endif
                      </td>
                      <td>{{ $aplicacao->user?->nome ?? '—' }}</td>
                      <td class="small text-body-secondary">
                        {{ $aplicacao->vasilhameAberto?->descricao_abertura ?? '—' }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      @empty
        <p class="text-muted mb-0">Nenhuma semana cadastrada.</p>
      @endforelse
    </div>
  </div>

  {{-- 4. Anotações --}}
  <div class="card mb-4">
    <div class="card-header">
      <h6 class="fw-semibold mb-0"><i class="ri-sticky-note-line me-1"></i>Anotações / textos</h6>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('prescricoes.observacoes.store', $prescricao) }}" class="mb-4 no-print">
        @csrf
        <input type="hidden" name="origem" value="imprimir" />

        <label class="form-label" for="nova_anotacao">Inserir anotação</label>
        <div class="d-flex gap-2">
          <textarea
            id="nova_anotacao"
            name="observacao"
            class="form-control @error('observacao') is-invalid @enderror"
            rows="2"
            maxlength="2000"
            placeholder="Escreva a anotação sobre esta prescrição">{{ old('observacao') }}</textarea>

          <button type="submit" class="btn btn-primary align-self-end">
            <i class="ri-add-line me-1"></i>Anotar
          </button>
        </div>
        @error('observacao')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </form>

      @forelse ($prescricao->observacoesRegistradas->sortByDesc('created_at') as $anotacao)
        <div class="border rounded p-3 mb-2">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold">
              <i class="ri-user-3-line me-1"></i>{{ $anotacao->user?->nome ?? 'usuário removido' }}
            </span>
            <small class="text-muted">
              <i class="ri-time-line me-1"></i>{{ $anotacao->criada_em_formatada }}
            </small>
          </div>
          <div class="mt-1" style="white-space: pre-line;">{{ $anotacao->observacao }}</div>
        </div>
      @empty
        <p class="text-muted mb-0">Nenhuma anotação registrada nesta prescrição.</p>
      @endforelse
    </div>
  </div>

  {{-- 5. Histórico (logs) --}}
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0"><i class="ri-history-line me-1"></i>Histórico da prescrição</h6>
      <span class="text-muted small">Total: {{ $prescricao->logs->count() }} registro(s)</span>
    </div>

    <div class="card-body">
      @forelse ($prescricao->logs as $log)
        <div class="border rounded p-3 mb-2">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span class="badge {{ $log->acao->corBadge() }}">
                <i class="{{ $log->acao->icone() }} me-1"></i>{{ $log->acao->label() }}
              </span>

              @if ($log->semana)
                <span class="badge bg-label-dark">Semana {{ $log->semana->numero }}</span>
              @endif

              <span>{{ $log->descricao }}</span>
            </div>

            <small class="text-muted">
              <i class="ri-user-3-line me-1"></i>{{ $log->user?->nome ?? 'usuário removido' }}
              · <i class="ri-time-line me-1"></i>{{ $log->criado_em_formatado }}
            </small>
          </div>

          @if ($log->alteracoes)
            <ul class="list-unstyled small mb-0 mt-2 ps-1">
              @foreach ($log->alteracoes as $alteracao)
                <li>
                  <span class="fw-semibold">{{ $alteracao['campo'] }}:</span>
                  <span class="text-danger text-decoration-line-through">{{ $alteracao['de'] }}</span>
                  <i class="ri-arrow-right-line mx-1"></i>
                  <span class="text-success fw-semibold">{{ $alteracao['para'] }}</span>
                </li>
              @endforeach
            </ul>
          @endif

          @if ($log->detalhes)
            <ul class="list-unstyled small text-body-secondary mb-0 mt-2 ps-1">
              @foreach ($log->detalhes as $rotulo => $valor)
                <li>
                  <span class="fw-semibold">{{ $rotulo }}:</span>
                  <span style="white-space: pre-line;">{{ $valor }}</span>
                </li>
              @endforeach
            </ul>
          @endif
        </div>
      @empty
        <p class="text-muted mb-0">Nenhum registro no histórico desta prescrição.</p>
      @endforelse
    </div>
  </div>

  @include('prescricoes._modal-editar-prescricao')

  @if ($financeiro)
    @include('prescricoes._modal-pagamento')
  @endif
@endsection

@push('styles')
  <style>
    /* Impressão: só o conteúdo, sem menu, botões, formulários e modais */
    @media print {
      .layout-menu,
      .layout-overlay,
      .layout-navbar,
      .content-footer,
      .no-print,
      .modal {
        display: none !important;
      }

      .card,
      .card-body,
      .card-header {
        border: 0 !important;
        box-shadow: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }

      body {
        font-size: 10pt;
      }

      @page {
        size: A4 portrait;
        margin: 12mm;
      }
    }
  </style>
@endpush

@push('scripts')
  @include('partials.crud-scripts')

  <script>
    window.prescricaoMedicosUrl = '{{ route('prescricoes.medicos') }}';

    document.addEventListener('DOMContentLoaded', function () {
      const formasComParcelas = @json($formasComParcelas);

      // Parcelas só nas formas que parcelam (recebimento e edição do pagamento)
      const ligarParcelas = (forma, wrap, parcelas) => {
        if (! forma || ! wrap || ! parcelas) return;

        const atualizar = () => {
          const exige = formasComParcelas.indexOf(forma.value) >= 0;

          wrap.classList.toggle('d-none', !exige);
          parcelas.disabled = !exige;
          parcelas.required = exige;

          if (! exige) parcelas.value = '1';
        };

        forma.addEventListener('change', atualizar);
        atualizar();
      };

      ligarParcelas(
        document.querySelector('[data-forma-receber]'),
        document.querySelector('[data-wrap-parcelas-receber]'),
        document.querySelector('[data-parcelas-receber]')
      );

      document.querySelectorAll('[data-pagamento-form]').forEach(function (modal) {
        ligarParcelas(
          modal.querySelector('[data-forma-pagamento]'),
          modal.querySelector('[data-wrap-parcelas-modal]'),
          modal.querySelector('[data-parcelas-pagamento]')
        );
      });

      // Médico: a lista da Feegow é buscada só quando o modal abre (a página não
      // depende da Feegow para carregar)
      const modalDados = document.getElementById('modal-editar-prescricao');

      if (modalDados) {
        const select = modalDados.querySelector('[data-medico-select]');
        const aviso = modalDados.querySelector('[data-medico-aviso]');
        const campoNome = document.getElementById('modal_medico_nome');
        let carregado = false;

        const atualizarNome = () => {
          const opcao = select.options[select.selectedIndex];

          campoNome.value = opcao ? (opcao.getAttribute('data-nome') || '') : '';
        };

        select.addEventListener('change', atualizarNome);

        modalDados.addEventListener('show.bs.modal', async function () {
          if (carregado) return;

          carregado = true;

          try {
            const resposta = await fetch(window.prescricaoMedicosUrl, { headers: { 'Accept': 'application/json' } });
            const dados = await resposta.json();
            const lista = dados.medicos || [];
            const existentes = Array.from(select.options).map((o) => o.value);

            lista.forEach(function (medico) {
              if (existentes.indexOf(String(medico.id)) >= 0) return;

              const opcao = document.createElement('option');

              opcao.value = medico.id;
              opcao.textContent = medico.conselho ? medico.nome + ' (' + medico.conselho + ')' : medico.nome;
              opcao.setAttribute('data-nome', medico.nome);

              select.appendChild(opcao);
            });

            aviso.textContent = 'Médicos carregados da Feegow (' + lista.length + '). Use "Sem médico" se não houver.';
          } catch (e) {
            aviso.textContent = 'Não foi possível carregar a lista da Feegow — o médico atual é mantido.';
          }
        });
      }
    });
  </script>
@endpush
