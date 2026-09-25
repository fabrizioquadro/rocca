@extends('layouts.app')

@section('title', 'Semana '.$semana->numero.'/'.$prescricao->quantidade_semanas)

@section('content')
  @php
    $parcela = $semana->parcelas->first();
    $agendada = $semana->status === \App\Enums\StatusSemana::Agendada;
  @endphp

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">
          Semana {{ $semana->numero }}/{{ $prescricao->quantidade_semanas }}
        </h4>

        @if ($semana->sem_aplicacao)
          <span class="badge bg-dark">Sem aplicação</span>
        @else
          <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>
        @endif

        <span class="text-muted">Prescrição #{{ $prescricao->id }} · {{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <div class="d-flex flex-wrap gap-2">
        {{-- Aplicação da semana: exige parcela paga ou liberação de administrador --}}
        @if ($agendada)
          {{-- Aplicação sequencial: semana anterior pendente bloqueia o envio --}}
          @if (! $semana->pode_ir_para_fila)
            {{-- O title fica no span: botão disabled não dispara hover em alguns navegadores --}}
            <span class="d-inline-block" title="{{ $semana->motivo_bloqueio_fila }}">
              <button type="button" class="btn btn-info" disabled>
                <i class="ri-lock-line me-1"></i>Enviar para Fila de Atendimento
              </button>
            </span>
          @elseif ($parcela?->esta_paga)
            <form
              method="POST"
              action="{{ route('prescricoes.semanas.fila', [$prescricao, $semana]) }}"
              data-confirmar="Enviar a semana {{ $semana->numero }} para a fila de atendimento?">
              @csrf
              <button type="submit" class="btn btn-info">
                <i class="ri-play-list-add-line me-1"></i>Enviar para Fila de Atendimento
              </button>
            </form>
          @else
            <button
              type="button"
              class="btn btn-info"
              data-bs-toggle="collapse"
              data-bs-target="#painel-fila"
              aria-expanded="false"
              aria-controls="painel-fila">
              <i class="ri-play-list-add-line me-1"></i>Enviar para Fila de Atendimento
            </button>
          @endif
        @endif

        {{-- Aplicação: quem iniciou o atendimento na Enfermagem é quem conduz --}}
        @if ($semana->atendimentoAberto)
          @if ($semana->atendimentoAberto->podeSerConduzidoPor(auth()->user()))
            <a href="{{ route('prescricoes.semanas.aplicar.form', [$prescricao, $semana]) }}" class="btn btn-primary">
              <i class="ri-syringe-line me-1"></i>Registrar aplicação
            </a>
          @else
            <button
              type="button"
              class="btn btn-primary"
              disabled
              title="{{ $semana->atendimentoAberto->bloqueio_de_outro_usuario }}">
              <i class="ri-lock-line me-1"></i>Registrar aplicação
            </button>
          @endif
        @elseif ($semana->pode_iniciar_atendimento)
          {{-- Iniciar atendimento cai direto na tela de registro da aplicação --}}
          <form
            method="POST"
            action="{{ route('prescricoes.semanas.atendimento.iniciar', [$prescricao, $semana]) }}"
            data-confirmar="Iniciar o atendimento da semana {{ $semana->numero }}? Você fica como responsável pela aplicação.">
            @csrf
            <button type="submit" class="btn btn-primary">
              <i class="ri-nurse-line me-1"></i>Iniciar atendimento
            </button>
          </form>
        @endif

        {{-- Paciente não compareceu: a semana volta para o agendamento --}}
        @if ($semana->pode_voltar_para_agendada)
          <form
            method="POST"
            action="{{ route('prescricoes.semanas.devolver', [$prescricao, $semana]) }}"
            data-confirmar="Devolver a semana {{ $semana->numero }} para Agendada? A liberação sem pagamento será desfeita e uma nova será exigida para voltar à fila.">
            @csrf
            <button type="submit" class="btn btn-outline-warning">
              <i class="ri-arrow-go-back-line me-1"></i>Devolver para Agendada
            </button>
          </form>
        @endif

        <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>

        @if ($semana->pode_ser_alterada)
          <a href="{{ route('prescricoes.semanas.edit', [$prescricao, $semana]) }}" class="btn btn-primary">
            <i class="ri-edit-line me-1"></i>Editar semana
          </a>
          <form
            method="POST"
            action="{{ route('prescricoes.semanas.destroy', [$prescricao, $semana]) }}"
            data-confirmar="Excluir esta semana? Os itens e a parcela vinculada também serão excluídos.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">
              <i class="ri-delete-bin-7-line me-1"></i>Excluir
            </button>
          </form>
        @else
          {{-- Semana que já entrou no fluxo de aplicação não pode ser alterada --}}
          <button type="button" class="btn btn-primary" disabled title="{{ $semana->motivo_bloqueio }}">
            <i class="ri-lock-line me-1"></i>Editar semana
          </button>
          <button type="button" class="btn btn-outline-danger" disabled title="{{ $semana->motivo_bloqueio }}">
            <i class="ri-lock-line me-1"></i>Excluir
          </button>
        @endif
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

    {{-- Sem parcela paga: liberação com email e senha de um administrador --}}
    @if ($agendada && $semana->pode_ir_para_fila && ! $parcela?->esta_paga)
      <div class="collapse {{ $errors->has('liberacao') || $errors->has('liberacao_email') ? 'show' : '' }}" id="painel-fila">
        <div class="card-body pt-0">
          <div class="border rounded p-4">
            <h6 class="fw-semibold mb-2">
              <i class="ri-lock-unlock-line me-1"></i>Liberação sem pagamento
            </h6>

            <p class="text-muted small mb-3">
              A parcela desta semana
              @if ($parcela)
                ({{ $parcela->valor_formatado }} —
                @if ((float) $parcela->valor_pago > 0)
                  parcialmente paga, em aberto {{ $parcela->valor_em_aberto_formatado }}
                @else
                  ainda não paga
                @endif
                )
              @else
                não existe (sem valor a receber)
              @endif
              . Para enviar a semana para a fila de atendimento é preciso a autorização de um
              administrador — fica registrado quem liberou.
            </p>

            <form method="POST" action="{{ route('prescricoes.semanas.fila', [$prescricao, $semana]) }}">
              @csrf

              <div class="row g-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label" for="liberacao_email">Email do administrador *</label>
                  <input
                    type="email"
                    id="liberacao_email"
                    name="liberacao_email"
                    class="form-control @error('liberacao_email') is-invalid @enderror"
                    value="{{ old('liberacao_email') }}"
                    required />
                  @error('liberacao_email')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-4">
                  <label class="form-label" for="liberacao_senha">Senha *</label>
                  <input
                    type="password"
                    id="liberacao_senha"
                    name="liberacao_senha"
                    class="form-control @error('liberacao') is-invalid @enderror"
                    autocomplete="off"
                    required />
                  @error('liberacao')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-4">
                  <button type="submit" class="btn btn-warning w-100">
                    <i class="ri-shield-keyhole-line me-1"></i>Liberar e enviar para a fila
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endif
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="border rounded p-4 h-100">
            <small class="text-muted d-block">Data prevista</small>
            <span class="fw-semibold">{{ $semana->data_prevista_formatada ?? '—' }}</span>
          </div>
        </div>

        <div class="col-md-3">
          <div class="border rounded p-4 h-100">
            <small class="text-muted d-block">Valor da semana</small>
            <span class="fw-semibold">{{ $semana->valor_total_formatado }}</span>
            <small class="text-body-secondary d-block">{{ $semana->itens->count() }} item(ns)</small>
          </div>
        </div>

        <div class="col-md-3">
          <div class="border rounded p-4 h-100">
            <small class="text-muted d-block">Aplicação</small>

            @if ($semana->sem_aplicacao)
              <span class="badge bg-dark">Sem aplicação</span>
            @else
              <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>
            @endif

            <small class="text-body-secondary d-block">
              {{ $semana->tem_aplicacao ? 'Tem medicamento com aplicação' : 'Nenhuma aplicação nesta semana' }}
            </small>

            @if ($semana->liberacao_descricao)
              <small class="text-warning d-block">
                <i class="ri-lock-unlock-line"></i> {{ $semana->liberacao_descricao }}
              </small>
            @endif
          </div>
        </div>

        <div class="col-md-3">
          <div class="border rounded p-4 h-100">
            <small class="text-muted d-block">Pagamento</small>

            @if ($parcela)
              <span class="badge {{ $parcela->status->corBadge() }}">{{ $parcela->status->label() }}</span>
              <small class="text-body-secondary d-block">
                {{ $parcela->numero_formatado }} parcela · {{ $parcela->valor_formatado }}
                @if ($parcela->vencimento)
                  <br />venc. {{ $parcela->vencimento_formatado }}
                @endif
              </small>

              @if ($parcela->tem_ajustes)
                <small class="text-body-secondary d-block">
                  bruto {{ $parcela->valor_bruto_formatado }}
                  @if ((float) $parcela->valor_desconto > 0)
                    − {{ $parcela->valor_desconto_formatado }}
                  @endif
                  @if ((float) $parcela->valor_adicional > 0)
                    + {{ $parcela->valor_adicional_formatado }}
                  @endif
                </small>
              @endif

              @if ((float) $parcela->valor_pago > 0)
                <small class="text-success d-block">
                  pago {{ $parcela->valor_pago_formatado }}
                  @if ($parcela->valor_em_aberto > 0)
                    · em aberto {{ $parcela->valor_em_aberto_formatado }}
                  @endif
                </small>
              @endif
            @else
              <span class="text-muted">Sem parcela gerada</span>
            @endif
          </div>
        </div>

        {{-- Chegada do paciente e atendimentos (a aplicação parcial gera mais de um) --}}
        @if ($semana->chegada_em || $semana->atendimentos->isNotEmpty())
          <div class="col-12">
            <div class="border rounded p-4">
              <small class="text-muted d-block mb-2">Chegada e atendimento</small>

              @if ($semana->chegada_em)
                <div>
                  <i class="ri-login-circle-line me-1"></i>
                  Chegou em <strong>{{ $semana->chegada_em_formatada }}</strong>

                  @if ($semana->tempo_de_espera)
                    <span class="badge bg-label-info ms-1">esperando {{ $semana->tempo_de_espera }}</span>
                  @endif
                </div>
              @endif

              @foreach ($semana->atendimentos as $atendimento)
                <div>
                  <i class="ri-heart-pulse-line me-1"></i>
                  {{ $atendimento->em_andamento ? 'Atendimento em andamento' : 'Atendimento' }}:
                  <strong>{{ $atendimento->iniciado_em_formatado }}</strong>
                  @if ($atendimento->finalizado_em)
                    → {{ $atendimento->finalizado_em_formatado }}
                  @endif
                  @if ($atendimento->iniciadoPor)
                    <span class="text-body-secondary">· {{ $atendimento->iniciadoPor->nome }}</span>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        @endif

        @if ($semana->observacao)
          <div class="col-12">
            <small class="text-muted d-block">Observação</small>
            <span>{{ $semana->observacao }}</span>
          </div>
        @endif

        {{-- Registro da aplicação na Feegow (agendamento) --}}
        @if ($semana->atendimentos->isNotEmpty())
          <div class="col-12">
            <div class="border rounded p-4">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                  <small class="text-muted d-block mb-2">Feegow (agendamento da aplicação)</small>

                  @if ($feegow)
                    <span class="badge {{ $feegow->situacao_cor }}">{{ $feegow->situacao_label }}</span>

                    @if ($feegow->agendamento_id)
                      <span class="text-body-secondary ms-1">agendamento #{{ $feegow->agendamento_id }}</span>
                    @endif

                    @if ($feegow->enviado_em)
                      <small class="text-body-secondary d-block">
                        Registrado em {{ $feegow->enviado_em->format('d/m/Y H:i') }}
                      </small>
                    @elseif ($feegow->proxima_tentativa)
                      <small class="text-body-secondary d-block">
                        Próxima tentativa em {{ $feegow->proxima_tentativa->format('d/m/Y H:i') }}
                      </small>
                    @endif

                    @if ($feegow->erro)
                      <small class="text-danger d-block">Último erro: {{ $feegow->erro }}</small>
                    @endif
                  @else
                    <span class="text-muted">Esta aplicação ainda não foi registrada na Feegow.</span>
                  @endif
                </div>

                <form method="POST" action="{{ route('prescricoes.semanas.feegow', [$prescricao, $semana]) }}">
                  @csrf

                  <button
                    type="submit"
                    class="btn btn-sm btn-outline-primary"
                    title="Registra a aplicação desta semana como agendamento na Feegow">
                    <i class="ri-cloud-line me-1"></i>
                    {{ $feegow ? ($feegow->enviado ? 'Enviar novamente' : 'Reenviar para a Feegow') : 'Registrar na Feegow' }}
                  </button>
                </form>
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  @php
    // Uma linha por medicamento aplicado (um vasilhame por linha), na ordem
    // dos atendimentos e do momento da aplicação
    $aplicacoesDosAtendimentos = $semana->atendimentos
        ->flatMap(fn ($atendimento) => $atendimento->aplicacoes
            ->sortBy('aplicado_em')
            ->map(fn ($aplicacao) => ['atendimento' => $atendimento, 'aplicacao' => $aplicacao]))
        ->values();

    // Junta os valores distintos de uma coluna das aplicações do item
    $juntar = fn ($aplicacoes, callable $valor) => $aplicacoes
        ->map($valor)
        ->filter(fn ($texto) => filled($texto) && $texto !== '—')
        ->unique()
        ->implode(' · ');
  @endphp

  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h6 class="fw-semibold mb-0">Itens da semana</h6>

      @if ($aplicacoesDosAtendimentos->isNotEmpty())
        <span class="text-muted small">
          {{ $aplicacoesDosAtendimentos->count() }} aplicação(ões)
          em {{ $semana->atendimentos->count() }} atendimento(s)
        </span>
      @endif
    </div>

    <div class="card-body">
      @if ($semana->itens->isEmpty())
        <p class="text-muted mb-0">
          {{ $semana->sem_aplicacao ? 'Semana marcada como "sem aplicação", por isso não tem itens.' : 'Nenhum item cadastrado.' }}
        </p>
      @else
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width: 190px;">Medicamento</th>
                <th style="width: 100px;">Quantidade</th>
                <th style="width: 120px;" class="text-end">Valor unitário</th>
                <th style="width: 120px;" class="text-end">Total</th>
                <th style="width: 130px;">Situação</th>
                <th style="width: 145px;">Chegada</th>
                <th style="width: 145px;">Atendimento</th>
                <th style="width: 145px;">Aplicação</th>
                <th style="min-width: 165px;">Quem aplicou</th>
                <th style="width: 130px;">Código de barras</th>
                <th style="width: 100px;">Lote</th>
                <th style="width: 105px;">Vencimento</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($semana->itens as $item)
                @php
                  // Aplicações deste item em todos os atendimentos
                  $aplicacoesDoItem = $aplicacoesDosAtendimentos
                      ->filter(fn ($linha) => $linha['aplicacao']->prescricao_semana_item_id === $item->id)
                      ->values();
                @endphp

                {{-- Item + aplicações, tudo na mesma linha --}}
                <tr>
                  <td>
                    <span class="fw-semibold">{{ $item->nome }}</span>
                    <small class="text-body-secondary d-block">
                      {{ $item->tipo === 'combo' ? 'Combo' : 'Medicamento' }}
                    </small>

                    {{-- No combo, qual componente foi aplicado --}}
                    @if ($item->tipo === 'combo' && $aplicacoesDoItem->isNotEmpty())
                      <small class="text-body-secondary d-block">
                        {{ $aplicacoesDoItem->map(fn ($linha) => $linha['aplicacao']->medicamento?->nome)->filter()->unique()->implode(' · ') }}
                      </small>
                    @endif

                    {{-- Vasilhame de outro tamanho do mesmo grupo (ex.: 60MG no item 90MG) --}}
                    @if ($item->tipo !== 'combo'
                        && $aplicacoesDoItem->contains(fn ($linha) => (int) $linha['aplicacao']->medicamento_id !== (int) $item->medicamento_id))
                      <small class="text-warning d-block">
                        {{ $aplicacoesDoItem->map(fn ($linha) => $linha['aplicacao']->medicamento?->nome)->filter()->unique()->implode(' · ') }}
                      </small>
                    @endif
                  </td>
                  <td>
                    {{ $item->eh_miligrama ? $item->quantidade_formatada.' mg' : $item->quantidade_formatada }}

                    {{-- Divisão entre vasilhames (ex.: 2 mg + 2,25 mg) --}}
                    @if ($aplicacoesDoItem->count() > 1)
                      <small class="text-body-secondary d-block">
                        {{ $aplicacoesDoItem->map(fn ($linha) => $linha['aplicacao']->quantidade_com_unidade)->implode(' + ') }}
                      </small>
                    @endif
                  </td>
                  <td class="text-end">{{ $item->valor_formatado }}</td>
                  <td class="text-end fw-semibold">{{ $item->valor_total_formatado }}</td>
                  <td>
                    @if ($item->gera_aplicacao)
                      <span class="badge {{ $item->status->corBadge() }}">{{ $item->status->label() }}</span>
                    @else
                      <span class="badge bg-label-secondary">Não se aplica</span>
                    @endif
                  </td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['atendimento']->chegada_em_formatada) ?: '—' }}</td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['atendimento']->iniciado_em_formatado) ?: '—' }}</td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['aplicacao']->aplicado_em_formatado) ?: '—' }}</td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['aplicacao']->user?->nome) ?: '—' }}</td>
                  <td class="font-monospace">{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['aplicacao']->codigo_barras) ?: '—' }}</td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['aplicacao']->lote) ?: '—' }}</td>
                  <td>{{ $juntar($aplicacoesDoItem, fn ($linha) => $linha['aplicacao']->vencimento_formatado) ?: '—' }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total da semana</th>
                <th class="text-end">{{ $semana->valor_total_formatado }}</th>
                <th colspan="8"></th>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif

      @if ($semana->atendimentos->contains(fn ($atendimento) => $atendimento->em_andamento))
        <p class="text-muted small mb-0 mt-2">
          O atendimento em andamento ainda não foi finalizado.
        </p>
      @endif
    </div>
  </div>

  {{-- Navegação entre as semanas da prescrição (só as que existirem) --}}
  @if ($semanaAnterior || $semanaProxima)
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
      @if ($semanaAnterior)
        <a
          href="{{ route('prescricoes.semanas.show', [$prescricao, $semanaAnterior]) }}"
          class="btn btn-outline-primary">
          <i class="ri-arrow-left-line me-1"></i>
          Semana {{ $semanaAnterior->numero }}
          @if ($semanaAnterior->data_prevista_formatada)
            <span class="text-body-secondary small ms-1">· {{ $semanaAnterior->data_prevista_formatada }}</span>
          @endif
        </a>
      @else
        <span></span>
      @endif

      @if ($semanaProxima)
        <a
          href="{{ route('prescricoes.semanas.show', [$prescricao, $semanaProxima]) }}"
          class="btn btn-outline-primary">
          Semana {{ $semanaProxima->numero }}
          @if ($semanaProxima->data_prevista_formatada)
            <span class="text-body-secondary small ms-1">· {{ $semanaProxima->data_prevista_formatada }}</span>
          @endif
          <i class="ri-arrow-right-line ms-1"></i>
        </a>
      @endif
    </div>
  @endif
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
