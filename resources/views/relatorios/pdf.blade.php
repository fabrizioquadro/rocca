<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>{{ $titulo }}</title>
  <style>
    @page {
      margin: 10mm 8mm;
    }

    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 8.5px;
      color: #222;
      margin: 0;
    }

    .cabecalho {
      border-bottom: 1px solid #999;
      padding-bottom: 4px;
      margin-bottom: 6px;
    }

    .cabecalho h1 {
      font-size: 14px;
      margin: 0;
    }

    .meta {
      font-size: 8px;
      color: #555;
      margin-top: 2px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    .resumo {
      margin-bottom: 6px;
    }

    .resumo td {
      border: 0;
      padding: 0 10px 3px 0;
      font-size: 8.5px;
      vertical-align: top;
    }

    thead th {
      background-color: #eeeeee;
      border: 0.5px solid #999;
      padding: 3px 4px;
      font-size: 8.5px;
      text-align: left;
    }

    tbody td,
    tfoot th {
      border: 0.5px solid #bbbbbb;
      padding: 3px 4px;
      font-size: 8.5px;
      vertical-align: top;
    }

    tfoot th {
      background-color: #f5f5f5;
      text-align: left;
    }

    .num {
      text-align: right;
    }

    .vazio {
      margin: 0;
      color: #555;
      font-size: 9px;
    }
  </style>
</head>
<body>
  <div class="cabecalho">
    <h1>{{ $titulo }}</h1>

    <div class="meta">
      Emitido em {{ $emitidoEm->format('d/m/Y') }} às {{ $emitidoEm->format('H:i') }}

      @foreach ($dados['filtros'] as $rotulo => $valor)
        · {{ $rotulo }}: {{ $valor }}
      @endforeach

      @if ($dados['legenda'])
        · {{ $dados['legenda'] }}
      @endif

      · {{ count($dados['linhas']) }} registro(s)
    </div>
  </div>

  @if ($dados['resumo'])
    <table class="resumo">
      <tr>
        @foreach ($dados['resumo'] as [$rotulo, $valor])
          <td>
            <strong>{{ $rotulo }}:</strong> {{ $valor !== '' ? $valor : '—' }}
          </td>
        @endforeach
      </tr>
    </table>
  @endif

  @if (empty($dados['colunas']) || empty($dados['linhas']))
    <p class="vazio">Nenhum registro encontrado com os filtros informados.</p>
  @else
    <table>
      <thead>
        @foreach ($dados['colunas'] as $coluna)
          <tr>
            @foreach ($coluna as $celula)
              <th>{{ $celula }}</th>
            @endforeach
          </tr>
        @endforeach
      </thead>

      <tbody>
        @foreach ($dados['linhas'] as $linha)
          <tr>
            @foreach ($linha as $celula)
              <td class="{{ \App\Support\Numero::pareceNumero($celula) ? 'num' : '' }}">{{ $celula }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>

      @if ($dados['totais'])
        <tfoot>
          @foreach ($dados['totais'] as $linha)
            <tr>
              @foreach ($linha as $celula)
                <th class="{{ \App\Support\Numero::pareceNumero($celula) ? 'num' : '' }}">{{ $celula }}</th>
              @endforeach
            </tr>
          @endforeach
        </tfoot>
      @endif
    </table>
  @endif
</body>
</html>
