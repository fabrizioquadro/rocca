@extends('layouts.app')

@section('title', 'Entrada de Estoque')

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
                <i class="ri-login-box-line ri-36px"></i>
              </span>
            </div>

            <div class="button-wrapper">
              <h4 class="mb-1">Entrada #{{ $entrada->id }}</h4>
              <div class="text-muted">
                {{ $entrada->clinica?->nome ?? 'Sem clínica' }}
                · {{ $entrada->fornecedor?->nome ?? 'Sem fornecedor' }}
                · NF {{ $entrada->numero_nota ?: 's/n' }}
                · {{ $entrada->data_entrada?->format('d/m/Y') ?? 'sem data' }}
              </div>
            </div>

            <div class="ms-auto d-flex gap-2">
              <a
                href="{{ route('estoque.entradas.etiquetas', $entrada) }}"
                class="btn btn-primary"
                target="_blank"
                title="Imprimir as etiquetas (código de barras) de todos os medicamentos desta entrada">
                <i class="ri-printer-line me-1"></i>Imprimir Etiquetas
              </a>

              <a href="{{ route('estoque.entradas.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>Voltar
              </a>
            </div>
          </div>
        </div>

        <!-- Dados da entrada -->
        <div class="card-body pt-0">
          <div class="row mt-1 g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="clinica"
                  value="{{ $entrada->clinica?->nome ?? '—' }}"
                  readonly />
                <label for="clinica">Clínica</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="fornecedor"
                  value="{{ $entrada->fornecedor?->nome ?? '—' }}"
                  readonly />
                <label for="fornecedor">Fornecedor</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="numero_nota"
                  value="{{ $entrada->numero_nota ?? '—' }}"
                  readonly />
                <label for="numero_nota">Número da nota</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="data_entrada"
                  value="{{ $entrada->data_entrada?->format('d/m/Y') ?? '—' }}"
                  readonly />
                <label for="data_entrada">Data de entrada</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="usuario"
                  value="{{ $entrada->user?->nome ?? '—' }}"
                  readonly />
                <label for="usuario">Lançado por</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="quantidade_total"
                  value="{{ $entrada->quantidade_total }}"
                  readonly />
                <label for="quantidade_total">Total de unidades</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input
                  type="text"
                  class="form-control"
                  id="valor_total"
                  value="{{ $entrada->valor_total_formatado }}"
                  readonly />
                <label for="valor_total">Valor total da entrada</label>
              </div>
            </div>

            @if ($entrada->observacao)
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <textarea class="form-control" id="observacao" style="height: 80px;" readonly>{{ $entrada->observacao }}</textarea>
                  <label for="observacao">Observação</label>
                </div>
              </div>
            @endif
          </div>

          <h6 class="fw-semibold mt-6 mb-3">Medicamentos (lote / código de barras / vencimento)</h6>

          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
              <thead class="table-light">
                <tr>
                  <th>Medicamento</th>
                  <th>Lote</th>
                  <th>Código de barras</th>
                  <th>Vencimento</th>
                  <th class="text-end" style="width: 120px;">Quantidade</th>
                  <th class="text-end" style="width: 140px;">Valor unitário</th>
                  <th class="text-end" style="width: 140px;">Valor total</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($entrada->itens as $item)
                  <tr>
                    <td>
                      {{ $item->medicamento?->nome ?? '—' }}

                      <a
                        href="{{ route('estoque.entradas.itens.etiquetas', [$entrada, $item]) }}"
                        class="text-body ms-1"
                        target="_blank"
                        title="Imprimir Etiquetas">
                        <i class="ri-printer-line"></i>
                      </a>
                    </td>
                    <td>{{ $item->lote ?? '—' }}</td>
                    <td>{{ $item->codigo_barras ?? '—' }}</td>
                    <td>{{ $item->vencimento_formatado ?? '—' }}</td>
                    <td class="text-end">{{ $item->quantidade }}</td>
                    <td class="text-end">{{ $item->valor_unitario_formatado ?? '—' }}</td>
                    <td class="text-end">{{ $item->valor_total_formatado ?? '—' }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum medicamento nesta entrada.</td>
                  </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="4" class="text-end">Total de unidades</th>
                  <th class="text-end">{{ $entrada->quantidade_total }}</th>
                  <th class="text-end">Valor total</th>
                  <th class="text-end">{{ $entrada->valor_total_formatado }}</th>
                </tr>
              </tfoot>
            </table>
          </div>

          <h6 class="fw-semibold mt-6 mb-3">Anexos (nota fiscal, recibo e etc.)</h6>

          <ul class="list-group mb-3">
            @forelse ($entrada->anexos as $anexo)
              <li class="list-group-item d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <i class="{{ $anexo->eh_imagem ? 'ri-image-line' : 'ri-file-text-line' }} ri-20px"></i>

                  <a href="{{ asset($anexo->arquivo) }}" target="_blank" rel="noopener">{{ $anexo->nome }}</a>

                  <small class="text-muted">{{ $anexo->tamanho_formatado }}</small>
                </div>

                <form
                  method="POST"
                  action="{{ route('estoque.entradas.anexos.destroy', [$entrada, $anexo]) }}"
                  data-confirmar="Remover este anexo?">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-text-danger" title="Remover">
                    <i class="ri-delete-bin-7-line"></i>
                  </button>
                </form>
              </li>
            @empty
              <li class="list-group-item text-muted">Nenhum anexo enviado.</li>
            @endforelse
          </ul>
        </div>
        <!-- /Dados da entrada -->
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
