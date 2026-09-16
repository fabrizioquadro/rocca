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
          @if ($parcela?->esta_paga)
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
    @if ($agendada && ! $parcela?->esta_paga)
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

        @if ($semana->observacao)
          <div class="col-12">
            <small class="text-muted d-block">Observação</small>
            <span>{{ $semana->observacao }}</span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h6 class="fw-semibold mb-0">Itens da semana</h6>
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
                <th style="width: 140px;">Tipo</th>
                <th>Medicamento / Combo</th>
                <th style="width: 120px;">Quantidade</th>
                <th style="width: 150px;" class="text-end">Valor unitário</th>
                <th style="width: 150px;" class="text-end">Total</th>
                <th style="width: 180px;">Aplicação</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($semana->itens as $item)
                <tr>
                  <td>{{ $item->tipo === 'combo' ? 'Combo' : 'Medicamento' }}</td>
                  <td>{{ $item->nome }}</td>
                  <td>{{ $item->quantidade_formatada }}</td>
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
                <th colspan="4" class="text-end">Total da semana</th>
                <th class="text-end">{{ $semana->valor_total_formatado }}</th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
