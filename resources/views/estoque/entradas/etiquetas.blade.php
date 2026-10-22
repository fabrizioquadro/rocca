<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Etiquetas — Entrada #{{ $entrada->id }}</title>
  <style>
    /* ============================================================
       Bobina: 100mm de largura, 3 etiquetas de 30x15mm por linha.
       Espaçamento: 3mm entre as colunas, 2mm entre as linhas e
       2mm de margem em cada lateral (30+3+30+3+30 = 96 + 2 + 2 = 100).

       O PASSO da bobina (altura da etiqueta + espaço entre as linhas =
       15 + 2 = 17mm) é o que a impressora avança por linha: uma linha de
       etiquetas por página. Assim cada linha começa no topo da sua
       etiqueta e o desalinhamento NÃO acumula de uma linha para a outra.

       A orientação é forçada em PAISAGEM no @page (abaixo): o diálogo de
       impressão já abre em paisagem, sem o usuário precisar trocar. O CSS
       não aceita combinar `size: <medidas>` com `landscape` (o navegador
       descarta a regra), então as medidas de cada página vêm do papel
       cadastrado no driver — mantenha a bobina de 100mm x 17mm como papel
       padrão da impressora.
       ============================================================ */
    :root {
      --bobina-largura: 100mm;
      --bobina-margem-lateral: 2mm;
      --etiqueta-largura: 30mm;
      --etiqueta-altura: 15mm;
      --etiqueta-espaco-coluna: 3mm;
      --etiqueta-espaco-linha: 2mm;
      --etiqueta-por-linha: 3;

      /* Ajuste fino da impressão, em mm: positivo desloca para a
         direita/baixo. Compensa a área não imprimível do driver quando o
         conteúdo não cai no centro da etiqueta. O padrão (1mm para a
         direita) corrige a margem não imprimível à esquerda da bobina;
         o valor pode ser calibrado na barra acima e fica salvo no
         computador da impressão. */
      --ajuste-x: 1mm;
      --ajuste-y: 0mm;
    }

    /* Paisagem fixa: o diálogo de impressão do navegador já abre assim.
       As medidas da página são as do papel padrão do driver (bobina de
       100mm de largura por 17mm de comprimento) — o CSS não permite
       `size: <medidas> landscape` (o navegador descarta a regra inteira).
       Se um dia o driver passar a respeitar medidas personalizadas e a
       orientação ficar sobrando, basta trocar por `size: 100mm 17mm;`. */
    @page {
      size: landscape;
      margin: 0;
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      margin: 0;
      padding: 0;
      color: #000;
      background: #fff;
      font-family: Arial, Helvetica, sans-serif;
    }

    /* ---- Etiquetas ---- */

    .folha {
      width: var(--bobina-largura);
      margin: 0 auto;
      padding: 0 var(--bobina-margem-lateral);
    }

    /* Alinhado à esquerda de propósito: numa linha incompleta (última
       linha) as etiquetas continuam nas colunas 1, 2, ... da bobina; se
       fossem centralizadas, cairiam sobre o vão entre as etiquetas. */
    .linha {
      position: relative;
      left: var(--ajuste-x);
      top: var(--ajuste-y);
      display: flex;
      align-items: flex-start;
      gap: var(--etiqueta-espaco-coluna);
      break-inside: avoid;
      page-break-inside: avoid;
    }

    /* Cada linha ocupa uma página: a impressora avança uma etiqueta por página,
       então nenhum desalinhamento se acumula de uma linha para a outra — nem
       depende do arredondamento de milímetros que o navegador faz. */
    .linha:not(:last-child) {
      break-after: page;
      page-break-after: always;
    }

    .etiqueta {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: var(--etiqueta-largura);
      height: var(--etiqueta-altura);
      padding: 0.8mm;
      margin: 0;
      overflow: hidden;
      background: #fff;
      text-align: center;
    }

    .etiqueta svg.barras {
      display: block;
      width: 100%;
      height: 8mm;
    }

    /* Definicoes dos codigos de barras: o SVG de cada codigo aparece uma unica
       vez e as etiquetas o reaproveitam (senao uma entrada de 100 unidades
       repetiria o mesmo desenho 100 vezes no HTML). */
    .simbolos {
      position: absolute;
      width: 0;
      height: 0;
      overflow: hidden;
    }

    .etiqueta .numero {
      font-size: 3mm;
      font-weight: 700;
      line-height: 1;
      letter-spacing: 0.1mm;
      margin-top: 0.3mm;
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
      font-size: 18px;
      margin: 0 0 4px;
    }

    .barra .detalhe {
      color: #555;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .barra .acoes {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 12px;
    }

    .barra .dicas {
      color: #666;
      font-size: 12px;
      line-height: 1.7;
      border-left: 3px solid #ddd;
      padding-left: 10px;
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
      background: #696cff;
      border-color: #696cff;
      color: #fff;
      font-weight: 600;
    }

    .campo {
      display: inline-flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 6px;
      font-size: 13px;
      color: #333;
    }

    /* Ajuste fino da impressão: fica entre os botões e as dicas. */
    .ajuste {
      margin-bottom: 12px;
    }

    .ajuste input {
      width: 70px;
    }

    .campo input {
      width: 80px;
      padding: 7px 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    .aviso {
      background: #fff8e1;
      border: 1px solid #ffe08a;
      border-radius: 6px;
      color: #7a5c00;
      font-size: 13px;
      padding: 8px 12px;
      margin-bottom: 12px;
    }

    @media screen {
      body {
        background: #f1f1f4;
        padding-bottom: 10mm;
      }

      .folha {
        margin-top: 4mm;
        padding-top: 2mm;
        padding-bottom: 2mm;
        background: #fff;
        box-shadow: 0 0 6px rgba(0, 0, 0, 0.12);
      }

      .etiqueta {
        outline: 0.2mm dashed #dcdcdc;
      }
    }

    @media print {
      .no-print {
        display: none !important;
      }

      .folha {
        width: var(--bobina-largura);
        margin: 0;
        padding-top: 0;
        padding-bottom: 0;
        box-shadow: none;
      }

      .etiqueta {
        outline: 0;
      }
    }
  </style>
</head>
<body>
  @php
    $porLinha = 3;
    $exibidas = count($etiquetas);
    $total = $total ?? $exibidas;
    $sobra = $exibidas % $porLinha;
    $simbolos = collect($etiquetas)->filter(fn ($etiqueta) => $etiqueta['id'] !== '')->unique('id')->values();
  @endphp

  <svg class="simbolos" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      @foreach ($simbolos as $simbolo)
        <symbol id="{{ $simbolo['id'] }}" viewBox="{{ $simbolo['viewBox'] }}">{!! $simbolo['conteudo'] !!}</symbol>
      @endforeach
    </defs>
  </svg>

  <div class="barra no-print">
    <h1>Etiquetas da entrada #{{ $entrada->id }}</h1>

    <div class="detalhe">
      @if ($item)
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

    @if ($total > $exibidas)
      <div class="aviso">
        Esta entrada tem {{ $total }} etiquetas e foram montadas apenas as {{ $exibidas }} primeiras.
        Para imprimir todas, use o ícone da impressora em cada medicamento.
      </div>
    @endif

    @if ($item && $exibidas % $porLinha !== 0)
      <div class="aviso">
        A bobina tem {{ $porLinha }} etiquetas por linha: sobram {{ $porLinha - $sobra }} etiqueta(s) em branco no fim.
      </div>
    @endif

    <div class="acoes">
      <button type="button" class="btn btn-principal" onclick="window.print()">
        <i class="ri-printer-line"></i> Imprimir
      </button>

      <a href="{{ route('estoque.entradas.show', $entrada) }}" class="btn">
        <i class="ri-arrow-left-line"></i> Voltar para a entrada
      </a>

      @if ($item)
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
      @else
        <a
          href="{{ route('estoque.entradas.etiquetas', $entrada) }}"
          class="btn"
          title="Recarregar as etiquetas de todos os itens">
          <i class="ri-refresh-line"></i> Atualizar
        </a>
      @endif
    </div>

    <div class="campo ajuste">
      <label for="ajuste-x">Ajuste fino da impressão</label>
      <input type="number" id="ajuste-x" step="0.5" min="-5" max="5" value="1" />
      <span>mm na horizontal</span>
      <input type="number" id="ajuste-y" step="0.5" min="-5" max="5" value="0" />
      <span>mm na vertical</span>
      <button type="button" class="btn" id="ajuste-zerar">Zerar</button>
    </div>

    <div class="dicas">
      1. Na janela de impressão, escolha a impressora de etiquetas.<br />
      2. Papel/tamanho: largura <strong>100mm</strong> e comprimento <strong>17mm</strong> (15mm da etiqueta + 2mm do vão entre as linhas) — uma linha de etiquetas por página; cadastre como papel personalizado no driver se ele pedir.<br />
      3. <strong>Orientação: paisagem</strong> (landscape) — a tela já abre assim, não precisa trocar. Margens: <strong>nenhuma</strong>. Escala: <strong>100%</strong> (nunca "ajustar à página").<br />
      4. Desmarque "cabeçalhos e rodapés" e desative o modo econômico/densidade baixa do driver.<br />
      5. <strong>No driver</strong>, o tipo de mídia deve ser <strong>etiqueta picotada com sensor de vão (gap)</strong> e o comprimento da etiqueta <strong>17mm</strong>. Se ficar em 18mm (o valor antigo) ou em "contínuo", a impressora avança mais do que a etiqueta e o erro cresce a cada linha.<br />
      6. Deixe o papel da bobina (100mm × 17mm) como <strong>papel padrão</strong> da impressora: é ele que define o comprimento de cada página. Se o conteúdo sair girado 90°, o papel está cadastrado com largura e comprimento invertidos.<br />
      7. Se o conteúdo sair deslocado dentro da etiqueta, use o <strong>Ajuste fino</strong> acima — ele já vem com <strong>1mm na horizontal</strong> (para compensar a margem não imprimível à esquerda): "horizontal" move para a direita e "vertical" para baixo (valores negativos movem para a esquerda/cima). O valor fica salvo neste computador.<br />
      8. Calibre/sensione a bobina na impressora (tecla de feed até ela achar o vão) antes do primeiro trabalho do dia.<br />
      9. Para conferir o alinhamento, reimprima umas <strong>30 etiquetas</strong> de um mesmo item (campo "Reimprimir"): se a última continuar caindo dentro da etiqueta, o passo do driver está certo; se o desenho for escorregando para fora, o comprimento da etiqueta no driver ainda não é 17mm.
    </div>
  </div>

  <div class="folha">
    @forelse (array_chunk($etiquetas, $porLinha) as $linha)
      <div class="linha">
        @foreach ($linha as $etiqueta)
          <div class="etiqueta">
            @if ($etiqueta['id'] !== '')
              <svg class="barras" viewBox="{{ $etiqueta['viewBox'] }}" preserveAspectRatio="xMidYMid meet" role="img">
                <use href="#{{ $etiqueta['id'] }}" xlink:href="#{{ $etiqueta['id'] }}"></use>
              </svg>
            @endif

            <span class="numero">{{ $etiqueta['codigo'] }}</span>
          </div>
        @endforeach
      </div>
    @empty
      <p class="no-print">Nenhum código de barras para imprimir nesta entrada.</p>
    @endforelse
  </div>

  <script>
    (function () {
      var CHAVE = 'estoque.etiquetas.ajuste';
      var PADRAO_X = 1; // mesmo padrão do --ajuste-x do CSS
      var PADRAO_Y = 0;
      var raiz = document.documentElement;
      var campoX = document.getElementById('ajuste-x');
      var campoY = document.getElementById('ajuste-y');

      function aplicar(x, y) {
        raiz.style.setProperty('--ajuste-x', x + 'mm');
        raiz.style.setProperty('--ajuste-y', y + 'mm');
        campoX.value = x;
        campoY.value = y;
        try {
          localStorage.setItem(CHAVE, JSON.stringify({ x: x, y: y }));
        } catch (e) {}
      }

      function ler(campo) {
        var valor = parseFloat(String(campo.value).replace(',', '.'));
        if (isNaN(valor)) {
          return 0;
        }
        return Math.max(-5, Math.min(5, valor));
      }

      var salvo = null;
      try {
        salvo = JSON.parse(localStorage.getItem(CHAVE) || 'null');
      } catch (e) {}

      aplicar(salvo ? Number(salvo.x) || 0 : PADRAO_X, salvo ? Number(salvo.y) || 0 : PADRAO_Y);

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
