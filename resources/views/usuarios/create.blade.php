@extends('layouts.app')

@section('title', 'Novo Usuário')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Novo usuário</h4>

      <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('usuarios.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="nome">Nome *</label>
            <input
              type="text"
              id="nome"
              name="nome"
              class="form-control @error('nome') is-invalid @enderror"
              value="{{ old('nome') }}"
              required />
            @error('nome')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="email">E-mail *</label>
            <input
              type="email"
              id="email"
              name="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email') }}"
              required />
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="password">Senha *</label>
            <input
              type="password"
              id="password"
              name="password"
              class="form-control @error('password') is-invalid @enderror"
              required />
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="tipo">Tipo *</label>
            <select id="tipo" name="tipo" class="form-select @error('tipo') is-invalid @enderror">
              @foreach (\App\Enums\TipoUsuario::cases() as $tipo)
                <option value="{{ $tipo->value }}" @selected(old('tipo', 'administrador') === $tipo->value)>
                  {{ $tipo->label() }}
                </option>
              @endforeach
            </select>
            @error('tipo')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="status">Status *</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
              @foreach (\App\Enums\StatusUsuario::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('status', 'ativo') === $status->value)>
                  {{ $status->label() }}
                </option>
              @endforeach
            </select>
            @error('status')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="clinica_id">Clínica</label>
            <select id="clinica_id" name="clinica_id" class="form-select @error('clinica_id') is-invalid @enderror">
              <option value="">Sem clínica</option>
              @foreach ($clinicas as $clinica)
                <option value="{{ $clinica->id }}" @selected(old('clinica_id') == $clinica->id)>
                  {{ $clinica->nome }}
                </option>
              @endforeach
            </select>
            @error('clinica_id')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="coren">COREN</label>
            <input
              type="text"
              id="coren"
              name="coren"
              class="form-control @error('coren') is-invalid @enderror"
              value="{{ old('coren') }}" />
            @error('coren')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="imagem">Imagem do usuário</label>
            <input
              type="file"
              id="imagem"
              name="imagem"
              class="form-control @error('imagem') is-invalid @enderror"
              accept="image/png,image/jpeg,image/webp" />
            <small class="text-muted">PNG, JPG ou WEBP — máx. 2MB</small>
            @error('imagem')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-3-line me-1"></i>Salvar usuário
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection
