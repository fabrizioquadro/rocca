<form method="POST" action="{{ $action }}">
  @csrf
  @if ($method !== 'POST')
    @method($method)
  @endif

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label" for="nome">Nome do combo *</label>
      <input
        type="text"
        id="nome"
        name="nome"
        class="form-control @error('nome') is-invalid @enderror"
        value="{{ old('nome', $combo?->nome) }}"
        required />
      @error('nome')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>

    <div class="col-md-3">
      <label class="form-label" for="status">Status *</label>
      <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
        @foreach (\App\Enums\StatusAtivoInativo::cases() as $status)
          <option value="{{ $status->value }}" @selected(old('status', $combo?->status?->value ?? 'ativo') === $status->value)>
            {{ $status->label() }}
          </option>
        @endforeach
      </select>
      @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
      @enderror
    </div>
  </div>

  <hr class="my-4" />

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h6 class="fw-semibold mb-0">Medicamentos do combo</h6>

    <button type="button" id="adicionar-item" class="btn btn-sm btn-outline-primary">
      <i class="ri-add-line me-1"></i>Adicionar medicamento
    </button>
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Medicamento</th>
          <th style="width: 170px;">Quantidade</th>
          <th style="width: 190px;">Valor</th>
          <th style="width: 60px;" class="text-center"></th>
        </tr>
      </thead>
      <tbody
        id="itens-combo"
        data-repeater
        data-iniciais="itensCombo"
        data-placeholder="Digite para buscar o medicamento..."></tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="text-end">Total do combo</th>
          <th id="total-combo">R$ 0,00</th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-3">
    <a href="{{ route('combos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">
      <i class="ri-save-3-line me-1"></i>Salvar combo
    </button>
  </div>
</form>

{{-- Modelo de linha usado pelo JS para adicionar medicamentos --}}
<template id="modelo-item">
  <tr>
    <td>
      <select name="itens[__INDICE__][medicamento_id]" class="form-select form-select-sm">
        <option value="">Selecione...</option>
        @foreach ($medicamentos as $medicamento)
          <option value="{{ $medicamento->id }}">{{ $medicamento->nome }}</option>
        @endforeach
      </select>
    </td>
    <td>
      <input
        type="text"
        name="itens[__INDICE__][quantidade]"
        class="form-control form-control-sm"
        data-quantidade
        inputmode="decimal"
        placeholder="Ex.: 6,25" />
    </td>
    <td>
      <input
        type="text"
        name="itens[__INDICE__][valor]"
        class="form-control form-control-sm"
        data-moeda
        inputmode="numeric"
        placeholder="R$ 0,00" />
    </td>
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-item title="Remover">
        <i class="ri-delete-bin-7-line"></i>
      </button>
    </td>
  </tr>
</template>

@push('scripts')
  <script>
    window.itensCombo = @json($itensIniciais);
  </script>

  @include('partials.linhas-dinamicas')
  @include('combos._form-script')
@endpush
