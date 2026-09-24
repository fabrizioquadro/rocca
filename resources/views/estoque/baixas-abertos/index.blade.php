@extends('layouts.app')

@section('title', 'Baixas de medicamentos abertos')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Baixas de medicamentos abertos</h4>

      <a href="{{ route('estoque.baixas-abertos.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Nova baixa de aberto
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
        <table id="tabela-baixas-abertos" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>#</th>
              <th>Clínica</th>
              <th>Data</th>
              <th>Vasilhames baixados</th>
              <th>Lançado por</th>
              <th class="text-end">Total (mg)</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($baixas as $baixa)
              <tr>
                <td class="text-center">
                  <a
                    href="{{ route('estoque.baixas-abertos.show', $baixa) }}"
                    class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                    title="Visualizar">
                    <i class="ri-eye-line"></i>
                  </a>
                </td>
                <td>{{ $baixa->id }}</td>
                <td>{{ $baixa->clinica?->nome ?? '—' }}</td>
                <td>{{ $baixa->data_formatada ?? '—' }}</td>
                <td>
                  <div class="text-muted small">
                    @forelse ($baixa->itens as $item)
                      <div>
                        {{ $item->medicamento?->nome ?? '—' }}
                        <span class="fw-semibold">({{ $item->mg_baixa_formatado }} mg)</span>
                        <span class="text-body-secondary">
                          · cód. {{ $item->codigo_barras ?? '—' }} · lote {{ $item->lote ?? '—' }}
                        </span>

                        @if ($item->motivo)
                          <div class="text-body-secondary">Motivo: {{ $item->motivo }}</div>
                        @endif
                      </div>
                    @empty
                      <div>—</div>
                    @endforelse
                  </div>
                </td>
                <td>{{ $baixa->user?->nome ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ $baixa->total_mg_formatado }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <small class="text-muted d-block mt-3">
        A baixa de aberto retira o saldo em mg do vasilhame (perda, quebra, vencimento ou sobra descartada).
        Sobrando saldo, o vasilhame continua aberto; zerando, ele é encerrado. Como é registro de saída,
        a baixa <strong>não pode ser excluída</strong>.
      </small>
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
      $('#tabela-baixas-abertos').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhuma baixa lançada. Clique em "Nova baixa de aberto" para lançar.',
          paginate: {
            first: 'Primeiro',
            last: 'Último',
            next: 'Próximo',
            previous: 'Anterior'
          }
        },
        order: [
          [1, 'desc']
        ],
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
