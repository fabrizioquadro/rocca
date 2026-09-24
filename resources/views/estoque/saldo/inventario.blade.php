@extends('layouts.app')

@section('title', 'Inventário do código '.($entradaItem->codigo_barras ?? '—'))

@section('content')
  @php
    $medicamento = $entradaItem->medicamento;

    $mgAbertos = (float) $vasilhames->filter(fn ($vasilhame) => $vasilhame->esta_em_uso)->sum('mg_restantes');
    $mgAplicados = (float) $vasilhames->sum(fn ($vasilhame) => $vasilhame->aplicacoes->sum('quantidade'));
    $mgBaixados = (float) $vasilhames->sum(fn ($vasilhame) => $vasilhame->baixas->sum('mg_baixa'));
  @endphp

  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Inventário do código de barras</h4>

      <a href="{{ route('estoque.saldo.show', $medicamento) }}" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Voltar
      </a>
    </div>

    <div class="card-body">
      @if (session('error'))
        <div class="alert alert-danger" role="alert">
          {{ session('error') }}
        </div>
      @endif

      <div class="row g-3">
        <div class="col-md-4">
          <small class="text-muted d-block">Medicamento</small>
          <span class="fw-semibold">{{ $medicamento?->nome ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Código de barras</small>
          <span class="font-monospace">{{ $entradaItem->codigo_barras ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Lote</small>
          <span>{{ $entradaItem->lote ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Vencimento</small>
          <span>{{ $entradaItem->vencimento_formatado ?? '—' }}</span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Clínica</small>
          <span>{{ $clinica?->nome ?? '—' }}</span>
        </div>

        <div class="col-md-4">
          <small class="text-muted d-block">Entrada</small>
          <span>
            {{ $entradaItem->entrada?->numero_nota ? 'Nota '.$entradaItem->entrada->numero_nota : 'Entrada #'.$entradaItem->entrada_id }}
            @if ($entradaItem->entrada?->fornecedor)
              · {{ $entradaItem->entrada->fornecedor->nome }}
            @endif
          </span>
        </div>

        <div class="col-md-2">
          <small class="text-muted d-block">Saldo fechado</small>
          <span class="fw-semibold {{ $saldo > 0 ? '' : 'text-danger' }}">{{ $saldo }}</span>
          <small class="text-muted">unidade(s)</small>
        </div>

        @if ($medicamento?->eh_miligrama)
          <div class="col-md-3">
            <small class="text-muted d-block">Mg em vasilhames abertos</small>
            <span class="fw-semibold">{{ \App\Support\Numero::formatar($mgAbertos) }} mg</span>
          </div>

          <div class="col-md-3">
            <small class="text-muted d-block">Mg aplicados / baixados</small>
            <span>
              {{ \App\Support\Numero::formatar($mgAplicados) }} mg
              / {{ \App\Support\Numero::formatar($mgBaixados) }} mg
            </span>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h6 class="fw-semibold mb-0">Movimentações do estoque (unidades)</h6>
    </div>

    <div class="card-body">
      @if ($movimentacoes->isEmpty())
        <p class="text-muted mb-0">Nenhuma movimentação de estoque deste código nesta clínica.</p>
      @else
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 150px;">Data</th>
                <th style="width: 170px;">Tipo</th>
                <th style="width: 120px;" class="text-end">Quantidade</th>
                <th style="width: 110px;" class="text-end">Saldo</th>
                <th style="min-width: 170px;">Usuário</th>
                <th>Detalhe</th>
              </tr>
            </thead>
            <tbody>
              @php $saldoCorrente = 0; @endphp

              @foreach ($movimentacoes as $movimentacao)
                @php $saldoCorrente += $movimentacao->quantidade; @endphp

                <tr>
                  <td>{{ $movimentacao->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                  <td>
                    <span class="badge bg-label-{{ $movimentacao->tipo->corBadge() }}">
                      {{ $movimentacao->tipo->label() }}
                    </span>
                  </td>
                  <td class="text-end fw-semibold {{ $movimentacao->quantidade < 0 ? 'text-danger' : 'text-success' }}">
                    {{ $movimentacao->quantidade_com_sinal }}
                  </td>
                  <td class="text-end">{{ $saldoCorrente }}</td>
                  <td>{{ $movimentacao->user?->nome ?? '—' }}</td>
                  <td class="small text-body-secondary">{{ $movimentacao->observacao ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Saldo atual</th>
                <th class="text-end">{{ $saldo }}</th>
                <th colspan="2"></th>
              </tr>
            </tfoot>
          </table>
        </div>
      @endif
    </div>
  </div>

  @if ($medicamento?->eh_miligrama)
    <div class="card">
      <div class="card-header">
        <h6 class="fw-semibold mb-0">Vasilhames abertos e o que saiu de cada um (mg)</h6>
      </div>

      <div class="card-body">
        @if ($vasilhames->isEmpty())
          <p class="text-muted mb-0">Nenhum vasilhame deste código foi aberto nesta clínica.</p>
        @else
          @foreach ($vasilhames as $vasilhame)
            @php
              $tamanho = (float) ($vasilhame->medicamento->tamanho_vasilhame ?? 0);

              // Aplicações (mg) e baixas (mg) deste frasco, em ordem de data
              $movimentos = collect()
                  ->concat($vasilhame->aplicacoes->map(fn ($aplicacao) => [
                      'data' => $aplicacao->aplicado_em,
                      'tipo' => 'Aplicação',
                      'quantidade' => (float) $aplicacao->quantidade,
                      'user' => $aplicacao->user?->nome,
                      'detalhe' => 'Prescrição #'.($aplicacao->item?->semana?->prescricao_id ?? '—')
                          .' — semana '.($aplicacao->item?->semana?->numero ?? '—')
                          .($aplicacao->item?->semana?->prescricao?->paciente?->nome
                              ? ' — '.$aplicacao->item->semana->prescricao->paciente->nome
                              : ''),
                  ]))
                  ->concat($vasilhame->baixas->map(fn ($itemBaixa) => [
                      'data' => $itemBaixa->created_at,
                      'tipo' => 'Baixa de aberto',
                      'quantidade' => (float) $itemBaixa->mg_baixa,
                      'user' => $itemBaixa->baixa?->user?->nome,
                      'detalhe' => 'Baixa #'.$itemBaixa->baixa_vasilhame_id
                          .($itemBaixa->motivo ? ' — '.$itemBaixa->motivo : ''),
                  ]))
                  ->sortBy(fn ($linha) => $linha['data']?->timestamp ?? 0)
                  ->values();

              $restante = $tamanho;
            @endphp

            <div class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                  <span class="fw-semibold">{{ $vasilhame->medicamento?->nome ?? '—' }}</span>
                  <small class="text-body-secondary d-block">{{ $vasilhame->descricao_abertura }}</small>
                </div>

                <div class="text-end">
                  <span class="badge {{ $vasilhame->esta_em_uso ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ $vasilhame->esta_em_uso ? 'Em uso' : 'Encerrado' }}
                  </span>
                  <small class="text-body-secondary d-block">
                    restam {{ \App\Support\Numero::formatar($vasilhame->mg_restantes) }} de
                    {{ \App\Support\Numero::formatar($tamanho) }} mg
                  </small>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 150px;">Data</th>
                      <th style="width: 160px;">Movimento</th>
                      <th style="width: 130px;" class="text-end">Quantidade (mg)</th>
                      <th style="width: 130px;" class="text-end">Restante (mg)</th>
                      <th style="min-width: 170px;">Quem</th>
                      <th>Detalhe</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>{{ $vasilhame->aberto_em?->format('d/m/Y H:i') ?? '—' }}</td>
                      <td><span class="badge bg-label-info">Abertura</span></td>
                      <td class="text-end text-success">+{{ \App\Support\Numero::formatar($tamanho) }}</td>
                      <td class="text-end">{{ \App\Support\Numero::formatar($tamanho) }}</td>
                      <td>{{ $vasilhame->abertoPor?->nome ?? '—' }}</td>
                      <td class="small text-body-secondary">Vasilhame aberto</td>
                    </tr>

                    @foreach ($movimentos as $movimento)
                      @php $restante = round($restante - $movimento['quantidade'], 3); @endphp

                      <tr>
                        <td>{{ $movimento['data']?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>
                          <span class="badge bg-label-{{ $movimento['tipo'] === 'Aplicação' ? 'success' : 'warning' }}">
                            {{ $movimento['tipo'] }}
                          </span>
                        </td>
                        <td class="text-end text-danger">-{{ \App\Support\Numero::formatar($movimento['quantidade']) }}</td>
                        <td class="text-end">{{ \App\Support\Numero::formatar($restante) }}</td>
                        <td>{{ $movimento['user'] ?? '—' }}</td>
                        <td class="small text-body-secondary">{{ $movimento['detalhe'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="3" class="text-end">Restante no vasilhame</th>
                      <th class="text-end">{{ \App\Support\Numero::formatar($restante) }}</th>
                      <th colspan="2"></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          @endforeach
        @endif
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
