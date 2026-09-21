@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  @if (session('success') || session('error'))
    <div class="card mb-6">
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
      </div>
    </div>
  @endif

  {{-- Área da Enfermagem: quem chegou e está esperando o atendimento --}}
  <div class="card mb-6">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-nurse-line ri-24px text-primary"></i>
        <h4 class="fw-semibold mb-0">Enfermagem</h4>
        <span class="badge bg-label-info">{{ $fila->count() }}</span>
      </div>

      <span class="text-muted small">
        Na fila de aplicação, pela ordem de chegada — clique em "Iniciar atendimento" quando chamar o paciente
      </span>
    </div>

    <div class="card-body">
      <div class="table-responsive text-nowrap">
        <table id="tabela-enfermagem" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;"></th>
              <th>Paciente</th>
              <th style="width: 110px;">Prescrição</th>
              <th class="text-center" style="width: 90px;">Semana</th>
              <th style="width: 150px;">Data prevista</th>
              <th style="width: 160px;">Chegada</th>
              <th style="width: 170px;">Situação</th>
              <th>Clínica</th>
              <th>Itens a aplicar</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($fila as $semana)
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
                      <form
                        method="POST"
                        action="{{ route('prescricoes.semanas.atendimento.iniciar', [$semana->prescricao, $semana]) }}"
                        data-confirmar="Iniciar o atendimento da semana {{ $semana->numero }}? Você fica responsável por ele.">
                        @csrf
                        <button type="submit" class="dropdown-item text-primary">
                          <i class="ri-play-circle-line me-2"></i>Iniciar atendimento
                        </button>
                      </form>

                      <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$semana->prescricao, $semana]) }}">
                        <i class="ri-arrow-right-circle-line me-2"></i>Acessar semana
                      </a>

                      <a class="dropdown-item" href="{{ route('prescricoes.show', $semana->prescricao) }}">
                        <i class="ri-file-list-2-line me-2"></i>Acessar prescrição
                      </a>

                      {{-- Paciente não compareceu: a semana volta para o agendamento --}}
                      @if ($semana->pode_voltar_para_agendada)
                        <form
                          method="POST"
                          action="{{ route('prescricoes.semanas.devolver', [$semana->prescricao, $semana]) }}"
                          data-confirmar="Devolver a semana {{ $semana->numero }} para Agendada? A liberação sem pagamento será desfeita.">
                          @csrf
                          <button type="submit" class="dropdown-item text-warning">
                            <i class="ri-arrow-go-back-line me-2"></i>Devolver para Agendada
                          </button>
                        </form>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  {{ $semana->prescricao->paciente?->nome ?? '—' }}

                  @if ($semana->prescricao->paciente?->nascimento)
                    <div class="text-body-secondary small">
                      Nasc. {{ $semana->prescricao->paciente->nascimento_formatado }}
                      @if ($semana->prescricao->paciente->idade !== null)
                        · {{ $semana->prescricao->paciente->idade }} anos
                      @endif
                    </div>
                  @endif

                  {{-- Observação cadastrada no paciente: precisa aparecer para a enfermagem --}}
                  @if ($semana->prescricao->paciente?->observacao)
                    <div class="text-warning small text-wrap" style="max-width: 320px;">
                      <i class="ri-alert-line"></i> {{ $semana->prescricao->paciente->observacao }}
                    </div>
                  @endif
                </td>
                <td>
                  <a href="{{ route('prescricoes.show', $semana->prescricao) }}">
                    #{{ $semana->prescricao->id }}
                  </a>
                </td>
                <td class="text-center fw-semibold">
                  {{ $semana->numero }}/{{ $semana->prescricao->quantidade_semanas }}
                </td>
                <td data-order="{{ $semana->data_prevista?->format('Y-m-d') }}">
                  {{ $semana->data_prevista_formatada ?? '—' }}

                  @if ($semana->foi_liberada)
                    <div class="text-warning small" title="{{ $semana->liberacao_descricao }}">
                      <i class="ri-lock-unlock-line"></i> Liberada sem pagamento
                    </div>
                  @endif
                </td>
                <td data-order="{{ $semana->chegada_em?->format('Y-m-d H:i') }}">
                  @if ($semana->chegada_em)
                    {{ $semana->chegada_em_formatada }}

                    @if ($semana->tempo_de_espera)
                      <div class="text-body-secondary small">esperando {{ $semana->tempo_de_espera }}</div>
                    @endif
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>

                  @if ($semana->status === \App\Enums\StatusSemana::AplicacaoParcial)
                    <div class="text-body-secondary small">voltou para aplicar o restante</div>
                  @endif
                </td>
                <td>{{ $semana->prescricao->clinica?->nome ?? '—' }}</td>
                <td class="text-wrap">
                  @forelse ($semana->itens as $item)
                    <div class="{{ $item->gera_aplicacao ? '' : 'text-muted' }}">
                      <span class="fw-semibold">{{ $item->quantidade_formatada }}</span>
                      × {{ $item->nome }}

                      @if (! $item->gera_aplicacao)
                        <span class="small">(sem aplicação)</span>
                      @endif
                    </div>
                  @empty
                    <span class="text-muted">Sem itens</span>
                  @endforelse
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Atendimentos em andamento: só quem iniciou conduz --}}
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-heart-pulse-line ri-24px text-warning"></i>
        <h4 class="fw-semibold mb-0">Em atendimento</h4>
        <span class="badge bg-label-warning">{{ $atendimentos->count() }}</span>
      </div>

      <span class="text-muted small">
        O atendimento só pode ser conduzido por quem o iniciou
      </span>
    </div>

    <div class="card-body">
      <div class="table-responsive text-nowrap">
        <table id="tabela-atendimento" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;"></th>
              <th>Paciente</th>
              <th style="width: 110px;">Prescrição</th>
              <th class="text-center" style="width: 90px;">Semana</th>
              <th style="width: 180px;">Em atendimento desde</th>
              <th style="width: 180px;">Responsável</th>
              <th>Clínica</th>
              <th>Itens a aplicar</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($atendimentos as $atendimento)
              @php
                $semana = $atendimento->semana;
                $meu = $atendimento->podeSerConduzidoPor(auth()->user());
              @endphp

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
                      @if ($meu)
                        <a
                          class="dropdown-item text-primary"
                          href="{{ route('prescricoes.semanas.aplicar.form', [$semana->prescricao, $semana]) }}">
                          <i class="ri-syringe-line me-2"></i>Registrar aplicação
                        </a>

                        <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$semana->prescricao, $semana]) }}">
                          <i class="ri-arrow-right-circle-line me-2"></i>Acessar semana
                        </a>

                        <form
                          method="POST"
                          action="{{ route('prescricoes.semanas.devolver', [$semana->prescricao, $semana]) }}"
                          data-confirmar="Devolver a semana {{ $semana->numero }} para Agendada?">
                          @csrf
                          <button type="submit" class="dropdown-item text-warning">
                            <i class="ri-arrow-go-back-line me-2"></i>Devolver para Agendada
                          </button>
                        </form>
                      @else
                        <span class="dropdown-item disabled" title="{{ $atendimento->bloqueio_de_outro_usuario }}">
                          <i class="ri-lock-line me-2"></i>Registrar aplicação
                        </span>

                        <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$semana->prescricao, $semana]) }}">
                          <i class="ri-eye-line me-2"></i>Visualizar semana
                        </a>
                      @endif

                      <a class="dropdown-item" href="{{ route('prescricoes.show', $semana->prescricao) }}">
                        <i class="ri-file-list-2-line me-2"></i>Acessar prescrição
                      </a>
                    </div>
                  </div>
                </td>
                <td>
                  {{ $semana->prescricao->paciente?->nome ?? '—' }}

                  @if ($semana->prescricao->paciente?->observacao)
                    <div class="text-warning small text-wrap" style="max-width: 320px;">
                      <i class="ri-alert-line"></i> {{ $semana->prescricao->paciente->observacao }}
                    </div>
                  @endif
                </td>
                <td>
                  <a href="{{ route('prescricoes.show', $semana->prescricao) }}">
                    #{{ $semana->prescricao->id }}
                  </a>
                </td>
                <td class="text-center fw-semibold">
                  {{ $semana->numero }}/{{ $semana->prescricao->quantidade_semanas }}
                </td>
                <td data-order="{{ $atendimento->iniciado_em?->format('Y-m-d H:i') }}">
                  {{ $atendimento->iniciado_em_formatado }}

                  @if ($semana->chegada_em)
                    <div class="text-body-secondary small">chegou {{ $semana->chegada_em->format('H:i') }}</div>
                  @endif
                </td>
                <td>
                  {{ $atendimento->iniciadoPor?->nome ?? '—' }}

                  @if ($meu)
                    <span class="badge bg-label-primary ms-1">você</span>
                  @endif
                </td>
                <td>{{ $semana->prescricao->clinica?->nome ?? '—' }}</td>
                <td class="text-wrap">
                  @forelse ($semana->itens as $item)
                    <div class="{{ $item->status === \App\Enums\StatusSemanaItem::Aplicado ? 'text-muted' : '' }}">
                      <span class="fw-semibold">{{ $item->quantidade_formatada }}</span>
                      × {{ $item->nome }}

                      @if ($item->status === \App\Enums\StatusSemanaItem::Aplicado)
                        <span class="badge bg-label-success">aplicado</span>
                      @endif
                    </div>
                  @empty
                    <span class="text-muted">Sem itens</span>
                  @endforelse
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script>
    $(function () {
      const opcoes = {
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          paginate: {
            first: 'Primeiro',
            last: 'Último',
            next: 'Próximo',
            previous: 'Anterior'
          }
        },
        order: [],
        columnDefs: [{
          targets: 0,
          orderable: false
        }],
        pageLength: 10
      };

      $('#tabela-enfermagem').DataTable(Object.assign({}, opcoes, {
        language: Object.assign({}, opcoes.language, {
          emptyTable: 'Nenhum paciente na fila de aplicação no momento.'
        })
      }));

      $('#tabela-atendimento').DataTable(Object.assign({}, opcoes, {
        language: Object.assign({}, opcoes.language, {
          emptyTable: 'Nenhum atendimento em andamento no momento.'
        })
      }));
    });
  </script>

  @include('partials.crud-scripts')
@endpush
