{{-- Envio de uma semana atrasada para a fila: pergunta se as semanas seguintes
     acompanham o atraso. Os formulários de envio trazem a proposta em
     data-fila-remanejar (JSON) e o hidden remanejar, respondido aqui. --}}
<div class="modal fade" id="modal-remanejar" tabindex="-1" aria-labelledby="modal-remanejar-titulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-remanejar-titulo">Remanejar as semanas seguintes?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <p class="text-muted small mb-3" id="remanejar-resumo"></p>

        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Semana</th>
                <th>De</th>
                <th>Para</th>
              </tr>
            </thead>
            <tbody id="remanejar-semanas"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-remanejar="0">
          Não, manter as datas
        </button>
        <button type="button" class="btn btn-info" data-remanejar="1">
          <i class="ri-drag-move-2-line me-1"></i>Sim, remanejar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById('modal-remanejar');

    if (!modal) return;

    const instancia = () => bootstrap.Modal.getOrCreateInstance(modal);

    let formPendente = null;

    // Intercepta o envio antes do confirm() do crud-scripts: com semana
    // atrasada a pergunta de remanejar é o próprio modal.
    document.addEventListener('submit', function (event) {
      const form = event.target;

      if (!form.dataset?.filaRemanejar || form.dataset.remanejarRespondido) {
        return;
      }

      const proposta = JSON.parse(form.dataset.filaRemanejar);

      if (!proposta.atraso) {
        return;
      }

      event.preventDefault();
      delete form.dataset.confirmar;

      formPendente = form;

      document.getElementById('remanejar-resumo').textContent =
        'Esta semana está ' + proposta.atraso + ' dia(s) atrasada. '
        + 'As semanas seguintes passariam para as datas abaixo:';

      const tbody = document.getElementById('remanejar-semanas');

      tbody.innerHTML = '';

      proposta.semanas.forEach(function (semana) {
        const linha = document.createElement('tr');

        ['Semana ' + semana.numero, semana.de, semana.para].forEach(function (texto, indice) {
          const celula = document.createElement('td');

          if (indice === 2) {
            celula.className = 'fw-semibold text-info';
          }

          celula.textContent = texto;
          linha.appendChild(celula);
        });

        tbody.appendChild(linha);
      });

      instancia().show();
    }, true);

    modal.addEventListener('click', function (event) {
      const botao = event.target.closest('[data-remanejar]');

      if (!botao || !formPendente) {
        return;
      }

      const form = formPendente;
      const campo = form.querySelector('input[name="remanejar"]');

      formPendente = null;

      if (campo) {
        campo.value = botao.dataset.remanejar;
      }

      form.dataset.remanejarRespondido = '1';

      instancia().hide();
      form.requestSubmit();
    });
  })();
</script>
