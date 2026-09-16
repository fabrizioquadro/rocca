@extends('layouts.app')

@section('title', 'Novo Combo')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Novo combo</h4>

      <a href="{{ route('combos.index') }}" class="btn btn-outline-secondary">
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

      @include('combos._form', [
        'action' => route('combos.store'),
        'method' => 'POST',
        'combo' => null,
        'medicamentos' => $medicamentos,
        'itensIniciais' => $itensIniciais,
      ])
    </div>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/select2/select2.css') }}" />
@endpush

@push('scripts')
  <script src="{{ asset('template/assets/vendor/libs/select2/select2.js') }}"></script>
  @include('partials.crud-scripts')
@endpush
