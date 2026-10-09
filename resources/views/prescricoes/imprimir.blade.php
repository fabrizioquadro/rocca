@extends('layouts.app')

@section('title', 'Imprimir cadastro — Prescrição #'.$prescricao->id)

@section('content')
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 no-print">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <h4 class="fw-semibold mb-0">Imprimir cadastro</h4>
        <span class="text-muted">Prescrição #{{ $prescricao->id }} · {{ $prescricao->paciente?->nome ?? '—' }}</span>
      </div>

      <div class="d-flex flex-wrap gap-2">
        {{-- Corrigir médico, clínica, tipo de atendimento, agendamento e observações --}}
        <a href="{{ route('prescricoes.edit', $prescricao) }}" class="btn btn-primary">
          <i class="ri-file-edit-line me-1"></i>Editar dados
        </a>

        <a href="{{ route('prescricoes.imprimir.pdf', $prescricao) }}" class="btn btn-outline-danger">
          <i class="ri-file-pdf-2-line me-1"></i>Gerar PDF
        </a>

        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
          <i class="ri-printer-line me-1"></i>Imprimir
        </button>

        <a href="{{ route('prescricoes.show', $prescricao) }}" class="btn btn-outline-secondary">
          <i class="ri-arrow-left-line me-1"></i>Voltar
        </a>
      </div>
    </div>

    {{-- Nova anotação direto da tela de impressão (a mesma da aba Observações) --}}
    <div class="card-body no-print">
      <form method="POST" action="{{ route('prescricoes.observacoes.store', $prescricao) }}">
        @csrf

        <label class="form-label" for="nova_anotacao">Inserir anotação</label>
        <div class="d-flex gap-2">
          <textarea
            id="nova_anotacao"
            name="observacao"
            class="form-control @error('observacao') is-invalid @enderror"
            rows="2"
            maxlength="2000"
            placeholder="Escreva a anotação sobre esta prescrição">{{ old('observacao') }}</textarea>

          <button type="submit" class="btn btn-primary align-self-end">
            <i class="ri-add-line me-1"></i>Anotar
          </button>
        </div>
        @error('observacao')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </form>
    </div>

    @if (session('success'))
      <div class="card-body pt-0 no-print">
        <div class="alert alert-success mb-0" role="alert">{{ session('success') }}</div>
      </div>
    @endif
  </div>

  {{-- Documento --}}
  <div class="card mb-4" id="documento-impressao">
    <div class="card-body">
      @include('prescricoes._impressao')
    </div>
  </div>
@endsection

@push('styles')
  <style>
    /* Visual do documento: tabelas de borda fina, como no sistema antigo */
    #documento-impressao {
      font-size: 12px;
    }

    .imp-tabela {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8px;
    }

    .imp-tabela th,
    .imp-tabela td {
      border: 1px solid #adb5bd;
      padding: 4px 6px;
      vertical-align: top;
    }

    .imp-tabela thead th,
    .imp-titulo-bloco td {
      background-color: #eef0f4;
      font-weight: 600;
    }

    .imp-rotulo {
      background-color: #f6f7f9;
      font-weight: 600;
      width: 13%;
      white-space: nowrap;
    }

    .imp-centro {
      text-align: center;
    }

    .imp-direita {
      text-align: right;
    }

    .imp-situacao {
      text-align: right;
      font-weight: 600;
      white-space: nowrap;
    }

    .imp-obs,
    .imp-vazio {
      color: #6c757d;
      font-size: 11px;
    }

    .imp-espaco {
      display: inline-block;
      width: 14px;
    }

    .imp-bloco {
      margin-top: 14px;
    }

    .imp-rodape {
      margin: 14px 0 0;
      color: #6c757d;
      font-size: 10px;
    }

    /* Impressão: só o documento, sem menu, botões e cards do sistema */
    @media print {
      .layout-menu,
      .layout-overlay,
      .layout-navbar,
      .content-footer,
      .no-print {
        display: none !important;
      }

      .card,
      .card-body {
        border: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      #documento-impressao {
        font-size: 10pt;
      }

      .imp-bloco {
        page-break-inside: avoid;
      }

      @page {
        size: A4 portrait;
        margin: 12mm;
      }
    }
  </style>
@endpush
