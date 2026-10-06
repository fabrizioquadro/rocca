@extends('layouts.app')

@section('title', 'Editar semana '.$semana->numero.'/'.$prescricao->quantidade_semanas)

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">
          Editar semana {{ $semana->numero }}/{{ $prescricao->quantidade_semanas }}
        </h4>
        <span class="text-muted">Prescrição #{{ $prescricao->id }} · {{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <a href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('prescricoes.semanas.update', [$prescricao, $semana]) }}" id="form-semana">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label" for="data_prevista">Data prevista</label>
            <input
              type="date"
              id="data_prevista"
              name="data_prevista"
              class="form-control"
              value="{{ old('data_prevista', $semana->data_prevista?->format('Y-m-d')) }}" />
            <small class="text-muted">Usada como vencimento da parcela desta semana.</small>
          </div>

          <div class="col-md-3">
            <label class="form-label d-block">Aplicação</label>
            <div class="form-check mt-2">
              <input
                class="form-check-input"
                type="checkbox"
                value="1"
                id="sem_aplicacao"
                name="sem_aplicacao"
                @checked(old('sem_aplicacao', $semana->sem_aplicacao)) />
              <label class="form-check-label" for="sem_aplicacao">
                Semana sem aplicação
              </label>
            </div>
            <small class="text-muted">Sem aplicação não leva medicamentos e não gera parcela.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="observacao">Observação</label>
            <textarea
              id="observacao"
              name="observacao"
              rows="2"
              maxlength="1000"
              class="form-control">{{ old('observacao', $semana->observacao) }}</textarea>
          </div>
        </div>

        <hr class="my-4" />

        <div id="area-itens" class="{{ old('sem_aplicacao', $semana->sem_aplicacao) ? 'd-none' : '' }}">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h6 class="fw-semibold mb-0">Medicamentos / Combos</h6>
            <span class="text-muted small">O valor vem do cadastro do medicamento/combo e pode ser alterado.</span>
          </div>

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
              <tbody id="itens-corpo">
                @forelse ($itens as $indice => $item)
                  @include('prescricoes.semanas._linha-item', ['indice' => $indice, 'item' => $item])
                @empty
                  @include('prescricoes.semanas._linha-item', ['indice' => 0, 'item' => []])
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total da semana</th>
                  <th class="text-end" id="total-semana">R$ 0,00</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          <button type="button" id="adicionar-item" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i>Adicionar medicamento/combo
          </button>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}" class="btn btn-outline-secondary">
            Cancelar
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="ri-save-3-line me-1"></i>Salvar semana
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modelo de linha de item (o JS clona para cada item novo) --}}
  <template id="modelo-item-edicao">
    @include('prescricoes.semanas._linha-item', ['indice' => '__INDICE__', 'item' => []])
  </template>
@endsection

@push('scripts')
  @include('partials.crud-scripts')

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const formulario = document.getElementById('form-semana');

      if (!formulario) {
        return;
      }

      const areaItens = document.getElementById('area-itens');
      const corpoItens = document.getElementById('itens-corpo');
      const modeloItem = document.getElementById('modelo-item-edicao');
      const botaoAdicionar = document.getElementById('adicionar-item');
      const checkSemAplicacao = document.getElementById('sem_aplicacao');
      const totalSemana = document.getElementById('total-semana');

      let proximoIndice = {{ max(count($itens), 1) }};

      const formatarMoeda = (numero) => {
        const partes = numero.toFixed(2).split('.');

        return 'R$ ' + partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
      };

      // Número -> texto do campo de moeda (ex.: 180 -> '180,00')
      const numeroParaCampoMoeda = (numero) => {
        const partes = Number(numero || 0).toFixed(2).split('.');

        return partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
      };

      // Texto do campo de moeda -> número (mesma regra da máscara: centavos)
      const moedaParaNumero = (texto) => {
        const digitos = String(texto || '').replace(/\D/g, '');

        return digitos ? parseInt(digitos, 10) / 100 : 0;
      };

      const valorDaOpcao = (select) => {
        const opcao = select ? select.options[select.selectedIndex] : null;

        if (!opcao || !opcao.value) {
          return 0;
        }

        return parseFloat(opcao.dataset.valor || '0') || 0;
      };

      // Aceita "4.25" e "4,25" (o separador pode variar conforme o navegador)
      const paraNumero = (texto) => parseFloat(String(texto || '').replace(',', '.')) || 0;

      // Ampola não tem fração (0,5 cobra 1), igual à regra da gravação
      const quantidadeCobrada = (linha, quantidade) => {
        const ehCombo = linha.querySelector('[data-tipo-item]').value === 'combo';
        const select = linha.querySelector(ehCombo ? '[data-select-combo]' : '[data-select-medicamento]');
        const opcao = select ? select.options[select.selectedIndex] : null;

        if (!ehCombo && opcao && opcao.value && opcao.dataset.tipo === 'ampola') {
          return Math.ceil(quantidade);
        }

        return quantidade;
      };

      const atualizarTotais = () => {
        let total = 0;

        corpoItens.querySelectorAll('[data-linha-item]').forEach(function (linha) {
          const totalItem = parseFloat(linha.querySelector('[data-total-item]').dataset.valor || '0') || 0;

          total += totalItem;
        });

        totalSemana.textContent = formatarMoeda(total);
      };

      // Alterna medicamento/combo e recalcula o total a partir do campo de
      // valor (que o usuário pode ter alterado).
      const atualizarLinha = (linha) => {
        const ehCombo = linha.querySelector('[data-tipo-item]').value === 'combo';
        const selectMedicamento = linha.querySelector('[data-select-medicamento]');
        const selectCombo = linha.querySelector('[data-select-combo]');

        linha.querySelector('[data-wrap-medicamento]').classList.toggle('d-none', ehCombo);
        linha.querySelector('[data-wrap-combo]').classList.toggle('d-none', !ehCombo);

        selectMedicamento.disabled = ehCombo;
        selectCombo.disabled = !ehCombo;

        const quantidade = paraNumero(linha.querySelector('[data-qtd-item]').value);
        const valor = moedaParaNumero(linha.querySelector('[data-valor-item]').value);
        const total = quantidadeCobrada(linha, quantidade) * valor;
        const celulaTotal = linha.querySelector('[data-total-item]');

        celulaTotal.dataset.valor = total.toFixed(2);
        celulaTotal.textContent = formatarMoeda(total);

        atualizarTotais();
      };

      // Escolheu um medicamento/combo: o valor volta a ser o do cadastro
      const aplicarValorDoCadastro = (linha) => {
        const ehCombo = linha.querySelector('[data-tipo-item]').value === 'combo';
        const select = linha.querySelector(ehCombo ? '[data-select-combo]' : '[data-select-medicamento]');
        const valor = valorDaOpcao(select);

        linha.querySelector('[data-valor-item]').value = valor > 0 ? numeroParaCampoMoeda(valor) : '';
      };

      const prepararLinha = (linha) => {
        const campoTipo = linha.querySelector('[data-tipo-item]');
        const selectMedicamento = linha.querySelector('[data-select-medicamento]');
        const selectCombo = linha.querySelector('[data-select-combo]');
        const campoQtd = linha.querySelector('[data-qtd-item]');
        const campoValor = linha.querySelector('[data-valor-item]');

        // Trocar tipo/medicamento/combo recarrega o valor do cadastro;
        // quantidade e valor digitados apenas recalculam o total.
        campoTipo.addEventListener('change', () => {
          atualizarLinha(linha);
          aplicarValorDoCadastro(linha);
          atualizarLinha(linha);
        });
        selectMedicamento.addEventListener('change', () => {
          aplicarValorDoCadastro(linha);
          atualizarLinha(linha);
        });
        selectCombo.addEventListener('change', () => {
          aplicarValorDoCadastro(linha);
          atualizarLinha(linha);
        });
        campoQtd.addEventListener('input', () => atualizarLinha(linha));
        campoValor.addEventListener('input', () => atualizarLinha(linha));

        linha.querySelector('[data-remover-item]').addEventListener('click', function () {
          linha.remove();
          atualizarTotais();
        });

        // Máscara de moeda no campo de valor (linhas criadas depois do load)
        if (typeof window.inicializarMascaras === 'function') {
          window.inicializarMascaras(linha);
        }

        atualizarLinha(linha);
      };

      corpoItens.querySelectorAll('[data-linha-item]').forEach(prepararLinha);

      const criarLinha = (html) => {
        const tabela = document.createElement('table');

        tabela.innerHTML = '<tbody>' + html + '</tbody>';

        return tabela.querySelector('tbody').firstElementChild;
      };

      botaoAdicionar.addEventListener('click', function () {
        const html = modeloItem.innerHTML.replace(/__INDICE__/g, proximoIndice);

        proximoIndice += 1;

        const linha = criarLinha(html);

        corpoItens.appendChild(linha);
        prepararLinha(linha);
      });

      // "Sem aplicação" esconde os medicamentos
      checkSemAplicacao.addEventListener('change', function () {
        areaItens.classList.toggle('d-none', checkSemAplicacao.checked);
      });

      atualizarTotais();
    });
  </script>
@endpush
