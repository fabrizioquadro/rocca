<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Etiquetas — Entrada #{{ $entrada->id }}</title>
  <style>
    /* ============================================================
       Bobina: 100mm de largura, 3 etiquetas de 30x15mm por linha.
       Espaçamento: 3mm entre as colunas, 3mm entre as linhas e
       2mm de margem em cada lateral (30+3+30+3+30 = 96 + 2 + 2 = 100).
       Se a bobina do cliente tiver outro espaçamento, basta ajustar
       as variáveis abaixo.
       ============================================================ */
    :root {
      --bobina-largura: 100mm;
      --bobina-margem-lateral: 2mm;
      --etiqueta-largura: 30mm;
      --etiqueta-altura: 15mm;
      --etiqueta-espaco-coluna: 3mm;
      --etiqueta-espaco-linha: 3mm;
      --etiqueta-por-linha: 3;
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

    .linha {
      display: flex;
      align-items: flex-start;
    }

    .etiqueta {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: var(--etiqueta-largura);
      height: var(--etiqueta-altura);
      padding: 0.6mm 0.6mm 0.8mm;
      margin: 0 var(--etiqueta-espaco-coluna) var(--etiqueta-espaco-linha) 0;
      overflow: hidden;
      background: #fff;
    }

    /* A última etiqueta da linha encosta na margem direita. */
    .linha .etiqueta:nth-child(3) {
      margin-right: 0;
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
      gap: 6px;
      font-size: 13px;
      color: #333;
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
      @page {
        margin: 0;
      }

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

    <div class="dicas">
      1. Na janela de impressão, escolha a impressora de etiquetas.<br />
      2. Papel/tamanho: largura <strong>100mm</strong> (se o driver pedir o comprimento, use o passo da bobina, ou seja, <strong>15mm por linha + 3mm de espaçamento = 18mm</strong>).<br />
      3. Margens: <strong>nenhuma</strong>. Escala: <strong>100%</strong> (não use "ajustar à página").<br />
      4. Desmarque "cabeçalhos e rodapés" e desative o modo econômico/densidade baixa do driver.
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
</body>
</html>
