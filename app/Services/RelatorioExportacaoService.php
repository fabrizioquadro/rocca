<?php

namespace App\Services;

use App\Support\Numero;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as OpcoesPlanilha;
use OpenSpout\Writer\XLSX\Writer as EscritorPlanilha;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportação dos relatórios em PDF e XLSX.
 *
 * O arquivo é montado a partir do MESMO HTML que a tela renderiza (o
 * componente `x-relatorio` marca o resumo, os filtros e a tabela): assim
 * qualquer coluna, total ou filtro novo aparece na exportação automaticamente,
 * sem precisar duplicar a montagem dos dados relatório por relatório.
 */
class RelatorioExportacaoService
{
    /**
     * Lê o HTML do relatório e devolve os blocos que a exportação usa.
     *
     * @return array{
     *     filtros: array<string, string>,
     *     legenda: ?string,
     *     resumo: array<int, array{0: string, 1: string}>,
     *     colunas: array<int, array<int, string>>,
     *     linhas: array<int, array<int, string>>,
     *     totais: array<int, array<int, string>>
     * }
     */
    public function extrair(string $html): array
    {
        $html = preg_replace('#<(script|noscript)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        $documento = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $documento->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $xpath = new DOMXPath($documento);
        $tabela = $this->no($xpath, "//div[contains(@class,'relatorio-tabela')]//table");

        return [
            'filtros' => $this->filtros($xpath),
            'legenda' => $this->texto($this->no($xpath, "//*[contains(@class,'relatorio-legenda')]")),
            'resumo' => $this->resumo($xpath),
            'colunas' => $tabela ? $this->linhasDaSecao($xpath, $tabela, 'thead') : [],
            'linhas' => $tabela ? $this->linhasDaSecao($xpath, $tabela, 'tbody') : [],
            'totais' => $tabela ? $this->linhasDaSecao($xpath, $tabela, 'tfoot') : [],
        ];
    }

    /**
     * PDF (A4 paisagem) com o resumo, os filtros aplicados e a tabela.
     */
    public function pdf(string $titulo, array $dados, string $nomeArquivo): Response
    {
        $pdf = Pdf::loadView('relatorios.pdf', [
            'titulo' => $titulo,
            'dados' => $dados,
            'emitidoEm' => now(),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($nomeArquivo);
    }

    /**
     * Planilha XLSX com o resumo, os filtros aplicados (em destaque) e a
     * tabela — valores numéricos viram células numéricas, prontas para somar.
     */
    public function xlsx(string $titulo, array $dados, string $nomeArquivo): Response
    {
        $caminho = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'relatorio-'.uniqid().'.xlsx';

        $escritor = new EscritorPlanilha(new OpcoesPlanilha());
        $escritor->openToFile($caminho);

        $tituloEstilo = (new Style())->setFontBold()->setFontSize(13);
        $negrito = (new Style())->setFontBold();
        $cabecalho = (new Style())->setFontBold()->setBackgroundColor('EEEEEE');

        $escrever = function (array $valores, ?Style $estilo = null) use ($escritor) {
            $escritor->addRow(Row::fromValues($valores, $estilo));
        };

        $escrever([$titulo], $tituloEstilo);
        $escrever(['Emitido em '.now()->format('d/m/Y H:i')]);

        if ($dados['legenda']) {
            $escrever(['Referência: '.$dados['legenda']]);
        }

        foreach ($dados['filtros'] as $rotulo => $valor) {
            $escrever([$rotulo.':', $valor]);
        }

        if ($dados['resumo']) {
            $escrever(['']);
        }

        foreach ($dados['resumo'] as [$rotulo, $valor]) {
            $escrever([$rotulo.':', Numero::paraCelula($valor)]);
        }

        if ($dados['colunas']) {
            $escrever(['']);
        }

        foreach ($dados['colunas'] as $coluna) {
            $escrever($coluna, $cabecalho);
        }

        foreach ($dados['linhas'] as $linha) {
            $escrever(array_map(Numero::paraCelula(...), $linha));
        }

        if ($dados['totais']) {
            $escrever(['']);

            foreach ($dados['totais'] as $linha) {
                $escrever(array_map(Numero::paraCelula(...), $linha), $negrito);
            }
        }

        $escritor->close();

        return response()->download($caminho, $nomeArquivo)->deleteFileAfterSend(true);
    }

    /**
     * Filtros aplicados no relatório (clínica, medicamento, tipo, período...),
     * usados no cabeçalho do arquivo exportado.
     *
     * @return array<string, string>
     */
    private function filtros(DOMXPath $xpath): array
    {
        $filtros = [];

        foreach ($xpath->query("//form[contains(@class,'relatorio-filtros')]//select") as $select) {
            $opcao = $this->no($xpath, ".//option[@selected]", $select);

            if (! $opcao || trim($opcao->getAttribute('value')) === '') {
                continue;
            }

            $filtros[$this->rotuloDoCampo($xpath, $select) ?? 'Filtro'] = $this->texto($opcao);
        }

        $inicio = $this->valorDoCampo($xpath, 'inicio');
        $fim = $this->valorDoCampo($xpath, 'fim');

        if ($inicio !== '' || $fim !== '') {
            $filtros['Período'] = trim($this->data($inicio).' a '.$this->data($fim), ' a');
        }

        foreach ($xpath->query("//form[contains(@class,'relatorio-filtros')]//input[@type='checkbox'][@checked]") as $input) {
            $rotulo = $this->rotuloDoCampo($xpath, $input);

            if ($rotulo) {
                $filtros['Filtro'] = $rotulo;
            }
        }

        return $filtros;
    }

    /**
     * Pares rótulo/valor do bloco de resumo do relatório.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function resumo(DOMXPath $xpath): array
    {
        $pares = [];

        foreach ($xpath->query("//div[contains(@class,'relatorio-resumo')]//small") as $rotulo) {
            $pares[] = [$this->texto($rotulo), $this->proximoIrmao($rotulo)];
        }

        return $pares;
    }

    /**
     * Células de uma seção da tabela (thead, tbody ou tfoot), já com os
     * `colspan` expandidos para todas as linhas ficarem alinhadas.
     *
     * @return array<int, array<int, string>>
     */
    private function linhasDaSecao(DOMXPath $xpath, DOMElement $tabela, string $secao): array
    {
        $linhasDoCabecalho = $xpath->query("./{$secao}/tr", $tabela);

        // Sem <tbody> explícito o navegador cria, mas o DOM não.
        if ($secao === 'tbody' && $linhasDoCabecalho->length === 0) {
            $linhasDoCabecalho = $xpath->query('./tr', $tabela);
        }

        $linhas = [];

        foreach ($linhasDoCabecalho as $linha) {
            $celulas = [];

            foreach ($xpath->query('./th|./td', $linha) as $celula) {
                $celulas[] = $this->texto($celula);

                $colspan = max(1, (int) $celula->getAttribute('colspan'));

                for ($i = 1; $i < $colspan; $i++) {
                    $celulas[] = '';
                }
            }

            if ($celulas !== []) {
                $linhas[] = $celulas;
            }
        }

        return $linhas;
    }

    /**
     * Rótulo do campo (texto do <label for="...">).
     */
    private function rotuloDoCampo(DOMXPath $xpath, DOMElement $campo): ?string
    {
        $id = $campo->getAttribute('id');

        if ($id === '') {
            return null;
        }

        $rotulo = $this->texto($this->no($xpath, "//label[@for='{$id}']"));

        return $rotulo !== '' ? $rotulo : null;
    }

    /**
     * Valor de um input pelo id (limpo).
     */
    private function valorDoCampo(DOMXPath $xpath, string $id): string
    {
        $campo = $this->no($xpath, "//input[@id='{$id}']");

        return $campo ? trim($campo->getAttribute('value')) : '';
    }

    /**
     * Data no formato da tela (d/m/Y).
     */
    private function data(string $valor): string
    {
        return $valor !== '' ? Carbon::parse($valor)->format('d/m/Y') : '';
    }

    /**
     * Texto do próximo elemento irmão (usado no resumo: rótulo -> valor).
     */
    private function proximoIrmao(DOMNode $no): string
    {
        $irmao = $no->nextSibling;

        while ($irmao && ! $irmao instanceof DOMElement) {
            $irmao = $irmao->nextSibling;
        }

        return $this->texto($irmao);
    }

    /**
     * Primeiro nó que casa com a consulta.
     */
    private function no(DOMXPath $xpath, string $consulta, ?DOMNode $contexto = null): ?DOMElement
    {
        $resultado = $xpath->query($consulta, $contexto);

        if ($resultado === false || $resultado->length === 0) {
            return null;
        }

        $no = $resultado->item(0);

        return $no instanceof DOMElement ? $no : null;
    }

    /**
     * Texto de um nó sem espaços repetidos (e sem espaços nas pontas).
     */
    private function texto(?DOMNode $no): string
    {
        if (! $no) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $no->textContent) ?? '');
    }
}
