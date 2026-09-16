<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntradaAnexo extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'entrada_anexos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entrada_id',
        'nome',
        'arquivo',
        'mime',
        'tamanho',
    ];

    /**
     * Entrada à qual o anexo pertence.
     */
    public function entrada()
    {
        return $this->belongsTo(Entrada::class, 'entrada_id');
    }

    /**
     * O anexo é uma imagem?
     */
    public function getEhImagemAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
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
}
