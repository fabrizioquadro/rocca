@extends('layouts.app')

@section('title', 'Transferência de Estoque')

@section('content')
  <div class="row">
    <div class="col-md-12">
      @if (session('success'))
        <div class="alert alert-success" role="alert">
          {{ session('success') }}
        </div>
      @endif

      <div class="card mb-6">
        <div class="card-body">
          <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-4 bg-label-warning">
                <i class="ri-arrow-left-right-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">Transferência #{{ $transferencia->id }}</h4>
              <div class="text-muted">
                {{ $transferencia->clinica?->nome ?? '—' }}
                <i class="ri-arrow-right-line"></i>
                {{ $transferencia->clinicaDestino?->nome ?? '—' }}
                · {{ $transferencia->data?->format('d/m/Y') ?? 'sem data' }}
                · {{ $transferencia->quantidade_total }} unidade(s)
              </div>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('estoque.transferencias.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
            </div>
          </div>
        </div>

        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="clinica"
                  value="{{ $transferencia->clinica?->nome ?? '—' }}"
                  readonly />
                <label for="clinica">Clínica de origem</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="clinica_destino"
                  value="{{ $transferencia->clinicaDestino?->nome ?? '—' }}"
                  readonly />
                <label for="clinica_destino">Clínica de destino</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="data"
                  value="{{ $transferencia->data?->format('d/m/Y') ?? '—' }}"
                  readonly />
                <label for="data">Data</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="usuario"
                  value="{{ $transferencia->user?->nome ?? '—' }}"
                  readonly />
                <label for="usuario">Lançado por</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="quantidade_total"
                  value="{{ $transferencia->quantidade_total }}"
                  readonly />
                <label for="quantidade_total">Total de unidades</label>
              </div>
            </div>

            @if ($transferencia->observacao)
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input
                    type="text"
                    class="form-control"
                    id="observacao"
                    value="{{ $transferencia->observacao }}"
                    readonly />
                  <label for="observacao">Observação</label>
                </div>
              </div>
            @endif
          </div>

          <h6 class="fw-semibold mt-6 mb-3">Medicamentos transferidos</h6>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>Medicamento</th>
                  <th>Código de barras</th>
                  <th>Lote</th>
                  <th>Vencimento</th>
                  <th class="text-end" style="width: 120px;">Quantidade</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($transferencia->itens as $item)
                  <tr>
                    <td>{{ $item->medicamento?->nome ?? '—' }}</td>
                    <td>{{ $item->entradaItem?->codigo_barras ?? '—' }}</td>
                    <td>{{ $item->entradaItem?->lote ?? '—' }}</td>
                    <td>{{ $item->entradaItem?->vencimento_formatado ?? '—' }}</td>
                    <td class="text-end">{{ $item->quantidade }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nenhum medicamento nesta transferência.</td>
                  </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total de unidades</th>
                  <th class="text-end">{{ $transferencia->quantidade_total }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
