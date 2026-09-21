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

      <a href="{{ route('prescricoes.semanas.show', [$prescricao, $semana]) }}" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Voltar
      </a>
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
                  <td>{{ $aplicacao->quantidade_formatada }}</td>
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
                <th style="width: 190px;">Código de barras</th>
                <th style="min-width: 220px;">Lote</th>
                <th style="min-width: 180px;">Observação</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($aMarcar as $item)
                @php
                  $medicamentosPermitidos = $item->tipo === 'combo'
                      ? $item->combo?->itens->pluck('medicamento_id')->filter()->implode(',')
                      : $item->medicamento_id;
                  $pendenteAntigo = (bool) old("itens.{$item->id}.pendente", $item->status === \App\Enums\StatusSemanaItem::Pendente);
                @endphp

                <tr data-item-aplicacao data-medicamentos-permitidos="{{ $medicamentosPermitidos }}">
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

                  {{-- Quantidade não é editável: é a quantidade prescrita/cobrada --}}
                  <td>{{ (int) $item->quantidade_cobranca }}</td>

                  <td>
                    <input
                      type="text"
                      name="itens[{{ $item->id }}][codigo_barras]"
                      class="form-control form-control-sm font-monospace"
                      placeholder="Ler código de barras"
                      autocomplete="off"
                      data-codigo-barras
                      value="{{ old("itens.{$item->id}.codigo_barras") }}" />
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
@endsection

@push('scripts')
  <script>
    window.estoqueCodigoBarrasUrl = '{{ route('estoque.buscarCodigoBarras') }}';
    window.aplicacaoClinicaId = '{{ $clinicaId }}';
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const linhas = document.querySelectorAll('[data-item-aplicacao]');

      if (!linhas.length) return;

      const hoje = new Date();
      hoje.setHours(0, 0, 0, 0);

      const textoVencimento = (texto) => {
        const partes = String(texto || '').split('/');

        if (partes.length !== 3) return null;

        return new Date(Number(partes[2]), Number(partes[1]) - 1, Number(partes[0]));
      };

      // Campos que só valem quando o medicamento foi aplicado
      const alternarCampos = (linha) => {
        const pendente = linha.querySelector('[data-pendente]').checked;
        const campoCodigo = linha.querySelector('[data-codigo-barras]');

        campoCodigo.disabled = pendente;

        if (pendente) {
          mostrarInfo(linha, '<span class="badge bg-label-secondary">Não aplicado</span>');
        } else if (campoCodigo.value.trim()) {
          consultarCodigo(linha);
        } else {
          mostrarInfo(linha, '<span class="badge bg-label-secondary">Aguardando código</span>');
        }
      };

      const mostrarInfo = (linha, html) => {
        linha.querySelector('[data-info-lote]').innerHTML = html || '';
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

        alternarCampos(linha);

        campoPendente.addEventListener('change', () => alternarCampos(linha));

        campoCodigo.addEventListener('change', () => consultarCodigo(linha));

        if (campoCodigo.value.trim()) {
          consultarCodigo(linha);
        }
      });

      // Marca/desmarca todas as linhas como pendentes
      const marcarTodos = document.querySelector('[data-marcar-todos-pendentes]');

      marcarTodos?.addEventListener('change', () => {
        linhas.forEach((linha) => {
          linha.querySelector('[data-pendente]').checked = marcarTodos.checked;

          alternarCampos(linha);
        });
      });
    });
  </script>

  @include('partials.crud-scripts')
@endpush
