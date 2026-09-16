@extends('layouts.app')

@section('title', 'Pacientes')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Pacientes</h4>

      <form method="POST" action="{{ route('pacientes.sincronizar') }}" class="m-0">
        @csrf
        <button type="submit" class="btn btn-primary">
          <i class="ri-cloud-download-line me-1"></i>Buscar pacientes da Feegow
        </button>
      </form>
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
        <table id="tabela-pacientes" class="table table-sm table-bordered">
          <thead class="table-light">
            <tr>
              <th class="text-center"></th>
              <th>#</th>
              <th>Nome</th>
              <th>Nascimento</th>
              <th>Idade</th>
              <th>CPF</th>
              <th>Telefone</th>
              <th>Celular</th>
              <th>E-mail</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pacientes as $paciente)
              <tr>
                <td class="text-center">
                  <a
                    href="{{ route('pacientes.show', $paciente) }}"
                    class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                    title="Visualizar">
                    <i class="ri-eye-line"></i>
                  </a>
                </td>
                <td>{{ $paciente->paciente_id }}</td>
                <td class="fw-semibold">{{ $paciente->nome }}</td>
                <td>{{ $paciente->nascimento_formatado ?? '—' }}</td>
                <td>{{ $paciente->idade ?? '—' }}</td>
                <td>{{ $paciente->cpf_formatado ?? '—' }}</td>
                <td>{{ $paciente->telefone_formatado ?? '—' }}</td>
                <td>{{ $paciente->celular_formatado ?? '—' }}</td>
                <td>{{ $paciente->email ?? '—' }}</td>
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
      $('#tabela-pacientes').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhum paciente. Clique em "Buscar pacientes da Feegow" para importar.',
          paginate: {
            first: 'Primeiro',
            last: 'Último',
            next: 'Próximo',
            previous: 'Anterior'
          }
        },
        order: [
          [2, 'asc']
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
