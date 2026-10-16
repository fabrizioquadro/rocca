{{-- "Adicionar mais arquivos" do campo de anexos (ver prescricoes/_campo-anexos) --}}
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('[data-anexos]');
    const modelo = document.querySelector('[data-anexo-template]');

    if (!container || !modelo) return;

    const botaoAdicionar = document.querySelector('[data-anexo-adicionar]');
    const resumo = document.querySelector('[data-anexo-escolhidos]');

    const linhas = () => Array.from(container.querySelectorAll('[data-anexo-linha]'));
    const arquivosDoCampo = (campo) => (campo && campo.files ? Array.from(campo.files) : []);

    const formatarTamanho = (bytes) => {
      if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
      if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';

      return bytes + ' B';
    };

    // Mostra o que foi escolhido somando todos os campos e só libera o
    // "remover" quando existe mais de um campo na tela
    const atualizar = () => {
      const todas = linhas();
      const arquivos = todas.flatMap((linha) => arquivosDoCampo(linha.querySelector('[data-anexo-campo]')));

      todas.forEach((linha) => {
        linha.querySelector('[data-anexo-remover]').classList.toggle('d-none', todas.length < 2);
      });

      if (!resumo) return;

      resumo.classList.toggle('d-none', arquivos.length === 0);
      resumo.innerHTML = '';

      arquivos.forEach((arquivo) => {
        const item = document.createElement('li');
        item.className = 'd-flex align-items-center gap-2';

        const nome = document.createElement('span');
        nome.textContent = arquivo.name;

        const tamanho = document.createElement('span');
        tamanho.textContent = formatarTamanho(arquivo.size);

        item.append(nome, tamanho);
        resumo.appendChild(item);
      });

      if (arquivos.length > 1) {
        const total = document.createElement('li');
        total.className = 'fw-semibold';
        total.textContent = arquivos.length + ' arquivos · ' +
          formatarTamanho(arquivos.reduce((soma, arquivo) => soma + arquivo.size, 0));

        resumo.appendChild(total);
      }
    };

    if (botaoAdicionar) {
      botaoAdicionar.addEventListener('click', () => {
        container.appendChild(modelo.content.cloneNode(true));
        atualizar();

        // Abre o seletor já no campo novo (o navegador só permite dentro do clique)
        const campos = container.querySelectorAll('[data-anexo-campo]');
        campos[campos.length - 1].click();
      });
    }

    container.addEventListener('change', (event) => {
      if (event.target.matches('[data-anexo-campo]')) atualizar();
    });

    container.addEventListener('click', (event) => {
      const remover = event.target.closest('[data-anexo-remover]');

      // O último campo não sai: sem campo nenhum não haveria onde anexar
      if (!remover || linhas().length < 2) return;

      remover.closest('[data-anexo-linha]').remove();
      atualizar();
    });

    atualizar();
  });
</script>
