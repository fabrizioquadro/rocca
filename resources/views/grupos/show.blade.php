@extends('layouts.app')

@section('title', 'Grupo')

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
                <i class="ri-group-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $grupo->nome }}</h4>
              <div class="text-muted">Grupo</div>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('grupos.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
              <a href="{{ route('grupos.edit', $grupo) }}" class="btn btn-primary">
                <i class="ri-pencil-line me-1"></i>Editar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do grupo -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $grupo->nome }}" readonly />
                <label for="nome">Nome</label>
              </div>
            </div>
          </div>
        </div>
        <!-- /Dados do grupo -->
      </div>
    </div>
  </div>
@endsection
