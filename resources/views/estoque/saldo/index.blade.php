@extends('layouts.app')

@section('title', 'Saldo de Estoque')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Saldo de estoque</h4>
    </div>

    <div class="card-body">
      @if (session('error'))
        <div class="alert alert-danger" role="alert">
          {{ session('error') }}
        </div>
      @endif

      <div class="table-responsive text-nowrap">
        <table id="tabela-saldo" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Medicamento</th>
              <th>Grupo</th>
              <th>Fabricante</th>
              <th class="text-end">Em estoque</th>
              <th>Estoque mínimo</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($medicamentos as $medicamento)
              @php
                $saldo = (int) $medicamento->saldo;
                $mgAbertos = (float) ($medicamento->mg_abertos ?? 0);
                $temEstoque = $saldo > 0 || $mgAbertos > 0;
                $abaixoDoMinimo = $medicamento->estoque_minimo !== null && $saldo < (int) $medicamento->estoque_minimo;
              @endphp
              <tr>
                <td class="text-center">
                  <a
                    href="{{ route('estoque.saldo.show', $medicamento) }}"
                    class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                    title="Visualizar">
                    <i class="ri-eye-line"></i>
                  </a>
                </td>
                <td class="fw-semibold">{{ $medicamento->nome }}</td>
                <td>{{ $medicamento->grupo?->nome ?? '—' }}</td>
                <td>{{ $medicamento->fabricante ?? '—' }}</td>
                <td class="text-end fw-semibold {{ $temEstoque ? '' : 'text-danger' }}">
                  {{ $saldo }}

                  @if ($medicamento->eh_miligrama)
                    <small class="d-block fw-normal text-body-secondary">fechado(s)</small>
                    <small class="d-block fw-normal text-body-secondary">
                      {{ \App\Support\Numero::formatar($mgAbertos) }} mg abertos
                    </small>
                  @endif
                </td>
                <td>{{ $medicamento->estoque_minimo ?? '—' }}</td>
                <td>
                  @if ($abaixoDoMinimo)
                    <span class="badge bg-label-danger">Abaixo do mínimo</span>
                  @elseif ($temEstoque)
                    <span class="badge bg-label-success">Em estoque</span>
                  @else
                    <span class="badge bg-label-secondary">Sem estoque</span>
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
      $('#tabela-saldo').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum medicamento cadastrado.',
          paginate: {
            first: 'Primeiro',
            last: 'Último',
            next: 'Próximo',
            previous: 'Anterior'
          }
        },
        order: [
          [1, 'asc']
        ],
        columnDefs: [{
          targets: 0,
          orderable: false
        }],
        pageLength: 10
      });
    });
  </script>
@endpush
