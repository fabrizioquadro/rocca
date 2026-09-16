@extends('layouts.app')

@section('title', 'Fornecedor')

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
                <i class="ri-store-2-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $fornecedor->nome }}</h4>
              <div class="text-muted mb-2">{{ $fornecedor->email ?? '—' }}</div>

              <span class="badge bg-label-{{ $fornecedor->status?->value === 'ativo' ? 'success' : 'warning' }}">
                {{ $fornecedor->status?->label() }}
              </span>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('fornecedores.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
              <a href="{{ route('fornecedores.edit', $fornecedor) }}" class="btn btn-primary">
                <i class="ri-pencil-line me-1"></i>Editar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do fornecedor -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $fornecedor->nome }}" readonly />
                <label for="nome">Nome</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="cnpj" value="{{ $fornecedor->cnpj_formatado ?? '—' }}" readonly />
                <label for="cnpj">CNPJ</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="email" value="{{ $fornecedor->email ?? '—' }}" readonly />
                <label for="email">E-mail</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="status" value="{{ $fornecedor->status?->label() ?? '—' }}" readonly />
                <label for="status">Status</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="telefone" value="{{ $fornecedor->telefone_formatado ?? '—' }}" readonly />
                <label for="telefone">Telefone</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="celular" value="{{ $fornecedor->celular_formatado ?? '—' }}" readonly />
                <label for="celular">Celular</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="created_at"
                  value="{{ $fornecedor->created_at?->format('d/m/Y H:i') ?? '—' }}"
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
                  value="{{ $fornecedor->updated_at?->format('d/m/Y H:i') ?? '—' }}"
                  readonly />
                <label for="updated_at">Última atualização</label>
              </div>
            </div>
          </div>
        </div>
        <!-- /Dados do fornecedor -->
      </div>
    </div>
  </div>
@endsection
