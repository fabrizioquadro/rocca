@extends('layouts.app')

@section('title', 'Medicamentos')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Medicamentos</h4>

      <a href="{{ route('medicamentos.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Novo medicamento
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
        <table id="tabela-medicamentos" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Nome</th>
              <th>Fabricante</th>
              <th>Tipo</th>
              <th>Grupo</th>
              <th>Últ. valor pago</th>
              <th>Valor de venda</th>
              <th>Est. mínimo</th>
              <th>Est. médio</th>
              <th>Gera aplicação</th>
              <th>Feegow aplicação</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($medicamentos as $medicamento)
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
                      <a class="dropdown-item" href="{{ route('medicamentos.show', $medicamento) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <a class="dropdown-item" href="{{ route('medicamentos.edit', $medicamento) }}">
                        <i class="ri-pencil-line me-2"></i>Editar
                      </a>
                      <form
                        method="POST"
                        action="{{ route('medicamentos.destroy', $medicamento) }}"
                        data-confirmar="Deseja realmente excluir este medicamento?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                          <i class="ri-delete-bin-7-line me-2"></i>Excluir
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="fw-semibold">{{ $medicamento->nome }}</span>
                  @if ($medicamento->tamanho_vasilhame !== null)
                    <small class="text-muted d-block">Vasilhame: {{ $medicamento->tamanho_vasilhame_formatado }}</small>
                  @endif
                </td>
                <td>{{ $medicamento->fabricante ?? '—' }}</td>
                <td>{{ $medicamento->tipo?->label() ?? '—' }}</td>
                <td>{{ $medicamento->grupo?->nome ?? '—' }}</td>
                <td>{{ $medicamento->ultimo_valor_pago_formatado ?? '—' }}</td>
                <td>{{ $medicamento->valor_venda_formatado ?? '—' }}</td>
                <td>{{ $medicamento->estoque_minimo ?? '—' }}</td>
                <td>{{ $medicamento->estoque_medio ?? '—' }}</td>
                <td>
                  <span class="badge bg-label-{{ $medicamento->gera_aplicacao ? 'success' : 'secondary' }}">
                    {{ $medicamento->gera_aplicacao ? 'Sim' : 'Não' }}
                  </span>
                </td>
                <td>{{ $medicamento->feegow_aplicacao_id ?? '—' }}</td>
                <td>
                  <span class="badge bg-label-{{ $medicamento->status?->value === 'ativo' ? 'success' : 'warning' }}">
                    {{ $medicamento->status?->label() }}
                  </span>
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
      $('#tabela-medicamentos').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum medicamento cadastrado. Clique em "Novo medicamento" para cadastrar.',
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

  @include('partials.crud-scripts')
@endpush
