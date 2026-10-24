<?php

/*
|--------------------------------------------------------------------------
| Etiquetas de estoque (impressora térmica de bobina)
|--------------------------------------------------------------------------
|
| Tudo o que a impressão precisa saber sobre o papel fica aqui, em
| milímetros — a unidade que o CSS de impressão entende.
|
| Impressora da clínica: térmica direta de 4 polegadas (bobina de 100 mm),
| 203 dpi (8 pontos por milímetro), mídia picotada com sensor de vão. A
| bobina tem 3 colunas de etiquetas de 30 x 15 mm por linha.
|
| A conta da largura precisa fechar a bobina:
|
|   margem_esquerda + colunas x largura_etiqueta + (colunas - 1) x vao_colunas + margem_direita
|         2        +    3   x       30        +        2        x      3        +      2        = 100
|
| A ALTURA DA PÁGINA (o passo que a impressora avança) é sempre
| altura_etiqueta + vao_linhas = 15 + 2 = 17 mm. É esse mesmo valor que o
| driver da impressora precisa ter como comprimento do papel.
|
| Se a clínica trocar a bobina, só estes números mudam.
|
*/

return [

    // Largura total da bobina: 4 polegadas = 100 mm (alguns rolos têm 102 mm).
    'largura_bobina' => (float) env('ETIQUETA_LARGURA_BOBINA', 100),

    // Faixa da bobina que fica fora das etiquetas (área não imprimível).
    'margem_esquerda' => (float) env('ETIQUETA_MARGEM_ESQUERDA', 2),
    'margem_direita' => (float) env('ETIQUETA_MARGEM_DIREITA', 2),

    // Etiquetas por linha e a medida de cada uma.
    'colunas' => (int) env('ETIQUETA_COLUNAS', 3),
    'largura_etiqueta' => (float) env('ETIQUETA_LARGURA', 30),
    'altura_etiqueta' => (float) env('ETIQUETA_ALTURA', 15),

    // Vãos entre as etiquetas. O vão de linhas é o que a impressora avança
    // além da etiqueta — ele entra na altura da página.
    'vao_colunas' => (float) env('ETIQUETA_VAO_COLUNAS', 3),
    'vao_linhas' => (float) env('ETIQUETA_VAO_LINHAS', 2),

    // Ajuste fino (mm) para compensar a área não imprimível do driver.
    // Positivo desloca para a direita/baixo. Calibrável na própria tela.
    'ajuste_x' => (float) env('ETIQUETA_AJUSTE_X', 1),
    'ajuste_y' => (float) env('ETIQUETA_AJUSTE_Y', 0),

    // Só para as instruções mostradas na tela de impressão.
    'impressora' => env('ETIQUETA_IMPRESSORA', 'Térmica 4" (203 dpi)'),
    'dpi' => (int) env('ETIQUETA_DPI', 203),

];
