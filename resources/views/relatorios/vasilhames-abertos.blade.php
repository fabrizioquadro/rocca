@extends('layouts.app')

@section('title', 'Relatório — Vasilhames abertos')

@section('content')
  <x-relatorio
    titulo="Vasilhames abertos"
    descricao="Frascos em uso e o saldo em mg que ainda resta em cada um."
    tabela="Vasilhames em uso ({{ $vasilhames->count() }})">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', [
        'rota' => route('relatorios.vasilhames-abertos'),
        'comPeriodo' => false,
        'comMedicamento' => true,
      ])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Vasilhames em uso</small>
          <span class="fw-semibold">{{ $vasilhames->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Saldo disponível</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($totalMg) }} mg</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Medicamentos diferentes</small>
          <span class="fw-semibold">{{ $vasilhames->pluck('medicamento_id')->unique()->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Clínicas</small>
          <span class="fw-semibold">{{ $vasilhames->pluck('clinica_id')->unique()->count() }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($vasilhames->isEmpty())
      <p class="text-muted mb-0">Nenhum vasilhame aberto com os filtros informados.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width: 200px;">Medicamento</th>
              <th style="width: 140px;">Código de barras</th>
              <th style="width: 110px;">Lote</th>
              <th style="width: 110px;">Vencimento</th>
              <th style="width: 150px;">Clínica</th>
              <th style="width: 130px;" class="text-end">Restam (mg)</th>
              <th style="min-width: 200px;">Aberto</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($vasilhames as $vasilhame)
              <tr>
                <td class="fw-semibold">{{ $vasilhame->medicamento?->nome ?? '—' }}</td>
                <td class="font-monospace">{{ $vasilhame->codigo_barras ?? '—' }}</td>
                <td>{{ $vasilhame->lote ?? '—' }}</td>
                <td>{{ $vasilhame->vencimento_formatado ?? '—' }}</td>
                <td>{{ $vasilhame->clinica?->nome ?? '—' }}</td>
                <td class="text-end fw-semibold">{{ \App\Support\Numero::formatar($vasilhame->mg_restantes) }}</td>
                <td class="small text-body-secondary">{{ $vasilhame->descricao_abertura }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th colspan="5" class="text-end">Total disponível</th>
              <th class="text-end">{{ \App\Support\Numero::formatar($totalMg) }}</th>
              <th></th>
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
