<?php

namespace App\Models;

use App\Support\Numero;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Vasilhame de medicamento do tipo miligrama que já foi aberto.
 *
 * Cada código de barras é um vasilhame: quando ele é aberto, sai do estoque
 * fechado (movimentação de "abertura") e passa a ter saldo em mg, consumido
 * pelas aplicações até zerar.
 */
class VasilhameAberto extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'vasilhames_abertos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entrada_item_id',
        'medicamento_id',
        'clinica_id',
        'mg_restantes',
        'aberto_em',
        'aberto_por_user_id',
        'esgotado_em',
        'observacao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mg_restantes' => 'decimal:3',
        'aberto_em' => 'datetime',
        'esgotado_em' => 'datetime',
    ];

    /**
     * Lote do estoque (código de barras, lote e vencimento).
     */
    public function entradaItem()
    {
        return $this->belongsTo(EntradaItem::class, 'entrada_item_id');
    }

    /**
     * Medicamento do vasilhame.
     */
    public function medicamento()
    {
        return $this->belongsTo(Medicamento::class, 'medicamento_id');
    }

    /**
     * Clínica onde o vasilhame está aberto.
     */
    public function clinica()
    {
        return $this->belongsTo(Clinica::class, 'clinica_id');
    }

    /**
     * Quem abriu o vasilhame.
     */
    public function abertoPor()
    {
        return $this->belongsTo(User::class, 'aberto_por_user_id');
    }

    /**
     * Aplicações que saíram deste vasilhame (em mg).
     */
    public function aplicacoes()
    {
        return $this->hasMany(PrescricaoSemanaAplicacao::class, 'vasilhame_aberto_id');
    }

    /**
     * Baixas lançadas neste vasilhame (mg descartados).
     */
    public function baixas()
    {
        return $this->hasMany(BaixaVasilhameItem::class, 'vasilhame_aberto_id');
    }

    /**
     * Só os vasilhames que ainda podem ser usados (têm mg e não esgotaram).
     */
    public function scopeEmUso($query)
    {
        return $query->whereNull('esgotado_em')->where('mg_restantes', '>', 0);
    }

    /**
     * O vasilhame ainda pode ser usado?
     */
    public function getEstaEmUsoAttribute(): bool
    {
        return $this->esgotado_em === null && (float) $this->mg_restantes > 0;
    }

    /**
     * Saldo do vasilhame formatado (ex.: 85,75 mg).
     */
    public function getMgRestantesFormatadoAttribute(): string
    {
        return Numero::formatar($this->mg_restantes).' mg';
    }

    /**
     * "Aberto por Fulano em 22/09/2026 14:30".
     */
    public function getDescricaoAberturaAttribute(): string
    {
        return 'Aberto por '.($this->abertoPor?->nome ?? 'usuário removido')
            .($this->aberto_em ? ' em '.$this->aberto_em->format('d/m/Y H:i') : '');
    }

    /**
     * Código de barras do vasilhame.
     */
    public function getCodigoBarrasAttribute(): ?string
    {
        return $this->entradaItem?->codigo_barras;
    }

    /**
     * Lote do vasilhame.
     */
    public function getLoteAttribute(): ?string
    {
        return $this->entradaItem?->lote;
    }

    /**
     * Vencimento do vasilhame (d/m/Y).
     */
    public function getVencimentoFormatadoAttribute(): ?string
    {
        return $this->entradaItem?->vencimento_formatado;
    }

    /**
     * Vasilhames de um código de barras que estão em uso na clínica.
     *
     * @return Collection<int, VasilhameAberto>
     */
    public static function emUsoPorCodigo(string $codigo, int $clinicaId, ?int $medicamentoId = null): Collection
    {
        return static::with(['entradaItem', 'medicamento', 'abertoPor'])
            ->emUso()
            ->where('clinica_id', $clinicaId)
            ->when($medicamentoId, fn ($query) => $query->where('medicamento_id', $medicamentoId))
            ->whereHas('entradaItem', fn ($query) => $query->where('codigo_barras', $codigo))
            ->orderBy('aberto_em')
            ->get();
    }

    /**
     * Vasilhames em uso na clínica para uma lista de medicamentos.
     *
     * @param  array<int, int>  $medicamentoIds
     * @return Collection<int, VasilhameAberto>
     */
    public static function emUsoDosMedicamentos(array $medicamentoIds, int $clinicaId): Collection
    {
        if ($medicamentoIds === []) {
            return collect();
        }

        return static::with(['entradaItem', 'medicamento', 'abertoPor'])
            ->emUso()
            ->where('clinica_id', $clinicaId)
            ->whereIn('medicamento_id', $medicamentoIds)
            ->orderBy('aberto_em')
            ->get();
    }
}
