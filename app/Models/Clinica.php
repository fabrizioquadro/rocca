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
}
