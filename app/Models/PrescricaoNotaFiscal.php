<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota fiscal (NFS-e) de uma prescrição.
 *
 * O número é sequencial por prescrição e compõe o código de integração da OS
 * na Omie: "{prescricao_id}/{numero}".
 */
class PrescricaoNotaFiscal extends Model
{
    use HasFactory;

    protected $table = 'prescricao_notas_fiscais';

    protected $fillable = [
        'prescricao_id',
        'numero',
        'codigo_integracao',
        'n_cod_servico',
        'descricao_servico',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'cidade_prestacao',
        'email_envio',
        'status',
        'n_cod_os',
        'n_cod_nfse',
        'emitida_em',
    ];

    protected $casts = [
        'quantidade' => 'decimal:2',
        'valor_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'emitida_em' => 'datetime',
    ];

    public function prescricao(): BelongsTo
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Valor total formatado (R$ 1.234,56).
     */
    public function getValorTotalFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->valor_total, 2, ',', '.');
    }
}
