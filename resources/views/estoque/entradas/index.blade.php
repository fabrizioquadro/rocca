@extends('layouts.app')

@section('title', 'Entradas de Estoque')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Entradas de estoque</h4>

      <a href="{{ route('estoque.entradas.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Nova entrada
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
        <table id="tabela-entradas" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>#</th>
              <th>Clínica</th>
              <th>Fornecedor</th>
              <th>Nota fiscal</th>
              <th>Data</th>
              <th>Lançado por</th>
              <th>Medicamentos</th>
              <th>Qtd. total</th>
              <th>Valor total</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($entradas as $entrada)
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
                      <a class="dropdown-item" href="{{ route('estoque.entradas.show', $entrada) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <form
                        method="POST"
                        action="{{ route('estoque.entradas.destroy', $entrada) }}"
                        data-confirmar="Excluir esta entrada? O estoque será estornado.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                          <i class="ri-delete-bin-7-line me-2"></i>Excluir
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
                <td>{{ $entrada->id }}</td>
                <td>{{ $entrada->clinica?->nome ?? '—' }}</td>
                <td>{{ $entrada->fornecedor?->nome ?? '—' }}</td>
                <td>{{ $entrada->numero_nota ?? '—' }}</td>
                <td>{{ $entrada->data_entrada?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $entrada->user?->nome ?? '—' }}</td>
                <td>
                  <div class="text-muted small">
                    @forelse ($entrada->itens as $item)
                      <div>
                        {{ $item->medicamento?->nome ?? '—' }}
                        <span class="fw-semibold">({{ $item->quantidade }})</span>
                      </div>
                    @empty
                      <div>—</div>
                    @endforelse
                  </div>
                </td>
                <td class="fw-semibold">{{ $entrada->quantidade_total }}</td>
                <td class="fw-semibold">{{ $entrada->valor_total_formatado }}</td>
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
      $('#tabela-entradas').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhuma entrada lançada. Clique em "Nova entrada" para lançar.',
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
