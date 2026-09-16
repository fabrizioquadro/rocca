@extends('layouts.app')

@section('title', 'Novo Fornecedor')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <h4 class="fw-semibold mb-0">Novo fornecedor</h4>

      <a href="{{ route('fornecedores.index') }}" class="btn btn-outline-secondary">
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

      <form method="POST" action="{{ route('fornecedores.store') }}">
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
            <label class="form-label" for="cnpj">CNPJ</label>
            <input
              type="text"
              id="cnpj"
              name="cnpj"
              class="form-control @error('cnpj') is-invalid @enderror"
              value="{{ old('cnpj') }}"
              placeholder="00.000.000/0000-00"
              inputmode="numeric" />
            @error('cnpj')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="email">E-mail</label>
            <input
              type="email"
              id="email"
              name="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email') }}" />
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="status">Status *</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
              @foreach (\App\Enums\StatusAtivoInativo::cases() as $status)
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
            <label class="form-label" for="telefone">Telefone</label>
            <input
              type="text"
              id="telefone"
              name="telefone"
              class="form-control @error('telefone') is-invalid @enderror"
              value="{{ old('telefone') }}"
              placeholder="(00) 0000-0000"
              inputmode="numeric" />
            @error('telefone')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label" for="celular">Celular</label>
            <input
              type="text"
              id="celular"
              name="celular"
              class="form-control @error('celular') is-invalid @enderror"
              value="{{ old('celular') }}"
              placeholder="(00) 00000-0000"
              inputmode="numeric" />
            @error('celular')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('fornecedores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
              <i class="ri-save-3-line me-1"></i>Salvar fornecedor
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
