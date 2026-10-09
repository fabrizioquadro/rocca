{{--
  Filtros padrão dos relatórios.
  Variáveis: $rota (obrigatória), $comPeriodo, $comClinica, $comMedicamento,
  $comTipo, $comVencidas, $comMedico, $comFormaPagamento, $comAtendimento,
  $comPaciente, $rotuloPeriodo.
--}}
<form method="GET" action="{{ $rota }}" class="relatorio-filtros row g-3 align-items-end">
  @if ($comPeriodo ?? true)
    <div class="col-md-2">
      <label class="form-label" for="inicio">{{ $rotuloPeriodo ?? 'De' }}</label>
      <input type="date" id="inicio" name="inicio" class="form-control" value="{{ $inicio?->format('Y-m-d') }}" />
    </div>

    <div class="col-md-2">
      <label class="form-label" for="fim">Até</label>
      <input type="date" id="fim" name="fim" class="form-control" value="{{ $fim?->format('Y-m-d') }}" />
    </div>
  @endif

  @if ($comClinica ?? true)
    <div class="col-md-3">
      <label class="form-label" for="clinica_id">Clínica</label>
      <select id="clinica_id" name="clinica_id" class="form-select">
        <option value="">Todas as clínicas</option>
        @foreach ($clinicas as $clinica)
          <option value="{{ $clinica->id }}" @selected((int) request('clinica_id') === $clinica->id)>
            {{ $clinica->nome }}
          </option>
        @endforeach
      </select>
    </div>
  @endif

  @if ($comMedicamento ?? false)
    <div class="col-md-3">
      <label class="form-label" for="medicamento_id">Medicamento</label>
      <select id="medicamento_id" name="medicamento_id" class="form-select">
        <option value="">Todos os medicamentos</option>
        @foreach ($medicamentos as $medicamento)
          <option value="{{ $medicamento->id }}" @selected((int) request('medicamento_id') === $medicamento->id)>
            {{ $medicamento->nome }}
          </option>
        @endforeach
      </select>
    </div>
  @endif

  @if ($comMedico ?? false)
    <div class="col-md-3">
      <label class="form-label" for="medico">Médico</label>
      <select id="medico" name="medico" class="form-select">
        <option value="">Todos os médicos</option>
        @foreach ($medicos as $nomeMedico)
          <option value="{{ $nomeMedico }}" @selected(request('medico') === $nomeMedico)>
            {{ $nomeMedico }}
          </option>
        @endforeach
      </select>
    </div>
  @endif

  @if ($comFormaPagamento ?? false)
    <div class="col-md-3">
      <label class="form-label" for="forma_pagamento">Forma de pagamento</label>
      <select id="forma_pagamento" name="forma_pagamento" class="form-select">
        <option value="">Todas as formas</option>
        @foreach ($formasPagamento as $valorForma => $rotuloForma)
          <option value="{{ $valorForma }}" @selected(request('forma_pagamento') === $valorForma)>
            {{ $rotuloForma }}
          </option>
        @endforeach
      </select>
    </div>
  @endif

  @if ($comAtendimento ?? false)
    <div class="col-md-3">
      <label class="form-label" for="tipo_atendimento">Tipo de atendimento</label>
      <select id="tipo_atendimento" name="tipo_atendimento" class="form-select">
        <option value="">Todos os atendimentos</option>
        @foreach ($tiposAtendimento as $valorTipo => $rotuloTipo)
          <option value="{{ $valorTipo }}" @selected(request('tipo_atendimento') === $valorTipo)>
            {{ $rotuloTipo }}
          </option>
        @endforeach
      </select>
    </div>
  @endif

  {{-- Paciente: a base é grande, então a escolha é por busca (Select2 + AJAX) --}}
  @if ($comPaciente ?? false)
    <div class="col-md-3">
      <label class="form-label" for="paciente_id">Paciente</label>
      <select id="paciente_id" name="paciente_id" class="form-select">
        @if ($pacienteFiltrado)
          <option value="{{ $pacienteFiltrado->id }}" selected>{{ $pacienteFiltrado->nome }}</option>
        @endif
      </select>
    </div>
  @endif

  @if ($comTipo ?? false)
    <div class="col-md-3">
      <label class="form-label" for="tipo">Tipo de movimentação</label>
      <select id="tipo" name="tipo" class="form-select">
        <option value="">Todos os tipos</option>
        @foreach ($tiposMovimentacao as $valor => $rotulo)
          <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $rotulo }}</option>
        @endforeach
      </select>
    </div>
  @endif

  @if ($comVencidas ?? false)
    <div class="col-md-2">
      <div class="form-check mt-md-4">
        <input
          type="checkbox"
          class="form-check-input"
          id="vencidas"
          name="vencidas"
          value="1"
          @checked(request()->boolean('vencidas')) />
        <label class="form-check-label" for="vencidas">Só vencidas</label>
      </div>
    </div>
  @endif

  <div class="col-md-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
      <i class="ri-search-line me-1"></i>Filtrar
    </button>

    <a href="{{ $rota }}" class="btn btn-outline-secondary" title="Limpar filtros">
      <i class="ri-refresh-line"></i>
    </a>
  </div>
</form>
