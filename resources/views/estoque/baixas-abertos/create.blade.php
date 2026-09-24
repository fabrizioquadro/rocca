@extends('layouts.app')

@section('title', 'Nova baixa de medicamento aberto')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Nova baixa de medicamento aberto</h4>

      <a href="{{ route('estoque.baixas-abertos.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('estoque.baixas-abertos.store') }}">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="clinica_id">Clínica (onde está o vasilhame aberto) *</label>
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

          <div class="col-md-3">
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

          <div class="col-md-5">
            <label class="form-label" for="observacao">Observação</label>
            <input
              type="text"
              id="observacao"
              name="observacao"
              class="form-control"
              maxlength="1000"
              placeholder="Ex.: baixa de fim de expediente"
              value="{{ old('observacao') }}" />
          </div>
        </div>

        <hr class="my-4" />

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h6 class="fw-semibold mb-0">Medicamentos abertos baixados</h6>

          <button type="button" id="adicionar-item" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i>Adicionar medicamento
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width: 220px;">Medicamento *</th>
                <th style="width: 180px;">Código de barras *</th>
                <th style="width: 110px;">Lote</th>
                <th style="width: 115px;">Vencimento</th>
                <th style="width: 130px;" class="text-end">Disponível (mg)</th>
                <th style="width: 130px;">Baixar (mg) *</th>
                <th style="width: 220px;">Motivo</th>
                <th style="width: 60px;" class="text-center"></th>
              </tr>
            </thead>
            <tbody
              id="itens-baixa-abertos"
              data-repeater
              data-iniciais="itensBaixaAbertos"
              data-placeholder="Digite para buscar..."></tbody>
            <tfoot>
              <tr>
                <th colspan="5" class="text-end">Total a baixar (mg)</th>
                <th id="total-mg" class="text-end">0</th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <small class="text-muted d-block mb-3">
          Escolha o medicamento, leia o código do vasilhame aberto e o sistema traz a quantidade disponível em mg
          (já preenchida na coluna "Baixar"). Pode alterar para dar baixa só em parte do frasco — o que sobrar
          continua aberto. Se zerar, o vasilhame é encerrado. A baixa não pode ser excluída.
        </small>

        <div class="d-flex justify-content-end gap-2">
          <a href="{{ route('estoque.baixas-abertos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Lançar baixa
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modelo de linha usado pelo repetidor --}}
  <template id="modelo-item">
    <tr>
      <td>
        <select name="itens[__INDICE__][medicamento_id]" class="form-select form-select-sm">
          <option value="">Selecione</option>
          @foreach ($medicamentos as $medicamento)
            <option value="{{ $medicamento->id }}">
              {{ $medicamento->nome }} — vasilhame de {{ $medicamento->tamanho_vasilhame_formatado ?? '—' }} mg
            </option>
          @endforeach
        </select>
      </td>
      <td>
        <input
          type="text"
          name="itens[__INDICE__][codigo_barras]"
          class="form-control form-control-sm font-monospace"
          data-buscar-vasilhame
          placeholder="Código do vasilhame"
          autocomplete="off" />
        <div class="text-danger small mt-1 d-none" data-erro-codigo></div>
      </td>
      <td class="small" data-info-lote>—</td>
      <td class="small" data-info-vencimento>—</td>
      <td class="small text-end fw-semibold" data-info-disponivel>—</td>
      <td>
        <input
          type="text"
          inputmode="decimal"
          name="itens[__INDICE__][mg_baixa]"
          class="form-control form-control-sm"
          placeholder="0" />
      </td>
      <td>
        <input
          type="text"
          name="itens[__INDICE__][motivo]"
          class="form-control form-control-sm"
          placeholder="Ex.: frasco quebrado, sobra descartada..." />
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
    window.itensBaixaAbertos = @json($itensIniciais);
    window.gruposDosMedicamentos = @json($grupos);
    window.vasilhameAbertoUrl = '{{ route('estoque.buscarVasilhame') }}';
  </script>

  @include('partials.crud-scripts')
  @include('partials.linhas-dinamicas')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const corpo = document.getElementById('itens-baixa-abertos');
      const totalEl = document.getElementById('total-mg');

      if (!corpo) return;

      if (window.jQuery && jQuery.fn.select2) {
        jQuery('#clinica_id').select2({
          width: '100%',
          language: {
            noResults: () => 'Nenhuma clínica encontrada',
            searching: () => 'Buscando...'
          }
        });
      }

      // 90 -> "90" | 85.75 -> "85,75"
      const formatarMg = (valor) => (Math.round(Number(valor) * 1000) / 1000)
        .toFixed(3)
        .replace(/0+$/, '')
        .replace(/\.$/, '')
        .replace('.', ',');

      const paraNumero = (texto) => {
        const limpo = String(texto || '').trim().replace(/\s/g, '');

        if (! limpo) return 0;

        return Number(limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo) || 0;
      };

      const atualizarTotal = () => {
        let total = 0;

        corpo.querySelectorAll('input[name$="[mg_baixa]"]').forEach((campo) => {
          total += paraNumero(campo.value);
        });

        if (totalEl) {
          totalEl.textContent = formatarMg(total);
        }
      };

      const consultarVasilhame = async (linha) => {
        const select = linha.querySelector('select[name$="[medicamento_id]"]');
        const campoCodigo = linha.querySelector('[data-buscar-vasilhame]');
        const campoMg = linha.querySelector('input[name$="[mg_baixa]"]');
        const aviso = linha.querySelector('[data-erro-codigo]');
        const clinica = document.getElementById('clinica_id');
        const codigo = (campoCodigo?.value || '').trim();

        const limpar = () => {
          ['[data-info-lote]', '[data-info-vencimento]', '[data-info-disponivel]'].forEach((seletor) => {
            const celula = linha.querySelector(seletor);

            if (celula) celula.textContent = '—';
          });

          if (aviso) {
            aviso.textContent = '';
            aviso.classList.add('d-none');
          }

          campoCodigo?.classList.remove('is-invalid');
        };

        const avisar = (mensagem) => {
          if (aviso) {
            aviso.textContent = mensagem;
            aviso.classList.remove('d-none');
          }

          campoCodigo?.classList.add('is-invalid');
        };

        limpar();

        if (! codigo) return;

        if (! clinica?.value) {
          avisar('Selecione a clínica primeiro.');

          return;
        }

        if (! select?.value) {
          avisar('Escolha o medicamento.');

          return;
        }

        const parametros = new URLSearchParams({
          codigo_barras: codigo,
          clinica_id: clinica.value,
          medicamento_ids: window.gruposDosMedicamentos[select.value] || select.value
        });

        try {
          const resposta = await fetch(window.vasilhameAbertoUrl + '?' + parametros.toString(), {
            headers: { 'Accept': 'application/json' }
          });
          const dados = await resposta.json();

          if (! dados.ok) {
            avisar(dados.mensagem || 'Não foi possível usar este código.');

            return;
          }

          const valores = {
            '[data-info-lote]': dados.lote ?? '—',
            '[data-info-vencimento]': dados.vencimento ?? '—',
            '[data-info-disponivel]': dados.mg_restantes_formatado ?? '—'
          };

          Object.keys(valores).forEach((seletor) => {
            const celula = linha.querySelector(seletor);

            if (celula) celula.textContent = valores[seletor];
          });

          // Já traz a quantidade disponível como sugestão de baixa
          if (campoMg && ! campoMg.value) {
            campoMg.value = formatarMg(dados.mg_restantes);
          }

          atualizarTotal();
        } catch (e) {
          avisar('Não foi possível consultar o código de barras.');
        }
      };

      // Leitor de código de barras: o Enter não pode enviar o formulário
      corpo.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || ! event.target.matches?.('[data-buscar-vasilhame]')) return;

        event.preventDefault();
        consultarVasilhame(event.target.closest('tr'));
      });

      corpo.addEventListener('focusout', (event) => {
        if (event.target.matches?.('[data-buscar-vasilhame]')) {
          consultarVasilhame(event.target.closest('tr'));
        }
      });

      // Trocar o medicamento refaz a consulta do código
      if (window.jQuery && jQuery.fn.select2) {
        jQuery(corpo).on('change', 'select[name$="[medicamento_id]"]', function () {
          consultarVasilhame(this.closest('tr'));
        });
      } else {
        corpo.addEventListener('change', (event) => {
          if (event.target.matches?.('select[name$="[medicamento_id]"]')) {
            consultarVasilhame(event.target.closest('tr'));
          }
        });
      }

      corpo.addEventListener('input', atualizarTotal);
      corpo.addEventListener('repeater:atualizado', atualizarTotal);

      atualizarTotal();
    });
  </script>
@endpush
