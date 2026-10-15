<?php

namespace App\Models;

use App\Enums\TipoMovimentacaoEstoque;
use App\Support\Numero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstoqueMovimentacao extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'estoque_movimentacoes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'medicamento_id',
        'entrada_item_id',
        'clinica_id',
        'tipo',
        'quantidade',
        'user_id',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoMovimentacaoEstoque::class,
        // Ampola aceita fração (0,5 por aplicação), então a quantidade da
        // movimentação não pode ser inteira: o cast antigo transformava 0,5 em 0.
        'quantidade' => 'decimal:3',
    ];

    /**
     * Medicamento movimentado.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Item da entrada (lote / código de barras) relacionado.
     */
    public function entradaItem()
    {
        return $this->belongsTo(EntradaItem::class, 'entrada_item_id');
    }

    /**
     * Clínica onde o estoque está.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Usuário que lançou a movimentação.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quantidade com sinal (ex.: +10 ou -3).
     */
    public function getQuantidadeComSinalAttribute(): string
    {
        return ($this->quantidade > 0 ? '+' : '').Numero::formatar($this->quantidade);
    }
}
