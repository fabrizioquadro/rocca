@extends('layouts.app')

@section('title', 'Clínicas')

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
@endpush

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Clínicas</h4>

      <form method="POST" action="{{ route('clinicas.buscarFeegow') }}" class="m-0">
        @csrf
        <button type="submit" class="btn btn-primary">
          <i class="ri-cloud-download-line me-1"></i>Buscar clínicas da Feegow
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

      @if ($errors->any())
        <div class="alert alert-danger" role="alert">
          @foreach ($errors->all() as $error)
            {{ $error }}<br />
          @endforeach
        </div>
      @endif

      <div class="table-responsive text-nowrap">
        <table id="tabela-clinicas" class="table table-bordered">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>CNPJ</th>
              <th>ID Feegow</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($clinicas as $clinica)
              <tr>
                <td>{{ $clinica->id }}</td>
                <td class="fw-semibold">{{ $clinica->nome }}</td>
                <td>{{ $clinica->cnpj }}</td>
                <td>{{ $clinica->id_feegow }}</td>
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
      $('#tabela-clinicas').DataTable({
        language: {
          search: 'Buscar:',
          lengthMenu: 'Mostrar _MENU_ registros',
          info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
          infoEmpty: 'Nenhum registro',
          infoFiltered: '(filtrado de _MAX_ no total)',
          zeroRecords: 'Nenhum registro encontrado',
          emptyTable: 'Nenhuma clínica cadastrada. Clique em "Buscar clínicas da Feegow" para importar.',
          paginate: {
            first: 'Primeiro',
            last: 'Último',
            next: 'Próximo',
            previous: 'Anterior'
          },
          aria: {
            sortAscending: ': ative para ordenar a coluna em ordem crescente',
            sortDescending: ': ative para ordenar a coluna em ordem decrescente'
          }
        },
        order: [
          [1, 'asc']
        ],
        pageLength: 10
      });
    });
  </script>
@endpush
