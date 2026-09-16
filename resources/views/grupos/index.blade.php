@extends('layouts.app')

@section('title', 'Grupos')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Grupos</h4>

      <a href="{{ route('grupos.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Novo grupo
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
        <table id="tabela-grupos" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Nome</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($grupos as $grupo)
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
                      <a class="dropdown-item" href="{{ route('grupos.show', $grupo) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <a class="dropdown-item" href="{{ route('grupos.edit', $grupo) }}">
                        <i class="ri-pencil-line me-2"></i>Editar
                      </a>
                      <form
                        method="POST"
                        action="{{ route('grupos.destroy', $grupo) }}"
                        data-confirmar="Deseja realmente excluir este grupo?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                          <i class="ri-delete-bin-7-line me-2"></i>Excluir
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
                <td class="fw-semibold">{{ $grupo->nome }}</td>
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
      $('#tabela-grupos').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum grupo cadastrado. Clique em "Novo grupo" para cadastrar.',
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
