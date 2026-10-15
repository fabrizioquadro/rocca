@extends('layouts.app')

@section('title', 'Prescrição #'.$prescricao->id.' — '.($prescricao->paciente?->nome ?? 'paciente'))

@section('content')
  {{-- Barra de ações (não sai na impressão) --}}
  <div class="card mb-4 no-print">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Prescrição do paciente</h4>
        <span class="text-muted">#{{ $prescricao->id }} · {{ $prescricao->paciente?->nome ?? '—' }}</span>
        <span class="badge bg-label-secondary">sem valores</span>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('prescricoes.imprimir.prescricao.pdf', $prescricao) }}" class="btn btn-outline-danger">
          <i class="ri-file-pdf-2-line me-1"></i>Baixar PDF
        </a>

        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
          <i class="ri-printer-line me-1"></i>Imprimir
        </button>

        <a href="{{ route('prescricoes.imprimir', $prescricao) }}" class="btn btn-outline-secondary">
          <i class="ri-file-list-3-line me-1"></i>Imprimir cadastro
        </a>

        <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>
      </div>
    </div>

    <div class="card-body">
      <p class="text-muted small mb-0">
        Versão para enviar ao paciente: sem valores, sem o bloco financeiro e sem os eventos
        financeiros do histórico. Lotes, códigos de barras, datas de aplicação e atendimentos continuam.
      </p>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      @include('prescricoes._impressao', ['prescricao' => $prescricao, 'comValores' => false])
    </div>
  </div>
@endsection

@push('styles')
  @include('prescricoes._impressao-estilo')

  <style>
    /* Impressão: só o documento, sem menu, botões e modais */
    @media print {
      .layout-menu,
      .layout-overlay,
      .layout-navbar,
      .content-footer,
      .no-print,
      .modal {
        display: none !important;
      }

      .card,
      .card-body,
      .card-header {
        border: 0 !important;
        box-shadow: none !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }

      @page {
        size: A4 portrait;
        margin: 12mm;
      }
    }
  </style>
@endpush
