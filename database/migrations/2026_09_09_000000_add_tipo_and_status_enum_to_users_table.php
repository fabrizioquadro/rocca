<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Recria a coluna status como ENUM (Ativo/Inativo/Excluído)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['ativo', 'inativo', 'excluido'])
                ->default('ativo')
                ->after('remember_token');

            // Novo campo tipo (Administrador/Secretaria/Enfermagem)
            $table->enum('tipo', ['administrador', 'secretaria', 'enfermagem'])
                ->default('administrador')
                ->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'tipo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('ativo')->after('remember_token');
        });
    }
};
