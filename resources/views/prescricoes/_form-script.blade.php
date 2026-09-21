<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Este build do Select2 nao dispara 'change' no <select> original, e os
    // handlers da tela escutam 'change' nos containers. Traduzimos os eventos
    // do Select2 para um 'change' nativo (com bubbles).
    const ligarEventosSelect2 = (elemento) => {
      jQuery(elemento).on('select2:select select2:unselect select2:clear', function () {
        const campo = this;

        setTimeout(function () {
          campo.dispatchEvent(new Event('change', { bubbles: true }));
        }, 0);
      });
    };

    // Cabecalho: clinica (lista local), paciente (base grande -> busca por AJAX)
    // e medico (poucos -> ja vem carregado da Feegow).
    if (window.jQuery && jQuery.fn.select2) {
      jQuery('#clinica_id').select2({
        width: '100%',
        language: {
          noResults: () => 'Nenhum registro encontrado',
          searching: () => 'Buscando...'
        }
      });
      ligarEventosSelect2(document.getElementById('clinica_id'));

      if (window.prescricaoPacientesUrl) {
        jQuery('#paciente_id').select2({
          width: '100%',
          placeholder: 'Digite para buscar o paciente...',
          allowClear: true,
          minimumInputLength: 3,
          language: {
            inputTooShort: () => 'Digite pelo menos 3 letras',
            noResults: () => 'Nenhum paciente encontrado',
            searching: () => 'Buscando...'
          },
          ajax: {
            url: window.prescricaoPacientesUrl,
            dataType: 'json',
            delay: 300,
            cache: true,
            data: (params) => ({ busca: params.term }),
            processResults: (data) => data
          }
        });
        ligarEventosSelect2(document.getElementById('paciente_id'));
      }

      jQuery('#medico_id').select2({
        width: '100%',
        placeholder: 'Selecione o médico...',
        allowClear: true,
        language: {
          noResults: () => 'Nenhum médico encontrado',
          searching: () => 'Buscando...'
        }
      });
      ligarEventosSelect2(document.getElementById('medico_id'));

      // Guarda o nome do medico (a Feegow pode mudar/remover o profissional)
      const medicoSelect = document.getElementById('medico_id');
      const campoMedicoNome = document.getElementById('medico_nome');

      if (medicoSelect && campoMedicoNome) {
        const atualizarMedicoNome = () => {
          const opcao = medicoSelect.options[medicoSelect.selectedIndex];

          campoMedicoNome.value = opcao ? (opcao.getAttribute('data-nome') || '') : '';
        };

        medicoSelect.addEventListener('change', atualizarMedicoNome);
        atualizarMedicoNome();
      }
    }

    // Observacao do paciente: e um aviso importante, entao aparece assim que
    // o paciente e escolhido (vem da busca de pacientes e do select2).
    const pacienteSelect = document.getElementById('paciente_id');
    const avisoObservacao = document.getElementById('aviso-observacao-paciente');
    const avisoObservacaoTexto = document.getElementById('aviso-observacao-paciente-texto');

    if (pacienteSelect && avisoObservacao && avisoObservacaoTexto) {
      const observacaoDoPaciente = () => {
        // O select2 guarda o registro completo (com a observacao) no item escolhido
        if (window.jQuery && jQuery.fn.select2 && jQuery(pacienteSelect).data('select2')) {
          const escolhidos = jQuery(pacienteSelect).select2('data');

          if (escolhidos && escolhidos.length && escolhidos[0].observacao) {
            return escolhidos[0].observacao;
          }
        }

        const opcao = pacienteSelect.options[pacienteSelect.selectedIndex];

        return opcao ? (opcao.getAttribute('data-observacao') || '') : '';
      };

      const atualizarObservacaoPaciente = () => {
        const texto = observacaoDoPaciente();

        avisoObservacaoTexto.textContent = texto;
        avisoObservacao.classList.toggle('d-none', !texto);
      };

      pacienteSelect.addEventListener('change', atualizarObservacaoPaciente);
      atualizarObservacaoPaciente();
    }

    const container = document.getElementById('semanas');
    const modeloSemana = document.getElementById('modelo-semana');
    const botaoAdicionarSemana = document.getElementById('adicionar-semana');
    const totalGeralEl = document.getElementById('total-geral');

    if (!container || !modeloSemana) return;

    let indiceSemana = 0;
    let indiceItem = 0;

    const formatarMoeda = (numero) => {
      const partes = numero.toFixed(2).split('.');

      return 'R$ ' + partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
    };

    const moedaParaNumero = (texto) => {
      const digitos = String(texto || '').replace(/\D/g, '');

      return digitos ? parseInt(digitos, 10) / 100 : 0;
    };

    // Le um numero de campo de quantidade (aceita virgula ou ponto como decimal)
    const numeroDoCampo = (valor) => {
      const numero = parseFloat(String(valor || '').replace(/\s/g, '').replace(',', '.').replace(/[^\d.]/g, ''));

      return isFinite(numero) ? numero : 0;
    };

    // Numero -> texto do campo de moeda mascarado (ex.: 180 -> '180,00')
    const numeroParaCampoMoeda = (valor) => {
      const numero = parseFloat(valor);

      if (!isFinite(numero)) return '';

      const partes = numero.toFixed(2).split('.');

      return partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
    };

    // Ao escolher o medicamento a quantidade ja comeca em 1 (se estiver vazia)
    const sugerirQuantidadePadrao = (campoQuantidade) => {
      if (campoQuantidade && !String(campoQuantidade.value || '').trim()) {
        campoQuantidade.value = '1';
      }
    };

    // Quantidade que sera COBRADA (o campo fica com o que foi digitado).
    // Ampola nao tem fracao (0,5 cobra 1); miligrama/procedimento e combo
    // cobram exatamente a quantidade informada.
    const campoQuantidadeDaLinha = (linha) =>
      linha.querySelector('input[name$="[quantidade]"], [data-gerador-quantidade]');

    const quantidadeParaCobranca = (linha) => {
      const quantidade = numeroDoCampo(campoQuantidadeDaLinha(linha)?.value);

      if (quantidade <= 0) return 0;

      const tipoItem = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';

      if (tipoItem !== 'medicamento') return quantidade;

      const select = linha.querySelector('[data-select-medicamento]');
      const opcao = select ? select.options[select.selectedIndex] : null;
      const tipoMedicamento = opcao && opcao.value ? opcao.getAttribute('data-tipo') : '';

      return tipoMedicamento === 'ampola' ? Math.ceil(quantidade) : quantidade;
    };

    // O valor do item vem do cadastro (medicamento: valor de venda /
    // combo: soma dos itens) e nao pode ser alterado na prescricao
    const aplicarValorDoItem = (campoValor, select) => {
      if (!campoValor || !select) return;

      const opcao = select.options[select.selectedIndex];
      const valor = opcao && opcao.value ? opcao.getAttribute('data-valor') : '';

      campoValor.value = valor ? numeroParaCampoMoeda(valor) : '';
    };

    // Caixa "semana sem aplicacao" da semana
    const campoSemAplicacao = (semana) =>
      semana ? semana.querySelector('[data-semana-sem-aplicacao]') : null;

    const inicializarSelect2 = (escopo) => {
      if (!window.jQuery || !jQuery.fn.select2) return;

      jQuery(escopo).find('select').each(function () {
        if (jQuery(this).hasClass('select2-hidden-accessible')) return;

        // O Select2 nao funciona em select desabilitado (o combo comeca desabilitado)
        if (this.disabled) return;

        // Dentro de um modal o dropdown precisa ficar no proprio modal, senao
        // ele fica atras do modal (z-index do modal e maior que o do Select2)
        const modalDoCampo = this.closest('.modal');

        jQuery(this).select2({
          width: '100%',
          dropdownParent: modalDoCampo ? jQuery(modalDoCampo) : jQuery(document.body),
          language: {
            noResults: () => 'Nenhum registro encontrado',
            searching: () => 'Buscando...'
          }
        });

        ligarEventosSelect2(this);
      });
    };

    // Mostra o select de medicamento ou o de combo, conforme o tipo escolhido
    const aplicarTipo = (linha) => {
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

      // O select que acabou de ser habilitado ainda pode nao ter o Select2
      inicializarSelect2(linha);
    };

    const totalDaLinha = (linha) =>
      quantidadeParaCobranca(linha) * moedaParaNumero(linha.querySelector('input[name$="[valor]"]')?.value);

    const totalDaSemana = (semana) =>
      Array.from(semana.querySelectorAll('tbody[data-itens-semana] tr')).reduce((soma, linha) => soma + totalDaLinha(linha), 0);

    const dataParaTextoBR = (dataIso) => {
      const partes = String(dataIso || '').split('-');

      return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : '';
    };

    // --- Desconto / adicional (rateados entre as parcelas) ---
    const descontoTipoEl = document.getElementById('desconto_tipo');
    const descontoValorEl = document.getElementById('desconto_valor');
    const descontoUnidadeEl = document.getElementById('desconto-unidade');
    const adicionalEl = document.getElementById('adicional_valor');

    // Numero digitado nos campos de desconto/adicional (aceita "1.234,56",
    // "10,5" e "10.5")
    const numeroDigitado = (valor) => {
      const texto = String(valor || '').replace(/\s/g, '');

      if (!texto) return 0;

      const normalizado = texto.indexOf(',') >= 0
        ? texto.replace(/\./g, '').replace(',', '.')
        : texto;
      const numero = parseFloat(normalizado.replace(/[^\d.]/g, ''));

      return isFinite(numero) ? numero : 0;
    };

    // Desconto (percentual ou valor fixo) e adicional aplicados sobre o bruto
    const ajustesDoFormulario = (bruto) => {
      const tipo = descontoTipoEl ? descontoTipoEl.value : '';
      const informado = numeroDigitado(descontoValorEl ? descontoValorEl.value : '');
      let desconto = 0;

      if (tipo === 'porcentagem') {
        desconto = bruto * (Math.min(Math.max(informado, 0), 100) / 100);
      } else if (tipo === 'valor') {
        desconto = Math.min(Math.max(informado, 0), bruto);
      }

      desconto = Math.round(desconto * 100) / 100;

      const adicional = Math.round(Math.max(numeroDigitado(adicionalEl ? adicionalEl.value : ''), 0) * 100) / 100;

      return {
        tipo,
        desconto,
        adicional,
        liquido: Math.round((bruto - desconto + adicional) * 100) / 100,
      };
    };

    // Resumo dos ajustes (rodapé da tabela de parcelas)
    const atualizarResumoAjustes = (bruto, desconto, adicional, tipo) => {
      const brutoEl = document.getElementById('total-bruto');
      const descontoEl = document.getElementById('total-desconto');
      const adicionalTextoEl = document.getElementById('total-adicional');

      if (brutoEl) brutoEl.textContent = formatarMoeda(bruto);
      if (descontoEl) descontoEl.textContent = formatarMoeda(desconto);
      if (adicionalTextoEl) adicionalTextoEl.textContent = formatarMoeda(adicional);

      // Coluna/aviso só aparecem quando existe o ajuste
      document.querySelectorAll('[data-coluna-desconto]').forEach((el) => {
        el.classList.toggle('d-none', desconto <= 0);
      });

      document.querySelectorAll('[data-coluna-adicional]').forEach((el) => {
        el.classList.toggle('d-none', adicional <= 0);
      });

      document.querySelectorAll('[data-desconto-resumo]').forEach((el) => {
        el.classList.toggle('d-none', desconto <= 0);
      });

      const descricaoEl = document.querySelector('[data-desconto-descricao]');

      if (descricaoEl) {
        const informado = numeroDigitado(descontoValorEl ? descontoValorEl.value : '');

        descricaoEl.textContent = tipo === 'porcentagem' ? informado + '%' : 'valor fixo';
      }
    };

    // Habilita o campo de desconto conforme o tipo escolhido
    const aplicarTipoDesconto = () => {
      const tipo = descontoTipoEl ? descontoTipoEl.value : '';

      if (descontoValorEl) {
        descontoValorEl.disabled = tipo === '';

        if (tipo === '') {
          descontoValorEl.value = '';
        }
      }

      if (descontoUnidadeEl) {
        descontoUnidadeEl.textContent = tipo === 'porcentagem' ? '%' : (tipo === 'valor' ? 'R$' : '—');
      }

      atualizarParcelas();
    };

    // Uma parcela para cada semana com aplicacao (vencimento = data da
    // aplicacao), com o desconto/adicional dividido igualmente
    const atualizarParcelas = () => {
      const corpo = document.getElementById('parcelas-corpo');
      const modelo = document.getElementById('modelo-parcela');
      const totalEl = document.getElementById('total-parcelas');

      if (!corpo || !modelo) return;

      // Semanas que geram parcela, com o valor bruto de cada uma
      const semanas = [];

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        // Semana sem aplicacao (ou sem itens) nao gera parcela
        if (campoSemAplicacao(semana)?.checked) return;

        const valor = totalDaSemana(semana);

        if (valor <= 0) return;

        semanas.push({ semana, valor });
      });

      const bruto = semanas.reduce((soma, item) => soma + item.valor, 0);
      const { tipo, desconto, adicional, liquido } = ajustesDoFormulario(bruto);

      corpo.innerHTML = '';

      // Rateio IGUALITARIO entre as parcelas (o valor de cada semana nao muda
      // a cota). A sobra de centavos vira 1 centavo a mais nas primeiras; se
      // alguma parcela for menor que a cota, o que sobrar vai para as seguintes
      const cotaIgualitaria = (valor, quantidade) => {
        const centavos = Math.round(valor * 100);
        const base = Math.floor(centavos / quantidade);
        const resto = centavos % quantidade;

        return Array.from({ length: quantidade }, (_, indice) => base + (indice < resto ? 1 : 0));
      };

      const cotasDesconto = semanas.length ? cotaIgualitaria(desconto, semanas.length) : [];
      const cotasAdicional = semanas.length ? cotaIgualitaria(adicional, semanas.length) : [];
      const rateio = [];

      let reserva = 0;

      semanas.forEach((item, indice) => {
        const brutoCentavos = Math.round(item.valor * 100);
        const cota = cotasDesconto[indice] + reserva;
        const aplicado = Math.min(cota, brutoCentavos);

        reserva = cota - aplicado;

        rateio.push({ bruto: brutoCentavos, desconto: aplicado, adicional: cotasAdicional[indice] });
      });

      if (reserva > 0) {
        rateio.forEach((linhaRateio) => {
          if (reserva <= 0) return;

          const espaco = linhaRateio.bruto - linhaRateio.desconto;
          const extra = Math.min(reserva, espaco);

          if (extra <= 0) return;

          linhaRateio.desconto += extra;
          reserva -= extra;
        });
      }

      semanas.forEach((item, indice) => {
        const rateado = rateio[indice];
        const valorDesconto = rateado.desconto / 100;
        const valorAdicional = rateado.adicional / 100;
        const valor = (rateado.bruto - rateado.desconto + rateado.adicional) / 100;

        const data = item.semana.querySelector('input[name$="[data_prevista]"]')?.value || '';
        const titulo = item.semana.querySelector('[data-titulo-semana]')?.textContent || '';
        const linha = modelo.content.firstElementChild.cloneNode(true);

        linha.querySelector('[data-parcela-numero]').textContent = (indice + 1) + 'ª';
        linha.querySelector('[data-parcela-vencimento]').textContent = dataParaTextoBR(data) || 'sem data';
        linha.querySelector('[data-parcela-semana]').textContent = titulo;
        linha.querySelector('[data-parcela-bruto]').textContent = formatarMoeda(item.valor);

        const celulaDesconto = linha.querySelector('[data-parcela-desconto]');

        celulaDesconto.textContent = '- ' + formatarMoeda(valorDesconto);
        celulaDesconto.classList.toggle('d-none', desconto <= 0);

        const celulaAdicional = linha.querySelector('[data-parcela-adicional]');

        celulaAdicional.textContent = '+ ' + formatarMoeda(valorAdicional);
        celulaAdicional.classList.toggle('d-none', adicional <= 0);

        linha.querySelector('[data-parcela-valor]').textContent = formatarMoeda(valor);

        corpo.appendChild(linha);
      });

      if (!semanas.length) {
        corpo.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Nenhuma semana com aplicação.</td></tr>';
      }

      if (totalEl) totalEl.textContent = formatarMoeda(liquido);

      atualizarResumoAjustes(bruto, desconto, adicional, tipo);
    };

    // Desconto/adicional recalculam as parcelas na hora
    if (descontoTipoEl) descontoTipoEl.addEventListener('change', aplicarTipoDesconto);
    if (descontoValorEl) descontoValorEl.addEventListener('input', atualizarParcelas);
    if (adicionalEl) adicionalEl.addEventListener('input', atualizarParcelas);
    aplicarTipoDesconto();

    // Um item exige anexo (prescricao medica) quando o medicamento e
    // ampola/miligrama com aplicacao (o combo herda isso dos medicamentos dele)
    const itemExigeAnexo = (linha) => {
      const tipo = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';
      const select = linha.querySelector(tipo === 'combo' ? '[data-select-combo]' : '[data-select-medicamento]');
      const opcao = select ? select.options[select.selectedIndex] : null;

      return !!(opcao && opcao.value && opcao.getAttribute('data-exige-anexo'));
    };

    const precisaAnexo = () =>
      Array.from(container.querySelectorAll('tbody[data-itens-semana] tr')).some(itemExigeAnexo);

    // Mostra/oculta o aviso de anexo obrigatorio
    const atualizarAvisoAnexo = () => {
      const precisa = precisaAnexo();

      document.querySelectorAll('[data-aviso-anexo]').forEach((el) => el.classList.toggle('d-none', !precisa));
      document.querySelectorAll('[data-anexo-obrigatorio]').forEach((el) => el.classList.toggle('d-none', !precisa));
    };

    const atualizarTotais = () => {
      let totalGeral = 0;

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        semana.querySelectorAll('tbody[data-itens-semana] tr').forEach((linha) => {
          const celulaItem = linha.querySelector('[data-total-item]');

          if (celulaItem) {
            celulaItem.textContent = formatarMoeda(totalDaLinha(linha));
          }
        });

        const totalSemana = totalDaSemana(semana);
        const celulaSemana = semana.querySelector('[data-total-semana]');

        if (celulaSemana) {
          celulaSemana.textContent = formatarMoeda(totalSemana);
        }

        totalGeral += totalSemana;
      });

      if (totalGeralEl) {
        totalGeralEl.textContent = formatarMoeda(totalGeral);
      }

      atualizarParcelas();
      atualizarAvisoAnexo();
    };

    const adicionarItem = (semana, item) => {
      const modeloItem = semana.querySelector('template[data-modelo-item]');
      const corpo = semana.querySelector('tbody[data-itens-semana]');

      if (!modeloItem || !corpo) return;

      const linha = modeloItem.content.firstElementChild.cloneNode(true);

      linha.innerHTML = linha.innerHTML.replace(/__INDICE_ITEM__/g, indiceItem++);

      corpo.appendChild(linha);

      if (item) {
        ['tipo', 'medicamento_id', 'combo_id', 'quantidade', 'valor'].forEach(function (campo) {
          const input = linha.querySelector('[name$="[' + campo + ']"]');

          if (input && item[campo] !== undefined && item[campo] !== null) {
            input.value = item[campo];
          }
        });
      }

      aplicarTipo(linha);
      inicializarSelect2(linha);

      if (typeof window.inicializarMascaras === 'function') {
        window.inicializarMascaras(linha);
      }

      atualizarTotais();
    };

    // Semana marcada como "sem aplicacao": nao aplica nada (sem itens),
    // fica com tonalidade escura e sem a area de medicamentos.
    const aplicarSemAplicacao = (semana) => {
      const marcada = !!campoSemAplicacao(semana)?.checked;
      const areaItens = semana.querySelector('[data-area-itens]');
      const badge = semana.querySelector('[data-badge-sem-aplicacao]');

      semana.classList.toggle('semana-sem-aplicacao', marcada);

      if (areaItens) areaItens.classList.toggle('d-none', marcada);
      if (badge) badge.classList.toggle('d-none', !marcada);

      if (marcada) {
        semana.querySelectorAll('tbody[data-itens-semana] tr').forEach((linha) => linha.remove());
      } else {
        const corpo = semana.querySelector('tbody[data-itens-semana]');

        if (corpo && !corpo.children.length) adicionarItem(semana);
      }

      atualizarTotais();
    };

    const renumerarSemanas = () => {
      container.querySelectorAll('[data-semana]').forEach(function (semana, posicao) {
        const titulo = semana.querySelector('[data-titulo-semana]');
        const campoNumero = semana.querySelector('[data-numero-semana]');

        if (titulo) {
          titulo.textContent = 'Semana ' + (posicao + 1);
        }

        if (campoNumero) {
          campoNumero.value = posicao + 1;
        }
      });
    };

    // A posicao (e o numero) da semana e definida pela data prevista:
    // mais antiga primeiro e, no fim, as semanas ainda sem data.
    const ordenarSemanas = () => {
      const comData = [];
      const semData = [];

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        const data = semana.querySelector('input[name$="[data_prevista]"]')?.value || '';

        if (data) {
          comData.push({ semana: semana, data: data });
        } else {
          semData.push(semana);
        }
      });

      comData.sort((a, b) => (a.data < b.data ? -1 : (a.data > b.data ? 1 : 0)));

      comData.forEach((item) => container.appendChild(item.semana));
      semData.forEach((semana) => container.appendChild(semana));

      renumerarSemanas();
    };

    const adicionarSemana = (dados) => {
      const bloco = modeloSemana.content.firstElementChild.cloneNode(true);

      bloco.innerHTML = bloco.innerHTML.replace(/__INDICE_SEMANA__/g, indiceSemana++);

      container.appendChild(bloco);

      const campoData = bloco.querySelector('input[name$="[data_prevista]"]');

      if (campoData && dados && dados.data_prevista) {
        campoData.value = dados.data_prevista;
      }

      const caixaSemAplicacao = campoSemAplicacao(bloco);

      if (caixaSemAplicacao && dados && dados.sem_aplicacao) {
        caixaSemAplicacao.checked = true;
      }

      (dados && dados.itens ? dados.itens : []).forEach((item) => adicionarItem(bloco, item));

      const corpo = bloco.querySelector('tbody[data-itens-semana]');

      if (corpo && !corpo.children.length) {
        adicionarItem(bloco);
      }

      aplicarSemAplicacao(bloco);
      renumerarSemanas();
      atualizarTotais();
    };

    // Semanas já informadas (após erro de validação)
    (window.semanasPrescricao || []).forEach((semana) => adicionarSemana(semana));

    if (!container.children.length) {
      adicionarSemana();
    }

    if (botaoAdicionarSemana) {
      botaoAdicionarSemana.addEventListener('click', () => adicionarSemana());
    }

    // Troca do tipo (medicamento/combo), escolha do item e quantidade
    container.addEventListener('change', function (event) {
      const linha = event.target.closest('tr');

      // Semana sem aplicacao
      if (event.target.matches('[data-semana-sem-aplicacao]')) {
        const semana = event.target.closest('[data-semana]');

        if (semana) aplicarSemAplicacao(semana);

        return;
      }

      if (event.target.matches('[data-tipo-item]')) {
        if (linha) {
          aplicarTipo(linha);
          atualizarTotais();
        }

        return;
      }

      // Medicamento escolhido: quantidade comeca em 1 e valor vem do cadastro
      if (event.target.matches('[data-select-medicamento]') && linha) {
        sugerirQuantidadePadrao(linha.querySelector('input[name$="[quantidade]"]'));
        aplicarValorDoItem(linha.querySelector('input[name$="[valor]"]'), event.target);
        atualizarTotais();

        return;
      }

      // Combo escolhido: valor vem do cadastro
      if (event.target.matches('[data-select-combo]') && linha) {
        aplicarValorDoItem(linha.querySelector('input[name$="[valor]"]'), event.target);
        atualizarTotais();

        return;
      }

      // Quantidade informada: o total ja e recalculado pelo listener de input

      // Mudou a data: a semana muda de posicao (e de numero)
      if (event.target.matches('input[name$="[data_prevista]"]')) {
        ordenarSemanas();
        atualizarTotais();
      }
    });

    container.addEventListener('input', atualizarTotais);

    container.addEventListener('click', function (event) {
      // Remover item da semana
      const removerItem = event.target.closest('[data-remover-item]');

      if (removerItem) {
        const linha = removerItem.closest('tr');
        const semana = removerItem.closest('[data-semana]');

        if (linha) {
          linha.remove();
        }

        const corpo = semana?.querySelector('tbody[data-itens-semana]');

        if (semana && corpo && !corpo.children.length) {
          adicionarItem(semana);
        }

        atualizarTotais();
        return;
      }

      // Adicionar item na semana
      const botaoItem = event.target.closest('[data-adicionar-item]');

      if (botaoItem) {
        const semana = botaoItem.closest('[data-semana]');

        if (semana) {
          adicionarItem(semana);
        }

        return;
      }

      // Remover semana
      const removerSemana = event.target.closest('[data-remover-semana]');

      if (removerSemana) {
        const semana = removerSemana.closest('[data-semana]');

        if (semana) {
          semana.remove();
        }

        if (!container.children.length) {
          adicionarSemana();
        }

        renumerarSemanas();
        atualizarTotais();
      }
    });

    // ------------------------- Gerador de semanas -------------------------
    const modalGeradorEl = document.getElementById('modal-gerador');
    const corpoGerador = document.querySelector('[data-gerador-itens]');
    const modeloItemGerador = document.getElementById('modelo-item-gerador');
    const totalGeradorEl = document.querySelector('[data-gerador-total]');
    const erroGeradorEl = document.querySelector('[data-gerador-erro]');
    const botaoAbrirGerador = document.getElementById('abrir-gerador');
    const botaoGerar = document.getElementById('gerador-gerar');

    const mostrarErroGerador = (mensagem) => {
      if (!erroGeradorEl) return;

      erroGeradorEl.textContent = mensagem;
      erroGeradorEl.classList.remove('d-none');
    };

    const limparErroGerador = () => {
      if (erroGeradorEl) erroGeradorEl.classList.add('d-none');
    };

    // Zera o gerador: data, numero de semanas, intervalo volta para 7 e sobra
    // apenas uma linha de medicamento.
    const reiniciarGerador = () => {
      const campoData = document.getElementById('gerador-data-inicio');
      const campoNumero = document.getElementById('gerador-numero');
      const campoIntervalo = document.getElementById('gerador-intervalo');

      if (campoData) campoData.value = '';
      if (campoNumero) campoNumero.value = '';
      if (campoIntervalo) campoIntervalo.value = '7';

      limparErroGerador();

      // A linha unica e recriada no "shown.bs.modal" (com o modal ja visivel)
      if (corpoGerador) corpoGerador.innerHTML = '';

      atualizarTotaisGerador();
    };

    const atualizarTotaisGerador = () => {
      if (!corpoGerador) return;

      let total = 0;

      corpoGerador.querySelectorAll('tr').forEach((linha) => {
        const quantidade = quantidadeParaCobranca(linha);
        const valor = moedaParaNumero(linha.querySelector('[data-gerador-valor]')?.value);
        const totalLinha = quantidade * valor;
        const celula = linha.querySelector('[data-total-item]');

        if (celula) celula.textContent = formatarMoeda(totalLinha);

        total += totalLinha;
      });

      if (totalGeradorEl) totalGeradorEl.textContent = formatarMoeda(total);
    };

    const adicionarItemGerador = (tipo) => {
      if (!corpoGerador || !modeloItemGerador) return;

      const linha = modeloItemGerador.content.firstElementChild.cloneNode(true);
      const campoTipo = linha.querySelector('[data-tipo-item]');

      if (campoTipo && tipo) campoTipo.value = tipo;

      corpoGerador.appendChild(linha);

      aplicarTipo(linha);
      inicializarSelect2(linha);

      if (typeof window.inicializarMascaras === 'function') {
        window.inicializarMascaras(linha);
      }

      atualizarTotaisGerador();
    };

    // Ao escolher o medicamento/combo o valor vem do cadastro (campo so leitura)
    const lerItemGerador = (linha) => {
      const tipo = linha.querySelector('[data-tipo-item]')?.value || 'medicamento';

      return {
        tipo: tipo,
        medicamento_id: tipo === 'medicamento' ? (linha.querySelector('[data-select-medicamento]')?.value || '') : null,
        combo_id: tipo === 'combo' ? (linha.querySelector('[data-select-combo]')?.value || '') : null,
        quantidade: linha.querySelector('[data-gerador-quantidade]')?.value || '',
        valor: linha.querySelector('[data-gerador-valor]')?.value || ''
      };
    };

    const dataParaCampoData = (data) => {
      const mes = String(data.getMonth() + 1).padStart(2, '0');
      const dia = String(data.getDate()).padStart(2, '0');

      return data.getFullYear() + '-' + mes + '-' + dia;
    };

    // "2026-09-11" -> Date (meio-dia local, sem problema de fuso)
    const dataDoCampo = (valor) => {
      const partes = String(valor || '').split('-').map(Number);

      return new Date(partes[0], (partes[1] || 1) - 1, partes[2] || 1, 12);
    };

    const semanaComData = (dataProcurada) => {
      let encontrada = null;

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        const campo = semana.querySelector('input[name$="[data_prevista]"]');

        if (!encontrada && campo && campo.value === dataProcurada) {
          encontrada = semana;
        }
      });

      return encontrada;
    };

    // Remove as linhas ainda sem item (a semana nasce com uma linha em branco)
    const removerLinhasVazias = (semana) => {
      semana.querySelectorAll('tbody[data-itens-semana] tr').forEach((linha) => {
        const medicamento = linha.querySelector('[data-select-medicamento]')?.value || '';
        const combo = linha.querySelector('[data-select-combo]')?.value || '';
        const quantidade = linha.querySelector('input[name$="[quantidade]"]')?.value || '';

        if (!medicamento && !combo && !quantidade) linha.remove();
      });
    };

    // Remove as semanas totalmente vazias (a tela sempre nasce com uma em branco)
    const removerSemanasVazias = () => {
      container.querySelectorAll('[data-semana]').forEach((semana) => {
        // Semana sem aplicacao nao tem itens de proposito: nao remover
        if (campoSemAplicacao(semana)?.checked) return;

        const campoData = semana.querySelector('input[name$="[data_prevista]"]');

        const temItem = Array.from(semana.querySelectorAll('tbody[data-itens-semana] tr')).some((linha) => {
          const medicamento = linha.querySelector('[data-select-medicamento]')?.value || '';
          const combo = linha.querySelector('[data-select-combo]')?.value || '';
          const quantidade = linha.querySelector('input[name$="[quantidade]"]')?.value || '';

          return medicamento || combo || quantidade;
        });

        if (!campoData?.value && !temItem) semana.remove();
      });
    };

    // Quando o intervalo entre duas semanas passa de 7 dias ficam "buracos" na
    // sequencia. Aqui cada data que falta entra como semana de pausa (sem
    // aplicacao), uma a cada 7 dias, entre as duas datas.
    const garantirSequenciaSemanal = () => {
      const datas = [];

      container.querySelectorAll('[data-semana]').forEach((semana) => {
        const data = semana.querySelector('input[name$="[data_prevista]"]')?.value || '';

        if (data) datas.push(data);
      });

      if (datas.length < 2) return;

      datas.sort();

      for (let i = 1; i < datas.length; i++) {
        const anterior = dataDoCampo(datas[i - 1]);
        const proxima = dataDoCampo(datas[i]);
        const dias = Math.round((proxima.getTime() - anterior.getTime()) / 86400000);

        // Intervalo de ate 7 dias: a sequencia semanal ja esta completa
        if (dias <= 7) continue;

        const data = new Date(anterior);

        data.setDate(data.getDate() + 7);

        while (data.getTime() < proxima.getTime()) {
          const dataSemana = dataParaCampoData(data);

          if (!semanaComData(dataSemana)) {
            adicionarSemana({ data_prevista: dataSemana, sem_aplicacao: true });
          }

          data.setDate(data.getDate() + 7);
        }
      }
    };

    const gerarSemanas = () => {
      limparErroGerador();

      const campoData = document.getElementById('gerador-data-inicio');
      const campoNumero = document.getElementById('gerador-numero');
      const campoIntervalo = document.getElementById('gerador-intervalo');

      const dataInicio = campoData?.value || '';
      const numeroSemanas = parseInt(campoNumero?.value || '', 10);
      const intervalo = parseInt(campoIntervalo?.value || '', 10);

      if (!dataInicio || !numeroSemanas || numeroSemanas < 1 || !intervalo || intervalo < 1) {
        mostrarErroGerador('Informe a data da 1ª semana, o número de semanas e o intervalo em dias.');

        return;
      }

      const itens = [];

      corpoGerador.querySelectorAll('tr').forEach((linha) => {
        const item = lerItemGerador(linha);

        if (item.medicamento_id || item.combo_id) {
          itens.push(item);
        }
      });

      if (!itens.length) {
        mostrarErroGerador('Escolha pelo menos um medicamento ou combo para repetir nas semanas.');

        return;
      }

      const data = dataDoCampo(dataInicio);

      removerSemanasVazias();

      for (let i = 0; i < numeroSemanas; i++) {
        const dataSemana = dataParaCampoData(data);
        const semana = semanaComData(dataSemana);

        if (semana) {
          // Se a semana existia como pausa (sem aplicacao), ela passa a ser
          // semana de aplicacao (igual ao gerador antigo, que desmarca a pausa)
          const caixaSemAplicacao = campoSemAplicacao(semana);

          if (caixaSemAplicacao?.checked) {
            caixaSemAplicacao.checked = false;
            aplicarSemAplicacao(semana);
          }

          removerLinhasVazias(semana);
          itens.forEach((item) => adicionarItem(semana, item));
        } else {
          adicionarSemana({ data_prevista: dataSemana, itens: itens });
        }

        data.setDate(data.getDate() + intervalo);
      }

      // Completa os buracos entre as semanas com semanas de pausa
      garantirSequenciaSemanal();

      // Limpa semanas que sobraram sem data, sem aplicacao e sem item
      removerSemanasVazias();

      ordenarSemanas();
      atualizarTotais();

      if (window.bootstrap && modalGeradorEl) {
        bootstrap.Modal.getOrCreateInstance(modalGeradorEl).hide();
      }
    };

    if (corpoGerador) {
      corpoGerador.addEventListener('change', (event) => {
        const linha = event.target.closest('tr');

        if (!linha) return;

        if (event.target.matches('[data-tipo-item]')) {
          aplicarTipo(linha);
          atualizarTotaisGerador();

          return;
        }

        if (event.target.matches('[data-select-medicamento]')) {
          // Medicamento escolhido: quantidade comeca em 1 e valor vem do cadastro
          sugerirQuantidadePadrao(linha.querySelector('[data-gerador-quantidade]'));
          aplicarValorDoItem(linha.querySelector('[data-gerador-valor]'), event.target);
          atualizarTotaisGerador();

          return;
        }

        if (event.target.matches('[data-select-combo]')) {
          aplicarValorDoItem(linha.querySelector('[data-gerador-valor]'), event.target);
          atualizarTotaisGerador();

          return;
        }
      });

      corpoGerador.addEventListener('input', atualizarTotaisGerador);

      corpoGerador.addEventListener('click', (event) => {
        const remover = event.target.closest('[data-remover-item]');

        if (!remover) return;

        const linha = remover.closest('tr');

        if (linha) linha.remove();

        if (!corpoGerador.children.length) adicionarItemGerador('medicamento');

        atualizarTotaisGerador();
      });
    }

    document.querySelectorAll('[data-gerador-adicionar]').forEach((botao) => {
      botao.addEventListener('click', () => {
        limparErroGerador();
        adicionarItemGerador(botao.dataset.geradorAdicionar);
      });
    });

    if (modalGeradorEl) {
      // A primeira linha nasce com o modal visível (o Select2 mede a largura corretamente)
      modalGeradorEl.addEventListener('shown.bs.modal', () => {
        if (corpoGerador && !corpoGerador.children.length) {
          adicionarItemGerador('medicamento');
        }
      });

      modalGeradorEl.addEventListener('hidden.bs.modal', limparErroGerador);
    }

    if (botaoAbrirGerador && modalGeradorEl && window.bootstrap) {
      botaoAbrirGerador.addEventListener('click', () => {
        // O gerador sempre abre zerado (data, nº de semanas, intervalo = 7 e uma linha só)
        reiniciarGerador();
        bootstrap.Modal.getOrCreateInstance(modalGeradorEl).show();
      });
    }

    if (botaoGerar) {
      botaoGerar.addEventListener('click', gerarSemanas);
    }

    // Anexo obrigatorio: bloqueia o envio se a regra pedir e nada foi anexado
    const formularioPrescricao = document.getElementById('form-prescricao');

    if (formularioPrescricao) {
      formularioPrescricao.addEventListener('submit', (event) => {
        if (!precisaAnexo()) return;

        const campoAnexos = document.getElementById('anexos');
        const temArquivo = campoAnexos && campoAnexos.files && campoAnexos.files.length > 0;

        if (!temArquivo) {
          event.preventDefault();
          alert('Anexe a prescrição médica: há medicamento do tipo ampola ou miligrama com aplicação.');

          if (campoAnexos) campoAnexos.focus();
        }
      });
    }

    atualizarTotaisGerador();
    atualizarTotais();
  });
</script>
