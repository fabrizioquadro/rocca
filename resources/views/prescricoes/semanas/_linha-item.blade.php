@php
  $tipo = ($item['tipo'] ?? 'medicamento') === 'combo' ? 'combo' : 'medicamento';
  $ehCombo = $tipo === 'combo';

  $medicamentoId = $item['medicamento_id'] ?? null;
  $comboId = $item['combo_id'] ?? null;

  $quantidade = (float) ($item['quantidade'] ?? 0);
  $quantidadeTexto = $quantidade > 0
      ? rtrim(rtrim(number_format($quantidade, 3, '.', ''), '0'), '.')
      : '';

  $medicamentoEscolhido = $medicamentoId ? $medicamentos->firstWhere('id', $medicamentoId) : null;

  $valor = $ehCombo
      ? (float) ($combos->firstWhere('id', $comboId)?->valor_total ?? 0)
      : (float) ($medicamentoEscolhido?->valor_venda ?? 0);

  // Ampola não tem fração (0,5 cobra 1), igual à regra da gravação
  $quantidadeCobranca = ($medicamentoEscolhido?->tipo === \App\Enums\TipoMedicamento::Ampola)
      ? ceil($quantidade)
      : $quantidade;

  $total = $quantidadeCobranca * $valor;
@endphp

<tr data-linha-item>
  <td>
    <select name="itens[{{ $indice }}][tipo]" class="form-select form-select-sm" data-tipo-item>
      <option value="medicamento" @selected(! $ehCombo)>Medicamento</option>
      <option value="combo" @selected($ehCombo)>Combo</option>
    </select>
  </td>

  <td>
    <div data-wrap-medicamento class="{{ $ehCombo ? 'd-none' : '' }}">
      <select
        name="itens[{{ $indice }}][medicamento_id]"
        class="form-select form-select-sm"
        data-select-medicamento
        @disabled($ehCombo)>
        <option value="">Selecione...</option>
        @foreach ($medicamentos as $medicamento)
          <option
            value="{{ $medicamento->id }}"
            data-valor="{{ $medicamento->valor_venda }}"
            data-tipo="{{ $medicamento->tipo->value }}"
            @selected($medicamentoId == $medicamento->id)>
            {{ $medicamento->nome }}
          </option>
        @endforeach
      </select>
    </div>

    <div data-wrap-combo class="{{ $ehCombo ? '' : 'd-none' }}">
      <select
        name="itens[{{ $indice }}][combo_id]"
        class="form-select form-select-sm"
        data-select-combo
        @disabled(! $ehCombo)>
        <option value="">Selecione...</option>
        @foreach ($combos as $combo)
          <option
            value="{{ $combo->id }}"
            data-valor="{{ $combo->valor_total }}"
            @selected($comboId == $combo->id)>
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
      name="itens[{{ $indice }}][quantidade]"
      class="form-control form-control-sm"
      placeholder="0"
      data-qtd-item
      value="{{ $quantidadeTexto }}" />
  </td>

  <td>
    <input
      type="text"
      class="form-control form-control-sm"
      readonly
      tabindex="-1"
      title="O valor vem do cadastro do medicamento/combo"
      placeholder="R$ 0,00"
      value="{{ 'R$ '.number_format($valor, 2, ',', '.') }}"
      data-valor-item />
  </td>

  <td class="text-end fw-semibold" data-total-item>
    {{ 'R$ '.number_format($total, 2, ',', '.') }}
  </td>

  <td class="text-center">
    <button type="button" class="btn btn-sm btn-icon btn-text-danger" data-remover-item title="Remover">
      <i class="ri-delete-bin-7-line"></i>
    </button>
  </td>
</tr>
