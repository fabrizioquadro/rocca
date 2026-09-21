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

      @if ($errors->any())
        <div class="alert alert-danger" role="alert">
          @foreach ($errors->all() as $error)
            {{ $error }}<br />
          @endforeach
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
              <th>Observação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pacientes as $paciente)
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
                      <a class="dropdown-item" href="{{ route('pacientes.show', $paciente) }}">
                        <i class="ri-eye-line me-2"></i>Visualizar
                      </a>

                      {{-- Observação interna: o modal é único e se preenche com estes dados --}}
                      <button
                        type="button"
                        class="dropdown-item"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-observacao"
                        data-paciente-id="{{ $paciente->id }}"
                        data-paciente-nome="{{ $paciente->nome }}"
                        data-paciente-observacao="{{ $paciente->observacao }}">
                        <i class="ri-chat-1-line me-2"></i>Observação
                      </button>
                    </div>
                  </div>
                </td>
                <td>{{ $paciente->paciente_id }}</td>
                <td class="fw-semibold">{{ $paciente->nome }}</td>
                <td>{{ $paciente->nascimento_formatado ?? '—' }}</td>
                <td>{{ $paciente->idade ?? '—' }}</td>
                <td>{{ $paciente->cpf_formatado ?? '—' }}</td>
                <td>{{ $paciente->telefone_formatado ?? '—' }}</td>
                <td>{{ $paciente->celular_formatado ?? '—' }}</td>
                <td>{{ $paciente->email ?? '—' }}</td>
                <td class="text-wrap" style="max-width: 280px;">
                  @if ($paciente->observacao)
                    <span title="{{ $paciente->observacao }}">
                      {{ \Illuminate\Support\Str::limit($paciente->observacao, 90) }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Observação do paciente: um único modal, preenchido pelo JS com os dados da linha --}}
  <div
    class="modal fade"
    id="modal-observacao"
    tabindex="-1"
    aria-hidden="true"
    data-url-base="{{ url('pacientes') }}">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" id="form-observacao">
          @csrf
          @method('PUT')

          <input type="hidden" name="paciente_id" id="observacao-paciente-id" value="{{ old('paciente_id') }}" />

          <div class="modal-header">
            <h5 class="modal-title">Observação do paciente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>

          <div class="modal-body">
            <p class="fw-semibold mb-2" data-observacao-paciente></p>

            <label class="form-label" for="observacao">Observação</label>
            <textarea
              id="observacao"
              name="observacao"
              class="form-control @error('observacao') is-invalid @enderror"
              rows="6"
              maxlength="2000"
              placeholder="Anotações internas sobre o paciente">{{ old('observacao') }}</textarea>
            @error('observacao')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <small class="text-muted d-block mt-2">
              Campo interno: não é sobrescrito quando os pacientes são buscados da Feegow.
            </small>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-line me-1"></i>Salvar observação
            </button>
          </div>
        </form>
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

      // O modal de observação é único: os dados vêm do item de menu que o abriu.
      const modalObservacao = document.getElementById('modal-observacao');
      const formObservacao = document.getElementById('form-observacao');
      const campoObservacao = document.getElementById('observacao');

      modalObservacao.addEventListener('show.bs.modal', function (evento) {
        const botao = evento.relatedTarget;

        // Sem relatedTarget a abertura é programática (voltou com erro):
        // mantém o texto que o usuário já tinha digitado.
        if (!botao) return;

        const id = botao.dataset.pacienteId;

        formObservacao.action = `${modalObservacao.dataset.urlBase}/${id}/observacao`;
        document.getElementById('observacao-paciente-id').value = id;
        document.querySelector('[data-observacao-paciente]').textContent = botao.dataset.pacienteNome;
        campoObservacao.value = botao.dataset.pacienteObservacao || '';
      });
    });
  </script>

  @if ($errors->has('observacao'))
    <script>
      // Voltou com erro de validação: reabre o modal no mesmo paciente
      document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('modal-observacao');
        const id = document.getElementById('observacao-paciente-id').value;
        const botao = document.querySelector(`[data-paciente-id="${id}"]`);

        document.getElementById('form-observacao').action = `${modal.dataset.urlBase}/${id}/observacao`;
        document.querySelector('[data-observacao-paciente]').textContent =
          botao ? botao.dataset.pacienteNome : `Paciente #${id}`;

        new bootstrap.Modal(modal).show();
      });
    </script>
  @endif
@endpush
