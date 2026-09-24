@extends('layouts.app')

@section('title', 'Registrar aplicação')

@section('content')
  @php
    $clinicaId = $prescricao->clinica_id;
    // Itens que ainda precisam de decisão neste atendimento
    $aMarcar = $semana->itens->filter(fn ($item) => $item->gera_aplicacao
        && $item->status !== \App\Enums\StatusSemanaItem::Aplicado);
  @endphp

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Registrar aplicação</h4>
        <span class="badge {{ $semana->status->corBadge() }}">{{ $semana->status->label() }}</span>
        <span class="text-muted">
          Semana {{ $semana->numero }}/{{ $prescricao->quantidade_semanas }} ·
          {{ $prescricao->paciente?->nome ?? '—' }}
        </span>
      </div>

      <div class="d-flex flex-wrap gap-2">
        @if ($medicamentosMiligrama->isNotEmpty())
          {{-- Medicamentos por mg: o vasilhame precisa ser aberto antes de usar --}}
          <button
            type="button"
            class="btn btn-info"
            data-bs-toggle="modal"
            data-bs-target="#modal-abrir-vasilhame">
            <i class="ri-archive-2-line me-1"></i>Abrir vasilhame
          </button>
        @endif

        <a href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>
      </div>
    </div>

    <div class="card-body pt-0">
      <div class="row g-3">
        <div class="col-md-4">
          <small class="text-muted d-block">Chegada</small>
          <span class="fw-semibold">{{ $semana->chegada_em_formatada ?? '—' }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Atendimento iniciado</small>
          <span class="fw-semibold">{{ $atendimento->iniciado_em_formatado }}</span>
          @if ($atendimento->iniciadoPor)
            <small class="text-body-secondary d-block">{{ $atendimento->iniciadoPor->nome }}</small>
          @endif
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Clínica</small>
          <span class="fw-semibold">{{ $prescricao->clinica?->nome ?? '—' }}</span>
        </div>
      </div>
    </div>

    @if ($errors->any())
      <div class="card-body pt-0">
        <div class="alert alert-danger mb-0" role="alert">
          @foreach ($errors->all() as $error)
            {{ $error }}<br />
          @endforeach
        </div>
      </div>
    @endif
  </div>

  @if ($atendimento->aplicacoes->isNotEmpty())
    <div class="card mb-4">
      <div class="card-header">
        <h6 class="fw-semibold mb-0">Já aplicado neste atendimento</h6>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Medicamento</th>
                <th style="width: 120px;">Quantidade</th>
                <th style="width: 150px;">Aplicado em</th>
                <th style="width: 130px;">Código de barras</th>
                <th style="width: 120px;">Lote</th>
                <th style="width: 120px;">Vencimento</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($atendimento->aplicacoes as $aplicacao)
                <tr>
                  <td>{{ $aplicacao->medicamento?->nome ?? $aplicacao->item?->nome ?? '—' }}</td>
                  <td>{{ $aplicacao->quantidade_com_unidade }}</td>
                  <td>{{ $aplicacao->aplicado_em_formatado }}</td>
                  <td>{{ $aplicacao->codigo_barras ?? '—' }}</td>
                  <td>{{ $aplicacao->lote ?? '—' }}</td>
                  <td>{{ $aplicacao->vencimento_formatado ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  <form
    method="POST"
    action="{{ route('prescricoes.semanas.aplicar', [$prescricao, $semana]) }}"
    id="form-aplicacao"
    data-confirmar="Finalizar o atendimento e registrar as aplicações?">
    @csrf

    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="fw-semibold mb-0">Aplicação da semana</h6>
        <span class="text-muted small">
          Marque o checkbox <strong>Pendente</strong> só no que não foi aplicado. Nos demais, leia o código de
          barras do lote — a data e a hora são as do momento em que salvar.
        </span>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 130px;">
                  <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" id="marcar-todos-pendentes" data-marcar-todos-pendentes />
                    <label class="form-check-label" for="marcar-todos-pendentes">Pendente</label>
                  </div>
                </th>
                <th style="min-width: 200px;">Medicamento</th>
                <th style="width: 110px;">Qtd. prescrita</th>
                <th style="min-width: 300px;">Código de barras</th>
                <th style="min-width: 220px;">Lote</th>
                <th style="min-width: 180px;">Observação</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($aMarcar as $item)
                @php
                  if ($item->tipo === 'combo') {
                      $medicamentosPermitidos = $item->combo?->itens->pluck('medicamento_id')->filter()->implode(',');
                  } elseif ($item->eh_miligrama) {
                      // Mesmo produto: vale vasilhame do medicamento do item ou de
                      // outro do mesmo grupo (Mounjaro 60MG / Mounjaro 90MG)
                      $medicamentosPermitidos = $item->medicamento->idsDoMesmoProduto()->implode(',');
                  } else {
                      $medicamentosPermitidos = $item->medicamento_id;
                  }

                  $pendenteAntigo = (bool) old("itens.{$item->id}.pendente", $item->status === \App\Enums\StatusSemanaItem::Pendente);
                @endphp

                <tr
                  data-item-aplicacao
                  data-item-id="{{ $item->id }}"
                  data-medicamentos-permitidos="{{ $medicamentosPermitidos }}"
                  @if ($item->eh_miligrama)
                    data-miligrama="1"
                    data-medicamento-id="{{ $item->medicamento_id }}"
                    data-necessario="{{ $item->quantidade_cobranca }}"
                  @endif>
                  <td>
                    <div class="form-check mb-0">
                      <input
                        type="checkbox"
                        class="form-check-input"
                        id="pendente-{{ $item->id }}"
                        name="itens[{{ $item->id }}][pendente]"
                        value="1"
                        data-pendente
                        @checked($pendenteAntigo) />
                      <label class="form-check-label" for="pendente-{{ $item->id }}">Pendente</label>
                    </div>
                  </td>

                  <td>
                    <span class="fw-semibold">{{ $item->nome }}</span>
                    <small class="text-body-secondary d-block">
                      {{ $item->tipo === 'combo' ? 'Combo' : 'Medicamento' }}
                      · previsto {{ $item->quantidade_formatada }}
                    </small>
                  </td>

                  {{-- Quantidade não é editável: é a quantidade prescrita/cobrada.
                       Medicamento por mg mostra a dose em mg. --}}
                  <td>{{ $item->eh_miligrama ? $item->quantidade_formatada.' mg' : (int) $item->quantidade_cobranca }}</td>

                  <td>
                    <div class="input-group input-group-sm">
                      <input
                        type="text"
                        name="itens[{{ $item->id }}][codigo_barras]"
                        class="form-control form-control-sm font-monospace"
                        placeholder="{{ $item->eh_miligrama ? '1º vasilhame' : 'Ler código de barras' }}"
                        autocomplete="off"
                        data-codigo-barras
                        value="{{ old("itens.{$item->id}.codigo_barras") }}" />

                      @if ($item->eh_miligrama)
                        {{-- Aplicação dividida em 2 vasilhames --}}
                        <button
                          type="button"
                          class="btn btn-outline-info btn-vasilhames"
                          data-bs-toggle="modal"
                          data-bs-target="#modal-vasilhames-{{ $item->id }}"
                          title="Aplicação com 2 vasilhames">
                          <i class="ri-archive-2-line"></i>
                        </button>
                      @endif
                    </div>

                    @if ($item->eh_miligrama)
                      {{-- 2º vasilhame: aparece só depois de confirmar no modal --}}
                      <input
                        type="text"
                        name="itens[{{ $item->id }}][codigo_barras_2]"
                        class="form-control form-control-sm font-monospace mt-1 d-none"
                        placeholder="2º vasilhame"
                        autocomplete="off"
                        data-codigo-barras-2
                        value="{{ old("itens.{$item->id}.codigo_barras_2") }}" />

                      {{-- Divisão da dose feita no modal (mg de cada vasilhame) --}}
                      <input type="hidden" name="itens[{{ $item->id }}][quantidade_1]" data-quantidade-1 value="{{ old("itens.{$item->id}.quantidade_1") }}" />
                      <input type="hidden" name="itens[{{ $item->id }}][quantidade_2]" data-quantidade-2 value="{{ old("itens.{$item->id}.quantidade_2") }}" />
                    @endif
                  </td>

                  <td data-info-lote>
                    <span class="badge bg-label-secondary">Aguardando código</span>
                  </td>

                  <td>
                    <input
                      type="text"
                      name="itens[{{ $item->id }}][observacao]"
                      class="form-control form-control-sm"
                      maxlength="1000"
                      placeholder="Observação do medicamento"
                      value="{{ old("itens.{$item->id}.observacao") }}" />
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if ($aMarcar->isEmpty())
          <p class="text-muted mb-0">
            Todos os medicamentos desta semana já foram aplicados — é só finalizar o atendimento.
          </p>
        @endif

        <div class="row g-3 mt-1">
          <div class="col-md-8">
            <label class="form-label" for="observacao">Observação geral do atendimento</label>
            <textarea
              id="observacao"
              name="observacao"
              class="form-control"
              rows="3"
              maxlength="2000"
              placeholder="Anotações do atendimento (ex.: paciente relatou dor no local)">{{ old('observacao', $atendimento->observacao) }}</textarea>
          </div>

          <div class="col-md-4 d-flex align-items-end justify-content-end">
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-line me-1"></i>Finalizar atendimento
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  @if ($medicamentosMiligrama->isNotEmpty())
    {{-- Só os medicamentos desta aplicação (por mg) podem abrir vasilhame aqui --}}
    <div class="modal fade" id="modal-abrir-vasilhame" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <form
            method="POST"
            action="{{ route('prescricoes.semanas.vasilhames.store', [$prescricao, $semana]) }}"
            id="form-abrir-vasilhame">
            @csrf

            <div class="modal-header">
              <h5 class="modal-title"><i class="ri-archive-2-line me-1"></i>Abrir vasilhame</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
              <p class="text-muted small mb-3">
                Ao abrir, o vasilhame sai do estoque fechado e passa a ter o saldo em mg, que as aplicações
                vão consumindo até zerar. Pode-se abrir mais de um vasilhame do mesmo medicamento.
              </p>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="vasilhame-medicamento">Medicamento *</label>
                  <select
                    id="vasilhame-medicamento"
                    name="medicamento_id"
                    class="form-select"
                    data-medicamentos-permitidos="{{ $medicamentosMiligrama->pluck('id')->implode(',') }}"
                    required>
                    <option value="">Selecione</option>
                    @foreach ($medicamentosMiligrama as $medicamento)
                      <option value="{{ $medicamento->id }}" @selected(old('medicamento_id') == $medicamento->id)>
                        {{ $medicamento->nome }}
                        — vasilhame de {{ $medicamento->tamanho_vasilhame_formatado ?? '—' }} mg
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="vasilhame-codigo">Código de barras *</label>
                  <input
                    type="text"
                    id="vasilhame-codigo"
                    name="codigo_barras"
                    class="form-control font-monospace"
                    placeholder="Ler código do vasilhame"
                    autocomplete="off"
                    value="{{ old('codigo_barras') }}"
                    required />
                </div>

                <div class="col-12" id="vasilhame-info"></div>

                <div class="col-12">
                  <label class="form-label" for="vasilhame-observacao">Observação</label>
                  <input
                    type="text"
                    id="vasilhame-observacao"
                    name="observacao"
                    class="form-control"
                    maxlength="1000"
                    value="{{ old('observacao') }}" />
                </div>
              </div>

              @if ($vasilhamesAbertos->isNotEmpty())
                <h6 class="fw-semibold mt-4 mb-2">Vasilhames abertos desta aplicação</h6>

                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Medicamento</th>
                        <th>Código de barras</th>
                        <th>Lote</th>
                        <th>Vencimento</th>
                        <th class="text-end">Restam</th>
                        <th>Aberto</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($vasilhamesAbertos as $vasilhame)
                        <tr>
                          <td>{{ $vasilhame->medicamento?->nome ?? '—' }}</td>
                          <td class="font-monospace">{{ $vasilhame->codigo_barras ?? '—' }}</td>
                          <td>{{ $vasilhame->lote ?? '—' }}</td>
                          <td>{{ $vasilhame->vencimento_formatado ?? '—' }}</td>
                          <td class="text-end fw-semibold">{{ $vasilhame->mg_restantes_formatado }}</td>
                          <td class="small text-body-secondary">{{ $vasilhame->descricao_abertura }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
              <button type="submit" class="btn btn-info">
                <i class="ri-lock-unlock-line me-1"></i>Abrir vasilhame
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

  {{-- Divisão da dose entre 2 vasilhames: abre pelo ícone ao lado do código --}}
  @foreach ($aMarcar->filter(fn ($item) => $item->eh_miligrama) as $item)
    <div
      class="modal fade"
      id="modal-vasilhames-{{ $item->id }}"
      tabindex="-1"
      aria-hidden="true"
      data-modal-vasilhames
      data-item-id="{{ $item->id }}"
      data-item-nome="{{ $item->nome }}"
      data-medicamento-id="{{ $item->medicamento_id }}"
      data-medicamentos-permitidos="{{ $item->medicamento->idsDoMesmoProduto()->implode(',') }}"
      data-necessario="{{ $item->quantidade_cobranca }}">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="ri-archive-2-line me-1"></i>Aplicação com 2 vasilhames</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>

          <div class="modal-body">
            <p class="text-muted small mb-3">
              <strong>{{ $item->nome }}</strong> — dose de
              <strong>{{ $item->quantidade_formatada }} mg</strong> a aplicar entre até 2 vasilhames já abertos.
              Informe o código e a quantidade que sai de cada um (a soma tem que fechar a dose).
            </p>

            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 70px;">Vasilhame</th>
                    <th style="min-width: 170px;">Código de barras</th>
                    <th style="width: 150px;">Quantidade (mg)</th>
                    <th>Lote, vencimento e saldo</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="fw-semibold">1º</td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm font-monospace"
                        placeholder="Ler código"
                        autocomplete="off"
                        aria-label="Código de barras do 1º vasilhame"
                        data-modal-codigo-1 />
                    </td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        inputmode="decimal"
                        placeholder="Ex.: 85,75"
                        aria-label="Quantidade aplicada do 1º vasilhame em mg"
                        data-modal-quantidade-1 />
                    </td>
                    <td class="small" data-modal-info-1>
                      <span class="text-body-secondary">Aguardando código</span>
                    </td>
                  </tr>

                  <tr>
                    <td class="fw-semibold">2º</td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm font-monospace"
                        placeholder="Ler código"
                        autocomplete="off"
                        aria-label="Código de barras do 2º vasilhame"
                        data-modal-codigo-2 />
                    </td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        inputmode="decimal"
                        placeholder="Ex.: 14,25"
                        aria-label="Quantidade aplicada do 2º vasilhame em mg"
                        data-modal-quantidade-2 />
                    </td>
                    <td class="small" data-modal-info-2>
                      <span class="text-body-secondary">Opcional — use se o 1º não fechar a dose</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-end">Dose prescrita</th>
                    <th>{{ $item->quantidade_formatada }} mg</th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="alert alert-info mt-3 mb-0 py-2 small" data-modal-resumo>
              Leia o código do 1º vasilhame para começar.
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-outline-warning me-auto"
              data-modal-voltar-1
              title="Desfaz a divisão e volta a permitir informar o código de 1 vasilhame"
              disabled>
              <i class="ri-arrow-go-back-line me-1"></i>Voltar para 1 vasilhame
            </button>

            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary" data-modal-confirmar disabled>
              <i class="ri-check-line me-1"></i>Confirmar aplicação
            </button>
          </div>
        </div>
      </div>
    </div>
  @endforeach
@endsection

@push('styles')
  <style>
    /* O .input-group-sm engorda qualquer .btn filho (padding .629rem 1rem):
       aqui o botão fica compacto e acompanha a altura do input. */
    .input-group-sm > .btn.btn-vasilhames {
      padding: 0 0.45rem;
      height: auto;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* Códigos vindos do modal dos 2 vasilhames: o usuário não mexe no campo */
    .input-codigo-travado {
      pointer-events: none;
      background-color: rgba(75, 70, 92, 0.06);
    }
  </style>
@endpush

@push('scripts')
  <script>
    window.estoqueCodigoBarrasUrl = '{{ route('estoque.buscarCodigoBarras') }}';
    window.estoqueVasilhameUrl = '{{ route('estoque.buscarVasilhame') }}';
    window.aplicacaoClinicaId = '{{ $clinicaId }}';
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const linhas = document.querySelectorAll('[data-item-aplicacao]');
      const hoje = new Date();

      hoje.setHours(0, 0, 0, 0);

      const arredondar = (valor) => Math.round(Number(valor) * 1000) / 1000;

      // 4.25 -> 4,25 (sem zeros desnecessários)
      const formatarMg = (valor) => arredondar(valor)
        .toFixed(3)
        .replace(/0+$/, '')
        .replace(/\.$/, '')
        .replace('.', ',');

      const textoVencimento = (texto) => {
        const partes = String(texto || '').split('/');

        if (partes.length !== 3) return null;

        return new Date(Number(partes[2]), Number(partes[1]) - 1, Number(partes[0]));
      };

      const mostrarInfo = (linha, html) => {
        linha.querySelector('[data-info-lote]').innerHTML = html || '';
      };

      const erro = (mensagem) => `<span class="small text-danger">${mensagem}</span>`;

      // 85,75 -> 85,75 (aceita vírgula ou ponto)
      const textoParaNumero = (texto) => {
        const limpo = String(texto || '').trim().replace(/\s/g, '');

        if (! limpo) return 0;

        const normalizado = limpo.includes(',')
          ? limpo.replace(/\./g, '').replace(',', '.')
          : limpo;

        return Number(normalizado) || 0;
      };

      const consultarCodigoDoVasilhame = async (medicamentosPermitidos, codigo) => {
        const url = new URL(window.estoqueVasilhameUrl, window.location.origin);
        url.searchParams.set('codigo_barras', codigo);
        url.searchParams.set('clinica_id', window.aplicacaoClinicaId);
        url.searchParams.set('medicamento_ids', medicamentosPermitidos || '');

        const resposta = await fetch(url, { headers: { 'Accept': 'application/json' } });

        return resposta.json();
      };

      const detalhesDoVasilhame = (dados) => `<span class="badge bg-label-success">Vasilhame aberto</span>`
        + `<span class="d-block small text-body-secondary mt-1">lote ${dados.lote ?? '—'}`
        + ` · venc. ${dados.vencimento ?? '—'}`
        + ` · restam <strong>${dados.mg_restantes_formatado}</strong></span>`;

      // Divisão confirmada no modal: o 2º input aparece e os dois ficam
      // travados — só abrindo o modal de novo para alterar
      const travarDivisao = (linha) => {
        const campo1 = linha.querySelector('[data-codigo-barras]');
        const campo2 = linha.querySelector('[data-codigo-barras-2]');

        if (! campo2) return;

        const travado = Boolean(linha.dataset.divisao);

        campo2.classList.toggle('d-none', ! travado || ! campo2.value.trim());

        [campo1, campo2].forEach((campo) => {
          campo.readonly = travado;
          campo.classList.toggle('input-codigo-travado', travado);
          campo.title = travado ? 'Preenchido pelo modal "Aplicação com 2 vasilhames"' : '';
        });
      };

      // Resumo da divisão (até 2 vasilhames) exibido na própria linha
      const mostrarDivisaoDaLinha = (linha) => {
        let divisao = [];

        try {
          divisao = JSON.parse(linha.dataset.divisao || '[]');
        } catch (e) {
          divisao = [];
        }

        if (! divisao.length) return false;

        const necessario = Number(linha.dataset.necessario || 0);

        let html = `<span class="badge bg-label-info">${divisao.length} vasilhame(s)</span>`;

        divisao.forEach((item, indice) => {
          html += `<span class="d-block small text-body-secondary mt-1">${indice + 1}º: lote ${item.lote ?? '—'}`
            + ` · <strong>${formatarMg(item.mg)} mg</strong></span>`;
        });

        html += `<span class="d-block small text-success mt-1">Total: ${formatarMg(necessario)} mg (dose)</span>`;

        mostrarInfo(linha, html);

        return true;
      };

      // Medicamento por mg: só vasilhame ABERTO pode ser aplicado. A linha tem
      // dois códigos (até 2 vasilhames) e, quando a divisão veio do modal, o
      // resumo dela é que fica na linha.
      const avaliarMiligrama = async (linha) => {
        const campo1 = linha.querySelector('[data-codigo-barras]');
        const campo2 = linha.querySelector('[data-codigo-barras-2]');
        const quantidade1 = linha.querySelector('[data-quantidade-1]');
        const necessario = Number(linha.dataset.necessario || 0);
        const codigo1 = campo1.value.trim();
        const codigo2 = campo2 ? campo2.value.trim() : '';

        linha.dataset.vasilhameId = '';
        linha.dataset.vasilhameIdSegundo = '';

        // Divisão confirmada no modal (com as quantidades de cada vasilhame)
        if (linha.dataset.divisao && textoParaNumero(quantidade1?.value) > 0) {
          mostrarDivisaoDaLinha(linha);

          return;
        }

        if (! codigo1) {
          mostrarInfo(linha, codigo2
            ? erro('Informe o código do 1º vasilhame.')
            : '<span class="badge bg-label-secondary">Aguardando código</span>');

          return;
        }

        mostrarInfo(linha, '<span class="small text-body-secondary">Consultando...</span>');

        let primeiro;

        try {
          primeiro = await consultarCodigoDoVasilhame(linha.dataset.medicamentosPermitidos, codigo1);
        } catch (e) {
          mostrarInfo(linha, erro('Não foi possível consultar o código de barras.'));

          return;
        }

        if (! primeiro.ok) {
          mostrarInfo(linha, erro(primeiro.mensagem));

          return;
        }

        linha.dataset.vasilhameId = primeiro.vasilhame_id;

        const restante1 = Number(primeiro.mg_restantes || 0);

        let html = detalhesDoVasilhame(primeiro);

        if (! codigo2) {
          if (restante1 < necessario) {
            html += `<span class="d-block small text-warning mt-1">Faltam ${formatarMg(necessario - restante1)} mg`
              + ' — leia o 2º vasilhame ou use "Aplicação com 2 vasilhames".</span>';
          }

          mostrarInfo(linha, html);

          return;
        }

        if (codigo2 === codigo1) {
          mostrarInfo(linha, html + erro('O 2º vasilhame precisa ser um código diferente do 1º.'));

          return;
        }

        let segundo;

        try {
          segundo = await consultarCodigoDoVasilhame(linha.dataset.medicamentosPermitidos, codigo2);
        } catch (e) {
          mostrarInfo(linha, html + erro('Não foi possível consultar o código do 2º vasilhame.'));

          return;
        }

        if (! segundo.ok) {
          mostrarInfo(linha, html + erro(segundo.mensagem));

          return;
        }

        linha.dataset.vasilhameIdSegundo = segundo.vasilhame_id;

        const total = arredondar(restante1 + Number(segundo.mg_restantes || 0));

        html += `<span class="d-block small text-body-secondary mt-1">2º: lote ${segundo.lote ?? '—'}`
          + ` · restam ${segundo.mg_restantes_formatado}</span>`;

        if (total < necessario) {
          html += '<span class="d-block small text-danger mt-1">Os vasilhames não têm a quantidade necessária:'
            + ` precisa de ${formatarMg(necessario)} mg e há ${formatarMg(total)} mg.</span>`;
        } else {
          html += `<span class="d-block small text-success mt-1">Disponível: ${formatarMg(total)} mg`
            + ` de ${formatarMg(necessario)} mg.</span>`;
        }

        mostrarInfo(linha, html);
      };

      // Campos que só valem quando o medicamento foi aplicado
      const alternarCampos = (linha) => {
        const pendente = linha.querySelector('[data-pendente]').checked;
        const campoCodigo = linha.querySelector('[data-codigo-barras]');

        linha.querySelectorAll('[data-codigo-barras], [data-codigo-barras-2]').forEach((campo) => {
          campo.disabled = pendente;
        });

        linha.querySelectorAll('[data-bs-toggle="modal"]').forEach((botao) => {
          botao.disabled = pendente;
        });

        if (pendente) {
          mostrarInfo(linha, '<span class="badge bg-label-secondary">Não aplicado</span>');
        } else if (linha.dataset.miligrama) {
          avaliarMiligrama(linha);
        } else if (campoCodigo.value.trim()) {
          consultarCodigo(linha);
        } else {
          mostrarInfo(linha, '<span class="badge bg-label-secondary">Aguardando código</span>');
        }
      };

      const consultarCodigo = async (linha) => {
        const campo = linha.querySelector('[data-codigo-barras]');
        const codigo = campo.value.trim();

        linha.dataset.entradaItemId = '';

        if (! codigo) {
          mostrarInfo(linha, '<span class="badge bg-label-secondary">Aguardando código</span>');

          return;
        }

        mostrarInfo(linha, '<span class="small text-body-secondary">Consultando...</span>');

        const url = new URL(window.estoqueCodigoBarrasUrl, window.location.origin);
        url.searchParams.set('codigo_barras', codigo);
        url.searchParams.set('clinica_id', window.aplicacaoClinicaId);

        try {
          const resposta = await fetch(url, { headers: { 'Accept': 'application/json' } });
          const dados = await resposta.json();

          if (! dados.ok) {
            mostrarInfo(linha, `<span class="small text-danger">${dados.mensagem}</span>`);

            return;
          }

          const permitidos = String(linha.dataset.medicamentosPermitidos || '').split(',').filter(Boolean);

          if (permitidos.length && ! permitidos.includes(String(dados.medicamento_id))) {
            mostrarInfo(linha, `<span class="small text-danger">Este código é de ${dados.medicamento} — não é o medicamento desta linha.</span>`);

            return;
          }

          const vencimento = textoVencimento(dados.vencimento);

          if (vencimento && vencimento < hoje) {
            mostrarInfo(linha, `<span class="small text-danger">Lote ${dados.lote} VENCIDO em ${dados.vencimento} — não pode ser aplicado.</span>`);

            return;
          }

          linha.dataset.entradaItemId = dados.entrada_item_id;

          mostrarInfo(
            linha,
            `<span class="badge bg-label-success">Lote ${dados.lote ?? '—'}</span>`
              + `<span class="d-block small text-body-secondary mt-1">venc. ${dados.vencimento ?? '—'}`
              + ` · saldo na clínica: <strong>${dados.saldo}</strong></span>`
          );
        } catch (erro) {
          mostrarInfo(linha, '<span class="small text-danger">Não foi possível consultar o código de barras.</span>');
        }
      };

      linhas.forEach((linha) => {
        const campoPendente = linha.querySelector('[data-pendente]');
        const campoCodigo = linha.querySelector('[data-codigo-barras]');

        // Divisão confirmada numa tentativa anterior volta a aparecer na linha
        if (linha.dataset.miligrama) {
          const divisao1 = linha.querySelector('[data-quantidade-1]');
          const divisao2 = linha.querySelector('[data-quantidade-2]');

          if (textoParaNumero(divisao1?.value) > 0) {
            linha.dataset.divisao = JSON.stringify([
              { lote: null, mg: textoParaNumero(divisao1.value) },
              ...(textoParaNumero(divisao2?.value) > 0
                ? [{ lote: null, mg: textoParaNumero(divisao2.value) }]
                : []),
            ]);
          }

          travarDivisao(linha);
        }

        alternarCampos(linha);

        campoPendente.addEventListener('change', () => alternarCampos(linha));

        campoCodigo.addEventListener('change', () => {
          if (linha.dataset.miligrama) {
            avaliarMiligrama(linha);
          } else {
            consultarCodigo(linha);
          }
        });
      });

      // Marca/desmarca todas as linhas como pendentes
      const marcarTodos = document.querySelector('[data-marcar-todos-pendentes]');

      marcarTodos?.addEventListener('change', () => {
        linhas.forEach((linha) => {
          linha.querySelector('[data-pendente]').checked = marcarTodos.checked;

          alternarCampos(linha);
        });
      });

      // Aplicação com 2 vasilhames: monta a divisão da dose e joga na linha ----
      document.querySelectorAll('[data-modal-vasilhames]').forEach((modal) => {
        const linha = document.querySelector(`[data-item-aplicacao][data-item-id="${modal.dataset.itemId}"]`);

        if (! linha) return;

        const campo1 = modal.querySelector('[data-modal-codigo-1]');
        const campo2 = modal.querySelector('[data-modal-codigo-2]');
        const quantidade1 = modal.querySelector('[data-modal-quantidade-1]');
        const quantidade2 = modal.querySelector('[data-modal-quantidade-2]');
        const info1 = modal.querySelector('[data-modal-info-1]');
        const info2 = modal.querySelector('[data-modal-info-2]');
        const resumo = modal.querySelector('[data-modal-resumo]');
        const confirmar = modal.querySelector('[data-modal-confirmar]');
        const voltarUm = modal.querySelector('[data-modal-voltar-1]');
        const necessario = Number(modal.dataset.necessario || 0);
        const vasilhames = { 1: null, 2: null };

        const restanteDe = (numero) => (vasilhames[numero] ? Number(vasilhames[numero].mg_restantes || 0) : null);

        // Lista do que impede de confirmar (vazia = tudo certo)
        const problemas = () => {
          const lista = [];
          const mg1 = textoParaNumero(quantidade1.value);
          const mg2 = textoParaNumero(quantidade2.value);
          const restante1 = restanteDe(1);
          const restante2 = restanteDe(2);

          if (! vasilhames[1]) {
            lista.push('Leia o código do 1º vasilhame (precisa estar aberto).');
          } else if (mg1 <= 0) {
            lista.push('Informe a quantidade que sai do 1º vasilhame.');
          } else if (mg1 > restante1 + 0.001) {
            lista.push(`O 1º vasilhame tem só ${formatarMg(restante1)} mg.`);
          }

          if (campo2.value.trim() || mg2 > 0) {
            if (! vasilhames[2]) {
              lista.push('Leia o código do 2º vasilhame (precisa estar aberto).');
            } else if (mg2 <= 0) {
              lista.push('Informe a quantidade que sai do 2º vasilhame.');
            } else if (mg2 > restante2 + 0.001) {
              lista.push(`O 2º vasilhame tem só ${formatarMg(restante2)} mg.`);
            }
          }

          const total = arredondar(mg1 + mg2);

          if (! lista.length && Math.abs(total - necessario) > 0.001) {
            lista.push(total < necessario
              ? `Faltam ${formatarMg(necessario - total)} mg para fechar a dose de ${formatarMg(necessario)} mg.`
              : `A soma (${formatarMg(total)} mg) passa a dose de ${formatarMg(necessario)} mg.`);
          }

          return lista;
        };

        const atualizarResumo = () => {
          const lista = problemas();

          if (lista.length) {
            resumo.className = 'alert alert-warning mt-3 mb-0 py-2 small';
            resumo.innerHTML = '• ' + lista.join('<br />• ');
            confirmar.disabled = true;

            return;
          }

          const mg1 = textoParaNumero(quantidade1.value);
          const mg2 = textoParaNumero(quantidade2.value);

          resumo.className = 'alert alert-success mt-3 mb-0 py-2 small';
          resumo.innerHTML = `1º vasilhame: <strong>${formatarMg(mg1)} mg</strong>`
            + (mg2 > 0 ? ` · 2º vasilhame: <strong>${formatarMg(mg2)} mg</strong>` : '')
            + ` · total <strong>${formatarMg(arredondar(mg1 + mg2))} mg</strong>`
            + ` de ${formatarMg(necessario)} mg da dose.`;
          confirmar.disabled = false;
        };

        const consultar = async (numero) => {
          const campo = numero === 1 ? campo1 : campo2;
          const info = numero === 1 ? info1 : info2;
          const codigo = campo.value.trim();

          vasilhames[numero] = null;

          if (! codigo) {
            info.innerHTML = numero === 2
              ? '<span class="text-body-secondary">Opcional — use se o 1º não fechar a dose</span>'
              : '<span class="text-body-secondary">Aguardando código</span>';

            atualizarResumo();

            return;
          }

          info.innerHTML = '<span class="text-body-secondary">Consultando...</span>';

          try {
            const dados = await consultarCodigoDoVasilhame(modal.dataset.medicamentosPermitidos, codigo);

            if (! dados.ok) {
              info.innerHTML = erro(dados.mensagem);
              atualizarResumo();

              return;
            }

            if (numero === 2 && codigo === campo1.value.trim()) {
              info.innerHTML = erro('O 2º vasilhame precisa ser de um código diferente do 1º.');
              atualizarResumo();

              return;
            }

            vasilhames[numero] = dados;

            info.innerHTML = detalhesDoVasilhame(dados);

            // Sugere a quantidade de cada vasilhame (não mexe no que foi digitado)
            if (numero === 1 && ! quantidade1.dataset.manual) {
              quantidade1.value = formatarMg(Math.min(Number(dados.mg_restantes || 0), necessario));
            }

            if (numero === 2 && ! quantidade2.dataset.manual) {
              quantidade2.value = formatarMg(Math.max(arredondar(necessario - textoParaNumero(quantidade1.value)), 0));
            }

            atualizarResumo();
          } catch (e) {
            info.innerHTML = erro('Não foi possível consultar o código.');

            atualizarResumo();
          }
        };

        campo1.addEventListener('change', () => consultar(1));
        campo2.addEventListener('change', () => consultar(2));

        [quantidade1, quantidade2].forEach((campo) => campo.addEventListener('input', () => {
          campo.dataset.manual = '1';

          atualizarResumo();
        }));

        // Reabre com o que já está na linha (volta de erro de validação)
        modal.addEventListener('show.bs.modal', () => {
          // Só faz sentido voltar para 1 vasilhame se a divisão estiver valendo
          voltarUm.disabled = ! linha.dataset.divisao;

          if (! campo1.value) {
            campo1.value = linha.querySelector('[data-codigo-barras]').value.trim();
          }

          if (! campo2.value) {
            campo2.value = linha.querySelector('[data-codigo-barras-2]').value.trim();
          }

          if (! quantidade1.value && linha.querySelector('[data-quantidade-1]').value) {
            quantidade1.value = linha.querySelector('[data-quantidade-1]').value;
            quantidade1.dataset.manual = '1';
          }

          if (! quantidade2.value && linha.querySelector('[data-quantidade-2]').value) {
            quantidade2.value = linha.querySelector('[data-quantidade-2]').value;
            quantidade2.dataset.manual = '1';
          }

          if (campo1.value.trim()) consultar(1);

          if (campo2.value.trim()) consultar(2);
        });

        confirmar.addEventListener('click', () => {
          if (confirmar.disabled) return;

          const codigo1 = campo1.value.trim();
          const mg1 = textoParaNumero(quantidade1.value);
          const mg2 = textoParaNumero(quantidade2.value);
          const codigo2 = mg2 > 0 ? campo2.value.trim() : '';

          // O envio é o form do atendimento: os 2 códigos vão para os inputs da
          // linha (travados) e a divisão da dose fica nos campos ocultos
          linha.querySelector('[data-codigo-barras]').value = codigo1;
          linha.querySelector('[data-codigo-barras-2]').value = codigo2;
          linha.querySelector('[data-quantidade-1]').value = formatarMg(mg1);
          linha.querySelector('[data-quantidade-2]').value = formatarMg(mg2);
          linha.dataset.divisao = JSON.stringify([
            { lote: vasilhames[1]?.lote, mg: mg1 },
            ...(mg2 > 0 ? [{ lote: vasilhames[2]?.lote, mg: mg2 }] : []),
          ]);

          travarDivisao(linha);
          mostrarDivisaoDaLinha(linha);

          bootstrap.Modal.getInstance(modal)?.hide();
        });

        // Volta para o modo de 1 vasilhame: desfaz a divisão e destrava o campo
        voltarUm.addEventListener('click', () => {
          delete linha.dataset.divisao;

          linha.querySelector('[data-codigo-barras-2]').value = '';
          linha.querySelector('[data-quantidade-1]').value = '';
          linha.querySelector('[data-quantidade-2]').value = '';

          // Zera o que estava montado no modal para ele voltar a sugerir
          campo2.value = '';
          quantidade1.value = '';
          quantidade2.value = '';
          delete quantidade1.dataset.manual;
          delete quantidade2.dataset.manual;
          vasilhames[1] = null;
          vasilhames[2] = null;

          travarDivisao(linha);
          avaliarMiligrama(linha);
          atualizarResumo();

          bootstrap.Modal.getInstance(modal)?.hide();
        });

        atualizarResumo();
      });

      // Modal "Abrir vasilhame": confere o código antes de abrir
      const formVasilhame = document.getElementById('form-abrir-vasilhame');

      if (formVasilhame) {
        const selectMedicamento = document.getElementById('vasilhame-medicamento');
        const campoVasilhame = document.getElementById('vasilhame-codigo');
        const infoVasilhame = document.getElementById('vasilhame-info');

        const consultarVasilhameDoModal = async () => {
          const codigo = campoVasilhame.value.trim();

          if (! codigo) {
            infoVasilhame.innerHTML = '';

            return;
          }

          infoVasilhame.innerHTML = '<span class="small text-body-secondary">Consultando...</span>';

          try {
            const url = new URL(window.estoqueVasilhameUrl, window.location.origin);
            url.searchParams.set('codigo_barras', codigo);
            url.searchParams.set('clinica_id', window.aplicacaoClinicaId);
            url.searchParams.set('medicamento_ids', selectMedicamento.dataset.medicamentosPermitidos || selectMedicamento.value);

            const resposta = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const dados = await resposta.json();

            if (dados.estado === 'fechado') {
              infoVasilhame.innerHTML = '<div class="alert alert-success mb-0 py-2">Vasilhame fechado ('
                + `${dados.saldo_fechado} unidade(s) em estoque) — pode abrir.</div>`;

              return;
            }

            if (dados.estado === 'aberto') {
              infoVasilhame.innerHTML = '<div class="alert alert-warning mb-0 py-2">Este vasilhame já está aberto'
                + ` (restam ${dados.mg_restantes_formatado}).</div>`;

              return;
            }

            infoVasilhame.innerHTML = `<div class="alert alert-danger mb-0 py-2">${dados.mensagem ?? 'Não foi possível usar este código.'}</div>`;
          } catch (e) {
            infoVasilhame.innerHTML = '<div class="alert alert-danger mb-0 py-2">Não foi possível consultar o código.</div>';
          }
        };

        campoVasilhame.addEventListener('change', consultarVasilhameDoModal);
        selectMedicamento.addEventListener('change', consultarVasilhameDoModal);
        consultarVasilhameDoModal();
      }

      @if ($errors->has('vasilhame') || $errors->has('medicamento_id') || $errors->has('codigo_barras'))
        // Deu erro ao abrir o vasilhame: reabre o modal com o que foi digitado
        const modalVasilhame = document.getElementById('modal-abrir-vasilhame');

        if (modalVasilhame && window.bootstrap) {
          new bootstrap.Modal(modalVasilhame).show();
        }
      @endif
    });
  </script>

  @include('partials.crud-scripts')
@endpush
