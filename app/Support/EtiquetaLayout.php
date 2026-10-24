<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Geometria da bobina de etiquetas, em milímetros.
 *
 * O navegador não faz a conta do layout por nós: a página de impressão é
 * montada com as medidas exatas da bobina (config/etiquetas.php) e cada
 * linha de etiquetas vira uma página. Como a página já nasce "deitada"
 * (100 mm de largura por 17 mm de altura), o diálogo de impressão não pede
 * orientação e o conteúdo não é redimensionado.
 */
class EtiquetaLayout
{
    public function __construct(
        public readonly float $larguraBobina,
        public readonly float $margemEsquerda,
        public readonly float $margemDireita,
        public readonly int $colunas,
        public readonly float $larguraEtiqueta,
        public readonly float $alturaEtiqueta,
        public readonly float $vaoColunas,
        public readonly float $vaoLinhas,
        public readonly float $ajusteX = 0.0,
        public readonly float $ajusteY = 0.0,
    ) {
        if ($this->colunas < 1) {
            throw new InvalidArgumentException('A bobina precisa ter pelo menos uma coluna de etiquetas.');
        }

        if ($this->larguraEtiqueta <= 0 || $this->alturaEtiqueta <= 0) {
            throw new InvalidArgumentException('A etiqueta precisa ter largura e altura maiores que zero.');
        }
    }

    /**
     * Lê as medidas de config/etiquetas.php.
     */
    public static function daConfig(): self
    {
        $config = config('etiquetas');

        return new self(
            larguraBobina: (float) $config['largura_bobina'],
            margemEsquerda: (float) $config['margem_esquerda'],
            margemDireita: (float) $config['margem_direita'],
            colunas: (int) $config['colunas'],
            larguraEtiqueta: (float) $config['largura_etiqueta'],
            alturaEtiqueta: (float) $config['altura_etiqueta'],
            vaoColunas: (float) $config['vao_colunas'],
            vaoLinhas: (float) $config['vao_linhas'],
            ajusteX: (float) $config['ajuste_x'],
            ajusteY: (float) $config['ajuste_y'],
        );
    }

    /**
     * Largura da página = largura da bobina.
     */
    public function larguraPagina(): float
    {
        return $this->larguraBobina;
    }

    /**
     * Altura da página = a etiqueta mais o vão até a próxima linha.
     *
     * É o passo que a impressora avança a cada página; precisa bater com o
     * comprimento do papel cadastrado no driver.
     */
    public function alturaPagina(): float
    {
        return $this->alturaEtiqueta + $this->vaoLinhas;
    }

    /**
     * Distância entre o início de uma coluna e o da seguinte.
     */
    public function passoColuna(): float
    {
        return $this->larguraEtiqueta + $this->vaoColunas;
    }

    /**
     * Distância da borda esquerda da bobina até a coluna informada (base 0).
     */
    public function posicaoColuna(int $indice): float
    {
        return $this->margemEsquerda + ($indice * $this->passoColuna());
    }

    /**
     * Largura ocupada pelas etiquetas de uma linha, sem as margens.
     */
    public function larguraUtil(): float
    {
        return ($this->colunas * $this->larguraEtiqueta)
            + ((($this->colunas - 1) * $this->vaoColunas));
    }

    /**
     * Quanto da bobina sobra fora das margens (negativo = não cabe).
     */
    public function sobra(): float
    {
        return $this->larguraBobina - $this->margemEsquerda - $this->margemDireita - $this->larguraUtil();
    }

    /**
     * Problemas de configuração para mostrar na tela de impressão.
     *
     * @return array<int, string>
     */
    public function avisos(): array
    {
        $avisos = [];

        if ($this->sobra() < -0.05) {
            $avisos[] = sprintf(
                'As %d colunas de %s não cabem na bobina de %s: faltam %s. Confira as medidas em config/etiquetas.php (ou no .env).',
                $this->colunas,
                $this->mm($this->larguraEtiqueta),
                $this->mm($this->larguraBobina),
                $this->mm(abs($this->sobra())),
            );
        } elseif ($this->sobra() > 0.5) {
            $avisos[] = sprintf(
                'Sobram %s de bobina fora das etiquetas (margens de %s + %s). Se o conteúdo sair deslocado, use o ajuste fino abaixo.',
                $this->mm($this->sobra()),
                $this->mm($this->margemEsquerda),
                $this->mm($this->margemDireita),
            );
        }

        return $avisos;
    }

    /**
     * Milímetros prontos para o CSS, sem zeros à toa: 30mm, 17.5mm, 0.6mm.
     */
    public function mm(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.').'mm';
    }

    /**
     * Converte milímetros em pontos da impressora (203 dpi = 8 pontos/mm).
     */
    public function pontos(float $milimetros): int
    {
        $dpi = (int) config('etiquetas.dpi', 203);

        return (int) round($milimetros * ($dpi / 25.4));
    }
}
