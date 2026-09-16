@extends('layouts.app')

@section('title', 'Novo Medicamento')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Novo medicamento</h4>

      <a href="{{ route('medicamentos.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('medicamentos.store') }}">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="nome">Nome *</label>
            <input
              type="text"
              id="nome"
              name="nome"
              class="form-control @error('nome') is-invalid @enderror"
              value="{{ old('nome') }}"
              required />
            @error('nome')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="fabricante">Fabricante</label>
            <input
              type="text"
              id="fabricante"
              name="fabricante"
              class="form-control @error('fabricante') is-invalid @enderror"
              value="{{ old('fabricante') }}" />
            @error('fabricante')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="tipo">Tipo *</label>
            <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
              @foreach (\App\Enums\TipoMedicamento::cases() as $tipo)
                <option value="{{ $tipo->value }}" @selected(old('tipo', 'ampola') === $tipo->value)>
                  {{ $tipo->label() }}
                </option>
              @endforeach
            </select>
            @error('tipo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          {{-- Só aparece quando o tipo for Miligrama --}}
          <div class="col-md-3 d-none" data-mostrar-se-tipo="miligrama">
            <label class="form-label" for="tamanho_vasilhame">Tamanho do vasilhame *</label>
            <input
              type="text"
              id="tamanho_vasilhame"
              name="tamanho_vasilhame"
              class="form-control @error('tamanho_vasilhame') is-invalid @enderror"
              value="{{ old('tamanho_vasilhame') }}"
              data-quantidade
              inputmode="decimal"
              placeholder="Ex.: 10,5" />
            @error('tamanho_vasilhame')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="grupo_id">Grupo</label>
            <select id="grupo_id" name="grupo_id" class="form-select @error('grupo_id') is-invalid @enderror">
              <option value="">Sem grupo</option>
              @foreach ($grupos as $grupo)
                <option value="{{ $grupo->id }}" @selected(old('grupo_id') == $grupo->id)>
                  {{ $grupo->nome }}
                </option>
              @endforeach
            </select>
            @error('grupo_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="status">Status *</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
              @foreach (\App\Enums\StatusAtivoInativo::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('status', 'ativo') === $status->value)>
                  {{ $status->label() }}
                </option>
              @endforeach
            </select>
            @error('status')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="ultimo_valor_pago">Último valor pago</label>
            <input
              type="text"
              id="ultimo_valor_pago"
              name="ultimo_valor_pago"
              class="form-control @error('ultimo_valor_pago') is-invalid @enderror"
              value="{{ old('ultimo_valor_pago') }}"
              data-moeda
              inputmode="numeric"
              placeholder="R$ 0,00" />
            @error('ultimo_valor_pago')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="valor_venda">Valor de venda</label>
            <input
              type="text"
              id="valor_venda"
              name="valor_venda"
              class="form-control @error('valor_venda') is-invalid @enderror"
              value="{{ old('valor_venda') }}"
              data-moeda
              inputmode="numeric"
              placeholder="R$ 0,00" />
            @error('valor_venda')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="estoque_minimo">Estoque mínimo</label>
            <input
              type="number"
              min="0"
              step="1"
              id="estoque_minimo"
              name="estoque_minimo"
              class="form-control @error('estoque_minimo') is-invalid @enderror"
              value="{{ old('estoque_minimo') }}" />
            @error('estoque_minimo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="estoque_medio">Estoque médio</label>
            <input
              type="number"
              min="0"
              step="1"
              id="estoque_medio"
              name="estoque_medio"
              class="form-control @error('estoque_medio') is-invalid @enderror"
              value="{{ old('estoque_medio') }}" />
            @error('estoque_medio')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label" for="feegow_aplicacao_id">Feegow — ID de aplicação</label>
            <input
              type="number"
              min="0"
              step="1"
              id="feegow_aplicacao_id"
              name="feegow_aplicacao_id"
              class="form-control @error('feegow_aplicacao_id') is-invalid @enderror"
              value="{{ old('feegow_aplicacao_id') }}" />
            @error('feegow_aplicacao_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-3">
            <label class="form-label d-block">Gera aplicação?</label>
            <div class="form-check form-switch mt-2">
              <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="gera_aplicacao"
                name="gera_aplicacao"
                value="1"
                @checked(old('gera_aplicacao')) />
              <label class="form-check-label" for="gera_aplicacao">Sim, gera aplicação</label>
            </div>
          </div>

          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('medicamentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-3-line me-1"></i>Salvar medicamento
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
