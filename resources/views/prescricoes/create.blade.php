@extends('layouts.app')

@section('title', 'Nova Prescrição')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Nova prescrição</h4>

      <a href="{{ route('prescricoes.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('prescricoes.store') }}" id="form-prescricao" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="paciente_id">Paciente *</label>
            <select id="paciente_id" name="paciente_id" class="form-select" required>
              @if ($pacienteSelecionado)
                <option
                  value="{{ $pacienteSelecionado->id }}"
                  data-observacao="{{ $pacienteSelecionado->observacao }}"
                  selected>
                  {{ $pacienteSelecionado->nome }}
                </option>
              @endif
            </select>
            <small class="text-muted">
              Digite o nome (ou o CPF) para buscar. Os pacientes vêm da base importada da Feegow.
            </small>

            {{-- Observação cadastrada no paciente: aparece antes de montar a prescrição --}}
            <div
              class="alert alert-warning d-flex gap-2 {{ $pacienteSelecionado?->observacao ? '' : 'd-none' }} mt-3 mb-0"
              id="aviso-observacao-paciente"
              role="alert">
              <i class="ri-alert-line ri-20px"></i>
              <div>
                <strong class="d-block">Atenção: observação do paciente</strong>
                <span
                  id="aviso-observacao-paciente-texto"
                  style="white-space: pre-line;">{{ $pacienteSelecionado?->observacao }}</span>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="medico_id">Médico</label>
            <select id="medico_id" name="medico_id" class="form-select">
              <option value="">Selecione...</option>
              @foreach ($medicos as $medico)
                <option value="{{ $medico['id'] }}" data-nome="{{ $medico['nome'] }}" @selected(old('medico_id') == $medico['id'])>
                  {{ $medico['conselho'] ? $medico['nome'].' ('.$medico['conselho'].')' : $medico['nome'] }}
                </option>
              @endforeach
            </select>
            <input type="hidden" id="medico_nome" name="medico_nome" value="{{ old('medico_nome') }}" />
            <small class="text-muted">Médicos carregados da Feegow ({{ count($medicos) }}).</small>
            @if ($erroMedicos)
              <small class="text-danger d-block">Não foi possível carregar os médicos: {{ $erroMedicos }}</small>
            @endif
          </div>

          <div class="col-md-4">
            <label class="form-label" for="clinica_id">Clínica *</label>

            @if ($clinicaUsuario)
              <input
                type="text"
                class="form-control"
                id="clinica_nome"
                value="{{ $clinicas->firstWhere('id', $clinicaUsuario)?->nome ?? 'Clínica do usuário logado' }}"
                readonly />
              <input type="hidden" name="clinica_id" value="{{ $clinicaUsuario }}" />
            @else
              <select id="clinica_id" name="clinica_id" class="form-select" required>
                <option value="">Selecione...</option>
                @foreach ($clinicas as $clinica)
                  <option value="{{ $clinica->id }}" @selected(old('clinica_id') == $clinica->id)>
                    {{ $clinica->nome }}
                  </option>
                @endforeach
              </select>
            @endif
          </div>

          <div class="col-md-4">
            <label class="form-label" for="tipo_atendimento">Tipo de atendimento *</label>
            <select id="tipo_atendimento" name="tipo_atendimento" class="form-select" required>
              <option value="">Selecione...</option>
              @foreach ($tipos as $tipo)
                <option value="{{ $tipo->value }}" @selected(old('tipo_atendimento') === $tipo->value)>
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
              value="{{ old('agendamento') }}" />
          </div>

          <div class="col-12">
            <label class="form-label" for="observacoes">Observações</label>
            <textarea
              id="observacoes"
              name="observacoes"
              rows="2"
              class="form-control">{{ old('observacoes') }}</textarea>
          </div>
        </div>

        <hr class="my-4" />

        <div class="mb-3">
          <label class="form-label" for="anexos">
            Anexos (exames, receitas, documentos e etc.)
            <span class="text-danger d-none" data-anexo-obrigatorio>obrigatório</span>
          </label>
          <input
            type="file"
            id="anexos"
            name="anexos[]"
            class="form-control @error('anexos') is-invalid @enderror @error('anexos.*') is-invalid @enderror"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.csv" />
          <small class="text-muted">PDF, imagem, XML ou CSV — máx. 10MB por arquivo</small>
          <div class="text-danger small d-none" data-aviso-anexo>
            Esta prescrição tem medicamento do tipo <strong>ampola</strong> ou <strong>miligrama</strong>
            com aplicação, então o anexo da prescrição médica é obrigatório.
          </div>
          @error('anexos')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
          @error('anexos.*')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Semanas</h6>

          <div class="d-flex gap-2">
            <button type="button" id="abrir-gerador" class="btn btn-sm btn-outline-info">
              <i class="ri-magic-line me-1"></i>Gerador de semanas
            </button>
            <button type="button" id="adicionar-semana" class="btn btn-sm btn-outline-primary">
              <i class="ri-add-line me-1"></i>Adicionar semana
            </button>
          </div>
        </div>

        <div id="semanas"></div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Desconto e adicional</h6>
          <small class="text-muted">
            Divididos igualmente entre as parcelas. O desconto é aplicado agora e não muda depois,
            mesmo que as semanas sejam alteradas.
          </small>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="desconto_tipo">Desconto</label>
            <select id="desconto_tipo" name="desconto_tipo" class="form-select">
              <option value="">Sem desconto</option>
              <option value="porcentagem" @selected(old('desconto_tipo') === 'porcentagem')>Porcentagem (%)</option>
              <option value="valor" @selected(old('desconto_tipo') === 'valor')>Valor fixo (R$)</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="desconto_valor">Valor do desconto</label>
            <div class="input-group">
              <input
                type="text"
                id="desconto_valor"
                name="desconto_valor"
                class="form-control"
                inputmode="decimal"
                placeholder="Ex.: 10 ou 50,00"
                value="{{ old('desconto_valor') }}"
                disabled />
              <span class="input-group-text" id="desconto-unidade">—</span>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="adicional_valor">Adicional (R$)</label>
            <input
              type="text"
              id="adicional_valor"
              name="adicional_valor"
              class="form-control"
              inputmode="decimal"
              placeholder="Ex.: 30,00"
              value="{{ old('adicional_valor') }}" />
          </div>
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Parcelas</h6>
          <small class="text-muted">
            Uma parcela para cada semana com aplicação, com vencimento na data da aplicação
          </small>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 110px;">Parcela</th>
                <th style="width: 165px;">Vencimento</th>
                <th>Semana</th>
                <th style="width: 150px;" class="text-end">Valor bruto</th>
                <th style="width: 150px;" class="text-end d-none" data-coluna-desconto>Desconto</th>
                <th style="width: 150px;" class="text-end d-none" data-coluna-adicional>Adicional</th>
                <th style="width: 150px;" class="text-end">Valor</th>
              </tr>
            </thead>
            <tbody id="parcelas-corpo"></tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total das parcelas</th>
                <th class="text-end" id="total-bruto">R$ 0,00</th>
                <th class="text-end text-danger d-none" data-coluna-desconto>
                  - <span id="total-desconto">R$ 0,00</span>
                </th>
                <th class="text-end text-success d-none" data-coluna-adicional>
                  + <span id="total-adicional">R$ 0,00</span>
                </th>
                <th class="text-end" id="total-parcelas">R$ 0,00</th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <small class="text-muted">
            Desconto e adicional divididos igualmente entre as parcelas (o valor de cada semana não muda a cota).
            <span class="d-none" data-desconto-resumo>
              Desconto: <strong><span data-desconto-descricao>0%</span></strong>.
            </span>
          </small>

          <div class="fw-semibold">Total das semanas: <span id="total-geral">R$ 0,00</span></div>
        </div>

        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-3">
          <a href="{{ route('prescricoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Salvar prescrição
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modelo de semana (o JS clona para cada semana) --}}
  <template id="modelo-semana">
    <div class="border rounded p-3 mb-3 bg-body-secondary bg-opacity-25" data-semana>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <h6 class="fw-semibold mb-0" data-titulo-semana>Semana</h6>
          <span class="badge bg-dark d-none" data-badge-sem-aplicacao>Sem aplicação</span>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="form-check mb-0">
            <input
              class="form-check-input"
              type="checkbox"
              value="1"
              id="sem_aplicacao___INDICE_SEMANA__"
              name="semanas[__INDICE_SEMANA__][sem_aplicacao]"
              data-semana-sem-aplicacao />
            <label class="form-check-label" for="sem_aplicacao___INDICE_SEMANA__">
              Semana sem aplicação
            </label>
          </div>

          <div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 small text-muted">Data prevista</label>
            <input
              type="date"
              name="semanas[__INDICE_SEMANA__][data_prevista]"
              class="form-control form-control-sm"
              style="width: 165px;" />
            <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-semana title="Remover semana">
              <i class="ri-delete-bin-7-line"></i>
            </button>
          </div>
        </div>
      </div>

      <input type="hidden" name="semanas[__INDICE_SEMANA__][numero]" value="1" data-numero-semana />

      <div data-area-itens>
        <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-2">
          <thead class="table-light">
            <tr>
              <th style="width: 150px;">Tipo</th>
              <th>Medicamento / Combo</th>
              <th style="width: 120px;">Quantidade</th>
              <th style="width: 150px;">Valor</th>
              <th style="width: 130px;" class="text-end">Total</th>
              <th style="width: 60px;" class="text-center"></th>
            </tr>
          </thead>
          <tbody data-itens-semana></tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="text-end">Total da semana</th>
              <th class="text-end" data-total-semana>R$ 0,00</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <button type="button" class="btn btn-sm btn-outline-primary" data-adicionar-item>
        <i class="ri-add-line me-1"></i>Adicionar medicamento/combo
      </button>
      </div>

      {{-- Modelo de item da semana (clonado dentro da semana) --}}
      <template data-modelo-item>
        <tr>
          <td>
            <select
              name="semanas[__INDICE_SEMANA__][itens][__INDICE_ITEM__][tipo]"
              class="form-select form-select-sm"
              data-tipo-item>
              <option value="medicamento" selected>Medicamento</option>
              <option value="combo">Combo</option>
            </select>
          </td>
          <td>
            <div data-wrap-medicamento>
              <select
                name="semanas[__INDICE_SEMANA__][itens][__INDICE_ITEM__][medicamento_id]"
                class="form-select form-select-sm"
                data-select-medicamento>
                <option value="">Selecione...</option>
                @foreach ($medicamentos as $medicamento)
                  <option
                    value="{{ $medicamento->id }}"
                    data-valor="{{ $medicamento->valor_venda }}"
                    data-tipo="{{ $medicamento->tipo->value }}"
                    @if ($medicamento->exige_anexo) data-exige-anexo="1" @endif>
                    {{ $medicamento->nome }}
                  </option>
                @endforeach
              </select>
            </div>

            <div data-wrap-combo class="d-none">
              <select
                name="semanas[__INDICE_SEMANA__][itens][__INDICE_ITEM__][combo_id]"
                class="form-select form-select-sm"
                data-select-combo
                disabled>
                <option value="">Selecione...</option>
                @foreach ($combos as $combo)
                  <option
                    value="{{ $combo->id }}"
                    data-valor="{{ $combo->valor_total }}"
                    @if ($combo->exige_anexo) data-exige-anexo="1" @endif>
                    {{ $combo->nome }}
                  </option>
                @endforeach
              </select>
            </div>
          </td>
          <td>
            <input
              type="number"
              min="0"
              step="any"
              name="semanas[__INDICE_SEMANA__][itens][__INDICE_ITEM__][quantidade]"
              class="form-control form-control-sm"
              placeholder="0" />
          </td>
          <td>
            <input
              type="text"
              name="semanas[__INDICE_SEMANA__][itens][__INDICE_ITEM__][valor]"
              class="form-control form-control-sm"
              readonly
              title="O valor vem do cadastro do medicamento/combo"
              placeholder="R$ 0,00" />
          </td>
          <td class="text-end fw-semibold" data-total-item>R$ 0,00</td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-item title="Remover">
              <i class="ri-delete-bin-7-line"></i>
            </button>
          </td>
        </tr>
      </template>
    </div>
  </template>

  {{-- Modelo de parcela (uma por semana com aplicação) --}}
  <template id="modelo-parcela">
    <tr>
      <td class="fw-semibold" data-parcela-numero>1ª</td>
      <td data-parcela-vencimento>-</td>
      <td class="text-muted" data-parcela-semana>Semana</td>
      <td class="text-end" data-parcela-bruto>R$ 0,00</td>
      <td class="text-end text-danger d-none" data-parcela-desconto>- R$ 0,00</td>
      <td class="text-end text-success d-none" data-parcela-adicional>+ R$ 0,00</td>
      <td class="text-end fw-semibold" data-parcela-valor>R$ 0,00</td>
    </tr>
  </template>

  {{-- Gerador de semanas: gera N semanas (data + intervalo) repetindo os itens escolhidos --}}
  <div class="modal fade" id="modal-gerador" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Gerador de semanas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="alert alert-danger d-none" data-gerador-erro role="alert"></div>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="gerador-data-inicio">Data da 1ª semana *</label>
              <input type="date" id="gerador-data-inicio" class="form-control" />
            </div>

            <div class="col-md-4">
              <label class="form-label" for="gerador-numero">Número de semanas *</label>
              <input type="number" min="1" step="1" id="gerador-numero" class="form-control" placeholder="Ex.: 4" />
            </div>

            <div class="col-md-4">
              <label class="form-label" for="gerador-intervalo">Intervalo entre semanas (dias) *</label>
              <input type="number" min="1" step="1" id="gerador-intervalo" class="form-control" placeholder="Ex.: 7" />
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 mb-3">
            <h6 class="fw-semibold mb-0">Itens repetidos em cada semana</h6>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-info" data-gerador-adicionar="combo">
                <i class="ri-add-line me-1"></i>Combo
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary" data-gerador-adicionar="medicamento">
                <i class="ri-add-line me-1"></i>Medicamento
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 150px;">Tipo</th>
                  <th>Medicamento / Combo</th>
                  <th style="width: 120px;">Quantidade</th>
                  <th style="width: 150px;">Valor</th>
                  <th style="width: 130px;" class="text-end">Total</th>
                  <th style="width: 60px;" class="text-center"></th>
                </tr>
              </thead>
              <tbody data-gerador-itens></tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total de cada semana</th>
                  <th class="text-end" data-gerador-total>R$ 0,00</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="alert alert-info mt-3 mb-0" role="alert">
            As semanas geradas são adicionadas à lista. Se já existir uma semana com a mesma data,
            os itens entram nela em vez de criar uma semana repetida.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" id="gerador-gerar" class="btn btn-primary">
            <i class="ri-magic-line me-1"></i>Gerar semanas
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Modelo de item do gerador (não é enviado no formulário) --}}
  <template id="modelo-item-gerador">
    <tr>
      <td>
        <select class="form-select form-select-sm" data-tipo-item>
          <option value="medicamento" selected>Medicamento</option>
          <option value="combo">Combo</option>
        </select>
      </td>
      <td>
        <div data-wrap-medicamento>
          <select class="form-select form-select-sm" data-select-medicamento>
            <option value="">Selecione...</option>
            @foreach ($medicamentos as $medicamento)
              <option
                value="{{ $medicamento->id }}"
                data-valor="{{ $medicamento->valor_venda }}"
                data-tipo="{{ $medicamento->tipo->value }}"
                @if ($medicamento->exige_anexo) data-exige-anexo="1" @endif>
                {{ $medicamento->nome }}
              </option>
            @endforeach
          </select>
        </div>

        <div data-wrap-combo class="d-none">
          <select class="form-select form-select-sm" data-select-combo disabled>
            <option value="">Selecione...</option>
            @foreach ($combos as $combo)
              <option
                value="{{ $combo->id }}"
                data-valor="{{ $combo->valor_total }}"
                @if ($combo->exige_anexo) data-exige-anexo="1" @endif>
                {{ $combo->nome }}
              </option>
            @endforeach
          </select>
        </div>
      </td>
      <td>
        <input
          type="number"
          min="0"
          step="any"
          class="form-control form-control-sm"
          placeholder="0"
          data-gerador-quantidade />
      </td>
      <td>
        <input
          type="text"
          class="form-control form-control-sm"
          readonly
          title="O valor vem do cadastro do medicamento/combo"
          placeholder="R$ 0,00"
          data-gerador-valor />
      </td>
      <td class="text-end fw-semibold" data-total-item>R$ 0,00</td>
      <td class="text-center">
        <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-item title="Remover">
          <i class="ri-delete-bin-7-line"></i>
        </button>
      </td>
    </tr>
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
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  <script>
    window.semanasPrescricao = @json($semanasIniciais);
    window.prescricaoPacientesUrl = '{{ route('prescricoes.pacientes') }}';
  </script>

  @include('partials.crud-scripts')
  @include('prescricoes._form-script')
@endpush
