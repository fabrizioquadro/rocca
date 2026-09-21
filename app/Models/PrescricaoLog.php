<?php

namespace App\Models;

use App\Enums\TipoLogPrescricao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Histórico da prescrição: um registro por acontecimento (criação, edição,
 * pagamento, aplicação e etc.) com autor, data/hora e o que mudou.
 */
class PrescricaoLog extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'prescricao_semana_id',
        'user_id',
        'acao',
        'descricao',
        'dados',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'acao' => TipoLogPrescricao::class,
        'dados' => 'array',
    ];

    /**
     * Grava um evento no histórico da prescrição.
     *
     * O formato de `$dados` é livre, mas dois padrões são exibidos na tela:
     *   ['alteracoes' => [['campo' => 'Data prevista', 'de' => '10/10', 'para' => '12/10']]]
     *   ['detalhes' => ['Clínica' => 'Rocca', 'Total' => 'R$ 1.200,00']]
     *
     * @param  array<string, mixed>  $dados
     */
    public static function registrar(
        Prescricao|int $prescricao,
        TipoLogPrescricao $acao,
        string $descricao,
        array $dados = [],
        ?int $prescricaoSemanaId = null
    ): self {
        return static::create([
            'prescricao_id' => $prescricao instanceof Prescricao ? $prescricao->id : $prescricao,
            'prescricao_semana_id' => $prescricaoSemanaId,
            'user_id' => auth()->id(),
            'acao' => $acao,
            'descricao' => $descricao,
            'dados' => $dados ?: null,
        ]);
    }

    /**
     * Prescrição do evento.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Semana relacionada ao evento (quando existe).
     */
    public function semana()
    {
        return $this->belongsTo(PrescricaoSemana::class, 'prescricao_semana_id');
    }

    /**
     * Usuário que fez a ação.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Data/hora do evento formatada (d/m/Y H:i).
     */
    public function getCriadoEmFormatadoAttribute(): ?string
    {
        return $this->created_at?->format('d/m/Y H:i');
    }

    /**
     * Mudanças de campo no formato "de → para".
     *
     * @return array<int, array<string, string>>
     */
    public function getAlteracoesAttribute(): array
    {
        return $this->dados['alteracoes'] ?? [];
    }

    /**
     * Detalhes livres (chave => valor) do evento.
     *
     * @return array<string, mixed>
     */
    public function getDetalhesAttribute(): array
    {
        return $this->dados['detalhes'] ?? [];
    }
}
