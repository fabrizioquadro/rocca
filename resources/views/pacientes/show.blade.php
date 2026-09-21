@extends('layouts.app')

@section('title', 'Paciente')

@section('content')
  <div class="row">
    <div class="col-md-12">
      @if (session('success'))
        <div class="alert alert-success" role="alert">
          {{ session('success') }}
        </div>
      @endif

      @if (session('error'))
        <div class="alert alert-danger" role="alert">
          {{ session('error') }}
        </div>
      @endif

      <div class="card mb-6">
        <!-- Cabeçalho -->
        <div class="card-body">
          <div class="d-flex align-items-start align-items-sm-center gap-6 flex-wrap">
            <div class="avatar avatar-xl">
              <span class="avatar-initial rounded-4 bg-label-primary">
                <i class="ri-user-heart-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $paciente->nome }}</h4>
              <div class="text-muted">
                {{ $paciente->nascimento_formatado ?? 'nascimento não informado' }}
                @if ($paciente->idade)
                  ({{ $paciente->idade }} anos)
                @endif
                · Feegow #{{ $paciente->paciente_id }}
              </div>
            </div>

            <div class="ms-auto d-flex gap-2">
              <form method="POST" action="{{ route('pacientes.atualizar', $paciente) }}" class="m-0">
                @csrf
                <button type="submit" class="btn btn-primary">
                  <i class="ri-refresh-line me-1"></i>Buscar dados completos na Feegow
                </button>
              </form>

              <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do paciente -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $paciente->nome }}" readonly />
                <label for="nome">Nome</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="nascimento"
                  value="{{ $paciente->nascimento_formatado ?? '—' }}"
                  readonly />
                <label for="nascimento">Nascimento</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="cpf" value="{{ $paciente->cpf_formatado ?? '—' }}" readonly />
                <label for="cpf">CPF</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="telefone"
                  value="{{ $paciente->telefone_formatado ?? '—' }}"
                  readonly />
                <label for="telefone">Telefone</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="celular"
                  value="{{ $paciente->celular_formatado ?? '—' }}"
                  readonly />
                <label for="celular">Celular</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="email" value="{{ $paciente->email ?? '—' }}" readonly />
                <label for="email">E-mail</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="atualizado_em"
                  value="{{ $paciente->updated_at?->format('d/m/Y H:i') ?? '—' }}"
                  readonly />
                <label for="atualizado_em">Última sincronização</label>
              </div>
            </div>
          </div>

          {{-- Observação interna do paciente (não vem da Feegow) --}}
          <form method="POST" action="{{ route('pacientes.observacao.update', $paciente) }}" class="mt-6">
            @csrf
            @method('PUT')

            <h6 class="fw-semibold mb-3">Observação</h6>

            <textarea
              id="observacao"
              name="observacao"
              class="form-control @error('observacao') is-invalid @enderror"
              rows="4"
              maxlength="2000"
              placeholder="Anotações internas sobre o paciente">{{ old('observacao', $paciente->observacao) }}</textarea>
            @error('observacao')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="ri-save-line me-1"></i>Salvar observação
              </button>
            </div>
          </form>

          <h6 class="fw-semibold mt-6 mb-3">Dados recebidos da Feegow</h6>

          <div class="accordion" id="accordion-json">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button
                  class="accordion-button collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#json-feegow"
                  aria-expanded="false"
                  aria-controls="json-feegow">
                  Ver JSON completo do paciente
                </button>
              </h2>
              <div id="json-feegow" class="accordion-collapse collapse" data-bs-parent="#accordion-json">
                <div class="accordion-body">
                  <pre class="mb-0 small" style="max-height: 400px; overflow: auto;">{{ json_encode($paciente->dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Dados do paciente -->
      </div>
    </div>
  </div>
@endsection
