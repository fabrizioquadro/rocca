@extends('layouts.app')

@section('title', 'Fornecedores')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Fornecedores</h4>

      <a href="{{ route('fornecedores.create') }}" class="btn btn-primary">
        <i class="ri-add-line me-1"></i>Novo fornecedor
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

      {{-- Listagem --}}
      <div class="table-responsive text-nowrap">
        <table id="tabela-fornecedores" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Nome</th>
              <th>CNPJ</th>
              <th>E-mail</th>
              <th>Telefone</th>
              <th>Celular</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($fornecedores as $fornecedor)
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
                      <a class="dropdown-item" href="{{ route('fornecedores.show', $fornecedor) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <a class="dropdown-item" href="{{ route('fornecedores.edit', $fornecedor) }}">
                        <i class="ri-pencil-line me-2"></i>Editar
                      </a>
                      <form
                        method="POST"
                        action="{{ route('fornecedores.destroy', $fornecedor) }}"
                        data-confirmar="Deseja realmente excluir este fornecedor?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                          <i class="ri-delete-bin-7-line me-2"></i>Excluir
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
                <td class="fw-semibold">{{ $fornecedor->nome }}</td>
                <td>{{ $fornecedor->cnpj_formatado ?? '—' }}</td>
                <td>{{ $fornecedor->email ?? '—' }}</td>
                <td>{{ $fornecedor->telefone_formatado ?? '—' }}</td>
                <td>{{ $fornecedor->celular_formatado ?? '—' }}</td>
                <td>
                  <span class="badge bg-label-{{ $fornecedor->status?->value === 'ativo' ? 'success' : 'warning' }}">
                    {{ $fornecedor->status?->label() }}
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
      $('#tabela-fornecedores').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum fornecedor cadastrado. Clique em "Novo fornecedor" para cadastrar.',
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
