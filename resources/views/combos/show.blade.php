@extends('layouts.app')

@section('title', 'Combo')

@section('content')
  <div class="row">
    <div class="col-md-12">
      @if (session('success'))
        <div class="alert alert-success" role="alert">
          {{ session('success') }}
        </div>
      @endif

      <div class="card mb-6">
        <!-- Cabeçalho -->
        <div class="card-body">
          <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-4 bg-label-primary">
                <i class="ri-gift-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $combo->nome }}</h4>
              <div class="text-muted mb-2">
                {{ $combo->itens->count() }} medicamento(s) · Total {{ $combo->valor_total_formatado }}
              </div>

              <span class="badge bg-label-{{ $combo->status?->value === 'ativo' ? 'success' : 'warning' }}">
                {{ $combo->status?->label() }}
              </span>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('combos.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
              <a href="{{ route('combos.edit', $combo) }}" class="btn btn-primary">
                <i class="ri-pencil-line me-1"></i>Editar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do combo -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $combo->nome }}" readonly />
                <label for="nome">Nome do combo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="status" value="{{ $combo->status?->label() ?? '—' }}" readonly />
                <label for="status">Status</label>
              </div>
            </div>
          </div>

          <h6 class="fw-semibold mt-6 mb-3">Medicamentos do combo</h6>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>Medicamento</th>
                  <th style="width: 160px;">Quantidade</th>
                  <th style="width: 180px;">Valor</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($combo->itens as $item)
                  <tr>
                    <td>{{ $item->medicamento?->nome ?? '—' }}</td>
                    <td>{{ $item->quantidade_formatada }}</td>
                    <td>{{ $item->valor_formatado }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Nenhum medicamento neste combo.</td>
                  </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="2" class="text-end">Total do combo</th>
                  <th>{{ $combo->valor_total_formatado }}</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <!-- /Dados do combo -->
      </div>
    </div>
  </div>
@endsection
