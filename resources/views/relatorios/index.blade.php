@extends('layouts.app')

@section('title', 'Relatórios')

@section('content')
  <div class="card">
    <div class="card-header">
      <h4 class="fw-semibold mb-0">Relatórios</h4>
      <small class="text-muted d-block">
        Estoque e vasilhames abertos, aplicações da enfermagem, pendências e financeiro.
      </small>
    </div>

    <div class="card-body">
      <div class="row g-4">
        @foreach ($relatorios as $relatorio)
          <div class="col-md-6 col-xl-4">
            <a href="{{ route($relatorio['rota']) }}" class="card h-100 text-decoration-none">
              <div class="card-body d-flex gap-3 align-items-start">
                <span
                  class="avatar-initial rounded bg-label-primary d-flex align-items-center justify-content-center"
                  style="width: 42px; height: 42px; flex: 0 0 42px;">
                  <i class="{{ $relatorio['icone'] }} ri-20px"></i>
                </span>

                <div>
                  <h6 class="fw-semibold mb-1">{{ $relatorio['titulo'] }}</h6>
                  <small class="text-muted">{{ $relatorio['descricao'] }}</small>
                </div>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
