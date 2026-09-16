<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Financeiro extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'financeiros';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'clinica_id',
        'valor_bruto',
        'desconto_tipo',
        'desconto_valor',
        'desconto_aplicado',
        'adicional_valor',
        'valor_total',
        'quantidade_parcelas',
        'observacao',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor_bruto' => 'decimal:2',
        'desconto_valor' => 'decimal:2',
        'desconto_aplicado' => 'decimal:2',
        'adicional_valor' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    /**
     * Prescrição que gerou o financeiro.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Clínica do financeiro.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Usuário que gerou o financeiro.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Parcelas do financeiro.
     */
    public function parcelas()
    {
        return $this->hasMany(FinanceiroParcela::class, 'financeiro_id')->orderBy('numero');
    }

    /**
     * Pagamentos recebidos (mais antigos primeiro).
     */
    public function pagamentos()
    {
        return $this->hasMany(FinanceiroPagamento::class, 'financeiro_id')
            ->orderBy('data_pagamento')
            ->orderBy('id');
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_total, 2, ',', '.');
    }

    /**
     * Valor já pago (soma do que foi alocado nas parcelas).
     */
    public function getValorPagoAttribute(): float
    {
        return round((float) $this->parcelas->sum(fn (FinanceiroParcela $parcela) => (float) $parcela->valor_pago), 2);
    }

    /**
     * Valor pago formatado (R$ 0,00).
     */
    public function getValorPagoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_pago, 2, ',', '.');
    }

    /**
     * Total efetivamente recebido (soma dos pagamentos lançados). Com o
     * financeiro quitado é igual ao valor pago; se sobrou dinheiro depois da
     * última parcela a diferença fica como crédito.
     */
    public function getValorRecebidoAttribute(): float
    {
        return round((float) $this->pagamentos->sum(fn (FinanceiroPagamento $pagamento) => (float) $pagamento->valor), 2);
    }

    /**
     * Valor recebido formatado (R$ 0,00).
     */
    public function getValorRecebidoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_recebido, 2, ',', '.');
    }

    /**
     * Valor recebido que não coube em nenhuma parcela (crédito a favor do
     * cliente). Acontece quando alguma parcela é reduzida/excluída depois do
     * pagamento.
     */
    public function getValorNaoAlocadoAttribute(): float
    {
        return round(max($this->valor_recebido - $this->valor_pago, 0), 2);
    }

    /**
     * Valor não alocado formatado (R$ 0,00).
     */
    public function getValorNaoAlocadoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_nao_alocado, 2, ',', '.');
    }

    /**
     * Valor em aberto (não pago).
     */
    public function getValorAbertoAttribute(): float
    {
        return (float) $this->valor_total - $this->valor_pago;
    }

    /**
     * Valor em aberto formatado (R$ 0,00).
     */
    public function getValorAbertoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_aberto, 2, ',', '.');
    }

    /**
     * Bruto formatado (R$ 0,00).
     */
    public function getValorBrutoFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_bruto, 2, ',', '.');
    }

    /**
     * O desconto é um percentual?
     */
    public function getDescontoEhPorcentagemAttribute(): bool
    {
        return $this->desconto_tipo === 'porcentagem';
    }

    /**
     * Valor do desconto em R$. É o valor CONGELADO no cadastro da prescrição:
     * alterar as semanas depois não muda o desconto (mesmo quando informado em
     * porcentagem).
     */
    public function getValorDescontoAttribute(): float
    {
        if ((float) $this->desconto_aplicado > 0) {
            return round((float) $this->desconto_aplicado, 2);
        }

        // Financeiro antigo, sem o valor congelado
        return $this->calcularDescontoDoCadastro();
    }

    /**
     * Desconto que corresponde ao que foi informado no cadastro, aplicado
     * sobre o bruto atual (usado só para congelar o valor na hora de gravar).
     */
    public function calcularDescontoDoCadastro(): float
    {
        $bruto = (float) $this->valor_bruto;
        $desconto = (float) $this->desconto_valor;

        if ($desconto <= 0 || $bruto <= 0) {
            return 0.0;
        }

        $valor = $this->desconto_eh_porcentagem
            ? $bruto * ($desconto / 100)
            : $desconto;

        return round(min($valor, $bruto), 2);
    }

    /**
     * Desconto formatado (R$ 0,00).
     */
    public function getValorDescontoFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_desconto, 2, ',', '.');
    }

    /**
     * Adicional formatado (R$ 0,00).
     */
    public function getValorAdicionalFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->adicional_valor, 2, ',', '.');
    }

    /**
     * Desconto informado no cadastro: "3% (R$ 92,25)" ou "R$ 50,00".
     * O percentual é informativo — o valor em R$ fica congelado no cadastro.
     */
    public function getDescontoDescricaoAttribute(): ?string
    {
        if (! $this->desconto_tipo || (float) $this->desconto_valor <= 0) {
            return null;
        }

        if (! $this->desconto_eh_porcentagem) {
            return $this->valor_desconto_formatado;
        }

        $percentual = rtrim(rtrim(number_format((float) $this->desconto_valor, 2, ',', '.'), '0'), ',');

        return $percentual.'% ('.$this->valor_desconto_formatado.')';
    }

    /**
     * O financeiro tem desconto e/ou adicional?
     */
    public function getTemAjustesAttribute(): bool
    {
        return $this->valor_desconto > 0 || (float) $this->adicional_valor > 0;
    }
}
