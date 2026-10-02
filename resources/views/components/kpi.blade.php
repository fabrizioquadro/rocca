@props([
  'rotulo',
  'valor',
  'icone' => 'ri-line-chart-line',
  'cor' => 'primary',
  'detalhe' => null,
  'variacao' => null,
])

{{-- Cartão de indicador do dashboard. A variação é o % em relação ao período anterior. --}}
<div {{ $attributes->merge(['class' => 'col-sm-6 col-xl-3']) }}>
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-start justify-content-between gap-3">
        <div class="flex-grow-1">
          <span class="text-muted d-block mb-1">{{ $rotulo }}</span>
          <h4 class="fw-semibold mb-1">{{ $valor }}</h4>

          @if ($variacao !== null)
            <span class="badge {{ $variacao >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
              <i class="{{ $variacao >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>
              {{ number_format(abs($variacao), 1, ',', '.') }}%
            </span>
            <small class="text-muted">vs período anterior</small>
          @endif

          @if ($detalhe)
            <small class="text-muted d-block {{ $variacao !== null ? 'mt-1' : '' }}">{{ $detalhe }}</small>
          @endif
        </div>

        <span
          class="rounded-circle bg-label-{{ $cor }} d-flex align-items-center justify-content-center flex-shrink-0"
          style="width: 42px; height: 42px;">
          <i class="{{ $icone }} ri-20px"></i>
        </span>
      </div>
    </div>
  </div>
</div>
