@extends('layouts.app')

@section('title', 'Nova Transferência')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Nova transferência entre clínicas</h4>

      <a href="{{ route('estoque.transferencias.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('estoque.transferencias.store') }}">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="clinica_id">Clínica de origem *</label>
            <select id="clinica_id" name="clinica_id" class="form-select @error('clinica_id') is-invalid @enderror" required>
              <option value="">Selecione a origem...</option>
              @foreach ($clinicas as $clinica)
                <option value="{{ $clinica->id }}" @selected(old('clinica_id') == $clinica->id)>
                  {{ $clinica->nome }}
                </option>
              @endforeach
            </select>
            @error('clinica_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-4">
            <label class="form-label" for="clinica_destino_id">Clínica de destino *</label>
            <select
              id="clinica_destino_id"
              name="clinica_destino_id"
              class="form-select @error('clinica_destino_id') is-invalid @enderror"
              required>
              <option value="">Selecione o destino...</option>
              @foreach ($clinicas as $clinica)
                <option value="{{ $clinica->id }}" @selected(old('clinica_destino_id') == $clinica->id)>
                  {{ $clinica->nome }}
                </option>
              @endforeach
            </select>
            @error('clinica_destino_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label" for="data">Data</label>
            <input
              type="date"
              id="data"
              name="data"
              class="form-control @error('data') is-invalid @enderror"
              value="{{ old('data', now()->format('Y-m-d')) }}" />
            @error('data')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label" for="observacao">Observação</label>
            <input
              type="text"
              id="observacao"
              name="observacao"
              class="form-control @error('observacao') is-invalid @enderror"
              value="{{ old('observacao') }}" />
            @error('observacao')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Medicamentos transferidos</h6>

          <button type="button" id="adicionar-item" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i>Adicionar medicamento
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width: 200px;">Código de barras *</th>
                <th>Medicamento</th>
                <th style="width: 110px;">Lote</th>
                <th style="width: 110px;">Vencimento</th>
                <th style="width: 100px;" class="text-end">Disponível</th>
                <th style="width: 110px;">Quantidade *</th>
                <th style="width: 60px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody
              id="itens-transferencia"
              data-repeater
              data-iniciais="itensTransferencia"
              data-url-buscar-codigo="{{ route('estoque.buscarCodigoBarras') }}"
              data-clinica="#clinica_id"></tbody>
            <tfoot>
              <tr>
                <th colspan="4" class="text-end">Total de unidades</th>
                <th id="total-unidades" class="text-end">0</th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <small class="text-muted d-block mb-3">
          Os códigos são buscados no estoque da <strong>clínica de origem</strong>. A saída da origem e a entrada no
          destino acontecem no mesmo lançamento. Se o mesmo medicamento estiver em mais de um lote, o que vence primeiro sai primeiro.
        </small>

        <div class="d-flex justify-content-end gap-2">
          <a href="{{ route('estoque.transferencias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Lançar transferência
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modelo de linha usado pelo JS --}}
  <template id="modelo-item">
    <tr>
      <td>
        <input
          type="text"
          name="itens[__INDICE__][codigo_barras]"
          class="form-control form-control-sm"
          data-buscar-codigo
          placeholder="Código de barras"
          autocomplete="off" />
        <div class="text-danger small mt-1 d-none" data-erro-codigo></div>
      </td>
      <td class="small" data-info-medicamento>—</td>
      <td class="small" data-info-lote>—</td>
      <td class="small" data-info-vencimento>—</td>
      <td class="small text-end fw-semibold" data-info-saldo>—</td>
      <td>
        <input
          type="number"
          min="1"
          step="1"
          name="itens[__INDICE__][quantidade]"
          class="form-control form-control-sm"
          placeholder="0" />
      </td>
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
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  <script>
    window.itensTransferencia = @json($itensIniciais);
  </script>

  @include('partials.crud-scripts')
  @include('partials.linhas-dinamicas')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.jQuery && jQuery.fn.select2) {
        jQuery('#clinica_id, #clinica_destino_id').select2({
          width: '100%',
          language: {
            noResults: () => 'Nenhuma clínica encontrada',
            searching: () => 'Buscando...'
          }
        });
      }

      const corpo = document.getElementById('itens-transferencia');
      const totalEl = document.getElementById('total-unidades');

      if (!corpo) return;

      const atualizarTotal = () => {
        let total = 0;

        corpo.querySelectorAll('input[name$="[quantidade]"]').forEach((el) => {
          const quantidade = parseInt(String(el.value).replace(/\D/g, ''), 10);

          if (!isNaN(quantidade)) {
            total += quantidade;
          }
        });

        if (totalEl) {
          totalEl.textContent = total;
        }
      };

      corpo.addEventListener('input', atualizarTotal);
      corpo.addEventListener('repeater:atualizado', atualizarTotal);

      atualizarTotal();
    });
  </script>
@endpush
