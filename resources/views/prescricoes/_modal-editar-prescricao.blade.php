{{--
  Edição dos dados da prescrição em modal: médico (lista da Feegow carregada na
  abertura), clínica, tipo de atendimento, agendamento e observações.
  Formulário dentro do corpo do modal, com o botão do rodapé ligado por form=
  (o Bootstrap espera header/body/footer como filhos diretos do .modal-content).
--}}
<div class="modal fade" id="modal-editar-prescricao" tabindex="-1" aria-labelledby="modal-editar-prescricao-titulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-editar-prescricao-titulo">
          <i class="ri-file-edit-line me-1"></i>Editar dados da prescrição
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        {{-- Paciente não é alterado aqui: trocaria a base da prescrição inteira --}}
        <p class="text-muted small mb-3">
          Paciente: <strong>{{ $prescricao->paciente?->nome ?? '—' }}</strong>
          — as semanas, os medicamentos e o financeiro continuam com as telas próprias.
        </p>

        <form
          method="POST"
          action="{{ route('prescricoes.update', $prescricao) }}"
          id="form-editar-prescricao">
          @csrf
          @method('PUT')
          <input type="hidden" name="origem" value="imprimir" />

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="modal_medico_id">Médico</label>
              <select
                id="modal_medico_id"
                name="medico_id"
                class="form-select"
                data-medico-select>
                <option value="">Sem médico</option>
                @if ($prescricao->medico_id)
                  <option value="{{ $prescricao->medico_id }}" data-nome="{{ $prescricao->medico_nome }}" selected>
                    {{ $prescricao->medico_nome ?? 'Médico atual' }}
                  </option>
                @endif
              </select>
              <small class="text-muted d-block" data-medico-aviso>Carregando a lista de médicos...</small>
              <input type="hidden" id="modal_medico_nome" name="medico_nome" value="{{ $prescricao->medico_nome }}" />
            </div>

            <div class="col-md-6">
              <label class="form-label" for="modal_clinica_id">Clínica *</label>
              <select id="modal_clinica_id" name="clinica_id" class="form-select" required>
                @foreach ($clinicas as $clinica)
                  <option value="{{ $clinica->id }}" @selected((int) $prescricao->clinica_id === (int) $clinica->id)>
                    {{ $clinica->nome }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="modal_tipo_atendimento">Tipo de atendimento *</label>
              <select id="modal_tipo_atendimento" name="tipo_atendimento" class="form-select" required>
                @foreach ($tipos as $tipo)
                  <option value="{{ $tipo->value }}" @selected($prescricao->tipo_atendimento === $tipo)>
                    {{ $tipo->label() }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="modal_agendamento">Agendamento</label>
              <input
                type="text"
                id="modal_agendamento"
                name="agendamento"
                class="form-control"
                maxlength="100"
                placeholder="Ex.: Terça-feira às 14h"
                value="{{ $prescricao->agendamento }}" />
            </div>

            <div class="col-12">
              <label class="form-label" for="modal_observacoes">Observações</label>
              <textarea
                id="modal_observacoes"
                name="observacoes"
                rows="2"
                class="form-control"
                maxlength="2000">{{ $prescricao->observacoes }}</textarea>
            </div>
          </div>
        </form>

        <div class="alert alert-warning py-2 mb-0 mt-3" role="alert">
          <i class="ri-information-line me-1"></i>
          Trocar a clínica muda de onde saem os medicamentos e onde o dinheiro é contabilizado
          (o financeiro acompanha). A alteração fica no histórico.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary" form="form-editar-prescricao">
          <i class="ri-save-3-line me-1"></i>Salvar dados
        </button>
      </div>
    </div>
  </div>
</div>
