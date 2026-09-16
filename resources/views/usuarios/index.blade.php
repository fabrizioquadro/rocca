@extends('layouts.app')

@section('title', 'Usuários')

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
@endpush

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Usuários</h4>

      <a href="{{ route('usuarios.create') }}" class="btn btn-primary">
        <i class="ri-user-add-line me-1"></i>Novo usuário
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
        <table id="tabela-usuarios" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>Usuário</th>
              <th>E-mail</th>
              <th>Clínica</th>
              <th>Tipo</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($usuarios as $usuario)
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
                      <a class="dropdown-item" href="{{ route('usuarios.show', $usuario) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>
                      <a class="dropdown-item" href="{{ route('usuarios.edit', $usuario) }}">
                        <i class="ri-pencil-line me-2"></i>Editar
                      </a>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm me-2 flex-shrink-0">
                      <img
                        src="{{ $usuario->imagem ? asset($usuario->imagem) : asset('template/assets/img/avatars/avatar-unisex.svg') }}"
                        alt="{{ $usuario->nome }}"
                        class="rounded-circle" />
                    </div>
                    <span class="fw-semibold">{{ $usuario->nome }}</span>
                  </div>
                </td>
                <td>{{ $usuario->email }}</td>
                <td>{{ $usuario->clinica?->nome ?? '—' }}</td>
                <td>{{ $usuario->tipo?->label() }}</td>
                <td>
                  @php
                    $badge = match ($usuario->status?->value) {
                      'ativo' => 'success',
                      'inativo' => 'warning',
                      default => 'danger',
                    };
                  @endphp
                  <span class="badge bg-label-{{ $badge }}">{{ $usuario->status?->label() }}</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script>
    $(function () {
      $('#tabela-usuarios').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum usuário cadastrado. Clique em "Novo usuário" para cadastrar.',
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
