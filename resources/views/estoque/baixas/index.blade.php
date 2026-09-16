@extends('layouts.app')

@section('title', 'Baixas de Estoque')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Baixas de estoque</h4>

      <a href="{{ route('estoque.baixas.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Nova baixa
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
        <table id="tabela-baixas" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>#</th>
              <th>Clínica</th>
              <th>Data</th>
              <th>Medicamentos</th>
              <th>Lançado por</th>
              <th>Qtd.</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($baixas as $baixa)
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
                      <a class="dropdown-item" href="{{ route('estoque.baixas.show', $baixa) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <form
                        method="POST"
                        action="{{ route('estoque.baixas.destroy', $baixa) }}"
                        data-confirmar="Excluir esta baixa? As unidades voltam para o estoque.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                          <i class="ri-delete-bin-7-line me-2"></i>Excluir
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
                <td>{{ $baixa->id }}</td>
                <td>{{ $baixa->clinica?->nome ?? '—' }}</td>
                <td>{{ $baixa->data?->format('d/m/Y') ?? '—' }}</td>
                <td>
                  <div class="text-muted small">
                    @forelse ($baixa->itens as $item)
                      <div>
                        {{ $item->medicamento?->nome ?? '—' }}
                        <span class="fw-semibold">({{ $item->quantidade }})</span>
                        <span class="text-body-secondary">· cód. {{ $item->entradaItem?->codigo_barras ?? '—' }}</span>
                        <div class="text-body-secondary">Motivo: {{ $item->motivo ?? '—' }}</div>
                      </div>
                    @empty
                      <div>—</div>
                    @endforelse
                  </div>
                </td>
                <td>{{ $baixa->user?->nome ?? '—' }}</td>
                <td class="fw-semibold">{{ $baixa->quantidade_total }}</td>
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
      $('#tabela-baixas').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhuma baixa lançada. Clique em "Nova baixa" para lançar.',
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
