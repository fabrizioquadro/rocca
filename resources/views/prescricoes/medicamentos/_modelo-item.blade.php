{{--
  Modelo de um item (medicamento/combo) dentro de uma semana.
  Os marcadores __SEMANA__ e __ITEM__ são trocados na hora de clonar.
--}}
<tr data-item>
  <td>
    <select
      name="semanas[__SEMANA__][itens][__ITEM__][tipo]"
      class="form-select form-select-sm"
      data-tipo-item>
      <option value="medicamento" selected>Medicamento</option>
      <option value="combo">Combo</option>
    </select>
  </td>
  <td>
    <div data-wrap-medicamento>
      <select
        name="semanas[__SEMANA__][itens][__ITEM__][medicamento_id]"
        class="form-select form-select-sm"
        data-select-medicamento>
        <option value="">Selecione...</option>
        @foreach ($medicamentos as $medicamento)
          <option
            value="{{ $medicamento->id }}"
            data-valor="{{ $medicamento->valor_venda }}"
            data-tipo="{{ $medicamento->tipo->value }}">
            {{ $medicamento->nome }}
          </option>
        @endforeach
      </select>
    </div>

    <div data-wrap-combo class="d-none">
      <select
        name="semanas[__SEMANA__][itens][__ITEM__][combo_id]"
        class="form-select form-select-sm"
        data-select-combo
        disabled>
        <option value="">Selecione...</option>
        @foreach ($combos as $combo)
          <option value="{{ $combo->id }}" data-valor="{{ $combo->valor_total }}">
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
      name="semanas[__SEMANA__][itens][__ITEM__][quantidade]"
      class="form-control form-control-sm"
      placeholder="0"
      data-quantidade />
  </td>
  <td>
    <input
      type="text"
      inputmode="decimal"
      name="semanas[__SEMANA__][itens][__ITEM__][valor]"
      class="form-control form-control-sm"
      placeholder="R$ 0,00"
      data-valor />
  </td>
  <td class="text-end fw-semibold" data-total-item>R$ 0,00</td>
  <td class="text-center">
    <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-item title="Remover item">
      <i class="ri-delete-bin-7-line"></i>
    </button>
  </td>
</tr>
