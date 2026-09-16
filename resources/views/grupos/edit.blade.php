@extends('layouts.app')

@section('title', 'Editar Grupo')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Editar grupo</h4>

      <a href="{{ route('grupos.show', $grupo) }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('grupos.update', $grupo) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="nome">Nome *</label>
            <input
              type="text"
              id="nome"
              name="nome"
              class="form-control @error('nome') is-invalid @enderror"
              value="{{ old('nome', $grupo->nome) }}"
              required />
            @error('nome')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('grupos.show', $grupo) }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-3-line me-1"></i>Salvar alterações
            </button>
          </div>
        </div>
      </form>
    </div>

    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
      <small class="text-muted">A exclusão é lógica: o grupo sai da listagem, mas permanece no banco.</small>

      <form method="POST" action="{{ route('grupos.destroy', $grupo) }}" data-confirmar="Deseja realmente excluir este grupo?">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger">
          <i class="ri-delete-bin-7-line me-1"></i>Excluir grupo
        </button>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
