<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescricaoAnexo extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'prescricao_anexos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescricao_id',
        'nome',
        'arquivo',
        'mime',
        'tamanho',
        'user_id',
        'feegow_file_id',
        'feegow_enviado_em',
        'feegow_erro',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'feegow_enviado_em' => 'datetime',
    ];

    /**
     * Prescrição à qual o anexo pertence.
     */
    public function prescricao()
    {
        return $this->belongsTo(Prescricao::class, 'prescricao_id');
    }

    /**
     * Usuário que anexou.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * O anexo é uma imagem?
     */
    public function getEhImagemAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * URL pública do arquivo.
     */
    public function getUrlAttribute(): string
    {
        return asset($this->arquivo);
    }

    /**
     * Tamanho do arquivo legível (ex.: 1,2 MB).
     */
    public function getTamanhoFormatadoAttribute(): string
    {
        $bytes = (int) $this->tamanho;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return $bytes.' B';
    }

    /**
     * Data do envio formatada (d/m/Y H:i).
     */
    public function getCriadoEmFormatadoAttribute(): ?string
    {
        return $this->created_at?->format('d/m/Y H:i');
    }

    /**
     * O anexo já foi enviado para o prontuário do paciente na Feegow?
     */
    public function getEnviadoParaFeegowAttribute(): bool
    {
        return $this->feegow_enviado_em !== null;
    }

    /**
     * Data do envio para a Feegow (d/m/Y H:i).
     */
    public function getFeegowEnviadoEmFormatadoAttribute(): ?string
    {
        return $this->feegow_enviado_em?->format('d/m/Y H:i');
    }
}
