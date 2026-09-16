{{-- Scripts compartilhados dos cadastros: máscaras + confirmação de exclusão --}}
<script>
  function aplicarMascara(el, posicoes, separadores) {
    if (!el) return;

    const totalDigitos = posicoes.reduce((total, posicao) => total + posicao, 0);

    const formatar = (valor) => {
      const digitos = valor.replace(/\D/g, '').slice(0, totalDigitos);
      let resultado = '';
      let posicao = 0;

      posicoes.forEach((quantidade, indice) => {
        const bloco = digitos.slice(posicao, posicao + quantidade);

        if (!bloco) return;

        if (indice > 0) {
          resultado += separadores[indice - 1] ?? '';
        }

        resultado += bloco;
        posicao += quantidade;
      });

      return resultado;
    };

    const aplicar = () => {
      el.value = formatar(el.value);
    };

    aplicar();
    el.addEventListener('input', aplicar);
  }

  // Formata como moeda brasileira (1.234,56)
  function formatarMoeda(valor) {
    const digitos = String(valor).replace(/\D/g, '').slice(0, 11);

    if (!digitos) return '';

    const centavos = digitos.slice(-2).padStart(2, '0');
    const inteiro = digitos.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';

    return inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + centavos;
  }

  // Mantém dígitos com vírgula decimal (ex.: 10,50)
  function formatarQuantidade(valor) {
    return String(valor).replace(/[^\d,.]/g, '').replace(/\./g, ',');
  }

  // Aplica as máscaras de moeda/quantidade dentro de um escopo.
  // Use após inserir linhas novas via JS: inicializarMascaras(linha)
  function inicializarMascaras(raiz) {
    const escopo = raiz || document;

    escopo.querySelectorAll('[data-moeda]').forEach(function (el) {
      if (el.dataset.mascaraAplicada) return;

      el.dataset.mascaraAplicada = '1';

      const aplicar = () => {
        el.value = formatarMoeda(el.value);
      };

      aplicar();
      el.addEventListener('input', aplicar);
    });

    escopo.querySelectorAll('[data-quantidade]').forEach(function (el) {
      if (el.dataset.mascaraAplicada) return;

      el.dataset.mascaraAplicada = '1';

      const aplicar = () => {
        el.value = formatarQuantidade(el.value);
      };

      aplicar();
      el.addEventListener('input', aplicar);
    });
  }

  window.inicializarMascaras = inicializarMascaras;

  document.addEventListener('DOMContentLoaded', function () {
    // Máscaras de CNPJ / telefone / celular
    aplicarMascara(document.getElementById('cnpj'), [2, 3, 3, 4, 2], ['.', '.', '/', '-']);
    aplicarMascara(document.getElementById('telefone'), [0, 2, 4, 4], ['(', ') ', '-']);
    aplicarMascara(document.getElementById('celular'), [0, 2, 5, 4], ['(', ') ', '-']);

    // Máscaras de moeda / quantidade (inclui campos de linhas dinâmicas já existentes)
    inicializarMascaras(document);

    // Campos exibidos apenas para um tipo específico:
    // <div data-mostrar-se-tipo="miligrama"> (compara com o valor do select #tipo)
    const selectTipo = document.getElementById('tipo');

    if (selectTipo) {
      const campos = document.querySelectorAll('[data-mostrar-se-tipo]');

      const atualizar = () => {
        campos.forEach((campo) => {
          campo.classList.toggle('d-none', campo.dataset.mostrarSeTipo !== selectTipo.value);
        });
      };

      atualizar();
      selectTipo.addEventListener('change', atualizar);
    }
  });

  // Confirmação antes de excluir (basta adicionar data-confirmar="mensagem" no form)
  document.addEventListener('submit', function (event) {
    const form = event.target;
    const mensagem = form.dataset?.confirmar;

    if (mensagem && !confirm(mensagem)) {
      event.preventDefault();
    }
  });
</script>
