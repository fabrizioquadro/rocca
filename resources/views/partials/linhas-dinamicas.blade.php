{{-- Repetidor de linhas (tabelas dinâmicas de itens).
     Uso:
       <tbody id="itens-x" data-repeater data-iniciais="itensX" data-placeholder="..."></tbody>
       <template id="modelo-item">linha contendo nomes como itens[__INDICE__][campo]</template>
       <button id="adicionar-item">...</button>
       <script>window.itensX = @json($itensIniciais);</script>
     Opcional: o container recebe o evento "repeater:atualizado" a cada inclusão/remoção de linha. --}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-repeater]').forEach(function (corpo) {
      const modelo = document.getElementById(corpo.dataset.modelo || 'modelo-item');
      const botaoAdicionar = document.getElementById(corpo.dataset.botao || 'adicionar-item');

      if (!modelo) return;

      const iniciais = window[corpo.dataset.iniciais] || corpo.dataset.iniciais || [];
      let indice = parseInt(corpo.dataset.proximoIndice || '0', 10);

      const notificar = () => {
        corpo.dispatchEvent(new CustomEvent('repeater:atualizado', { bubbles: true }));
      };

      const adicionarLinha = (item) => {
        const linha = modelo.content.firstElementChild.cloneNode(true);

        linha.innerHTML = linha.innerHTML.replace(/__INDICE__/g, indice++);

        corpo.appendChild(linha);

        // Preenche os campos informados (nome do campo entre colchetes)
        if (item) {
          Object.keys(item).forEach(function (campo) {
            const input = linha.querySelector('[name$="[' + campo + ']"]');

            if (input) {
              input.value = item[campo] ?? '';
            }
          });
        }

        // Select2 (busca digitando) nos selects da linha
        if (window.jQuery && jQuery.fn.select2) {
          jQuery(linha).find('select').select2({
            width: '100%',
            placeholder: corpo.dataset.placeholder || 'Digite para buscar...',
            allowClear: true,
            language: {
              noResults: () => 'Nenhum registro encontrado',
              searching: () => 'Buscando...'
            }
          });
        }

        // Máscaras (moeda/quantidade) nos campos da linha
        if (typeof window.inicializarMascaras === 'function') {
          window.inicializarMascaras(linha);
        }

        notificar();
      };

      // Linhas já informadas (edição ou reaproveitamento após erro de validação)
      (Array.isArray(iniciais) ? iniciais : []).forEach((item) => adicionarLinha(item));

      if (!corpo.children.length) {
        adicionarLinha();
      }

      if (botaoAdicionar) {
        botaoAdicionar.addEventListener('click', () => adicionarLinha());
      }

      corpo.addEventListener('click', function (event) {
        const botao = event.target.closest('[data-remover-item]');

        if (!botao) return;

        const linha = botao.closest('tr');

        if (linha) {
          linha.remove();
        }

        // Sempre mantém pelo menos uma linha
        if (!corpo.children.length) {
          adicionarLinha();
        }

        notificar();
      });

      // Botão de gerar código de barras da linha
      // (o container precisa ter data-url-codigo com a rota do gerador)
      corpo.addEventListener('click', function (event) {
        const botao = event.target.closest('[data-gerar-codigo]');

        if (!botao || !corpo.dataset.urlCodigo) return;

        const linha = botao.closest('tr');
        const select = linha?.querySelector('select');
        const campo = linha?.querySelector('input[name$="[codigo_barras]"]');
        const medicamentoId = select?.value;

        if (!medicamentoId) {
          alert('É necessário escolher o medicamento.');
          return;
        }

        botao.disabled = true;

        fetch(corpo.dataset.urlCodigo + '?medicamento_id=' + encodeURIComponent(medicamentoId), {
          headers: { Accept: 'application/json' }
        })
          .then((resposta) => resposta.json())
          .then((json) => {
            if (campo && json.codigo) {
              campo.value = json.codigo;
            }
          })
          .catch(() => alert('Não foi possível gerar o código de barras.'))
          .finally(() => {
            botao.disabled = false;
          });
      });

      // Ao sair do campo de lote ou de código de barras, confere no banco se aquele
      // código já foi lançado com outro lote (ou outro medicamento).
      corpo.addEventListener('focusout', function (event) {
        const campo = event.target;

        if (!corpo.dataset.urlVerificarCodigo || !campo.matches) return;

        if (!campo.matches('input[name$="[codigo_barras]"], input[name$="[lote]"]')) return;

        const linha = campo.closest('tr');
        const campoCodigo = linha?.querySelector('input[name$="[codigo_barras]"]');
        const campoLote = linha?.querySelector('input[name$="[lote]"]');
        const select = linha?.querySelector('select');
        const aviso = linha?.querySelector('[data-erro-codigo]');

        const limparAviso = () => {
          if (aviso) {
            aviso.textContent = '';
            aviso.classList.add('d-none');
          }

          campoCodigo?.classList.remove('is-invalid');
        };

        if (!campoCodigo?.value) {
          limparAviso();
          return;
        }

        const parametros = new URLSearchParams({
          codigo_barras: campoCodigo.value,
          lote: campoLote?.value || '',
          medicamento_id: select?.value || ''
        });

        fetch(corpo.dataset.urlVerificarCodigo + '?' + parametros.toString(), {
          headers: { Accept: 'application/json' }
        })
          .then((resposta) => resposta.json())
          .then((json) => {
            limparAviso();

            if (!json.ok && aviso) {
              aviso.textContent = json.mensagem;
              aviso.classList.remove('d-none');
              campoCodigo.classList.add('is-invalid');
            }
          })
          .catch(() => {});
      });

      // Busca do código de barras (baixa / transferência): preenche as colunas de
      // medicamento, lote, vencimento e quantidade disponível na própria linha.
      const colunasInfo = [
        '[data-info-medicamento]',
        '[data-info-lote]',
        '[data-info-vencimento]',
        '[data-info-saldo]'
      ];

      const preencherColunas = (linha, json) => {
        const valores = {
          '[data-info-medicamento]': json?.medicamento || '—',
          '[data-info-lote]': json?.lote || '—',
          '[data-info-vencimento]': json?.vencimento || '—',
          '[data-info-saldo]': json?.saldo ?? '—'
        };

        colunasInfo.forEach(function (seletor) {
          const celula = linha?.querySelector(seletor);

          if (celula) {
            celula.textContent = valores[seletor];
          }
        });
      };

      const buscarCodigoBarras = (campo) => {
        if (!corpo.dataset.urlBuscarCodigo) return;

        const linha = campo.closest('tr');
        const aviso = linha?.querySelector('[data-erro-codigo]');
        const codigo = (campo.value || '').trim();

        const limpar = () => {
          preencherColunas(linha);

          if (aviso) {
            aviso.textContent = '';
            aviso.classList.add('d-none');
          }

          campo.classList.remove('is-invalid');
        };

        if (!codigo) {
          limpar();
          return;
        }

        const clinica = document.querySelector(corpo.dataset.clinica || '#clinica_id');

        if (!clinica || !clinica.value) {
          limpar();

          if (aviso) {
            aviso.textContent = 'Selecione a clínica primeiro.';
            aviso.classList.remove('d-none');
          }

          return;
        }

        const parametros = new URLSearchParams({
          codigo_barras: codigo,
          clinica_id: clinica.value
        });

        fetch(corpo.dataset.urlBuscarCodigo + '?' + parametros.toString(), {
          headers: { Accept: 'application/json' }
        })
          .then((resposta) => resposta.json())
          .then((json) => {
            limpar();

            if (!json.ok) {
              if (aviso) {
                aviso.textContent = json.mensagem;
                aviso.classList.remove('d-none');
                campo.classList.add('is-invalid');
              }

              return;
            }

            preencherColunas(linha, json);

            const campoQuantidade = linha?.querySelector('input[name$="[quantidade]"]');

            if (campoQuantidade) {
              campoQuantidade.max = json.saldo;

              if (!campoQuantidade.value) {
                campoQuantidade.focus();
              }
            }
          })
          .catch(() => {});
      };

      // Leitor de código de barras: o Enter não pode enviar o formulário
      corpo.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.matches) return;

        if (!event.target.matches('[data-buscar-codigo]')) return;

        event.preventDefault();
        buscarCodigoBarras(event.target);
      });

      // Também busca ao sair do campo e enquanto digita (com uma pequena pausa)
      let temporizadorBusca = null;

      corpo.addEventListener('focusout', function (event) {
        if (!event.target.matches || !event.target.matches('[data-buscar-codigo]')) return;

        buscarCodigoBarras(event.target);
      });

      corpo.addEventListener('input', function (event) {
        if (!event.target.matches || !event.target.matches('[data-buscar-codigo]')) return;

        clearTimeout(temporizadorBusca);

        if ((event.target.value || '').trim().length < 3) return;

        temporizadorBusca = setTimeout(() => buscarCodigoBarras(event.target), 400);
      });

      // Ao trocar a clínica, refaz a busca dos códigos já informados
      const seletorClinica = corpo.dataset.clinica || '#clinica_id';
      const campoClinica = document.querySelector(seletorClinica);

      if (campoClinica && corpo.dataset.urlBuscarCodigo) {
        campoClinica.addEventListener('change', function () {
          corpo.querySelectorAll('[data-buscar-codigo]').forEach(function (campo) {
            if ((campo.value || '').trim()) {
              campo.dispatchEvent(new Event('focusout', { bubbles: true }));
            }
          });
        });
      }
    });
  });
</script>
