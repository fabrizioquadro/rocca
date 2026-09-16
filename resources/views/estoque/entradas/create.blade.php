@extends('layouts.app')

@section('title', 'Nova Entrada de Estoque')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Nova entrada (nota fiscal)</h4>

      <a href="{{ route('estoque.entradas.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('estoque.entradas.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="clinica_id">Clínica que está comprando *</label>
            <select id="clinica_id" name="clinica_id" class="form-select @error('clinica_id') is-invalid @enderror" required>
              <option value="">Selecione a clínica...</option>
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
            <label class="form-label" for="fornecedor_id">Fornecedor</label>
            <select id="fornecedor_id" name="fornecedor_id" class="form-select @error('fornecedor_id') is-invalid @enderror">
              <option value="">Sem fornecedor</option>
              @foreach ($fornecedores as $fornecedor)
                <option value="{{ $fornecedor->id }}" @selected(old('fornecedor_id') == $fornecedor->id)>
                  {{ $fornecedor->nome }}
                </option>
              @endforeach
            </select>
            @error('fornecedor_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label" for="numero_nota">Número da nota</label>
            <input
              type="text"
              id="numero_nota"
              name="numero_nota"
              class="form-control @error('numero_nota') is-invalid @enderror"
              value="{{ old('numero_nota') }}" />
            @error('numero_nota')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-2">
            <label class="form-label" for="data_entrada">Data de entrada</label>
            <input
              type="date"
              id="data_entrada"
              name="data_entrada"
              class="form-control @error('data_entrada') is-invalid @enderror"
              value="{{ old('data_entrada', now()->format('Y-m-d')) }}" />
            @error('data_entrada')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12">
            <label class="form-label" for="observacao">Observação</label>
            <textarea
              id="observacao"
              name="observacao"
              rows="2"
              class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
            @error('observacao')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12">
            <label class="form-label" for="anexos">Anexos (nota fiscal, recibo e etc.)</label>
            <input
              type="file"
              id="anexos"
              name="anexos[]"
              class="form-control @error('anexos') is-invalid @enderror @error('anexos.*') is-invalid @enderror"
              multiple
              accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.csv" />
            <small class="text-muted">PDF, imagem, XML ou CSV — máx. 10MB por arquivo</small>
            @error('anexos')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            @error('anexos.*')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Medicamentos da entrada</h6>

          <button type="button" id="adicionar-item" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i>Adicionar medicamento
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>Medicamento</th>
                <th style="width: 140px;">Lote</th>
                <th style="width: 170px;">Código de barras *</th>
                <th style="width: 155px;">Vencimento</th>
                <th style="width: 120px;">Quantidade *</th>
                <th style="width: 140px;">Valor unitário</th>
                <th style="width: 130px;" class="text-end">Valor total</th>
                <th style="width: 60px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody
              id="itens-entrada"
              data-repeater
              data-iniciais="itensEntrada"
              data-url-codigo="{{ route('estoque.entradas.gerarCodigoBarras') }}"
              data-url-verificar-codigo="{{ route('estoque.entradas.verificarCodigoBarras') }}"
              data-placeholder="Digite para buscar o medicamento..."></tbody>
            <tfoot>
              <tr>
                <th colspan="4" class="text-end">Total de unidades</th>
                <th id="total-unidades">0</th>
                <th class="text-end">Valor total</th>
                <th class="text-end" id="total-valor">R$ 0,00</th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <small class="text-muted d-block mb-3">
          Escolha o medicamento e clique no ícone de código de barras para gerar o código automático
          (sequencial por medicamento). Ele também pode ser digitado ou lido com leitor de código de barras.
          <br />
          <strong>Código de barras sempre com o mesmo lote:</strong> ao sair do campo de lote ou de código de barras
          o sistema verifica se aquele código já foi lançado com outro lote (ou outro medicamento) e avisa na hora.
          <br />
          O valor unitário informado atualiza o último valor pago no cadastro do medicamento.
        </small>

        <div class="d-flex justify-content-end gap-2">
          <a href="{{ route('estoque.entradas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Lançar entrada
          </button>
        </div>
      </form>
    </div>
  </div>

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
        <input type="text" name="itens[__INDICE__][lote]" class="form-control form-control-sm" placeholder="Lote" />
      </td>
      <td>
        <div class="input-group input-group-sm">
          <input
            type="text"
            name="itens[__INDICE__][codigo_barras]"
            class="form-control"
            placeholder="Código de barras" />
          <button
            type="button"
            class="btn btn-outline-secondary"
            data-gerar-codigo
            title="Gerar código de barras">
            <i class="ri-barcode-line"></i>
          </button>
        </div>
        <div class="text-danger small mt-1 d-none" data-erro-codigo></div>
      </td>
      <td>
        <input type="date" name="itens[__INDICE__][vencimento]" class="form-control form-control-sm" />
      </td>
      <td>
        <input
          type="number"
          min="1"
          step="1"
          name="itens[__INDICE__][quantidade]"
          class="form-control form-control-sm"
          placeholder="0" />
      </td>
      <td>
        <input
          type="text"
          name="itens[__INDICE__][valor_unitario]"
          class="form-control form-control-sm"
          data-moeda
          inputmode="numeric"
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
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/select2/select2.css') }}" />
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  <script>
    window.itensEntrada = @json($itensIniciais);
  </script>

  @include('partials.crud-scripts')
  @include('partials.linhas-dinamicas')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Busca digitando nos selects do cabeçalho
      if (window.jQuery && jQuery.fn.select2) {
        jQuery('#clinica_id').select2({
          width: '100%',
          language: {
            noResults: () => 'Nenhuma clínica encontrada',
            searching: () => 'Buscando...'
          }
        });

        jQuery('#fornecedor_id').select2({
          width: '100%',
          placeholder: 'Digite para buscar o fornecedor...',
          allowClear: true,
          language: {
            noResults: () => 'Nenhum fornecedor encontrado',
            searching: () => 'Buscando...'
          }
        });
      }

      const corpo = document.getElementById('itens-entrada');
      const unidadesEl = document.getElementById('total-unidades');
      const valorEl = document.getElementById('total-valor');

      if (!corpo) return;

      const moedaParaNumero = (texto) => {
        const digitos = String(texto).replace(/\D/g, '');

        return digitos ? parseInt(digitos, 10) / 100 : 0;
      };

      const formatarMoeda = (numero) => {
        const partes = numero.toFixed(2).split('.');

        return 'R$ ' + partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
      };

      // Total de unidades, total por item e valor total da entrada
      const atualizarTotais = () => {
        let unidades = 0;
        let valor = 0;

        corpo.querySelectorAll('tr').forEach((linha) => {
          const campoQuantidade = linha.querySelector('input[name$="[quantidade]"]');
          const campoUnitario = linha.querySelector('input[name$="[valor_unitario]"]');

          const quantidade = parseInt(String(campoQuantidade?.value || '').replace(/\D/g, ''), 10) || 0;
          const unitario = moedaParaNumero(campoUnitario?.value);
          const totalLinha = quantidade * unitario;
          const celulaTotal = linha.querySelector('[data-total-item]');

          if (celulaTotal) {
            celulaTotal.textContent = formatarMoeda(totalLinha);
          }

          unidades += quantidade;
          valor += totalLinha;
        });

        if (unidadesEl) unidadesEl.textContent = unidades;
        if (valorEl) valorEl.textContent = formatarMoeda(valor);
      };

      corpo.addEventListener('input', atualizarTotais);
      corpo.addEventListener('repeater:atualizado', atualizarTotais);

      atualizarTotais();
    });
  </script>
@endpush
