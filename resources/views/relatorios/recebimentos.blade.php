@extends('layouts.app')

@section('title', 'Relatório — Recebimentos')

@section('content')
  <x-relatorio
    titulo="Recebimentos"
    descricao="Pagamentos registrados no período, por forma de pagamento e usuário."
    tabela="Pagamentos ({{ $pagamentos->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.recebimentos'),
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Pagamentos</small>
          <span class="fw-semibold">{{ $pagamentos->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Total recebido</small>
          <span class="fw-semibold text-success">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</span>
        </div>

        @foreach ($porForma as $forma => $valor)
          <div class="col-md-3">
            <small class="text-muted d-block">{{ $forma }}</small>
            <span class="fw-semibold">R$ {{ number_format($valor, 2, ',', '.') }}</span>
          </div>
        @endforeach
      </div>
    </x-slot:resumo>

    @if ($pagamentos->isEmpty())
      <p class="text-muted mb-0">Nenhum recebimento no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 115px;">Data</th>
              <th style="min-width: 180px;">Paciente</th>
              <th style="width: 90px;">Prescrição</th>
              <th style="width: 150px;">Clínica</th>
              <th style="width: 150px;">Forma de pagamento</th>
              <th style="width: 120px;" class="text-end">Valor</th>
              <th style="min-width: 150px;">Usuário</th>
              <th style="min-width: 160px;">Observação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($pagamentos as $pagamento)
              <tr>
                <td class="fw-semibold">{{ $pagamento->data_formatada ?? '—' }}</td>
                <td>{{ $pagamento->financeiro?->prescricao?->paciente?->nome ?? '—' }}</td>
                <td>#{{ $pagamento->financeiro?->prescricao_id ?? '—' }}</td>
                <td>{{ $pagamento->financeiro?->clinica?->nome ?? '—' }}</td>
                <td>{{ $pagamento->forma_descricao }}</td>
                <td class="text-end fw-semibold">{{ $pagamento->valor_formatado }}</td>
                <td>{{ $pagamento->user?->nome ?? '—' }}</td>
                <td class="small text-body-secondary">{{ $pagamento->observacao ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-end">Total recebido</th>
              <th class="text-end">R$ {{ number_format($totalRecebido, 2, ',', '.') }}</th>
              <th colspan="2"></th>
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
