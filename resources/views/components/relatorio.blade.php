@props([
    'titulo',
    'descricao' => null,
    'tabela' => 'Resultado',
    'legenda' => null,
])

@php
    // Nome da rota atual (ex.: relatorios.aplicacoes) — dá os links de exportação
    // e evita mostrar os botões quando o próprio HTML é usado para gerar o arquivo.
    $rotaAtual = request()->route()?->getName();
    $exportavel = is_string($rotaAtual)
        && str_starts_with($rotaAtual, 'relatorios.')
        && ! str_ends_with($rotaAtual, '.pdf')
        && ! str_ends_with($rotaAtual, '.xlsx')
        && $rotaAtual !== 'relatorios.index';
@endphp

<style>
  /* Impressão do relatório: só o conteúdo, sem menu/botões/filtros */
  @media print {
    .layout-menu,
    .layout-overlay,
    .layout-navbar,
    .content-footer,
    .no-print,
    .btn {
      display: none !important;
    }

    .card {
      border: 0 !important;
      box-shadow: none !important;
      margin-bottom: 0 !important;
    }

    .card-body {
      padding: 0 !important;
    }
  }
</style>

<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h4 class="fw-semibold mb-0">{{ $titulo }}</h4>

      @if ($descricao)
        <small class="text-muted">{{ $descricao }}</small>
      @endif
    </div>

    <div class="d-flex flex-wrap gap-2 no-print">
      @if ($exportavel)
        <a
          href="{{ route($rotaAtual.'.pdf', request()->query()) }}"
          class="btn btn-outline-danger"
          title="Baixar este relatório em PDF">
          <i class="ri-file-pdf-2-line me-1"></i>PDF
        </a>

        <a
          href="{{ route($rotaAtual.'.xlsx', request()->query()) }}"
          class="btn btn-outline-success"
          title="Baixar este relatório em Excel (XLSX)">
          <i class="ri-file-excel-2-line me-1"></i>Excel
        </a>
      @endif

      <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="ri-printer-line me-1"></i>Imprimir
      </button>

      <a href="{{ route('relatorios.index') }}" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Relatórios
      </a>
    </div>
  </div>

  @isset($filtros)
    <div class="card-body pt-0 no-print">
      @if ($errors->any())
        <div class="alert alert-danger" role="alert">
          @foreach ($errors->all() as $error)
            {{ $error }}<br />
          @endforeach
        </div>
      @endif

      {{ $filtros }}
    </div>
  @endisset

  @isset($resumo)
    <div class="card-body pt-0 relatorio-resumo">
      {{ $resumo }}
    </div>
  @endisset
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h6 class="fw-semibold mb-0">{{ $tabela }}</h6>

    @isset($legenda)
      <small class="text-muted relatorio-legenda">{{ $legenda }}</small>
    @endisset
  </div>

  <div class="card-body relatorio-tabela">{{ $slot }}</div>
</div>
