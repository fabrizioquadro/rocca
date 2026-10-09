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
          <strong>Corrija os erros abaixo:</strong><br />
          @foreach ($errors->all() as $error)
            {{ $error }}<br />
          @endforeach
        </div>
      @endif

      <p class="text-muted small">
        Aqui você corrige os dados do cabeçalho da prescrição. As semanas, os medicamentos e o financeiro
        continuam com as telas próprias — esta edição não mexe neles.
      </p>

      <form method="POST" action="{{ route('prescricoes.update', $prescricao) }}">
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
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Salvar alterações
          </button>
          <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
            Cancelar
          </a>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
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
@endpush
