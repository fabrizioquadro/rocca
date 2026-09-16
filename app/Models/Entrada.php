<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entrada extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'entradas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'clinica_id',
        'fornecedor_id',
        'user_id',
        'numero_nota',
        'data_entrada',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_entrada' => 'date',
    ];

    /**
     * Clínica que comprou os medicamentos.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Fornecedor da nota fiscal.
     */
    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
    }

    /**
     * Usuário que lançou a entrada.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Medicamentos que entraram no estoque.
     */
    public function itens()
    {
        return $this->hasMany(EntradaItem::class, 'entrada_id');
    }

    /**
     * Anexos da entrada (nota fiscal, recibo e etc.).
     */
    public function anexos()
    {
        return $this->hasMany(EntradaAnexo::class, 'entrada_id');
    }

    /**
     * Quantidade total de unidades da entrada.
     */
    public function getQuantidadeTotalAttribute(): int
    {
        return (int) $this->itens->sum('quantidade');
    }

    /**
     * Valor total da entrada (soma dos valores unitários × quantidades).
     */
    public function getValorTotalAttribute(): float
    {
        return (float) $this->itens->sum(fn ($item) => $item->valor_total);
    }

    /**
     * Valor total formatado (R$ 0,00).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format($this->valor_total, 2, ',', '.');
    }
}
