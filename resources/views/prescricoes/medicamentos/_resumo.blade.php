{{--
  Resumo das alterações da edição da prescrição: aparece no FIM da própria
  tela de edição, depois de "Conferir alterações", e nada foi gravado ainda.
  Só o "Autorizar e aplicar" grava.
--}}
@php
  $dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');

  $rotulos = [
    \App\Services\PrescricaoEdicaoService::CRIAR => ['Nova', 'bg-label-primary'],
    \App\Services\PrescricaoEdicaoService::ALTERAR => ['Alterada', 'bg-label-warning'],
    \App\Services\PrescricaoEdicaoService::EXCLUIR => ['Excluída', 'bg-label-danger'],
    \App\Services\PrescricaoEdicaoService::MANTER => ['Sem alteração', 'bg-label-secondary'],
  ];
@endphp

<div class="card border-warning mt-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="fw-semibold mb-0">
      <i class="ri-file-list-3-line me-1"></i>Resumo das alterações
    </h5>
    <span class="badge bg-label-warning">nada foi gravado ainda</span>
  </div>

  <div class="card-body">
    @foreach ($avisos as $aviso)
      <div class="alert alert-danger d-flex gap-2" role="alert">
        <i class="ri-error-warning-line ri-20px"></i>
        <div>{{ $aviso }}</div>
      </div>
    @endforeach

    <div class="row g-3 mb-4">
      @foreach ([
        'criadas' => ['Semanas criadas', 'primary', 'ri-add-circle-line'],
        'alteradas' => ['Semanas alteradas', 'warning', 'ri-edit-line'],
        'excluidas' => ['Semanas excluídas', 'danger', 'ri-delete-bin-7-line'],
        'mantidas' => ['Semanas sem alteração', 'secondary', 'ri-checkbox-blank-circle-line'],
      ] as $chave => [$rotulo, $cor, $icone])
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <small class="text-muted d-block">{{ $rotulo }}</small>
            <span class="fw-semibold fs-5">
              <i class="ri {{ $icone }} text-{{ $cor }} me-1"></i>{{ $resumo[$chave] }}
            </span>
          </div>
        </div>
      @endforeach
    </div>

    <h6 class="fw-semibold mb-3">O que vai mudar nas semanas</h6>

    <div class="table-responsive mb-4">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 100px;">Semana</th>
            <th style="width: 140px;">Situação</th>
            <th>Detalhe</th>
            <th style="width: 170px;" class="text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($plano as $posicao => $item)
            @php
              [$rotulo, $cor] = $rotulos[$item['acao']];
              $numero = $item['semana'] ? $item['semana']->numero : $posicao + 1;
            @endphp

            <tr>
              <td class="fw-semibold">{{ $numero }}</td>
              <td>
                <span class="badge {{ $cor }}">{{ $rotulo }}</span>

                @if (! empty($item['motivo_bloqueio']))
                  <span class="badge bg-label-secondary d-block mt-1" title="{{ $item['motivo_bloqueio'] }}">
                    <i class="ri-lock-line"></i> travada
                  </span>
                @endif
              </td>
              <td>
                <div><strong>Itens:</strong> {{ $item['itens_descricao'] }}</div>

                @if ($item['dados']['data_prevista'] ?? null)
                  <div class="text-muted small">
                    Data prevista: {{ \Illuminate\Support\Carbon::parse($item['dados']['data_prevista'])->format('d/m/Y') }}
                  </div>
                @endif

                @if ($item['dados']['sem_aplicacao'] ?? false)
                  <div class="text-muted small">Semana sem aplicação</div>
                @endif

                @if ($item['dados']['observacao'] ?? null)
                  <div class="text-muted small">Observação: {{ $item['dados']['observacao'] }}</div>
                @endif

                @if ($item['alteracoes'])
                  <ul class="list-unstyled mb-0 mt-2 small">
                    @foreach ($item['alteracoes'] as $alteracao)
                      <li>
                        <strong>{{ $alteracao['campo'] }}:</strong>
                        <span class="text-danger">{{ $alteracao['de'] }}</span>
                        <i class="ri-arrow-right-line mx-1"></i>
                        <span class="text-success">{{ $alteracao['para'] }}</span>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </td>
              <td class="text-end">
                @if ($item['acao'] === \App\Services\PrescricaoEdicaoService::EXCLUIR)
                  <span class="text-danger">- {{ $dinheiro($item['valor_total_anterior']) }}</span>
                @elseif ($item['acao'] === \App\Services\PrescricaoEdicaoService::CRIAR)
                  <span class="text-success">+ {{ $dinheiro($item['valor_total']) }}</span>
                @elseif ($item['valor_total'] !== $item['valor_total_anterior'])
                  <span class="text-muted d-block small">{{ $dinheiro($item['valor_total_anterior']) }}</span>
                  <span class="fw-semibold">{{ $dinheiro($item['valor_total']) }}</span>
                @else
                  {{ $dinheiro($item['valor_total']) }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <h6 class="fw-semibold mb-3">Efeito no financeiro</h6>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Financeiro</th>
                <th class="text-end">Agora</th>
                <th class="text-end">Depois</th>
              </tr>
            </thead>
            <tbody>
              @foreach ([
                'bruto' => 'Valor bruto (semanas)',
                'total' => 'Total do financeiro',
                'recebido' => 'Já recebido',
                'aberto' => 'Em aberto',
              ] as $chave => $rotulo)
                <tr>
                  <td>{{ $rotulo }}</td>
                  <td class="text-end">{{ $dinheiro($financeiro['antes'][$chave]) }}</td>
                  <td class="text-end fw-semibold">{{ $dinheiro($financeiro['depois'][$chave]) }}</td>
                </tr>
              @endforeach

              <tr>
                <td>Parcelas</td>
                <td class="text-end">{{ count($financeiro['antes']['parcelas']) }}</td>
                <td class="text-end fw-semibold">{{ count($financeiro['depois']['parcelas']) }}</td>
              </tr>

              @if ($financeiro['depois']['nao_alocado'] > 0)
                <tr class="table-warning">
                  <td>Crédito a favor do paciente</td>
                  <td class="text-end">{{ $dinheiro($financeiro['antes']['nao_alocado']) }}</td>
                  <td class="text-end fw-semibold">{{ $dinheiro($financeiro['depois']['nao_alocado']) }}</td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>

        <p class="text-muted small mt-2 mb-0">
          O desconto
          @if ($financeiro['antes']['desconto_descricao'])
            ({{ $financeiro['antes']['desconto_descricao'] }})
          @endif
          e o adicional de {{ $dinheiro($financeiro['antes']['adicional']) }} continuam como estão: eles são
          do cadastro e não mudam com a edição das semanas.
          O valor recebido é redistribuído automaticamente nas parcelas novas.
        </p>
      </div>

      <div class="col-lg-6">
        <small class="text-muted d-block mb-2">Parcelas depois da alteração</small>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 70px;">#</th>
                <th style="width: 110px;">Vencimento</th>
                <th class="text-end">Valor</th>
                <th class="text-end">Pago</th>
                <th style="width: 110px;">Situação</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($financeiro['depois']['parcelas'] as $parcela)
                <tr>
                  <td>{{ $parcela['numero'] }}ª</td>
                  <td>{{ $parcela['vencimento'] ?? '—' }}</td>
                  <td class="text-end">{{ $dinheiro($parcela['valor']) }}</td>
                  <td class="text-end">{{ $dinheiro($parcela['valor_pago']) }}</td>
                  <td>
                    @if ($parcela['status'])
                      <span class="badge {{ $parcela['status']->corBadge() }}">{{ $parcela['status']->label() }}</span>
                    @else
                      —
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-muted">
                    Nenhuma parcela: as semanas que sobraram não geram cobrança.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <hr class="my-4" />

    <form method="POST" action="{{ route('prescricoes.editar.aplicar', $prescricao) }}">
      @csrf

      <div class="d-flex flex-wrap align-items-center gap-3">
        <button type="submit" class="btn btn-primary">
          <i class="ri-check-double-line me-1"></i>Autorizar e aplicar
        </button>

        <span class="text-muted small">
          Para mexer em algo, ajuste o formulário acima e clique em
          <strong>Conferir alterações</strong> de novo.
        </span>
      </div>
    </form>
  </div>
</div>
