@extends('layouts.app')

@section('title', 'Prescrições')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Prescrições</h4>

      <a href="{{ route('prescricoes.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Nova prescrição
      </a>
    </div>

    <div class="card-body">
      @if (session('success'))
        <div class="alert alert-success" role="alert">
          {{ session('success') }}
        </div>
      @endif

      @if (session('error'))
        <div class="alert alert-danger" role="alert">
          {{ session('error') }}
        </div>
      @endif

      <div class="table-responsive text-nowrap">
        <table id="tabela-prescricoes" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Paciente</th>
              <th>Médico</th>
              <th>Clínica</th>
              <th>Tipo de atendimento</th>
              <th>Agendamento</th>
              <th class="text-center">Semanas</th>
              <th>Situação</th>
              <th class="text-end">Valor total</th>
              <th class="text-end">Desconto</th>
              <th class="text-end">Adicional</th>
              <th class="text-end">Pago</th>
              <th class="text-end">Restante</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($prescricoes as $prescricao)
              @php
                $financeiro = $prescricao->financeiro;
                $desconto = (float) ($financeiro?->valor_desconto ?? 0);
                $adicional = (float) ($financeiro?->adicional_valor ?? 0);
                $pago = (float) ($financeiro?->valor_pago ?? 0);
                $restante = (float) ($financeiro?->valor_aberto ?? 0);
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
                      <a class="dropdown-item" href="{{ route('prescricoes.show', $prescricao) }}">
                        <i class="ri-arrow-right-circle-line me-2"></i>Acessar
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
                </td>
                <td>
                  {{ $prescricao->medico_nome ?? '—' }}
                  @if ($prescricao->medico_id)
                    <div class="text-body-secondary small">cód. {{ $prescricao->medico_id }}</div>
                  @endif
                </td>
                <td>{{ $prescricao->clinica?->nome ?? '—' }}</td>
                <td>{{ $prescricao->tipo_atendimento?->label() ?? '—' }}</td>
                <td>{{ $prescricao->agendamento ?? '—' }}</td>
                {{-- Progresso: número da última semana aplicada / total de semanas da prescrição --}}
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
                {{-- Situação derivada das semanas: nada é gravado na prescrição --}}
                <td data-order="{{ $prescricao->situacao_ordem }}">
                  <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
                </td>
                <td class="text-end fw-semibold" data-order="{{ $financeiro?->valor_total ?? $prescricao->valor_total }}">
                  {{ $financeiro?->valor_total_formatado ?? $prescricao->valor_total_formatado }}
                </td>

                <td class="text-end {{ $desconto > 0 ? 'text-danger' : 'text-muted' }}" data-order="{{ $desconto }}">
                  @if ($desconto > 0)
                    <span title="Desconto: {{ $financeiro->desconto_descricao }}">
                      - {{ $financeiro->valor_desconto_formatado }}
                    </span>
                  @else
                    —
                  @endif
                </td>

                <td class="text-end {{ $adicional > 0 ? 'text-success' : 'text-muted' }}" data-order="{{ $adicional }}">
                  {{ $adicional > 0 ? '+ '.$financeiro->valor_adicional_formatado : '—' }}
                </td>

                <td class="text-end {{ $pago > 0 ? 'text-success' : 'text-muted' }}" data-order="{{ $pago }}">
                  {{ $pago > 0 ? $financeiro->valor_pago_formatado : '—' }}
                </td>

                <td class="text-end" data-order="{{ $restante }}">
                  @if (! $financeiro)
                    <span class="text-muted">—</span>
                  @elseif ($restante <= 0)
                    <span class="badge bg-label-success">Quitado</span>
                  @else
                    <span class="fw-semibold">{{ $financeiro->valor_aberto_formatado }}</span>
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
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script>
    $(function () {
      $('#tabela-prescricoes').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhuma prescrição cadastrada. Clique em "Nova prescrição" para cadastrar.',
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
      });
    });
  </script>

  @include('partials.crud-scripts')
@endpush
