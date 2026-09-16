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

          <h6 class="fw-semibold mt-6 mb-3">Detalhe por clínica, código de barras e lote</h6>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>Clínica</th>
                  <th>Código de barras</th>
                  <th>Lote</th>
                  <th>Vencimento</th>
                  <th class="text-end" style="width: 130px;">Quantidade</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($linhas as $linha)
                  <tr>
                    <td>{{ $linha['clinica'] }}</td>
                    <td>{{ $linha['codigo_barras'] }}</td>
                    <td>{{ $linha['lote'] }}</td>
                    <td>{{ $linha['vencimento_formatado'] }}</td>
                    <td class="text-end fw-semibold">{{ $linha['quantidade'] }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nenhuma unidade deste medicamento em estoque.</td>
                  </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total</th>
                  <th class="text-end">{{ $total }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <!-- /Resumo -->
      </div>
    </div>
  </div>
@endsection
