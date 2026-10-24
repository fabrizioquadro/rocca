@php
    /** @var \App\Support\EtiquetaLayout $layout */
    $porPagina = $layout->colunas;
    $paginas = array_chunk($etiquetas, $porPagina);
    $exibidas = count($etiquetas);
    $total = $total ?? $exibidas;
    $teste = $teste ?? false;
    $simbolos = collect($etiquetas)->filter(fn ($etiqueta) => $etiqueta['id'] !== '')->unique('id')->values();
    $avisos = $layout->avisos();
    $sobra = $exibidas % $porPagina;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>
    Etiquetas{{ $entrada ? ' — Entrada #'.$entrada->id : '' }}{{ $teste ? ' — Teste de alinhamento' : '' }}
  </title>
  <style>
    /* ============================================================
       A página de impressão É a bobina: 100 mm de largura por 17 mm
       de altura (15 mm da etiqueta + 2 mm do vão entre as linhas).

       Não existe "landscape" aqui de propósito. O CSS `size` recebe a
       medida exata do papel e o navegador entrega ao driver uma página
       deitada por natureza — por isso ninguém precisa escolher
       orientação no diálogo de impressão.

       Cada linha de etiquetas é uma página, então o avanço da
       impressora é sempre de um passo (17 mm): nenhum desalinhamento
       acumula de uma linha para a outra.
       ============================================================ */
    @page {
      size: {{ $layout->mm($layout->larguraPagina()) }} {{ $layout->mm($layout->alturaPagina()) }};
      margin: 0;
    }

    :root {
      --largura-pagina: {{ $layout->mm($layout->larguraPagina()) }};
      --altura-pagina: {{ $layout->mm($layout->alturaPagina()) }};
      --largura-etiqueta: {{ $layout->mm($layout->larguraEtiqueta) }};
      --altura-etiqueta: {{ $layout->mm($layout->alturaEtiqueta) }};

      /* Ajuste fino (mm): positivo move para a direita/baixo. Corrige a
         área não imprimível do driver quando o conteúdo não cai no centro
         da etiqueta. O valor calibrado fica salvo neste computador. */
      --ajuste-x: {{ $layout->mm($layout->ajusteX) }};
      --ajuste-y: {{ $layout->mm($layout->ajusteY) }};
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      background: #f1f1f4;
      color: #000;
      font-family: Arial, Helvetica, sans-serif;
    }

    /* ---- Uma página = uma linha de etiquetas ---- */

    .pagina {
      position: relative;
      width: var(--largura-pagina);

      /* 0,3 mm a menos que o papel: garante que um arredondamento de
         subpixel não empurre a página inteira para a seguinte (o que
         sairia como etiqueta em branco entre as linhas). O conteúdo fica
         no topo, então essa diferença não aparece na impressão. */
      height: calc(var(--altura-pagina) - 0.3mm);
      overflow: hidden;
      background: #fff;
      break-inside: avoid;
      page-break-inside: avoid;
      break-after: page;
      page-break-after: always;
    }

    .pagina:last-child {
      break-after: auto;
      page-break-after: auto;
    }

    .etiqueta {
      position: absolute;
      top: var(--ajuste-y);
      width: var(--largura-etiqueta);
      height: var(--altura-etiqueta);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 0.6mm;
      overflow: hidden;
      text-align: center;
    }

    .etiqueta svg.barras {
      display: block;
      width: 100%;
      height: 8mm;
    }

    .etiqueta .numero {
      margin-top: 0.2mm;
      font-size: 3mm;
      font-weight: 700;
      line-height: 1;
      letter-spacing: 0.1mm;
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }

    /* Teste de alinhamento: a borda mostra se a área útil cai dentro da
       etiqueta física. */
    .etiqueta.etiqueta-teste {
      outline: 0.25mm solid #000;
    }

    .etiqueta.etiqueta-teste .numero {
      font-size: 4mm;
    }

    /* Definições dos códigos de barras: cada código aparece uma única vez
       no HTML e as etiquetas repetidas o reaproveitam. */
    .simbolos {
      position: absolute;
      width: 0;
      height: 0;
      overflow: hidden;
    }

    /* ---- Barra de ações (não sai na impressão) ---- */

    .barra {
      max-width: 210mm;
      margin: 0 auto 6mm;
      padding: 5mm;
      background: #fff;
      border-bottom: 1px solid #e5e5e5;
    }

    .barra h1 {
      margin: 0 0 4px;
      font-size: 18px;
    }

    .barra .detalhe {
      margin-bottom: 12px;
      color: #555;
      font-size: 13px;
    }

    .barra .acoes {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 12px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #fff;
      color: #333;
      font-size: 14px;
      text-decoration: none;
      cursor: pointer;
    }

    .btn-principal {
      border-color: #696cff;
      background: #696cff;
      color: #fff;
      font-weight: 600;
    }

    .campo {
      display: inline-flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 12px;
      color: #333;
      font-size: 13px;
    }

    .campo input {
      width: 80px;
      padding: 7px 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    .campo.ajuste input {
      width: 70px;
    }

    .aviso {
      margin-bottom: 12px;
      padding: 8px 12px;
      border: 1px solid #ffe08a;
      border-radius: 6px;
      background: #fff8e1;
      color: #7a5c00;
      font-size: 13px;
    }

    .dicas {
      margin-top: 4px;
      color: #555;
      font-size: 12.5px;
      line-height: 1.65;
    }

    .dicas summary {
      cursor: pointer;
      font-weight: 600;
      color: #333;
    }

    .dicas ol {
      margin: 8px 0 0;
      padding-left: 18px;
    }

    .dicas code {
      padding: 0 3px;
      border-radius: 3px;
      background: #f1f1f4;
    }

    @media screen {
      body {
        overflow-x: auto;
        padding-bottom: 10mm;
      }

      .folha {
        padding: 4mm 0;
      }

      .pagina {
        margin: 0 auto 3mm;
        outline: 0.2mm dashed #c9c9c9;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.12);
      }

      .etiqueta {
        outline: 0.2mm dotted #dedede;
      }
    }

    @media print {
      html,
      body {
        width: var(--largura-pagina);
        background: #fff;
      }

      .no-print {
        display: none !important;
      }

      .folha {
        padding: 0;
      }

      .pagina {
        margin: 0;
        outline: 0;
        box-shadow: none;
      }

      .etiqueta {
        outline: 0;
      }

      /* Mantém o preto do código de barras mesmo com "modo econômico"
         ligado no navegador. */
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
    }
  </style>
</head>
<body>
  <svg class="simbolos" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      @foreach ($simbolos as $simbolo)
        <symbol id="{{ $simbolo['id'] }}" viewBox="{{ $simbolo['viewBox'] }}">{!! $simbolo['conteudo'] !!}</symbol>
      @endforeach
    </defs>
  </svg>

  <div class="barra no-print">
    <h1>
      @if ($teste)
        Teste de alinhamento das etiquetas
      @elseif ($item)
        Etiquetas — {{ $item->medicamento?->nome ?? 'Medicamento' }}
      @else
        Etiquetas da entrada #{{ $entrada->id }}
      @endif
    </h1>

    <div class="detalhe">
      @if ($teste)
        {{ $exibidas }} etiqueta(s) numeradas — cada uma mostra a linha e a coluna.
        Confira se a borda de cada etiqueta cai dentro da etiqueta física e se o
        número da última linha continua no lugar.
      @elseif ($item)
        {{ $item->medicamento?->nome ?? 'Medicamento' }}
        · lote {{ $item->lote ?: 's/n' }}
        · {{ $exibidas }} etiqueta(s)
        @if ($exibidas !== (int) $item->quantidade)
          (o item tem {{ $item->quantidade }} unidade(s))
        @endif
      @else
        {{ $entrada->clinica?->nome ?? 'Sem clínica' }}
        · {{ $entrada->itens->count() }} medicamento(s)
        · {{ $exibidas }} etiqueta(s) de {{ $total }}
      @endif
    </div>

    @foreach ($avisos as $aviso)
      <div class="aviso">{{ $aviso }}</div>
    @endforeach

    @if (! $teste && $total > $exibidas)
      <div class="aviso">
        Esta entrada tem {{ $total }} etiquetas e foram montadas apenas as {{ $exibidas }} primeiras.
        Para imprimir todas, use o ícone da impressora em cada medicamento.
      </div>
    @endif

    @if (! $teste && $sobra !== 0)
      <div class="aviso">
        A bobina tem {{ $porPagina }} etiquetas por linha: sobram {{ $porPagina - $sobra }}
        etiqueta(s) em branco no fim.
      </div>
    @endif

    <div class="acoes">
      <button type="button" class="btn btn-principal" onclick="window.print()">
        <i class="ri-printer-line"></i> Imprimir
      </button>

      <a href="{{ route('estoque.etiquetas.teste') }}" class="btn">
        <i class="ri-ruler-2-line"></i> {{ $teste ? 'Recarregar teste' : 'Teste de alinhamento' }}
      </a>

      @if ($entrada)
        <a href="{{ route('estoque.entradas.show', $entrada) }}" class="btn">
          <i class="ri-arrow-left-line"></i> Voltar para a entrada
        </a>
      @endif
    </div>

    @if (! $teste && $item)
      <form method="GET" class="campo" action="{{ route('estoque.entradas.itens.etiquetas', [$entrada, $item]) }}">
        <label for="quantidade">Reimprimir</label>
        <input
          type="number"
          id="quantidade"
          name="quantidade"
          min="1"
          max="{{ \App\Services\EtiquetaService::LIMITE }}"
          value="{{ $exibidas }}" />
        <span>etiqueta(s)</span>
        <button type="submit" class="btn">Aplicar</button>
      </form>
    @endif

    <div class="campo ajuste">
      <label for="ajuste-x">Ajuste fino da impressão</label>
      <input type="number" id="ajuste-x" step="0.5" min="-5" max="5" value="{{ (float) $layout->ajusteX }}" />
      <span>mm na horizontal</span>
      <input type="number" id="ajuste-y" step="0.5" min="-5" max="5" value="{{ (float) $layout->ajusteY }}" />
      <span>mm na vertical</span>
      <button type="button" class="btn" id="ajuste-zerar">Zerar</button>
    </div>

    <details class="dicas">
      <summary>Como configurar a impressora (fazer uma vez, depois é só imprimir)</summary>

      <ol>
        <li>
          Impressora: térmica direta de <strong>4 polegadas (100 mm)</strong>, <strong>203 dpi</strong>
          (8 pontos por mm), mídia <strong>picotada com sensor de vão (gap)</strong>.
        </li>
        <li>
          <strong>No driver</strong> (Preferências de impressão → Papel/Tamanho):
          use um papel personalizado de <strong>{{ $layout->mm($layout->larguraPagina()) }} de largura
          por {{ $layout->mm($layout->alturaPagina()) }} de comprimento</strong> e deixe-o como
          <strong>papel padrão</strong> da impressora. Largura e comprimento invertidos = conteúdo girado 90°.
        </li>
        <li>
          Tipo de mídia: <strong>etiqueta com vão (gap)</strong> e sensor de vão ligado —
          nunca "contínuo" nem "marca preta". Se a impressora estiver em contínuo, ela avança
          sem procurar o vão e o desenho escorrega a cada linha.
        </li>
        <li>
          <strong>Calibre a bobina</strong> ao trocar o rolo: com a tampa fechada, segure o botão
          <strong>FEED</strong> até ela piscar e soltar um trecho de etiquetas.
        </li>
        <li>
          No navegador (use <strong>Chrome ou Edge</strong>), <code>Ctrl+P</code>:
          <br />• Tamanho do papel: <strong>{{ $layout->mm($layout->larguraPagina()) }} ×
          {{ $layout->mm($layout->alturaPagina()) }}</strong> (o CSS já pede esse tamanho; confirme que
          apareceu "Personalizado" e não A4/Letter);
          <br />• <strong>Não há orientação para escolher</strong> — a página já é deitada;
          <br />• Escala: <strong>100% / Padrão</strong> (nunca "Ajustar à área de impressão");
          <br />• Margens: <strong>Nenhuma</strong>; desmarque "Cabeçalhos e rodapés";
          <br />• Se o navegador lembrar uma configuração antiga deste papel, corrija uma vez e
          ele passa a reutilizar.
        </li>
        <li>
          <strong>Confira antes de gastar bobina:</strong> use o
          <a href="{{ route('estoque.etiquetas.teste') }}">teste de alinhamento</a>
          (10 linhas = 30 etiquetas). Se a borda da etiqueta impressa crescer para fora da etiqueta
          física linha após linha, o comprimento do papel no driver está maior que
          {{ $layout->mm($layout->alturaPagina()) }}.
        </li>
        <li>
          Se o conteúdo sair deslocado dentro da etiqueta, corrija no <strong>Ajuste fino</strong>
          acima (fica salvo neste computador): horizontal move para a direita, vertical para baixo;
          valores negativos movem para a esquerda/cima.
        </li>
      </ol>
    </details>
  </div>

  <div class="folha">
    @forelse ($paginas as $pagina)
      <div class="pagina">
        @foreach ($pagina as $indice => $etiqueta)
          <div
            class="etiqueta{{ $teste ? ' etiqueta-teste' : '' }}"
            style="left: calc({{ $layout->mm($layout->posicaoColuna($indice)) }} + var(--ajuste-x));">
            @if ($etiqueta['id'] !== '')
              <svg
                class="barras"
                viewBox="{{ $etiqueta['viewBox'] }}"
                preserveAspectRatio="none"
                role="img"
                aria-label="{{ $etiqueta['codigo'] }}">
                <use href="#{{ $etiqueta['id'] }}" xlink:href="#{{ $etiqueta['id'] }}"></use>
              </svg>
            @endif

            <span class="numero">{{ $etiqueta['codigo'] }}</span>
          </div>
        @endforeach
      </div>
    @empty
      <p class="no-print" style="padding: 6mm;">Nenhum código de barras para imprimir.</p>
    @endforelse
  </div>

  <script>
    (function () {
      var CHAVE = 'estoque.etiquetas.ajuste';
      var raiz = document.documentElement;
      var campoX = document.getElementById('ajuste-x');
      var campoY = document.getElementById('ajuste-y');

      function ler(campo) {
        var valor = parseFloat(String(campo.value).replace(',', '.'));
        if (isNaN(valor)) {
          return 0;
        }
        return Math.max(-5, Math.min(5, valor));
      }

      function aplicar(x, y) {
        raiz.style.setProperty('--ajuste-x', x + 'mm');
        raiz.style.setProperty('--ajuste-y', y + 'mm');
        campoX.value = x;
        campoY.value = y;
        try {
          localStorage.setItem(CHAVE, JSON.stringify({ x: x, y: y }));
        } catch (e) {}
      }

      var salvo = null;
      try {
        salvo = JSON.parse(localStorage.getItem(CHAVE) || 'null');
      } catch (e) {}

      if (salvo) {
        aplicar(Number(salvo.x) || 0, Number(salvo.y) || 0);
      }

      [campoX, campoY].forEach(function (campo) {
        campo.addEventListener('change', function () {
          aplicar(ler(campoX), ler(campoY));
        });
      });

      document.getElementById('ajuste-zerar').addEventListener('click', function () {
        aplicar(0, 0);
      });
    })();
  </script>
</body>
</html>
