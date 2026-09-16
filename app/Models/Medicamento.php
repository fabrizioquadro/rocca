<?php

namespace App\Models;

use App\Enums\StatusAtivoInativo;
use App\Enums\TipoMedicamento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicamento extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'medicamentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'fabricante',
        'tipo',
        'tamanho_vasilhame',
        'grupo_id',
        'status',
        'ultimo_valor_pago',
        'valor_venda',
        'estoque_minimo',
        'estoque_medio',
        'gera_aplicacao',
        'feegow_aplicacao_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tipo' => TipoMedicamento::class,
        'status' => StatusAtivoInativo::class,
        'ultimo_valor_pago' => 'decimal:2',
        'valor_venda' => 'decimal:2',
        'tamanho_vasilhame' => 'decimal:2',
        'gera_aplicacao' => 'boolean',
    ];

    /**
     * Grupo ao qual o medicamento pertence.
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    /**
     * Movimentações de estoque do medicamento.
     */
    public function movimentacoes()
    {
        return $this->hasMany(EstoqueMovimentacao::class, 'medicamento_id');
    }

    /**
     * Saldo atual do estoque (soma das movimentações).
     */
    public function getEstoqueAtualAttribute(): int
    {
        return (int) $this->movimentacoes()->sum('quantidade');
    }

    /**
     * Saldo do estoque em uma clínica específica
     * (null = todas as clínicas).
     */
    public function estoqueNaClinica(?int $clinicaId): int
    {
        return (int) $this->movimentacoes()
            ->when($clinicaId, fn ($query) => $query->where('clinica_id', $clinicaId))
            ->sum('quantidade');
    }

    /**
     * Último valor pago formatado (R$ 0,00).
     */
    public function getUltimoValorPagoFormatadoAttribute(): ?string
    {
        return $this->formatarMoeda($this->ultimo_valor_pago);
    }

    /**
     * Valor de venda formatado (R$ 0,00).
     */
    public function getValorVendaFormatadoAttribute(): ?string
    {
        return $this->formatarMoeda($this->valor_venda);
    }

    /**
     * Exige anexar a prescrição médica? Sim para ampola ou miligrama
     * que gera aplicação.
     */
    public function getExigeAnexoAttribute(): bool
    {
        return (bool) $this->gera_aplicacao
            && in_array($this->tipo, [TipoMedicamento::Ampola, TipoMedicamento::Miligrama], true);
    }

    /**
     * Tamanho do vasilhame formatado, sem zeros desnecessários (ex.: 10,5).
     */
    public function getTamanhoVasilhameFormatadoAttribute(): ?string
    {
        if ($this->tamanho_vasilhame === null) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $this->tamanho_vasilhame, 2, ',', '.'), '0'), ',');
    }

    /**
     * Formata um valor monetário.
     */
    private function formatarMoeda($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}
