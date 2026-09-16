@extends('layouts.app')

@section('title', 'Medicamento')

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
                <i class="ri-capsule-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">{{ $medicamento->nome }}</h4>
              <div class="text-muted mb-2">
                {{ $medicamento->tipo?->label() ?? '—' }}
                @if ($medicamento->fabricante)
                  · {{ $medicamento->fabricante }}
                @endif
                @if ($medicamento->grupo)
                  · {{ $medicamento->grupo->nome }}
                @endif
              </div>

              <span class="badge bg-label-{{ $medicamento->status?->value === 'ativo' ? 'success' : 'warning' }}">
                {{ $medicamento->status?->label() }}
              </span>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a href="{{ route('medicamentos.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
              <a href="{{ route('medicamentos.edit', $medicamento) }}" class="btn btn-primary">
                <i class="ri-pencil-line me-1"></i>Editar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados do medicamento -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="nome" value="{{ $medicamento->nome }}" readonly />
                <label for="nome">Nome</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="fabricante"
                  value="{{ $medicamento->fabricante ?? '—' }}"
                  readonly />
                <label for="fabricante">Fabricante</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="tipo"
                  value="{{ $medicamento->tipo?->label() ?? '—' }}"
                  readonly />
                <label for="tipo">Tipo</label>
              </div>
            </div>

            @if ($medicamento->tamanho_vasilhame !== null)
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input
                    type="text"
                    class="form-control"
                    id="tamanho_vasilhame"
                    value="{{ $medicamento->tamanho_vasilhame_formatado }}"
                    readonly />
                  <label for="tamanho_vasilhame">Tamanho do vasilhame</label>
                </div>
              </div>
            @endif

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="grupo"
                  value="{{ $medicamento->grupo?->nome ?? '—' }}"
                  readonly />
                <label for="grupo">Grupo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="status"
                  value="{{ $medicamento->status?->label() ?? '—' }}"
                  readonly />
                <label for="status">Status</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="ultimo_valor_pago"
                  value="{{ $medicamento->ultimo_valor_pago_formatado ?? '—' }}"
                  readonly />
                <label for="ultimo_valor_pago">Último valor pago</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="valor_venda"
                  value="{{ $medicamento->valor_venda_formatado ?? '—' }}"
                  readonly />
                <label for="valor_venda">Valor de venda</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="estoque_minimo"
                  value="{{ $medicamento->estoque_minimo ?? '—' }}"
                  readonly />
                <label for="estoque_minimo">Estoque mínimo</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="estoque_medio"
                  value="{{ $medicamento->estoque_medio ?? '—' }}"
                  readonly />
                <label for="estoque_medio">Estoque médio</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="gera_aplicacao"
                  value="{{ $medicamento->gera_aplicacao ? 'Sim' : 'Não' }}"
                  readonly />
                <label for="gera_aplicacao">Gera aplicação</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="feegow_aplicacao_id"
                  value="{{ $medicamento->feegow_aplicacao_id ?? '—' }}"
                  readonly />
                <label for="feegow_aplicacao_id">Feegow — ID de aplicação</label>
              </div>
            </div>
          </div>
        </div>
        <!-- /Dados do medicamento -->
      </div>
    </div>
  </div>
@endsection
