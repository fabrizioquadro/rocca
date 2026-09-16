@extends('layouts.app')

@section('title', 'Alterar Senha')

@section('content')
  <div class="row">
    <div class="col-md-8 col-lg-6">
      <div class="card mb-6">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h4 class="fw-semibold mb-0">Alterar senha</h4>

          <a href="{{ route('perfil.edit') }}" class="btn btn-outline-secondary">
            <i class="ri-arrow-left-line me-1"></i>Voltar
          </a>
        </div>

        <div class="card-body">
          @if (session('success'))
            <div class="alert alert-success" role="alert">
              {{ session('success') }}
            </div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger" role="alert">
              <strong>Corrija os erros abaixo:</strong><br />
              @foreach ($errors->all() as $error)
                {{ $error }}<br />
              @endforeach
            </div>
          @endif

          <form method="POST" action="{{ route('perfil.senha.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-5">
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <input
                    type="password"
                    id="senha_atual"
                    name="senha_atual"
                    class="form-control @error('senha_atual') is-invalid @enderror"
                    placeholder="Senha atual"
                    required
                    autocomplete="current-password" />
                  <label for="senha_atual">Senha atual *</label>
                  @error('senha_atual')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="Nova senha"
                    required
                    autocomplete="new-password" />
                  <label for="password">Nova senha *</label>
                  @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
                <small class="text-muted">Mínimo de 6 caracteres.</small>
              </div>

              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    class="form-control"
                    placeholder="Confirme a nova senha"
                    required
                    autocomplete="new-password" />
                  <label for="password_confirmation">Confirmar nova senha *</label>
                </div>
              </div>

              <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('perfil.edit') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                  <i class="ri-save-3-line me-1"></i>Salvar nova senha
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
