@extends('layouts.app')

@section('title', 'Prescrição #'.$prescricao->id)

@section('content')
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Prescrição #{{ $prescricao->id }}</h4>
        <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
        <span class="text-muted">{{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ route('prescricoes.index') }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>
      </div>
    </div>

    @if (session('success') || session('error') || $errors->any())
      {{-- pb-0 + alertas mb-0 deixavam a mensagem colada na borda do card --}}
      <div class="card-body d-flex flex-column gap-2">
        @if (session('success'))
          <div class="alert alert-success mb-0" role="alert">
            {{ session('success') }}
          </div>
        @endif

        @if (session('error'))
          <div class="alert alert-danger mb-0" role="alert">
            {{ session('error') }}
          </div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger mb-0" role="alert">
            @foreach ($errors->all() as $error)
              {{ $error }}<br />
            @endforeach
          </div>
        @endif
      </div>
    @endif
  </div>

  <div class="card" id="abas-prescricao">
    @php $abaAtiva = in_array(request('aba'), ['resumo', 'semanas', 'financeiro', 'observacoes', 'logs'], true) ? request('aba') : 'resumo'; @endphp

    <div class="card-header p-0 pb-5">
      <div class="nav-align-top">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
            <button
              type="button"
              class="nav-link {{ $abaAtiva === 'resumo' ? 'active' : '' }}"
              role="tab"
              data-bs-toggle="tab"
              data-bs-target="#aba-resumo"
              aria-controls="aba-resumo"
              aria-selected="{{ $abaAtiva === 'resumo' ? 'true' : 'false' }}">
              <i class="ri-file-text-line me-1"></i>Resumo
            </button>
          </li>
          <li class="nav-item">
            <button
              type="button"
              class="nav-link {{ $abaAtiva === 'semanas' ? 'active' : '' }}"
              role="tab"
              data-bs-toggle="tab"
              data-bs-target="#aba-semanas"
              aria-controls="aba-semanas"
              aria-selected="{{ $abaAtiva === 'semanas' ? 'true' : 'false' }}">
              <i class="ri-calendar-check-line me-1"></i>Semanas
            </button>
          </li>
          <li class="nav-item">
            <button
              type="button"
              class="nav-link {{ $abaAtiva === 'financeiro' ? 'active' : '' }}"
              role="tab"
              data-bs-toggle="tab"
              data-bs-target="#aba-financeiro"
              aria-controls="aba-financeiro"
              aria-selected="{{ $abaAtiva === 'financeiro' ? 'true' : 'false' }}">
              <i class="ri-money-dollar-circle-line me-1"></i>Financeiro
            </button>
          </li>

          <li class="nav-item">
            <button
              type="button"
              class="nav-link {{ $abaAtiva === 'observacoes' ? 'active' : '' }}"
              role="tab"
              data-bs-toggle="tab"
              data-bs-target="#aba-observacoes"
              aria-controls="aba-observacoes"
              aria-selected="{{ $abaAtiva === 'observacoes' ? 'true' : 'false' }}">
              <i class="ri-chat-1-line me-1"></i>Observações
            </button>
          </li>

          <li class="nav-item">
            <button
              type="button"
              class="nav-link {{ $abaAtiva === 'logs' ? 'active' : '' }}"
              role="tab"
              data-bs-toggle="tab"
              data-bs-target="#aba-logs"
              aria-controls="aba-logs"
              aria-selected="{{ $abaAtiva === 'logs' ? 'true' : 'false' }}">
              <i class="ri-history-line me-1"></i>Logs
            </button>
          </li>
        </ul>
      </div>
    </div>

    <div class="card-body">
      <div class="tab-content p-0">
        <div class="tab-pane fade {{ $abaAtiva === 'resumo' ? 'show active' : '' }}" id="aba-resumo" role="tabpanel">
          <div class="row g-3">
        <div class="col-md-4">
          <small class="text-muted d-block">Paciente</small>
          <span class="fw-semibold">{{ $prescricao->paciente?->nome ?? '—' }}</span>

          @if ($prescricao->paciente?->nascimento)
            <small class="text-body-secondary d-block">
              Nasc. {{ $prescricao->paciente->nascimento_formatado }}
              @if ($prescricao->paciente->idade !== null)
                · {{ $prescricao->paciente->idade }} anos
              @endif
            </small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Médico</small>
          <span class="fw-semibold">{{ $prescricao->medico_nome ?? '—' }}</span>
          @if ($prescricao->medico_id)
            <small class="text-body-secondary d-block">cód. {{ $prescricao->medico_id }}</small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Clínica</small>
          <span class="fw-semibold">{{ $prescricao->clinica?->nome ?? '—' }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Tipo de atendimento</small>
          <span class="fw-semibold">{{ $prescricao->tipo_atendimento?->label() ?? '—' }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Agendamento</small>
          <span class="fw-semibold">{{ $prescricao->agendamento ?? '—' }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Cadastrada por</small>
          <span class="fw-semibold">{{ $prescricao->user?->nome ?? '—' }}</span>
          <small class="text-body-secondary d-block">{{ $prescricao->created_at?->format('d/m/Y H:i') }}</small>
        </div>

        @if ($prescricao->observacoes)
          <div class="col-12">
            <small class="text-muted d-block">Observações</small>
            <span>{{ $prescricao->observacoes }}</span>
          </div>
        @endif
          </div>

          <hr class="my-4" />

          <div class="row g-3">
            <div class="col-md-4">
              <div class="border rounded p-4 h-100">
                <small class="text-muted d-block">Valor total</small>
                <span class="fw-semibold">
                  {{ $prescricao->financeiro?->valor_total_formatado ?? $prescricao->valor_total_formatado }}
                </span>

                @if ($prescricao->financeiro?->tem_ajustes)
                  <small class="text-body-secondary d-block">
                    bruto {{ $prescricao->financeiro->valor_bruto_formatado }}
                    @if ($prescricao->financeiro->valor_desconto > 0)
                      · desconto {{ $prescricao->financeiro->valor_desconto_formatado }}
                    @endif
                    @if ((float) $prescricao->financeiro->adicional_valor > 0)
                      · adicional {{ $prescricao->financeiro->valor_adicional_formatado }}
                    @endif
                  </small>
                @endif
              </div>
            </div>

            <div class="col-md-4">
              <div class="border rounded p-4 h-100">
                <small class="text-muted d-block">Procedimento</small>
                <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
                <small class="text-body-secondary d-block">
                  {{ $prescricao->quantidade_semanas }} semana(s) ·
                  {{ $prescricao->semanas_aplicadas }} de {{ $prescricao->semanas_com_aplicacao }} aplicadas
                </small>
              </div>
            </div>

            <div class="col-md-4">
              <div class="border rounded p-4 h-100">
                <small class="text-muted d-block">Financeiro</small>

                @if ($prescricao->financeiro)
                  <span class="fw-semibold">{{ $prescricao->financeiro->valor_total_formatado }}</span>
                  <small class="text-body-secondary d-block">
                    {{ $prescricao->financeiro->quantidade_parcelas }} parcela(s) ·
                    em aberto {{ $prescricao->financeiro->valor_aberto_formatado }}
                  </small>
                @else
                  <span class="text-muted">Sem parcelas</span>
                @endif
              </div>
            </div>
          </div>

          <hr class="my-4" />

          <h6 class="fw-semibold mb-3">Anexos</h6>

          <ul class="list-group">
            @forelse ($prescricao->anexos as $anexo)
              <li class="list-group-item d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <i class="{{ $anexo->eh_imagem ? 'ri-image-line' : 'ri-file-text-line' }} ri-20px"></i>

                  <a href="{{ $anexo->url }}" target="_blank" rel="noopener">{{ $anexo->nome }}</a>

                  <small class="text-muted">{{ $anexo->tamanho_formatado }}</small>
                  <small class="text-body-secondary">
                    {{ $anexo->criado_em_formatado }}{{ $anexo->user ? ' · '.$anexo->user->nome : '' }}
                  </small>
                </div>

                <form
                  method="POST"
                  action="{{ route('prescricoes.anexos.destroy', [$prescricao, $anexo]) }}"
                  data-confirmar="Remover este anexo?">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                    <i class="ri-delete-bin-7-line"></i>
                  </button>
                </form>
              </li>
            @empty
              <li class="list-group-item text-muted">Nenhum anexo enviado.</li>
            @endforelse
          </ul>
        </div>

        <div class="tab-pane fade {{ $abaAtiva === 'semanas' ? 'show active' : '' }}" id="aba-semanas" role="tabpanel">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h6 class="fw-semibold mb-0">Semanas</h6>
            <span class="text-muted small">
              Total das semanas: <strong>{{ $prescricao->valor_total_formatado }}</strong>
            </span>
          </div>

          @if ($prescricao->semanas->isEmpty())
            <p class="text-muted mb-0">Nenhuma semana cadastrada.</p>
          @else
            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 60px;" class="text-center"></th>
                    <th style="width: 90px;">Semana</th>
                    <th style="width: 150px;">Data prevista</th>
                    <th>Situação</th>
                    <th style="width: 150px;" class="text-end">Valor</th>
                    <th style="width: 180px;">Pagamento</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($prescricao->semanas as $semana)
                    @php $parcela = $semana->parcelas->first(); @endphp

                    <tr>
                      <td class="text-center">
                        <div class="dropdown">
                          <button
                            type="button"
                            class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                            data-bs-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="Ações">
                            <i class="ri-more-2-fill"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}">
                              <i class="ri-arrow-right-circle-line me-2"></i>Acessar
                            </a>

                            {{-- Semana paga e ainda fora do fluxo (agendada ou com aplicação
                                 parcial): entra na fila de aplicação direto daqui --}}
                            @if ($semana->pode_enviar_para_fila)
                              <form
                                method="POST"
                                action="{{ route('prescricoes.semanas.fila', [$prescricao, $semana]) }}"
                                data-confirmar="Enviar a semana {{ $semana->numero }} para a fila de aplicação?">
                                @csrf
                                <input type="hidden" name="origem" value="semanas">
                                <button type="submit" class="dropdown-item text-info">
                                  <i class="ri-play-list-add-line me-2"></i>Enviar para Fila de Aplicação
                                </button>
                              </form>
                            @elseif ($semana->motivo_bloqueio_envio_fila)
                              {{-- Aplicação sequencial: mostra o motivo em vez de sumir com a opção --}}
                              <span class="dropdown-item disabled" title="{{ $semana->motivo_bloqueio_envio_fila }}">
                                <i class="ri-lock-line me-2"></i>Enviar para Fila de Aplicação
                              </span>
                            @endif

                            {{-- Paciente não compareceu: a semana volta para o agendamento --}}
                            @if ($semana->pode_voltar_para_agendada)
                              <form
                                method="POST"
                                action="{{ route('prescricoes.semanas.devolver', [$prescricao, $semana]) }}"
                                data-confirmar="Devolver a semana {{ $semana->numero }} para Agendada? A liberação sem pagamento será desfeita.">
                                @csrf
                                <button type="submit" class="dropdown-item text-warning">
                                  <i class="ri-arrow-go-back-line me-2"></i>Devolver para Agendada
                                </button>
                              </form>
                            @endif

                            @if ($semana->pode_ser_alterada)
                              <a class="dropdown-item" href="{{ route('prescricoes.semanas.edit', [$prescricao, $semana]) }}">
                                <i class="ri-edit-line me-2"></i>Editar
                              </a>
                            @else
                              {{-- Semana que já entrou no fluxo de aplicação não pode ser alterada --}}
                              <span class="dropdown-item disabled" title="{{ $semana->motivo_bloqueio }}">
                                <i class="ri-lock-line me-2"></i>Editar
                              </span>
                            @endif

                            @if ($semana->pode_ser_alterada)
                              <form
                                method="POST"
                                action="{{ route('prescricoes.semanas.destroy', [$prescricao, $semana]) }}"
                                data-confirmar="Excluir a semana {{ $semana->numero }}? Os itens e a parcela vinculada também serão excluídos.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger">
                                  <i class="ri-delete-bin-7-line me-2"></i>Excluir
                                </button>
                              </form>
                            @else
                              <span class="dropdown-item disabled text-danger" title="{{ $semana->motivo_bloqueio }}">
                                <i class="ri-lock-line me-2"></i>Excluir
                              </span>
                            @endif
                          </div>
                        </div>
                      </td>
                      <td class="fw-semibold">{{ $semana->numero }}/{{ $prescricao->quantidade_semanas }}</td>
                      <td>{{ $semana->data_prevista_formatada ?? '—' }}</td>
                      <td>
                        {{-- Semana sem aplicação mostra apenas o aviso, sem o status --}}
                        @if ($semana->sem_aplicacao)
                          <span class="badge bg-dark">Sem aplicação</span>
                        @else
                          <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>
                        @endif
                      </td>
                      <td class="text-end">{{ $semana->valor_total_formatado }}</td>
                      <td>
                        @if ($parcela)
                          <span class="badge {{ $parcela->status->corBadge() }}">{{ $parcela->status->label() }}</span>

                          @if ($parcela->vencimento)
                            <small class="text-body-secondary d-block">venc. {{ $parcela->vencimento_formatado }}</small>
                          @endif
                        @else
                          <span class="text-muted">—</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="4" class="text-end">Total das semanas</th>
                    <th class="text-end">{{ $prescricao->valor_total_formatado }}</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          @endif
        </div>

        <div class="tab-pane fade {{ $abaAtiva === 'financeiro' ? 'show active' : '' }}" id="aba-financeiro" role="tabpanel">
      @if (! $prescricao->financeiro)
        <p class="text-muted mb-0">Nenhuma parcela gerada (sem semanas com aplicação).</p>
      @else
        @php
          $financeiro = $prescricao->financeiro;
          $temDesconto = $financeiro->valor_desconto > 0;
          $temAdicional = (float) $financeiro->adicional_valor > 0;
          $temAjustes = $temDesconto || $temAdicional;
        @endphp

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
          <h6 class="fw-semibold mb-0">Financeiro</h6>

          <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="text-muted small">Total: <strong>{{ $financeiro->valor_total_formatado }}</strong></span>
            <span class="text-muted small">Pago: <strong>{{ $financeiro->valor_pago_formatado }}</strong></span>
            <span class="text-muted small">Em aberto: <strong>{{ $financeiro->valor_aberto_formatado }}</strong></span>

            <button
              type="button"
              class="btn btn-sm btn-outline-primary"
              data-bs-toggle="collapse"
              data-bs-target="#painel-financeiro"
              aria-expanded="false"
              aria-controls="painel-financeiro">
              <i class="ri-edit-line me-1"></i>Editar desconto/adicional
            </button>
          </div>
        </div>

        @if ($financeiro->observacao)
          <p class="text-muted small mb-3">
            <i class="ri-sticky-note-line me-1"></i>{{ $financeiro->observacao }}
          </p>
        @endif

        {{-- Ajustes do financeiro: na edição o desconto é sempre em valor (R$) --}}
        <div
          class="collapse {{ $errors->has('desconto_valor') || $errors->has('adicional_valor') || $errors->has('observacao') ? 'show' : '' }}"
          id="painel-financeiro">
          <form
            method="POST"
            action="{{ route('prescricoes.financeiro.update', $prescricao) }}"
            class="border rounded p-4 mb-4">
            @csrf
            @method('PUT')

            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label" for="financeiro_desconto">Desconto (R$)</label>
                <input
                  type="text"
                  id="financeiro_desconto"
                  name="desconto_valor"
                  class="form-control @error('desconto_valor') is-invalid @enderror"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  value="{{ old('desconto_valor', (float) $financeiro->valor_desconto > 0 ? number_format((float) $financeiro->valor_desconto, 2, ',', '.') : '') }}" />
                @error('desconto_valor')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-3">
                <label class="form-label" for="financeiro_adicional">Adicional (R$)</label>
                <input
                  type="text"
                  id="financeiro_adicional"
                  name="adicional_valor"
                  class="form-control @error('adicional_valor') is-invalid @enderror"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  value="{{ old('adicional_valor', (float) $financeiro->adicional_valor > 0 ? number_format((float) $financeiro->adicional_valor, 2, ',', '.') : '') }}" />
                @error('adicional_valor')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label" for="financeiro_observacao">Observação</label>
                <input
                  type="text"
                  id="financeiro_observacao"
                  name="observacao"
                  class="form-control @error('observacao') is-invalid @enderror"
                  maxlength="1000"
                  placeholder="Ex.: desconto autorizado pela gerência"
                  value="{{ old('observacao', $financeiro->observacao) }}" />
                @error('observacao')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                  <i class="ri-save-3-line me-1"></i>Salvar
                </button>
              </div>
            </div>

            <small class="text-muted d-block mt-2">
              O desconto na edição é sempre em <strong>valor (R$)</strong>. As parcelas e os recebimentos
              são recalculados, com o desconto e o adicional divididos igualmente entre elas.
            </small>
          </form>
        </div>

        {{-- Efetuar pagamento: o valor entra da 1ª parcela para a última --}}
        <form
          method="POST"
          action="{{ route('prescricoes.pagamentos.store', $prescricao) }}"
          class="border rounded p-4 mb-4">
          @csrf

          <div class="row g-3 align-items-end">
            <div class="col-md-2">
              <label class="form-label" for="pagamento_valor">Valor *</label>
              <input
                type="text"
                id="pagamento_valor"
                name="valor"
                class="form-control @error('valor') is-invalid @enderror"
                data-moeda
                inputmode="numeric"
                placeholder="R$ 0,00"
                required />
              @error('valor')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-3">
              <label class="form-label" for="pagamento_forma">Forma de pagamento *</label>
              <select
                id="pagamento_forma"
                name="forma_pagamento"
                class="form-select @error('forma_pagamento') is-invalid @enderror"
                required>
                <option value="">Selecione...</option>
                @foreach ($formasPagamento as $valor => $rotulo)
                  <option value="{{ $valor }}" @selected(old('forma_pagamento') === $valor)>{{ $rotulo }}</option>
                @endforeach
              </select>
              @error('forma_pagamento')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            {{-- Parcelas do cartão/link: aparece só para as formas que parcelam --}}
            <div
              class="col-md-2 {{ in_array(old('forma_pagamento'), $formasComParcelas, true) ? '' : 'd-none' }}"
              data-wrap-parcelas>
              <label class="form-label" for="pagamento_parcelas">Parcelas *</label>
              <select
                id="pagamento_parcelas"
                name="parcelas"
                class="form-select @error('parcelas') is-invalid @enderror">
                @foreach ($parcelasDisponiveis as $numeroParcelas)
                  <option value="{{ $numeroParcelas }}" @selected((int) old('parcelas', 1) === $numeroParcelas)>
                    {{ $numeroParcelas }}x
                  </option>
                @endforeach
              </select>
              @error('parcelas')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-2">
              <label class="form-label" for="pagamento_data">Data *</label>
              <input
                type="date"
                id="pagamento_data"
                name="data_pagamento"
                class="form-control @error('data_pagamento') is-invalid @enderror"
                value="{{ old('data_pagamento', now()->toDateString()) }}"
                required />
              @error('data_pagamento')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-3">
              <label class="form-label" for="pagamento_observacao">Observação</label>
              <input
                type="text"
                id="pagamento_observacao"
                name="observacao"
                class="form-control"
                maxlength="255"
                placeholder="Ex.: autorização, bandeira..."
                value="{{ old('observacao') }}" />
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <small class="text-muted">
              O valor é alocado sempre da 1ª parcela para a última: a primeira é quitada e o que sobrar vai para a seguinte.
              Parcelas com recebimento parcial ficam como <strong>Parcial</strong>.
            </small>

            <button type="submit" class="btn btn-primary">
              <i class="ri-money-dollar-circle-line me-1"></i>Receber
            </button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 100px;">Parcela</th>
                <th style="width: 130px;">Vencimento</th>
                <th>Semana</th>
                <th style="width: 130px;" class="text-end">Valor bruto</th>
                <th style="width: 130px;" class="text-end {{ $temDesconto ? '' : 'd-none' }}">Desconto</th>
                <th style="width: 130px;" class="text-end {{ $temAdicional ? '' : 'd-none' }}">Adicional</th>
                <th style="width: 130px;" class="text-end">Valor</th>
                <th style="width: 130px;" class="text-end">Pago</th>
                <th style="width: 110px;">Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($financeiro->parcelas as $parcela)
                <tr>
                  <td class="fw-semibold">{{ $parcela->numero_formatado }}</td>
                  <td>{{ $parcela->vencimento_formatado ?? '—' }}</td>
                  <td class="text-muted">
                    {{ $parcela->semana ? 'Semana '.$parcela->semana->numero : '—' }}
                  </td>
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
                  <td>
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
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>

        @if ($temAjustes)
          <div class="d-flex flex-wrap gap-4 mt-3">
            @if ($financeiro->desconto_descricao)
              <span class="text-muted small">
                Desconto: <strong>{{ $financeiro->desconto_descricao }}</strong>
                (não é recalculado ao alterar as semanas — pode ser ajustado em "Editar desconto/adicional")
              </span>
            @endif

            <span class="text-muted small">
              Desconto e adicional divididos igualmente entre as parcelas.
            </span>
          </div>
        @endif

        <h6 class="fw-semibold mb-3 mt-4">Pagamentos recebidos</h6>

        @if ($financeiro->pagamentos->isEmpty())
          <p class="text-muted mb-0">Nenhum pagamento registrado.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 130px;">Data</th>
                  <th style="width: 160px;" class="text-end">Valor</th>
                  <th style="width: 190px;">Forma</th>
                  <th>Observação</th>
                  <th style="width: 190px;">Registrado por</th>
                  <th style="width: 70px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($financeiro->pagamentos as $pagamento)
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
                    <td class="text-muted">{{ $pagamento->observacao ?? '—' }}</td>
                    <td class="text-muted">{{ $pagamento->user?->nome ?? '—' }}</td>
                    <td class="text-center">
                      <form
                        method="POST"
                        action="{{ route('prescricoes.pagamentos.destroy', [$prescricao, $pagamento]) }}"
                        data-confirmar="Remover este pagamento? As parcelas serão recalculadas.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                          <i class="ri-delete-bin-7-line"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <th class="text-end">Recebido</th>
                  <th class="text-end">{{ $financeiro->valor_recebido_formatado }}</th>
                  <th colspan="4"></th>
                </tr>
              </tfoot>
            </table>
          </div>

          @if ($financeiro->valor_nao_alocado > 0)
            <div class="alert alert-info mt-3 mb-0" role="alert">
              <i class="ri-information-line me-1"></i>
              Há <strong>{{ $financeiro->valor_nao_alocado_formatado }}</strong> recebido que não coube em nenhuma parcela
              (crédito a favor do cliente). Ajuste as parcelas ou remova o pagamento.
            </div>
          @endif
        @endif
      @endif
        </div>

        {{-- Observações: cada registro guarda o texto, o autor e a data/hora --}}
        <div class="tab-pane fade {{ $abaAtiva === 'observacoes' ? 'show active' : '' }}" id="aba-observacoes" role="tabpanel">
          <form method="POST" action="{{ route('prescricoes.observacoes.store', $prescricao) }}" class="mb-5">
            @csrf

            <label class="form-label" for="nova_observacao">Nova observação</label>
            <textarea
              id="nova_observacao"
              name="observacao"
              class="form-control @error('observacao') is-invalid @enderror"
              rows="3"
              maxlength="2000"
              placeholder="Escreva a observação sobre esta prescrição">{{ old('observacao') }}</textarea>
            @error('observacao')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
              <small class="text-muted">
                A observação fica registrada com o seu usuário e a data/hora do lançamento.
              </small>

              <button type="submit" class="btn btn-primary">
                <i class="ri-add-line me-1"></i>Registrar observação
              </button>
            </div>
          </form>

          <h6 class="fw-semibold mb-3">Histórico</h6>

          @forelse ($prescricao->observacoesRegistradas as $observacao)
            <div class="border rounded p-3 mb-2">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">
                  <i class="ri-user-3-line me-1"></i>{{ $observacao->user?->nome ?? 'usuário removido' }}
                </span>

                <small class="text-muted">
                  <i class="ri-time-line me-1"></i>{{ $observacao->criada_em_formatada }}
                </small>
              </div>

              <div class="mt-1" style="white-space: pre-line;">{{ $observacao->observacao }}</div>
            </div>
          @empty
            <p class="text-muted mb-0">Nenhuma observação registrada nesta prescrição.</p>
          @endforelse
        </div>

        {{-- Logs: tudo o que aconteceu na prescrição, do mais recente para o mais antigo --}}
        <div class="tab-pane fade {{ $abaAtiva === 'logs' ? 'show active' : '' }}" id="aba-logs" role="tabpanel">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h6 class="fw-semibold mb-0">Histórico da prescrição</h6>
            <span class="text-muted small">Total: {{ $prescricao->logs->count() }} registro(s)</span>
          </div>

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
    </div>
  </div>
@endsection

@push('styles')
  <style>
    /* Respiro interno das abas do acesso à prescrição */
    #abas-prescricao .tab-content.p-0 {
      padding-top: .75rem !important;
    }
  </style>
@endpush

@push('scripts')
  @include('partials.crud-scripts')

  <script>
    // Parcelas do cartão/link só aparecem nas formas que parcelam
    document.addEventListener('DOMContentLoaded', function () {
      const forma = document.getElementById('pagamento_forma');
      const parcelas = document.getElementById('pagamento_parcelas');
      const wrap = document.querySelector('[data-wrap-parcelas]');
      const formasComParcelas = @json($formasComParcelas);

      if (!forma || !parcelas || !wrap) return;

      const atualizar = () => {
        const exige = formasComParcelas.indexOf(forma.value) >= 0;

        wrap.classList.toggle('d-none', !exige);
        parcelas.disabled = !exige;
        parcelas.required = exige;

        if (!exige) parcelas.value = '1';
      };

      forma.addEventListener('change', atualizar);
      atualizar();
    });
  </script>
@endpush
