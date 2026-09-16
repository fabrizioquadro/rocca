<?php

namespace App\Models;

use App\Enums\StatusAtivoInativo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nome da tabela (o pluralizador do Laravel geraria "fornecedors").
     *
     * @var string
     */
    protected $table = 'fornecedores';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome',
        'cnpj',
        'email',
        'telefone',
        'celular',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => StatusAtivoInativo::class,
    ];

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

    /**
     * Telefone formatado ((00) 0000-0000).
     */
    public function getTelefoneFormatadoAttribute(): ?string
    {
        return $this->formatarTelefone($this->telefone);
    }

    /**
     * Celular formatado ((00) 00000-0000).
     */
    public function getCelularFormatadoAttribute(): ?string
    {
        return $this->formatarTelefone($this->celular);
    }

    /**
     * Formata um número de telefone/celular conforme a quantidade de dígitos.
     */
    private function formatarTelefone(?string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        if (strlen($digitos) === 11) {
            return preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $digitos);
        }

        if (strlen($digitos) === 10) {
            return preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $digitos);
        }

        return $numero;
    }
}
