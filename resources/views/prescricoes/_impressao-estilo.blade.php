{{--
  Aparência do documento da prescrição (tabelas "imp-*").
  Fica num arquivo só porque a tela de impressão e o PDF (dompdf) precisam
  sair iguais — quem inclui define o @page e o tamanho da fonte do corpo.
--}}
<style>
  .imp-tabela {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
  }

  .imp-tabela th,
  .imp-tabela td {
    border: 0.5px solid #adb5bd;
    padding: 2px 3px;
    vertical-align: top;
  }

  .imp-tabela thead th,
  .imp-titulo-bloco td {
    background-color: #eeeeee;
    font-weight: bold;
  }

  .imp-cabecalho td {
    border: 0;
    padding: 0 0 3px;
  }

  .imp-titulo {
    text-align: right;
    font-size: 11px;
  }

  .imp-clinica {
    font-size: 11px;
  }

  .imp-rotulo {
    background-color: #f6f7f9;
    font-weight: bold;
    white-space: nowrap;
  }

  .imp-centro {
    text-align: center;
  }

  .imp-direita {
    text-align: right;
  }

  .imp-situacao {
    text-align: right;
    font-weight: bold;
    white-space: nowrap;
  }

  .imp-obs,
  .imp-vazio {
    color: #555;
  }

  .imp-espaco {
    display: inline-block;
    width: 10px;
  }

  .imp-bloco {
    margin-top: 8px;
    page-break-inside: avoid;
  }

  .imp-rodape {
    margin-top: 8px;
    color: #555;
    font-size: 7px;
  }
</style>
