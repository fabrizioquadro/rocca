@extends('layouts.app')

@section('title', 'Perfil')

@section('content')
  <div class="row">
    <div class="col-md-12">
      {{-- Alertas --}}
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

      <div class="card mb-6">
        <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <!-- Foto e ações -->
          <div class="card-body">
            <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
              <img
                id="preview-imagem"
                src="{{ $usuario->imagem ? asset($usuario->imagem) : asset('template/assets/img/avatars/avatar-unisex.svg') }}"
                alt="{{ $usuario->nome }}"
                class="d-block w-px-100 h-px-100 rounded-4" />

              <div class="button-wrapper">
                <label for="imagem" class="btn btn-outline-primary me-2 mb-2">
                  <i class="ri-upload-2-line me-1"></i>Alterar foto
                </label>
                <input
                  type="file"
                  id="imagem"
                  name="imagem"
                  class="d-none @error('imagem') is-invalid @enderror"
                  accept="image/png,image/jpeg,image/webp" />

                <p class="text-muted mb-0">PNG, JPG ou WEBP (máx. 2MB)</p>

                @error('imagem')
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="ms-auto d-flex gap-2">
                <a href="{{ route('perfil.senha') }}" class="btn btn-outline-secondary">
                  <i class="ri-key-2-line me-1"></i>Alterar senha
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="ri-save-3-line me-1"></i>Salvar alterações
                </button>
              </div>
            </div>
          </div>

          <!-- Dados do usuário -->
          <div class="card-body pt-0">
            <div class="row mt-1 g-5">
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input
                    type="text"
                    id="nome"
                    name="nome"
                    class="form-control @error('nome') is-invalid @enderror"
                    placeholder="Nome"
                    value="{{ old('nome', $usuario->nome) }}"
                    required />
                  <label for="nome">Nome *</label>
                  @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="E-mail"
                    value="{{ old('email', $usuario->email) }}"
                    required />
                  <label for="email">E-mail *</label>
                  @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input
                    type="text"
                    id="coren"
                    name="coren"
                    class="form-control @error('coren') is-invalid @enderror"
                    placeholder="COREN"
                    value="{{ old('coren', $usuario->coren) }}" />
                  <label for="coren">COREN</label>
                  @error('coren')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select
                    id="clinica_id"
                    name="clinica_id"
                    class="form-select @error('clinica_id') is-invalid @enderror">
                    <option value="">Sem clínica</option>
                    @foreach ($clinicas as $clinica)
                      <option value="{{ $clinica->id }}" @selected(old('clinica_id', $usuario->clinica_id) == $clinica->id)>
                        {{ $clinica->nome }}
                      </option>
                    @endforeach
                  </select>
                  <label for="clinica_id">Clínica</label>
                  @error('clinica_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
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
            </div>
          </div>
          <!-- /Dados do usuário -->
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    // Pré-visualização da foto ao escolher um arquivo
    document.getElementById('imagem')?.addEventListener('change', function (event) {
      const arquivo = event.target.files && event.target.files[0];

      if (arquivo) {
        document.getElementById('preview-imagem').src = URL.createObjectURL(arquivo);
      }
    });
  </script>
@endpush
