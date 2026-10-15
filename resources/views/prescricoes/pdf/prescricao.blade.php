<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Prescrição #{{ $prescricao->id }} — {{ $prescricao->paciente?->nome ?? 'paciente' }}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 10mm 8mm;
    }

    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 8px;
      color: #222;
      margin: 0;
    }
  </style>

  @include('prescricoes._impressao-estilo')
</head>
<body>
  @include('prescricoes._impressao')
</body>
</html>
