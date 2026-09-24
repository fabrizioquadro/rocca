@extends('layouts.app')

@section('title', 'Relatório — Prescrições')

@section('content')
  <x-relatorio
    titulo="Prescrições"
    descricao="Prescrições do período com valores, desconto, adicional e quanto já foi recebido."
    tabela="Prescrições ({{ $prescricoes->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.prescricoes'),
        'rotuloPeriodo' => 'Cadastro de',
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-2">
          <small class="text-muted d-block">Valor bruto</small>
          <span class="fw-semibold">R$ {{ number_format($totais['bruto'], 2, ',', '.') }}</span>
        </div>
        <div class="col-md-2">
          <small class="text-muted d-block">Descontos</small>
          <span class="fw-semibold text-danger">- R$ {{ number_format($totais['desconto'], 2, ',', '.') }}</span>
        </div>
        <div class="col-md-2">
          <small class="text-muted d-block">Adicionais</small>
          <span class="fw-semibold text-success">+ R$ {{ number_format($totais['adicional'], 2, ',', '.') }}</span>
        </div>
        <div class="col-md-2">
          <small class="text-muted d-block">Total</small>
          <span class="fw-semibold">R$ {{ number_format($totais['total'], 2, ',', '.') }}</span>
        </div>
        <div class="col-md-2">
          <small class="text-muted d-block">Recebido</small>
          <span class="fw-semibold">R$ {{ number_format($totais['recebido'], 2, ',', '.') }}</span>
        </div>
        <div class="col-md-2">
          <small class="text-muted d-block">Em aberto</small>
          <span class="fw-semibold text-danger">R$ {{ number_format($totais['aberto'], 2, ',', '.') }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($prescricoes->isEmpty())
      <p class="text-muted mb-0">Nenhuma prescrição no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 70px;">Nº</th>
              <th style="width: 110px;">Cadastro</th>
              <th style="min-width: 180px;">Paciente</th>
              <th style="width: 140px;">Clínica</th>
              <th style="width: 150px;">Situação</th>
              <th style="width: 120px;" class="text-end">Bruto</th>
              <th style="width: 110px;" class="text-end">Desconto</th>
              <th style="width: 110px;" class="text-end">Adicional</th>
              <th style="width: 110px;" class="text-end">Total</th>
              <th style="width: 110px;" class="text-end">Recebido</th>
              <th style="width: 110px;" class="text-end">Em aberto</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($prescricoes as $prescricao)
              <tr>
                <td class="fw-semibold">
                  <a href="{{ route('prescricoes.show', $prescricao) }}">#{{ $prescricao->id }}</a>
                </td>
                <td>{{ $prescricao->created_at?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $prescricao->paciente?->nome ?? '—' }}</td>
                <td>{{ $prescricao->clinica?->nome ?? '—' }}</td>
                <td>
                  <span class="badge {{ $prescricao->situacao_cor }}">{{ $prescricao->situacao }}</span>
                </td>
                <td class="text-end">{{ $prescricao->financeiro?->valor_bruto_formatado ?? '—' }}</td>
                <td class="text-end">{{ $prescricao->financeiro?->valor_desconto_formatado ?? '—' }}</td>
                <td class="text-end">{{ $prescricao->financeiro?->valor_adicional_formatado ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ $prescricao->financeiro?->valor_total_formatado ?? '—' }}</td>
                <td class="text-end">{{ $prescricao->financeiro?->valor_recebido_formatado ?? '—' }}</td>
                <td class="text-end">{{ $prescricao->financeiro?->valor_aberto_formatado ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-end">Total</th>
              <th class="text-end">R$ {{ number_format($totais['bruto'], 2, ',', '.') }}</th>
              <th class="text-end">R$ {{ number_format($totais['desconto'], 2, ',', '.') }}</th>
              <th class="text-end">R$ {{ number_format($totais['adicional'], 2, ',', '.') }}</th>
              <th class="text-end">R$ {{ number_format($totais['total'], 2, ',', '.') }}</th>
              <th class="text-end">R$ {{ number_format($totais['recebido'], 2, ',', '.') }}</th>
              <th class="text-end">R$ {{ number_format($totais['aberto'], 2, ',', '.') }}</th>
            </tr>
          </tfoot>
        </table>
      </div>
    @endif
  </x-relatorio>
@endsection

@push('scripts')
  @include('partials.crud-scripts')
@endpush
