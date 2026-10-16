{{--
  Modelo de uma semana da edição de medicamentos (clonado pelo JS).
  Os marcadores __SEMANA__ e __ITEM__ são trocados na hora de clonar.
--}}
<div class="border rounded p-3 mb-3" data-semana data-chave="__SEMANA__" data-bloqueada="0">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="badge bg-label-primary">Semana <span data-numero-semana>—</span></span>

      <span class="badge bg-label-secondary d-none" data-travada>
        <i class="ri-lock-line me-1"></i><span data-travada-motivo>Já aplicada — não pode ser alterada</span>
      </span>

      <span class="badge bg-label-info d-none" data-status></span>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
      <input type="hidden" name="semanas[__SEMANA__][id]" value="" data-id />

      <div class="form-check form-check-inline mb-0">
        <input
          type="checkbox"
          class="form-check-input"
          id="sem_aplicacao___SEMANA__"
          name="semanas[__SEMANA__][sem_aplicacao]"
          value="1"
          data-sem-aplicacao />
        <label class="form-check-label" for="sem_aplicacao___SEMANA__">Sem aplicação</label>
      </div>

      <input
        type="date"
        name="semanas[__SEMANA__][data_prevista]"
        class="form-control form-control-sm"
        style="width: 160px"
        data-data-prevista />

      <button
        type="button"
        class="btn btn-sm btn-icon btn-text-danger"
        data-remover-semana
        title="Excluir semana">
        <i class="ri-delete-bin-7-line"></i>
      </button>
    </div>
  </div>

  <input
    type="text"
    name="semanas[__SEMANA__][observacao]"
    class="form-control form-control-sm mb-2"
    maxlength="255"
    placeholder="Observação da semana (opcional)"
    data-observacao />

  <div data-area-itens>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-2">
        <thead class="table-light">
          <tr>
            <th style="width: 140px;">Tipo</th>
            <th>Medicamento / Combo</th>
            <th style="width: 120px;">Quantidade</th>
            <th style="width: 130px;">Valor</th>
            <th style="width: 130px;" class="text-end">Total</th>
            <th style="width: 50px;"></th>
          </tr>
        </thead>
        <tbody data-itens-semana></tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="text-end">Total da semana</th>
            <th class="text-end" data-total-semana>R$ 0,00</th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <button type="button" class="btn btn-sm btn-outline-primary" data-adicionar-item>
      <i class="ri-add-line me-1"></i>Adicionar medicamento/combo
    </button>
  </div>
</div>
