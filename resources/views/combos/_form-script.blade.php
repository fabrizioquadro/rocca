<script>
  // Soma os valores dos itens do combo (o comportamento das linhas fica em partials/linhas-dinamicas)
  document.addEventListener('DOMContentLoaded', function () {
    const corpo = document.getElementById('itens-combo');
    const totalEl = document.getElementById('total-combo');

    if (!corpo) return;

    const valorParaNumero = (texto) => {
      const digitos = String(texto).replace(/\D/g, '');

      return digitos ? parseInt(digitos, 10) / 100 : 0;
    };

    const formatarTotal = (numero) => {
      const partes = numero.toFixed(2).split('.');

      return 'R$ ' + partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + partes[1];
    };

    const atualizarTotal = () => {
      let total = 0;

      corpo.querySelectorAll('input[name$="[valor]"]').forEach((el) => {
        total += valorParaNumero(el.value);
      });

      if (totalEl) {
        totalEl.textContent = formatarTotal(total);
      }
    };

    corpo.addEventListener('input', atualizarTotal);
    corpo.addEventListener('repeater:atualizado', atualizarTotal);

    atualizarTotal();
  });
</script>
