<?php

namespace App\Http\Controllers;

use App\Models\Entrada;
use App\Models\EntradaItem;
use App\Services\EtiquetaService;
use App\Support\EtiquetaLayout;
use Illuminate\Http\Request;

/**
 * Impressão das etiquetas (código de barras) do estoque.
 *
 * A página sai com o tamanho exato da bobina — 100 mm de largura por
 * 17 mm de altura (30 mm de etiqueta + 2 mm de vão) — e cada linha de
 * etiquetas ocupa uma página. Assim o diálogo de impressão não tem
 * orientação para escolher, não redimensiona o conteúdo e a impressora
 * avança exatamente um passo por linha.
 *
 * A tela fica em resources/views/estoque/etiquetas.blade.php e as medidas
 * da bobina em config/etiquetas.php.
 */
class EtiquetaImpressaoController extends Controller
{
    public function __construct(private EtiquetaService $etiquetas)
    {
    }

    /**
     * Etiquetas de todos os medicamentos da entrada (uma por unidade recebida).
     */
    public function entrada(Entrada $entrada)
    {
        $entrada->load(['itens.medicamento', 'clinica', 'fornecedor']);

        $etiquetas = $this->etiquetas->daEntrada($entrada);

        return $this->tela([
            'entrada' => $entrada,
            'item' => null,
            'etiquetas' => array_slice($etiquetas, 0, EtiquetaService::LIMITE),
            'total' => count($etiquetas),
        ]);
    }

    /**
     * Etiquetas de um único medicamento da entrada.
     *
     * `?quantidade=N` reimprime apenas N etiquetas (ex.: etiqueta danificada);
     * sem o parâmetro sai a quantidade cheia do item.
     */
    public function item(Request $request, Entrada $entrada, EntradaItem $entrada_item)
    {
        abort_unless((int) $entrada_item->entrada_id === (int) $entrada->id, 404);

        $entrada_item->load('medicamento');

        $quantidade = (int) $request->input('quantidade', $entrada_item->quantidade);
        $quantidade = max(1, min(EtiquetaService::LIMITE, $quantidade));

        return $this->tela([
            'entrada' => $entrada,
            'item' => $entrada_item,
            'etiquetas' => $this->etiquetas->doItem($entrada_item, $quantidade),
            'total' => $quantidade,
        ]);
    }

    /**
     * Folha de teste do alinhamento: numera cada etiqueta (linha + coluna)
     * e desenha a borda da área útil, para ver na hora se o passo da
     * impressora está certo ou se o desenho escorrega a cada linha.
     *
     * Gasta uma linha de etiquetas por linha impressa — 10 linhas por padrão.
     */
    public function teste(Request $request)
    {
        $layout = EtiquetaLayout::daConfig();

        $linhas = (int) $request->input('linhas', 10);
        $linhas = max(1, min(50, $linhas));

        $letras = range('A', 'Z');
        $etiquetas = [];

        for ($linha = 1; $linha <= $linhas; $linha++) {
            for ($coluna = 0; $coluna < $layout->colunas; $coluna++) {
                $etiquetas[] = [
                    'codigo' => str_pad((string) $linha, 2, '0', STR_PAD_LEFT).' '.$letras[$coluna],
                    'id' => '',
                    'viewBox' => '',
                    'conteudo' => '',
                    'medicamento' => null,
                    'lote' => null,
                ];
            }
        }

        return $this->tela([
            'entrada' => null,
            'item' => null,
            'etiquetas' => $etiquetas,
            'total' => count($etiquetas),
            'teste' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function tela(array $dados)
    {
        return view('estoque.etiquetas', $dados + [
            'layout' => EtiquetaLayout::daConfig(),
            'teste' => false,
        ]);
    }
}
