@extends('layouts.app')

@section('title', 'Secretária')

@section('content')
  {{-- 1º card: busca por paciente (todas as prescrições do paciente escolhido) --}}
  <div class="card mb-6">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-search-line ri-24px text-primary"></i>
        <h4 class="fw-semibold mb-0">Pesquisar por paciente</h4>

        @if ($paciente)
          <span class="badge bg-label-primary">{{ $prescricoes->count() }} prescrição(ões)</span>
        @endif
      </div>
    </div>

    <div class="card-body">
      <form method="GET" action="{{ route('secretaria.index') }}">
        {{-- align-items-end alinha o botão com o bottom do select: por isso o texto de
             ajuda fica FORA da row (se ficasse dentro, aumentaria a altura da coluna). --}}
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label" for="paciente_id">Paciente</label>
            <select id="paciente_id" name="paciente_id" class="form-select">
              @if ($paciente)
                <option value="{{ $paciente->id }}" selected>{{ $paciente->nome }}</option>
              @endif
            </select>
          </div>

          <div class="col-md-3">
            <button type="submit" class="btn btn-primary">
              <i class="ri-search-line me-1"></i>Pesquisar
            </button>
          </div>
        </div>

        <small class="text-muted d-block mt-2">
          Digite o nome (ou o CPF) para buscar. Os pacientes vêm da base importada da Feegow.
        </small>
      </form>

      @if ($paciente?->observacao)
        <div class="alert alert-warning d-flex gap-2 mt-4 mb-0" role="alert">
          <i class="ri-alert-line ri-20px"></i>
          <div>
            <strong class="d-block">Atenção: observação do paciente</strong>
            <span style="white-space: pre-line;">{{ $paciente->observacao }}</span>
          </div>
        </div>
      @endif

      @if ($paciente)
        <div class="table-responsive text-nowrap mt-4">
          <table id="tabela-prescricoes-paciente" class="table table-sm table-bordered">
            <thead class="table-light">
              <tr>
                <th class="text-center" style="width: 60px;"></th>
                <th style="width: 120px;">Prescrição</th>
                <th style="width: 120px;">Cadastro</th>
                <th class="text-center" style="width: 110px;">Semanas</th>
                <th style="width: 190px;">Situação</th>
                <th>Clínica</th>
                <th class="text-end" style="width: 130px;">Valor total</th>
                <th class="text-end" style="width: 130px;">Pago</th>
                <th class="text-end" style="width: 130px;">Em aberto</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($prescricoes as $prescricao)
                @php $financeiro = $prescricao->financeiro; @endphp

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
                        <a class="dropdown-item" href="{{ route('prescricoes.show', $prescricao) }}">
                          <i class="ri-arrow-right-circle-line me-2"></i>Acessar prescrição
                        </a>

                        <a
                          class="dropdown-item"
                          href="{{ route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro']) }}">
                          <i class="ri-money-dollar-circle-line me-2"></i>Financeiro / receber
                        </a>
                      </div>
                    </div>
                  </td>
                  <td>
                    <a href="{{ route('prescricoes.show', $prescricao) }}">
                      #{{ $prescricao->id }}
                    </a>
                  </td>
                  <td>{{ $prescricao->created_at?->format('d/m/Y') ?? '—' }}</td>
                  {{-- Progresso: número da última semana aplicada / total de semanas --}}
                  <td class="text-center" data-order="{{ $prescricao->ultima_semana_aplicada ?? 0 }}">
                    @if ($prescricao->semanas_com_aplicacao === 0)
                      <span class="text-muted">—</span>
                    @else
                      @if ($prescricao->ultima_semana_aplicada === null)
                        <div class="text-body-secondary small">Não iniciada</div>
                      @endif

                      <span class="fw-semibold">
                        {{ $prescricao->ultima_semana_aplicada ?? 0 }}/{{ $prescricao->quantidade_semanas }}
                      </span>
                    @endif
                  </td>
                  <td>
                    <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
                  </td>
                  <td>{{ $prescricao->clinica?->nome ?? '—' }}</td>
                  <td class="text-end fw-semibold">
                    {{ $financeiro?->valor_total_formatado ?? $prescricao->valor_total_formatado }}
                  </td>
                  <td class="text-end text-success">
                    {{ (float) ($financeiro?->valor_pago ?? 0) > 0 ? $financeiro->valor_pago_formatado : '—' }}
                  </td>
                  <td class="text-end {{ (float) ($financeiro?->valor_aberto ?? 0) > 0 ? 'text-danger' : 'text-muted' }}">
                    {{ (float) ($financeiro?->valor_aberto ?? 0) > 0 ? $financeiro->valor_aberto_formatado : '—' }}
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mt-4 mb-0">
          Escolha um paciente e clique em <strong>Pesquisar</strong> para ver todas as prescrições dele.
        </p>
      @endif
    </div>
  </div>

  {{-- Área da Secretária: prescrições com semana agendada para hoje --}}
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-calendar-check-line ri-24px text-primary"></i>
        <h4 class="fw-semibold mb-0">Prescrições agendadas para hoje</h4>
        <span class="badge bg-label-primary">{{ $agendadas->count() }}</span>
      </div>

      <span class="text-muted small">
        {{ $dia->format('d/m/Y') }} — semanas com status <strong>Agendada</strong> para este dia
      </span>
    </div>

    <div class="card-body">
      <div class="table-responsive text-nowrap">
        <table id="tabela-agendadas" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;"></th>
              <th>Paciente</th>
              <th style="width: 110px;">Prescrição</th>
              <th class="text-center" style="width: 90px;">Semana</th>
              <th style="width: 200px;">Pagamento</th>
              <th>Clínica</th>
              <th>Itens</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($agendadas as $semana)
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
                      <a
                        class="dropdown-item"
                        href="{{ route('prescricoes.show', ['prescricao' => $semana->prescricao, 'aba' => 'financeiro']) }}">
                        <i class="ri-money-dollar-circle-line me-2"></i>Financeiro / receber
                      </a>

                      <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$semana->prescricao, $semana]) }}">
                        <i class="ri-arrow-right-circle-line me-2"></i>Acessar semana
                      </a>

                      <a class="dropdown-item" href="{{ route('prescricoes.show', $semana->prescricao) }}">
                        <i class="ri-file-list-2-line me-2"></i>Acessar prescrição
                      </a>
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

                  {{-- Observação cadastrada no paciente --}}
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
                <td>
                  @php $parcela = $semana->parcelas->first(); @endphp

                  @if ($parcela)
                    <span class="badge {{ $parcela->status->corBadge() }}">{{ $parcela->status->label() }}</span>

                    <div class="text-body-secondary small">
                      {{ $parcela->valor_formatado }}

                      @if ((float) $parcela->valor_pago > 0)
                        · pago {{ $parcela->valor_pago_formatado }}
                      @endif

                      @if ($parcela->valor_em_aberto > 0)
                        · <span class="text-danger">em aberto {{ $parcela->valor_em_aberto_formatado }}</span>
                      @endif
                    </div>

                    @if ($semana->foi_liberada)
                      <div class="text-warning small" title="{{ $semana->liberacao_descricao }}">
                        <i class="ri-lock-unlock-line"></i> Liberada sem pagamento
                      </div>
                    @endif
                  @else
                    <span class="text-muted">Sem parcela gerada</span>
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

  {{-- Prescrições com semana agendada para um dia que já passou --}}
  <div class="card mt-6">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-alarm-warning-line ri-24px text-danger"></i>
        <h4 class="fw-semibold mb-0">Semanas em atraso</h4>
        <span class="badge bg-label-danger">{{ $atrasadas->count() }}</span>
      </div>

      <span class="text-muted small">
        Semana ainda <strong>Agendada</strong> com data prevista anterior a {{ $dia->format('d/m/Y') }} — ordenado pelas mais atrasadas
      </span>
    </div>

    <div class="card-body">
      <div class="table-responsive text-nowrap">
        <table id="tabela-atrasadas" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;"></th>
              <th>Paciente</th>
              <th style="width: 110px;">Prescrição</th>
              <th style="width: 220px;">Semanas em atraso</th>
              <th style="width: 170px;">Dias em atraso</th>
              <th>Clínica</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($atrasadas as $prescricao)
              @php $maisAntiga = $prescricao->semanas_em_atraso->first(); @endphp

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
                      <a
                        class="dropdown-item"
                        href="{{ route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro']) }}">
                        <i class="ri-money-dollar-circle-line me-2"></i>Financeiro / receber
                      </a>

                      @if ($maisAntiga)
                        <a class="dropdown-item" href="{{ route('prescricoes.semanas.show', [$prescricao, $maisAntiga]) }}">
                          <i class="ri-arrow-right-circle-line me-2"></i>Acessar semana {{ $maisAntiga->numero }}
                        </a>
                      @endif

                      <a class="dropdown-item" href="{{ route('prescricoes.show', $prescricao) }}">
                        <i class="ri-file-list-2-line me-2"></i>Acessar prescrição
                      </a>
                    </div>
                  </div>
                </td>
                <td>
                  {{ $prescricao->paciente?->nome ?? '—' }}

                  @if ($prescricao->paciente?->nascimento)
                    <div class="text-body-secondary small">
                      Nasc. {{ $prescricao->paciente->nascimento_formatado }}
                      @if ($prescricao->paciente->idade !== null)
                        · {{ $prescricao->paciente->idade }} anos
                      @endif
                    </div>
                  @endif

                  {{-- Observação cadastrada no paciente --}}
                  @if ($prescricao->paciente?->observacao)
                    <div class="text-warning small text-wrap" style="max-width: 320px;">
                      <i class="ri-alert-line"></i> {{ $prescricao->paciente->observacao }}
                    </div>
                  @endif
                </td>
                <td>
                  <a href="{{ route('prescricoes.show', $prescricao) }}">
                    #{{ $prescricao->id }}
                  </a>
                </td>
                <td>
                  @foreach ($prescricao->semanas_em_atraso as $semana)
                    <div>
                      <span class="fw-semibold">Semana {{ $semana->numero }}</span>
                      <span class="text-body-secondary small">· {{ $semana->data_prevista_formatada }}</span>
                    </div>
                  @endforeach
                </td>
                <td>
                  <span class="badge bg-label-danger">
                    {{ $prescricao->dias_de_atraso }} {{ $prescricao->dias_de_atraso === 1 ? 'dia' : 'dias' }}
                  </span>

                  @if ($maisAntiga)
                    <div class="text-body-secondary small">desde {{ $maisAntiga->data_prevista_formatada }}</div>
                  @endif
                </td>
                <td>{{ $prescricao->clinica?->nome ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Estoque baixo: linha amarela abaixo do médio, vermelha abaixo do mínimo --}}
  <div class="card mt-6">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <i class="ri-error-warning-line ri-24px text-warning"></i>
        <h4 class="fw-semibold mb-0">Medicamentos abaixo do nível</h4>
        <span class="badge bg-label-warning">{{ $abaixoDoNivel->count() }}</span>
      </div>

      <span class="text-muted small">
        Saldo total (todas as clínicas) abaixo do estoque médio ou mínimo cadastrado no medicamento —
        os abaixo do mínimo aparecem primeiro
      </span>
    </div>

    <div class="card-body">
      <div class="table-responsive text-nowrap">
        <table id="tabela-estoque-baixo" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;"></th>
              <th>Medicamento</th>
              <th class="text-center" style="width: 110px;">Saldo atual</th>
              <th class="text-center" style="width: 110px;">Estoque médio</th>
              <th class="text-center" style="width: 110px;">Estoque mínimo</th>
              <th style="width: 170px;">Situação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($abaixoDoNivel as $medicamento)
              @php $abaixoDoMinimo = $medicamento->nivel_estoque === 'minimo'; @endphp

              <tr class="{{ $abaixoDoMinimo ? 'table-danger' : 'table-warning' }}">
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
                      <a class="dropdown-item" href="{{ route('estoque.saldo.show', $medicamento) }}">
                        <i class="ri-stack-line me-2"></i>Acessar saldo
                      </a>

                      <a class="dropdown-item" href="{{ route('estoque.saldo.index') }}">
                        <i class="ri-list-check-2 me-2"></i>Todo o saldo
                      </a>
                    </div>
                  </div>
                </td>
                <td>
                  {{ $medicamento->nome }}

                  @if ($medicamento->fabricante)
                    <div class="text-body-secondary small">{{ $medicamento->fabricante }}</div>
                  @endif

                  @if ($medicamento->grupo)
                    <div class="text-body-secondary small">Grupo: {{ $medicamento->grupo->nome }}</div>
                  @endif
                </td>
                <td class="text-center fw-semibold">{{ $medicamento->saldo_atual }}</td>
                <td class="text-center">{{ $medicamento->estoque_medio ?? '—' }}</td>
                <td class="text-center">{{ $medicamento->estoque_minimo ?? '—' }}</td>
                <td>
                  @if ($abaixoDoMinimo)
                    <span class="badge bg-label-danger">Abaixo do mínimo</span>
                  @else
                    <span class="badge bg-label-warning">Abaixo do médio</span>
                  @endif
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
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/select2/select2.css') }}" />
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  <script>
    window.secretariaPacientesUrl = '{{ route('prescricoes.pacientes') }}';

    $(function () {
      // Paciente: base grande, então a busca é por AJAX (igual ao cadastro da prescrição)
      $('#paciente_id').select2({
        width: '100%',
        placeholder: 'Digite para buscar o paciente...',
        allowClear: true,
        minimumInputLength: 3,
        language: {
          inputTooShort: () => 'Digite pelo menos 3 letras',
          noResults: () => 'Nenhum paciente encontrado',
          searching: () => 'Buscando...'
        },
        ajax: {
          url: window.secretariaPacientesUrl,
          dataType: 'json',
          delay: 300,
          cache: true,
          data: (params) => ({ busca: params.term }),
          processResults: (data) => data
        }
      });

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

      $('#tabela-agendadas').DataTable(Object.assign({}, opcoes, {
        language: Object.assign({}, opcoes.language, {
          emptyTable: 'Nenhuma prescrição com semana agendada para hoje.'
        })
      }));

      $('#tabela-atrasadas').DataTable(Object.assign({}, opcoes, {
        language: Object.assign({}, opcoes.language, {
          emptyTable: 'Nenhuma prescrição com semana em atraso.'
        })
      }));

      $('#tabela-estoque-baixo').DataTable(Object.assign({}, opcoes, {
        language: Object.assign({}, opcoes.language, {
          emptyTable: 'Nenhum medicamento abaixo do nível de estoque.'
        })
      }));

      if ($('#tabela-prescricoes-paciente').length) {
        $('#tabela-prescricoes-paciente').DataTable(Object.assign({}, opcoes, {
          language: Object.assign({}, opcoes.language, {
            emptyTable: 'Nenhuma prescrição para este paciente.'
          })
        }));
      }
    });
  </script>
@endpush
