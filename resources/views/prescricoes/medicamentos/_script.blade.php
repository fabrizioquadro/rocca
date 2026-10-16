{{--
  Edição dos medicamentos e semanas: monta as semanas na tela (como no
  cadastro), permite adicionar/excluir semanas e medicamentos, e mantém as
  semanas já aplicadas travadas (só leitura). O envio vai para "Conferir
  alterações", que mostra o resumo antes de gravar.
--}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('semanas');
    const modeloSemana = document.getElementById('modelo-semana');
    const modeloItem = document.getElementById('modelo-item');
    const totalGeralEl = document.getElementById('total-geral');

    if (!container || !modeloSemana || !modeloItem) return;

    let sequenciaSemana = 0;
    let sequenciaItem = 0;

    // --- Números e dinheiro -------------------------------------------------

    const numero = (valor) => {
      const texto = String(valor ?? '').replace(/\s/g, '');

      if (!texto) return 0;

      const normalizado = texto.indexOf(',') >= 0
        ? texto.replace(/\./g, '').replace(',', '.')
        : texto;
      const convertido = parseFloat(normalizado.replace(/[^\d.-]/g, ''));

      return isFinite(convertido) ? convertido : 0;
    };

    const moeda = (valor) => 'R$ ' + (valor || 0).toFixed(2).replace('.', ',');

    const valorParaCampo = (valor) => {
      if (valor === null || valor === undefined || valor === '') return '';

      return numero(valor).toFixed(2).replace('.', ',');
    };

    // --- Acessos ao DOM ----------------------------------------------------

    const corpoDeItens = (semana) => semana.querySelector('tbody[data-itens-semana]');
    const linhasDeItens = (semana) => Array.from(semana.querySelectorAll('tbody[data-itens-semana] tr'));
    const novoSufixo = () => 'novo_' + (++sequenciaSemana);
    const novoIndice = () => 'i' + (++sequenciaItem);

    // --- Cálculos (mesma regra do cadastro) --------------------------------

    // Ampola não tem fração na cobrança (0,5 cobra 1); miligrama, procedimento
    // e combo cobram exatamente a quantidade informada
    const quantidadeParaCobranca = (linha) => {
      const quantidade = numero(linha.querySelector('[data-quantidade]')?.value);

      if (quantidade <= 0) return 0;

      const tipo = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';

      if (tipo !== 'medicamento') return quantidade;

      const select = linha.querySelector('[data-select-medicamento]');
      const opcao = select ? select.options[select.selectedIndex] : null;
      const tipoMedicamento = opcao && opcao.value ? opcao.getAttribute('data-tipo') : '';

      return tipoMedicamento === 'ampola' ? Math.ceil(quantidade) : quantidade;
    };

    const totalDaLinha = (linha) => quantidadeParaCobranca(linha) * numero(linha.querySelector('[data-valor]')?.value);

    const totalDaSemana = (semana) => linhasDeItens(semana).reduce((soma, linha) => soma + totalDaLinha(linha), 0);

    const atualizarTotais = () => {
      let geral = 0;

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        linhasDeItens(semana).forEach((linha) => {
          const celula = linha.querySelector('[data-total-item]');

          if (celula) celula.textContent = moeda(totalDaLinha(linha));
        });

        const total = totalDaSemana(semana);
        const celulaSemana = semana.querySelector('[data-total-semana]');

        if (celulaSemana) celulaSemana.textContent = moeda(total);

        geral += total;
      });

      if (totalGeralEl) totalGeralEl.textContent = moeda(geral);
    };

    // A semana é renumerada pela posição na tela: é assim que ela fica gravada
    const renumerarSemanas = () => {
      container.querySelectorAll('[data-semana]').forEach((semana, posicao) => {
        const numeroEl = semana.querySelector('[data-numero-semana]');

        if (numeroEl) numeroEl.textContent = posicao + 1;
      });
    };

    // --- Select2 -----------------------------------------------------------

    const ligarEventosSelect2 = (elemento) => {
      if (!window.jQuery) return;

      jQuery(elemento).on('select2:select select2:unselect select2:clear', function () {
        const campo = this;

        setTimeout(function () {
          campo.dispatchEvent(new Event('change', { bubbles: true }));
        }, 0);
      });
    };

    const inicializarSelect2 = (escopo) => {
      if (!window.jQuery || !jQuery.fn.select2) return;

      jQuery(escopo).find('select').each(function () {
        if (this.disabled || jQuery(this).hasClass('select2-hidden-accessible')) return;

        jQuery(this).select2({
          width: '100%',
          language: {
            noResults: () => 'Nenhum registro encontrado',
            searching: () => 'Buscando...'
          }
        });

        ligarEventosSelect2(this);
      });
    };

    // --- Itens ------------------------------------------------------------

    // Mostra o select de medicamento ou o de combo, conforme o tipo escolhido
    const aplicarTipo = (linha, comSelect2 = true) => {
      const tipo = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';
      const ehMedicamento = tipo === 'medicamento';

      const wrapMedicamento = linha.querySelector('[data-wrap-medicamento]');
      const wrapCombo = linha.querySelector('[data-wrap-combo]');
      const campoMedicamento = linha.querySelector('[data-select-medicamento]');
      const campoCombo = linha.querySelector('[data-select-combo]');

      if (wrapMedicamento) wrapMedicamento.classList.toggle('d-none', !ehMedicamento);
      if (wrapCombo) wrapCombo.classList.toggle('d-none', ehMedicamento);
      if (campoMedicamento) campoMedicamento.disabled = !ehMedicamento;
      if (campoCombo) campoCombo.disabled = ehMedicamento;

      if (comSelect2) inicializarSelect2(linha);
    };

    // O valor do item vem do cadastro (medicamento: valor de venda / combo:
    // soma dos itens) e vai sendo preenchido conforme a escolha
    const aplicarValorDoItem = (linha) => {
      const campoValor = linha.querySelector('[data-valor]');
      const select = selectDoItem(linha);

      if (!campoValor || !select) return;

      const opcao = select.options[select.selectedIndex];
      const valor = opcao && opcao.value ? opcao.getAttribute('data-valor') : '';

      if (valor) campoValor.value = valorParaCampo(valor);
    };

    const selectDoItem = (linha) => {
      const tipo = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';

      return linha.querySelector(tipo === 'combo' ? '[data-select-combo]' : '[data-select-medicamento]');
    };

    const criarItem = (semana, dados, bloqueada = false) => {
      const chave = semana.dataset.chave;
      const html = modeloItem.innerHTML
        .replace(/__SEMANA__/g, chave)
        .replace(/__ITEM__/g, novoIndice());

      corpoDeItens(semana).insertAdjacentHTML('beforeend', html.trim());

      const linha = corpoDeItens(semana).lastElementChild;
      const item = dados || {};

      const tipo = item.tipo === 'combo' ? 'combo' : 'medicamento';

      linha.querySelector('[data-tipo-item]').value = tipo;
      linha.querySelector('[data-quantidade]').value = item.quantidade ?? '';
      linha.querySelector('[data-valor]').value = valorParaCampo(item.valor);

      if (tipo === 'combo') {
        linha.querySelector('[data-select-combo]').value = item.combo_id ?? '';
      } else {
        linha.querySelector('[data-select-medicamento]').value = item.medicamento_id ?? '';
      }

      aplicarTipo(linha, !bloqueada);
      atualizarTotais();

      return linha;
    };

    // --- Semanas ----------------------------------------------------------

    // Semana marcada como "sem aplicação": não aplica nada (sem itens), fica
    // com a tonalidade escura e sem a área de medicamentos
    const aplicarSemAplicacao = (semana) => {
      const marcada = !!semana.querySelector('[data-sem-aplicacao]')?.checked;
      const areaItens = semana.querySelector('[data-area-itens]');

      semana.classList.toggle('semana-sem-aplicacao', marcada);

      if (areaItens) areaItens.classList.toggle('d-none', marcada);

      if (marcada) {
        linhasDeItens(semana).forEach((linha) => linha.remove());
      } else if (!linhasDeItens(semana).length) {
        criarItem(semana);
      }

      atualizarTotais();
    };

    // Semana já aplicada: fica só para leitura. O id continua sendo enviado
    // (hidden) para o servidor saber que ela existe e não mudou de lugar.
    const travarSemana = (semana, estado) => {
      semana.dataset.bloqueada = '1';

      semana.querySelectorAll('input:not([type="hidden"]), select, button').forEach((campo) => {
        campo.disabled = true;
      });

      const badge = semana.querySelector('[data-travada]');

      if (badge) {
        badge.classList.remove('d-none');

        const texto = semana.querySelector('[data-travada-motivo]');

        if (texto) texto.textContent = estado?.status_label ? estado.status_label + ' — não pode ser alterada' : 'Já aplicada — não pode ser alterada';
      }
    };

    const mostrarStatus = (semana, estado) => {
      const badge = semana.querySelector('[data-status]');

      if (!badge || !estado?.status_label) return;

      badge.classList.remove('d-none');
      badge.classList.add(estado.status_cor || 'bg-label-info');
      badge.textContent = estado.status_label;
    };

    const criarSemana = (dados, estado) => {
      const item = dados || {};
      const chave = item.id ? String(item.id) : novoSufixo();

      const html = modeloSemana.innerHTML.replace(/__SEMANA__/g, chave);
      const molde = document.createElement('div');

      molde.innerHTML = html.trim();

      const semana = molde.firstElementChild;

      container.appendChild(semana);

      semana.querySelector('[data-id]').value = item.id ?? '';
      semana.querySelector('[data-data-prevista]').value = item.data_prevista ?? '';
      semana.querySelector('[data-observacao]').value = item.observacao ?? '';
      semana.querySelector('[data-sem-aplicacao]').checked = !!item.sem_aplicacao;

      const itens = Array.isArray(item.itens) ? item.itens : [];

      if (estado?.bloqueada) {
        // A semana travada mostra o que ela tem hoje e não abre a área de itens
        semana.querySelector('[data-area-itens]').classList.remove('d-none');
        itens.forEach((linhaItem) => criarItem(semana, linhaItem, true));
        travarSemana(semana, estado);
      } else if (item.sem_aplicacao) {
        aplicarSemAplicacao(semana);
      } else if (itens.length) {
        itens.forEach((linhaItem) => criarItem(semana, linhaItem));
      } else {
        criarItem(semana);
      }

      mostrarStatus(semana, estado);

      if (!estado?.bloqueada) {
        inicializarSelect2(semana);
      }

      renumerarSemanas();
      atualizarTotais();

      return semana;
    };

    // --- Eventos ----------------------------------------------------------

    document.getElementById('adicionar-semana')?.addEventListener('click', () => {
      criarSemana(null, null);
    });

    container.addEventListener('click', (event) => {
      const removerSemana = event.target.closest('[data-remover-semana]');

      if (removerSemana && !removerSemana.disabled) {
        const semana = removerSemana.closest('[data-semana]');
        const restantes = container.querySelectorAll('[data-semana]').length;

        if (restantes > 1) {
          semana.remove();
        } else {
          alert('A prescrição precisa ficar com pelo menos uma semana.');
        }

        renumerarSemanas();
        atualizarTotais();

        return;
      }

      const adicionarItem = event.target.closest('[data-adicionar-item]');

      if (adicionarItem && !adicionarItem.disabled) {
        criarItem(adicionarItem.closest('[data-semana]'));

        return;
      }

      const removerItem = event.target.closest('[data-remover-item]');

      if (removerItem && !removerItem.disabled) {
        removerItem.closest('tr').remove();
        atualizarTotais();
      }
    });

    container.addEventListener('change', (event) => {
      const alvo = event.target;

      if (alvo.matches('[data-sem-aplicacao]')) {
        aplicarSemAplicacao(alvo.closest('[data-semana]'));
      } else if (alvo.matches('[data-tipo-item]')) {
        aplicarTipo(alvo.closest('tr'));
        aplicarValorDoItem(alvo.closest('tr'));
        atualizarTotais();
      } else if (alvo.matches('[data-select-medicamento], [data-select-combo]')) {
        aplicarValorDoItem(alvo.closest('tr'));
        atualizarTotais();
      }
    });

    container.addEventListener('input', (event) => {
      if (event.target.matches('[data-quantidade], [data-valor]')) {
        atualizarTotais();
      }
    });

    // --- Montagem inicial -------------------------------------------------

    const semanasIniciais = Array.isArray(window.prescricaoSemanas) ? window.prescricaoSemanas : [];
    const estado = window.prescricaoEstado || {};

    semanasIniciais.forEach((semana) => {
      criarSemana(semana, semana && semana.id ? estado[String(semana.id)] : null);
    });

    if (!semanasIniciais.length) {
      criarSemana(null, null);
    }

    renumerarSemanas();
    atualizarTotais();
  });
</script>
