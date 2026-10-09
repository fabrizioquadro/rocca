{{-- Edição de um pagamento já lançado: um modal por recebimento, com os
     valores atuais. Ao salvar, o recebido é redistribuído nas parcelas. --}}
@foreach ($financeiro->pagamentos as $pagamento)
  <div
    class="modal fade"
    id="modal-editar-pagamento-{{ $pagamento->id }}"
    tabindex="-1"
    aria-labelledby="modal-editar-pagamento-titulo-{{ $pagamento->id }}"
    aria-hidden="true"
    data-pagamento-form>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modal-editar-pagamento-titulo-{{ $pagamento->id }}">
            <i class="ri-money-dollar-circle-line me-1"></i>Editar pagamento
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <p class="text-muted small mb-3">
            Recebimento de <strong>{{ $pagamento->valor_formatado }}</strong>
            em {{ $pagamento->data_formatada }} — registrado por
            {{ $pagamento->user?->nome ?? '—' }}.
            Depois de salvar, o valor recebido é redistribuído nas parcelas.
          </p>

          {{-- O formulário fica dentro do corpo do modal (o Bootstrap espera
               header/body/footer como filhos diretos do .modal-content) --}}
          <form
            method="POST"
            action="{{ route('prescricoes.pagamentos.update', [$prescricao, $pagamento]) }}"
            id="form-editar-pagamento-{{ $pagamento->id }}">
            @csrf
            @method('PUT')

            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label class="form-label" for="editar_valor_{{ $pagamento->id }}">Valor *</label>
                <input
                  type="text"
                  id="editar_valor_{{ $pagamento->id }}"
                  name="valor"
                  class="form-control"
                  data-moeda
                  inputmode="numeric"
                  placeholder="R$ 0,00"
                  value="{{ number_format((float) $pagamento->valor, 2, ',', '.') }}"
                  required />
              </div>

              <div class="col-md-3">
                <label class="form-label" for="editar_forma_{{ $pagamento->id }}">Forma de pagamento *</label>
                <select
                  id="editar_forma_{{ $pagamento->id }}"
                  name="forma_pagamento"
                  class="form-select"
                  data-forma-pagamento
                  required>
                  <option value="">Selecione...</option>
                  @foreach ($formasPagamento as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected($pagamento->forma_pagamento?->value === $valor)>
                      {{ $rotulo }}
                    </option>
                  @endforeach
                </select>
              </div>

              {{-- Parcelas do cartão/link: só nas formas que parcelam --}}
              <div class="col-md-2 d-none" data-wrap-parcelas-modal>
                <label class="form-label" for="editar_parcelas_{{ $pagamento->id }}">Parcelas *</label>
                <select
                  id="editar_parcelas_{{ $pagamento->id }}"
                  name="parcelas"
                  class="form-select"
                  data-parcelas-pagamento>
                  @foreach ($parcelasDisponiveis as $numeroParcelas)
                    <option value="{{ $numeroParcelas }}" @selected((int) $pagamento->parcelas === $numeroParcelas)>
                      {{ $numeroParcelas }}x
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-md-2">
                <label class="form-label" for="editar_data_{{ $pagamento->id }}">Data *</label>
                <input
                  type="date"
                  id="editar_data_{{ $pagamento->id }}"
                  name="data_pagamento"
                  class="form-control"
                  value="{{ $pagamento->data_pagamento?->toDateString() }}"
                  required />
              </div>

              <div class="col-md-2">
                <label class="form-label" for="editar_identificador_{{ $pagamento->id }}">ID</label>
                <input
                  type="text"
                  id="editar_identificador_{{ $pagamento->id }}"
                  name="identificador"
                  class="form-control"
                  maxlength="100"
                  placeholder="NSU/autorização"
                  value="{{ $pagamento->identificador }}" />
              </div>

              <div class="col-12">
                <label class="form-label" for="editar_observacao_{{ $pagamento->id }}">Observação</label>
                <input
                  type="text"
                  id="editar_observacao_{{ $pagamento->id }}"
                  name="observacao"
                  class="form-control"
                  maxlength="255"
                  value="{{ $pagamento->observacao }}" />
              </div>
            </div>
          </form>

          <div class="alert alert-warning py-2 mb-0 mt-3" role="alert">
            <i class="ri-information-line me-1"></i>
            Corrigir o valor muda o que está quitado: as parcelas são recalculadas a partir do total recebido.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" form="form-editar-pagamento-{{ $pagamento->id }}">
            <i class="ri-save-3-line me-1"></i>Salvar pagamento
          </button>
        </div>
      </div>
    </div>
  </div>
@endforeach

<script>
  // Parcelas só aparecem nas formas que parcelam (cartão/link)
  document.addEventListener('DOMContentLoaded', function () {
    const formasComParcelas = @json($formasComParcelas);

    document.querySelectorAll('[data-pagamento-form]').forEach(function (modal) {
      const forma = modal.querySelector('[data-forma-pagamento]');
      const wrap = modal.querySelector('[data-wrap-parcelas-modal]');
      const parcelas = modal.querySelector('[data-parcelas-pagamento]');

      if (! forma || ! wrap || ! parcelas) return;

      const atualizar = () => {
        const exige = formasComParcelas.indexOf(forma.value) >= 0;

        wrap.classList.toggle('d-none', !exige);
        parcelas.disabled = !exige;
        parcelas.required = exige;

        if (! exige) parcelas.value = '1';
      };

      forma.addEventListener('change', atualizar);
      atualizar();
    });
  });
</script>
