<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinica extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'cnpj',
        'id_feegow',
    ];

    /**
     * Usuários vinculados a esta clínica.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'clinica_id');
    }

    /**
     * CNPJ formatado (00.000.000/0000-00).
     */
    public function getCnpjFormatadoAttribute(): ?string
    {
        $cnpj = preg_replace('/\D+/', '', (string) $this->cnpj);

        if (strlen($cnpj) !== 14) {
            return $this->cnpj;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj);
    }
}
