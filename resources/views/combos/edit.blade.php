@extends('layouts.app')

@section('title', 'Editar Combo')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Editar combo</h4>

      <a href="{{ route('combos.show', $combo) }}" class="btn btn-outline-secondary">
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
        'action' => route('combos.update', $combo),
        'method' => 'PUT',
        'combo' => $combo,
        'medicamentos' => $medicamentos,
        'itensIniciais' => $itensIniciais,
      ])
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
      <small class="text-muted">A exclusão é lógica: o combo sai da listagem, mas permanece no banco.</small>

      <form method="POST" action="{{ route('combos.destroy', $combo) }}" data-confirmar="Deseja realmente excluir este combo?">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger">
          <i class="ri-delete-bin-7-line me-1"></i>Excluir combo
        </button>
      </form>
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
