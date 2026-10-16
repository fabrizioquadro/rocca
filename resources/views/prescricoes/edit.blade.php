@extends('layouts.app')

@section('title', 'Editar Prescrição #'.$prescricao->id)

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Editar prescrição #{{ $prescricao->id }}</h4>
        <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
        <span class="text-muted">{{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Voltar
      </a>
    </div>

    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger" role="alert">
          <strong>Não foi possível continuar:</strong>
          <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <p class="text-muted small">
        Nesta tela você corrige o cabeçalho, os anexos, os medicamentos e as semanas da prescrição.
        Ao conferir, aparece no fim da página o resumo do que vai mudar — inclusive o efeito no
        financeiro — e nada é gravado antes de você autorizar.
        As semanas que já têm aplicação registrada ficam travadas (cadeado): elas são o histórico do
        que foi aplicado e não podem mudar. O financeiro tem a aba própria na tela da prescrição.
      </p>

      <form method="POST" action="{{ route('prescricoes.update', $prescricao) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="paciente_nome">Paciente</label>
            <input
              type="text"
              id="paciente_nome"
              class="form-control"
              value="{{ $prescricao->paciente?->nome ?? '—' }}"
              readonly />
            <small class="text-muted">O paciente da prescrição não é alterado por aqui.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="medico_id">Médico</label>

            @if ($medicos)
              <select id="medico_id" name="medico_id" class="form-select">
                <option value="">Selecione...</option>

                {{-- Médico gravado na prescrição que não vem mais da Feegow: mantém na lista --}}
                @if ($prescricao->medico_id && ! collect($medicos)->contains(fn ($medico) => (int) $medico['id'] === (int) $prescricao->medico_id))
                  <option value="{{ $prescricao->medico_id }}" data-nome="{{ $prescricao->medico_nome }}" selected>
                    {{ $prescricao->medico_nome ?? 'Médico gravado' }} (fora da lista atual)
                  </option>
                @endif

                @foreach ($medicos as $medico)
                  <option
                    value="{{ $medico['id'] }}"
                    data-nome="{{ $medico['nome'] }}"
                    @selected((int) old('medico_id', $prescricao->medico_id) === (int) $medico['id'])>
                    {{ $medico['conselho'] ? $medico['nome'].' ('.$medico['conselho'].')' : $medico['nome'] }}
                  </option>
                @endforeach
              </select>
              <input type="hidden" id="medico_nome" name="medico_nome" value="{{ old('medico_nome', $prescricao->medico_nome) }}" />
              <small class="text-muted">Médicos carregados da Feegow ({{ count($medicos) }}). Deixe em branco se não houver médico.</small>
            @else
              {{-- Sem a Feegow o nome é editado direto no texto --}}
              <input
                type="text"
                id="medico_nome"
                name="medico_nome"
                class="form-control"
                maxlength="150"
                value="{{ old('medico_nome', $prescricao->medico_nome) }}"
                placeholder="Nome do médico" />
              <input type="hidden" name="medico_id" value="" />
              <small class="text-danger d-block">
                Não foi possível carregar os médicos da Feegow{{ $erroMedicos ? ': '.$erroMedicos : '.' }}
                Preencha o nome à mão (ou deixe em branco).
              </small>
            @endif
          </div>

          <div class="col-md-4">
            <label class="form-label" for="clinica_id">Clínica *</label>
            <select id="clinica_id" name="clinica_id" class="form-select" required>
              <option value="">Selecione...</option>
              @foreach ($clinicas as $clinica)
                <option value="{{ $clinica->id }}" @selected((int) old('clinica_id', $prescricao->clinica_id) === (int) $clinica->id)>
                  {{ $clinica->nome }}
                </option>
              @endforeach
            </select>
            <small class="text-muted">De qual clínica saem os medicamentos desta prescrição.</small>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="tipo_atendimento">Tipo de atendimento *</label>
            <select id="tipo_atendimento" name="tipo_atendimento" class="form-select" required>
              <option value="">Selecione...</option>
              @foreach ($tipos as $tipo)
                <option
                  value="{{ $tipo->value }}"
                  @selected(old('tipo_atendimento', $prescricao->tipo_atendimento?->value) === $tipo->value)>
                  {{ $tipo->label() }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="agendamento">Agendamento</label>
            <input
              type="text"
              id="agendamento"
              name="agendamento"
              class="form-control"
              maxlength="100"
              placeholder="Ex.: Terça-feira às 14h"
              value="{{ old('agendamento', $prescricao->agendamento) }}" />
          </div>

          <div class="col-12">
            <label class="form-label" for="observacoes">Observações</label>
            <textarea
              id="observacoes"
              name="observacoes"
              rows="2"
              class="form-control">{{ old('observacoes', $prescricao->observacoes) }}</textarea>
          </div>

          <div class="col-12">
            <hr class="my-2" />

            @include('prescricoes._campo-anexos')
          </div>
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Medicamentos e semanas</h6>

          <button type="button" id="adicionar-semana" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i>Adicionar semana
          </button>
        </div>

        <div id="semanas"></div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <small class="text-muted">
            Valor atual da prescrição: <strong>{{ $prescricao->valor_total_formatado }}</strong>
            @if ($financeiro['existe'])
              · financeiro: <strong>{{ number_format($financeiro['total'], 2, ',', '.') }}</strong>
              @if ($financeiro['recebido'] > 0)
                (já recebido {{ number_format($financeiro['recebido'], 2, ',', '.') }})
              @endif
            @endif
          </small>

          <div class="fw-semibold">Total das semanas (novo): <span id="total-geral">R$ 0,00</span></div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="ri-file-list-3-line me-1"></i>Conferir alterações
          </button>
          <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
            Cancelar
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- O resumo do que vai mudar aparece no fim da própria tela, depois de conferir --}}
  @if (! empty($resumoAlteracoes))
    @include('prescricoes.medicamentos._resumo', [
      'plano' => $resumoAlteracoes['plano'],
      'resumo' => $resumoAlteracoes['resumo'],
      'avisos' => $resumoAlteracoes['avisos'],
      'financeiro' => $resumoAlteracoes['financeiro'],
    ])
  @endif

  {{-- Modelo de semana (clonado pelo JS para cada semana, incluindo as novas) --}}
  <template id="modelo-semana">
    @include('prescricoes.medicamentos._modelo-semana')
  </template>

  {{-- Modelo de item, usado dentro de qualquer semana --}}
  <template id="modelo-item">
    @include('prescricoes.medicamentos._modelo-item', ['medicamentos' => $medicamentos, 'combos' => $combos])
  </template>
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/select2/select2.css') }}" />

  <style>
    /* Semana marcada como "sem aplicação" fica com tonalidade escura */
    [data-semana].semana-sem-aplicacao {
      background-color: rgba(0, 0, 0, .18) !important;
      border-color: rgba(0, 0, 0, .35) !important;
    }

    /* Semana com aplicação registrada: travada, só para leitura */
    [data-semana][data-bloqueada="1"] {
      background-color: rgba(0, 0, 0, .04);
    }
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  <script>
    window.prescricaoSemanas = @json(array_values($semanas));
    window.prescricaoEstado = @json($estado);

    // O nome do médico é gravado junto com o id (a Feegow pode mudar/remover o profissional)
    document.addEventListener('DOMContentLoaded', function () {
      const medicoSelect = document.getElementById('medico_id');
      const campoMedicoNome = document.getElementById('medico_nome');

      if (! medicoSelect || ! campoMedicoNome) return;

      const atualizarMedicoNome = () => {
        const opcao = medicoSelect.options[medicoSelect.selectedIndex];

        campoMedicoNome.value = opcao ? (opcao.getAttribute('data-nome') || '') : '';
      };

      // Só depois do carregamento, para não apagar o nome já gravado
      medicoSelect.addEventListener('change', atualizarMedicoNome);
    });
  </script>

  @include('prescricoes._campo-anexos-script')
  @include('prescricoes.medicamentos._script')
@endpush
