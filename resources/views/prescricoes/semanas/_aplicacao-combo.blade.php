@php
  $componentes = $item->combo?->itens ?? collect();

  // Medicamentos do combo que já saíram em algum atendimento desta semana
  $aplicados = $item->aplicacoes
      ->whereNotNull('medicamento_id')
      ->pluck('medicamento_id')
      ->map(fn ($id) => (int) $id);

  // Só aparece o que ainda falta aplicar (numa volta para completar o combo)
  $aFazer = $componentes
      ->reject(fn ($componente) => $aplicados->contains((int) $componente->medicamento_id))
      ->values();
@endphp

{{-- Cabeçalho do combo. Os medicamentos de baixo é que são lidos: cada um com
     o seu próprio código de barras e o seu "Pendente". --}}
<tr class="table-info">
  <td colspan="6">
    <span class="fw-semibold"><i class="ri-gift-line me-1"></i>{{ $item->nome }}</span>
    <small class="text-body-secondary ms-2">
      Combo · {{ $componentes->count() }} medicamento(s) · {{ $item->quantidade_formatada }} combo(s)
    </small>
  </td>
</tr>

@forelse ($aFazer as $componente)
  @php
    $chave = $componente->id;
    $quantidade = $item->quantidadeDoComponente($componente);
    $pendenteAntigo = (bool) old("itens.{$item->id}.componentes.{$chave}.pendente", false);
  @endphp

  <tr
    data-item-aplicacao
    data-componente-id="{{ $chave }}"
    data-medicamentos-permitidos="{{ $componente->medicamento_id }}">
    <td>
      <div class="form-check mb-0">
        <input
          type="checkbox"
          class="form-check-input"
          id="pendente-combo-{{ $item->id }}-{{ $chave }}"
          name="itens[{{ $item->id }}][componentes][{{ $chave }}][pendente]"
          value="1"
          data-pendente
          @checked($pendenteAntigo) />
        <label class="form-check-label" for="pendente-combo-{{ $item->id }}-{{ $chave }}">Pendente</label>
      </div>
    </td>

    <td>
      <span class="fw-semibold">{{ $componente->medicamento?->nome ?? '—' }}</span>
      <small class="text-body-secondary d-block">Medicamento do combo</small>
    </td>

    <td>{{ \App\Support\Numero::formatar($quantidade) }}</td>

    <td>
      <input
        type="text"
        name="itens[{{ $item->id }}][componentes][{{ $chave }}][codigo_barras]"
        class="form-control form-control-sm font-monospace"
        placeholder="Ler código de barras"
        autocomplete="off"
        data-codigo-barras
        value="{{ old("itens.{$item->id}.componentes.{$chave}.codigo_barras") }}" />
    </td>

    <td data-info-lote>
      <span class="badge bg-label-secondary">Aguardando código</span>
    </td>

    <td>
      <input
        type="text"
        name="itens[{{ $item->id }}][componentes][{{ $chave }}][observacao]"
        class="form-control form-control-sm"
        maxlength="1000"
        placeholder="Observação do medicamento"
        value="{{ old("itens.{$item->id}.componentes.{$chave}.observacao") }}" />
    </td>
  </tr>
@empty
  <tr>
    <td colspan="6" class="text-muted small">Nenhum medicamento restante neste combo.</td>
  </tr>
@endforelse
