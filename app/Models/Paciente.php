<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use HasFactory;

    /**
     * Nome da tabela.
     *
     * @var string
     */
    protected $table = 'pacientes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'paciente_id',
        'nome',
        'nascimento',
        'cpf',
        'telefone',
        'celular',
        'email',
        'observacao',
        'dados',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'nascimento' => 'date',
        'dados' => 'array',
    ];

    /**
     * Nascimento formatado (d/m/Y).
     */
    public function getNascimentoFormatadoAttribute(): ?string
    {
        return $this->nascimento?->format('d/m/Y');
    }

    /**
     * Idade do paciente.
     */
    public function getIdadeAttribute(): ?int
    {
        return $this->nascimento?->age;
    }

    /**
     * CPF formatado (000.000.000-00).
     */
    public function getCpfFormatadoAttribute(): ?string
    {
        $cpf = preg_replace('/\D+/', '', (string) $this->cpf);

        if (strlen($cpf) !== 11) {
            return $this->cpf;
        }

        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf);
    }

    /**
     * Telefone formatado.
     */
    public function getTelefoneFormatadoAttribute(): ?string
    {
        return $this->formatarTelefone($this->telefone);
    }

    /**
     * Celular formatado.
     */
    public function getCelularFormatadoAttribute(): ?string
    {
        return $this->formatarTelefone($this->celular);
    }

    /**
     * Formata telefone/celular conforme a quantidade de dígitos.
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
