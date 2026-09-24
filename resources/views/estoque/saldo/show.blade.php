@extends('layouts.app')

@section('title', 'Saldo do Medicamento')

@section('content')
  <div class="row">
    <div class="col-md-12">
      <div class="card mb-6">
        <!-- Cabeçalho -->
        <div class="card-body">
          <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-4 bg-label-primary">
                <i class="ri-capsule-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $medicamento->nome }}</h4>
              <div class="text-muted">
                {{ $medicamento->grupo?->nome ?? 'Sem grupo' }}
                @if ($medicamento->fabricante)
                  · {{ $medicamento->fabricante }}
                @endif
                · {{ $total }} unidade(s) em estoque
                @if ($medicamento->eh_miligrama)
                  · {{ \App\Support\Numero::formatar($mgAbertos) }} mg em vasilhames abertos
                @endif
              </div>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('estoque.saldo.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
            </div>
          </div>
        </div>

        <!-- Resumo -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="medicamento" value="{{ $medicamento->nome }}" readonly />
                <label for="medicamento">Medicamento</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="grupo" value="{{ $medicamento->grupo?->nome ?? '—' }}" readonly />
                <label for="grupo">Grupo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="estoque_minimo"
                  value="{{ $medicamento->estoque_minimo ?? '—' }}"
                  readonly />
                <label for="estoque_minimo">Estoque mínimo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="total" value="{{ $total }}" readonly />
                <label for="total">Total em estoque</label>
              </div>
            </div>
          </div>

          <h6 class="fw-semibold mt-6 mb-3">
            Detalhe por clínica, código de barras e lote
            @if ($medicamento->eh_miligrama)
              <span class="text-body-secondary fw-normal">(vasilhames fechados)</span>
            @endif
          </h6>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>Clínica</th>
                  <th>Código de barras</th>
                  <th>Lote</th>
                  <th>Vencimento</th>
                  <th class="text-end" style="width: 130px;">Quantidade</th>
                  <th class="text-center" style="width: 110px;">Inventário</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($linhas as $linha)
                  <tr>
                    <td>{{ $linha['clinica'] }}</td>
                    <td class="font-monospace">{{ $linha['codigo_barras'] }}</td>
                    <td>{{ $linha['lote'] }}</td>
                    <td>{{ $linha['vencimento_formatado'] }}</td>
                    <td class="text-end fw-semibold">{{ $linha['quantidade'] }}</td>
                    <td class="text-center">
                      {{-- Histórico do código: entradas, saídas, transferências e aplicações --}}
                      <a
                        href="{{ route('estoque.saldo.inventario', [$linha['entrada_item_id'], 'clinica_id' => $linha['clinica_id']]) }}"
                        class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                        title="Inventário do código de barras">
                        <i class="ri-file-list-3-line"></i>
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhuma unidade deste medicamento em estoque.</td>
                  </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total</th>
                  <th class="text-end">{{ $total }}</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          @if ($medicamento->eh_miligrama)
            {{-- Vasilhames abertos: cada um tem o próprio saldo em mg --}}
            <h6 class="fw-semibold mt-6 mb-3">Vasilhames abertos</h6>

            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Clínica</th>
                    <th>Código de barras</th>
                    <th>Lote</th>
                    <th>Vencimento</th>
                    <th class="text-end" style="width: 130px;">Mg restantes</th>
                    <th style="width: 120px;">Situação</th>
                    <th>Aberto</th>
                    <th class="text-center" style="width: 110px;">Inventário</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($vasilhames as $vasilhame)
                    <tr>
                      <td>{{ $vasilhame->clinica?->nome ?? '—' }}</td>
                      <td class="font-monospace">{{ $vasilhame->codigo_barras ?? '—' }}</td>
                      <td>{{ $vasilhame->lote ?? '—' }}</td>
                      <td>{{ $vasilhame->vencimento_formatado ?? '—' }}</td>
                      <td class="text-end fw-semibold">{{ \App\Support\Numero::formatar($vasilhame->mg_restantes) }}</td>
                      <td>
                        @if ($vasilhame->esta_em_uso)
                          <span class="badge bg-label-success">Em uso</span>
                        @else
                          <span class="badge bg-label-secondary">Esgotado</span>
                        @endif
                      </td>
                      <td class="small text-body-secondary">{{ $vasilhame->descricao_abertura }}</td>
                      <td class="text-center">
                        {{-- Histórico do frasco: aplicações e baixas em mg --}}
                        <a
                          href="{{ route('estoque.saldo.inventario', [$vasilhame->entrada_item_id, 'clinica_id' => $vasilhame->clinica_id]) }}"
                          class="btn btn-sm btn-icon btn-text-secondary waves-effect"
                          title="Inventário do código de barras">
                          <i class="ri-file-list-3-line"></i>
                        </a>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="8" class="text-center text-muted py-4">
                        Nenhum vasilhame aberto.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="4" class="text-end">Em uso</th>
                    <th class="text-end">{{ \App\Support\Numero::formatar($mgAbertos) }}</th>
                    <th colspan="3"></th>
                  </tr>
                </tfoot>
              </table>
            </div>
          @endif
        </div>
        <!-- /Resumo -->
      </div>
    </div>
  </div>
@endsection
