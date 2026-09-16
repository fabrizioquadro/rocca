<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceiroPagamento extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'financeiro_pagamentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'financeiro_id',
        'valor',
        'forma_pagamento',
        'parcelas',
        'data_pagamento',
        'observacao',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor' => 'decimal:2',
        'forma_pagamento' => FormaPagamento::class,
        'parcelas' => 'integer',
        'data_pagamento' => 'date',
    ];

    /**
     * Financeiro do pagamento.
     */
    public function financeiro()
    {
        return $this->belongsTo(Financeiro::class, 'financeiro_id');
    }

    /**
     * Usuário que registrou o pagamento.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Valor formatado (R$ 0,00).
     */
    public function getValorFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor, 2, ',', '.');
    }

    /**
     * Data do pagamento formatada (d/m/Y).
     */
    public function getDataFormatadaAttribute(): ?string
    {
        return $this->data_pagamento?->format('d/m/Y');
    }

    /**
     * Forma de pagamento por extenso ("Cartão Crédito").
     */
    public function getFormaLabelAttribute(): string
    {
        return $this->forma_pagamento?->label() ?? '—';
    }

    /**
     * Forma + parcelas ("Cartão Crédito 3x").
     */
    public function getFormaDescricaoAttribute(): string
    {
        if (! $this->forma_pagamento) {
            return '—';
        }

        return $this->parcelas > 1
            ? $this->forma_pagamento->label().' '.$this->parcelas.'x'
            : $this->forma_pagamento->label();
    }
}
