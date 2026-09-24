@extends('layouts.app')

@section('title', 'Relatório — Aplicações por medicamento')

@section('content')
  <x-relatorio
    titulo="Aplicações por medicamento"
    descricao="Quanto de cada medicamento foi aplicado, quantos pacientes e a última aplicação."
    tabela="Resumo por medicamento ({{ $linhas->count() }})"
    legenda="{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}">
    <x-slot:filtros>
      @include('relatorios.partials.filtros', ['rota' => route('relatorios.aplicacoes-por-medicamento')])
    </x-slot:filtros>

    <x-slot:resumo>
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted d-block">Medicamentos aplicados</small>
          <span class="fw-semibold">{{ $linhas->count() }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Total de aplicações</small>
          <span class="fw-semibold">{{ (int) $linhas->sum('aplicacoes') }}</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Mg aplicados</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($linhas->sum('mg')) }} mg</span>
        </div>
        <div class="col-md-3">
          <small class="text-muted d-block">Unidades aplicadas</small>
          <span class="fw-semibold">{{ \App\Support\Numero::formatar($linhas->sum('unidades')) }}</span>
        </div>
      </div>
    </x-slot:resumo>

    @if ($linhas->isEmpty())
      <p class="text-muted mb-0">Nenhuma aplicação no período.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width: 220px;">Medicamento</th>
              <th style="width: 120px;" class="text-end">Aplicações</th>
              <th style="width: 130px;" class="text-end">Mg aplicados</th>
              <th style="width: 140px;" class="text-end">Unidades</th>
              <th style="width: 120px;" class="text-end">Pacientes</th>
              <th style="width: 150px;">Última aplicação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($linhas as $linha)
              <tr>
                <td class="fw-semibold">{{ $linha['medicamento'] }}</td>
                <td class="text-end">{{ $linha['aplicacoes'] }}</td>
                <td class="text-end">{{ $linha['mg'] > 0 ? \App\Support\Numero::formatar($linha['mg']) : '—' }}</td>
                <td class="text-end">{{ $linha['unidades'] > 0 ? \App\Support\Numero::formatar($linha['unidades']) : '—' }}</td>
                <td class="text-end">{{ $linha['pacientes'] }}</td>
                <td>{{ $linha['ultima']?->format('d/m/Y H:i') ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <th class="text-end">Total</th>
              <th class="text-end">{{ (int) $linhas->sum('aplicacoes') }}</th>
              <th class="text-end">{{ \App\Support\Numero::formatar($linhas->sum('mg')) }}</th>
              <th class="text-end">{{ \App\Support\Numero::formatar($linhas->sum('unidades')) }}</th>
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
