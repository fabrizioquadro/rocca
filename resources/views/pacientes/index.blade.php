@extends('layouts.app')

@section('title', 'Pacientes')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Pacientes</h4>

      <div class="d-flex flex-wrap align-items-center gap-2">
        <form
          method="POST"
          action="{{ route('pacientes.integrar-omie-lote') }}"
          class="m-0"
          data-omie-lote
          data-total="{{ $pendentesOmie }}">
          @csrf
          <button type="submit" class="btn btn-outline-primary" data-omie-botao @disabled($pendentesOmie === 0)>
            <i class="ri-links-line me-1"></i>Integrar com a Omie
            @if ($pendentesOmie > 0)
              <span class="badge bg-label-primary ms-1">{{ $pendentesOmie }}</span>
            @endif
          </button>
        </form>

        <form method="POST" action="{{ route('pacientes.sincronizar') }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-primary">
            <i class="ri-cloud-download-line me-1"></i>Buscar pacientes da Feegow
          </button>
        </form>
      </div>
    </div>

    <div class="card-body">
      {{-- Progresso da integração em lote (a tela chama a rota em lotes) --}}
      <div class="mb-4 d-none" data-omie-progresso>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <small class="text-muted" data-omie-progresso-texto>Integrando com a Omie…</small>
          <small class="text-muted fw-semibold" data-omie-progresso-percentual>0%</small>
        </div>
        <div class="progress" style="height: 8px;">
          <div
            class="progress-bar progress-bar-striped progress-bar-animated"
            role="progressbar"
            style="width: 0%"
            aria-valuenow="0"
            aria-valuemin="0"
            aria-valuemax="100"
            data-omie-progresso-barra></div>
        </div>
      </div>

      <div class="alert alert-warning d-none" role="alert" data-omie-falhas></div>

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
              <th>Omie</th>
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

                      @unless ($paciente->integrado_omie)
                        <form method="POST" action="{{ route('pacientes.integrar-omie', $paciente) }}">
                          @csrf
                          <button type="submit" class="dropdown-item">
                            <i class="ri-links-line me-2"></i>Integrar com a Omie
                          </button>
                        </form>
                      @endunless
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
                <td class="text-center">
                  @if ($paciente->integrado_omie)
                    <span
                      class="badge bg-label-success"
                      title="Código do cliente na Omie{{ $paciente->omie_integrado_em ? ' · integrado em '.$paciente->omie_integrado_em->format('d/m/Y H:i') : '' }}">
                      {{ $paciente->codigo_cliente_omie }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
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

  {{-- Confirmação de envio (data-confirmar) dos botões de integração --}}
  @include('partials.crud-scripts')

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

      // ---- Integração em lote com a Omie ----
      // Cada paciente gasta duas chamadas externas (~2,5 s): a tela pede um
      // lote por vez e mostra o progresso, para nenhuma requisição estourar
      // o tempo limite do servidor.
      const formLote = document.querySelector('[data-omie-lote]');

      if (formLote) {
        const botaoLote = formLote.querySelector('[data-omie-botao]');
        const caixa = document.querySelector('[data-omie-progresso]');
        const barra = document.querySelector('[data-omie-progresso-barra]');
        const texto = document.querySelector('[data-omie-progresso-texto]');
        const percentual = document.querySelector('[data-omie-progresso-percentual]');
        const caixaFalhas = document.querySelector('[data-omie-falhas]');

        const total = parseInt(formLote.dataset.total, 10) || 0;
        const token = formLote.querySelector('input[name="_token"]').value;

        const pintarProgresso = (processados, integrados) => {
          const feito = total > 0 ? Math.min(100, Math.round((processados / total) * 100)) : 0;

          barra.style.width = feito + '%';
          barra.setAttribute('aria-valuenow', feito);
          percentual.textContent = feito + '%';
          texto.textContent = `Integrando com a Omie… ${processados} de ${total} (${integrados} concluído(s))`;
        };

        const listarFalhas = (falhas) => {
          caixaFalhas.innerHTML = '';

          if (!falhas.length) {
            caixaFalhas.classList.add('d-none');

            return;
          }

          const titulo = document.createElement('div');
          titulo.className = 'fw-semibold mb-1';
          titulo.textContent = falhas.length + ' paciente(s) sem os dados fiscais necessários:';
          caixaFalhas.appendChild(titulo);

          const lista = document.createElement('ul');
          lista.className = 'mb-0 small';

          falhas.slice(0, 15).forEach((falha) => {
            const item = document.createElement('li');
            item.textContent = `${falha.nome}: ${falha.motivo}`;
            lista.appendChild(item);
          });

          if (falhas.length > 15) {
            const item = document.createElement('li');
            item.textContent = `… e mais ${falhas.length - 15}`;
            lista.appendChild(item);
          }

          caixaFalhas.appendChild(lista);
          caixaFalhas.classList.remove('d-none');
        };

        formLote.addEventListener('submit', async function (evento) {
          evento.preventDefault();

          if (!window.confirm(`Integrar ${total} paciente(s) pendente(s) com a Omie? Pode levar alguns minutos — a tela mostra o progresso.`)) {
            return;
          }

          botaoLote.disabled = true;
          caixa.classList.remove('d-none');
          caixaFalhas.classList.add('d-none');
          pintarProgresso(0, 0);

          const falhas = [];
          let integrados = 0;
          let processados = 0;

          try {
            for (let rodada = 0; rodada < 500; rodada++) {
              const resposta = await fetch(formLote.action, {
                method: 'POST',
                headers: {
                  'Accept': 'application/json',
                  'X-CSRF-TOKEN': token,
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ignorar: falhas.map((falha) => falha.id) }),
              });

              const dados = await resposta.json().catch(() => ({}));

              if (!resposta.ok || !dados.ok) {
                throw new Error(dados.mensagem || 'Falha ao integrar com a Omie.');
              }

              integrados += dados.integrados || 0;
              processados += (dados.integrados || 0) + (dados.falhas || []).length;
              (dados.falhas || []).forEach((falha) => falhas.push(falha));

              pintarProgresso(processados, integrados);
              listarFalhas(falhas);

              if (!dados.restantes) {
                break;
              }

              // Sem nenhum avanço (nem integrado, nem falha nova): evita repetir
              // o mesmo lote para sempre.
              if (!dados.integrados && !(dados.falhas || []).length) {
                break;
              }
            }
          } catch (erro) {
            caixaFalhas.textContent = erro.message;
            caixaFalhas.classList.remove('d-none');
          }

          texto.textContent = `Concluído: ${integrados} paciente(s) integrado(s)`
            + (falhas.length ? `, ${falhas.length} sem dados fiscais.` : '.');
          percentual.textContent = '100%';
          barra.style.width = '100%';
          barra.classList.remove('progress-bar-animated');
          botaoLote.disabled = false;

          // Recarrega para atualizar a coluna Omie e o contador de pendentes.
          if (integrados > 0 && !falhas.length) {
            window.setTimeout(() => window.location.reload(), 1500);
          }
        });
      }
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
