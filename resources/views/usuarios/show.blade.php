@extends('layouts.app')

@section('title', 'Usuário')

@section('content')
  <div class="row">
    <div class="col-md-12">
      <div class="card mb-6">
        <!-- Cabeçalho do usuário -->
        <div class="card-body">
          <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
            <img
              src="{{ $usuario->imagem ? asset($usuario->imagem) : asset('template/assets/img/avatars/avatar-unisex.svg') }}"
              alt="{{ $usuario->nome }}"
              class="d-block w-px-100 h-px-100 rounded-4" />

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $usuario->nome }}</h4>
              <div class="text-muted mb-2">{{ $usuario->email }}</div>

              @php
                $badge = match ($usuario->status?->value) {
                  'ativo' => 'success',
                  'inativo' => 'warning',
                  default => 'danger',
                };
              @endphp
              <span class="badge bg-label-{{ $badge }}">{{ $usuario->status?->label() }}</span>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
              <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-primary">
                <i class="ri-pencil-line me-1"></i>Editar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do usuário -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $usuario->nome }}" readonly />
                <label for="nome">Nome</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="email" value="{{ $usuario->email }}" readonly />
                <label for="email">E-mail</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="tipo" value="{{ $usuario->tipo?->label() ?? '—' }}" readonly />
                <label for="tipo">Tipo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="status" value="{{ $usuario->status?->label() ?? '—' }}" readonly />
                <label for="status">Status</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="clinica" value="{{ $usuario->clinica?->nome ?? '—' }}" readonly />
                <label for="clinica">Clínica</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="coren" value="{{ $usuario->coren ?? '—' }}" readonly />
                <label for="coren">COREN</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="created_at"
                  value="{{ $usuario->created_at?->format('d/m/Y H:i') ?? '—' }}"
                  readonly />
                <label for="created_at">Cadastrado em</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="updated_at"
                  value="{{ $usuario->updated_at?->format('d/m/Y H:i') ?? '—' }}"
                  readonly />
                <label for="updated_at">Última atualização</label>
              </div>
            </div>
          </div>
        </div>
        <!-- /Dados do usuário -->
      </div>
    </div>
  </div>
@endsection
