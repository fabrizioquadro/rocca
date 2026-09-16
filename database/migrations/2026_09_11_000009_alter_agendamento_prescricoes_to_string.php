<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O agendamento da prescrição é um texto livre (ex.: "Terça-feira às 14h"),
     * não uma data/hora.
     */
    public function up(): void
    {
        // Guarda o que existir para não perder nada ao trocar o tipo
        $valores = DB::table('prescricoes')->pluck('agendamento', 'id')->all();

        Schema::table('prescricoes', function (Blueprint $table) {
            $table->dropColumn('agendamento');
        });

        Schema::table('prescricoes', function (Blueprint $table) {
            $table->string('agendamento', 100)->nullable()->after('tipo_atendimento');
        });

        foreach ($valores as $id => $valor) {
            if ($valor) {
                DB::table('prescricoes')->where('id', $id)->update(['agendamento' => $valor]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricoes', function (Blueprint $table) {
            $table->dropColumn('agendamento');
        });

        Schema::table('prescricoes', function (Blueprint $table) {
            $table->dateTime('agendamento')->nullable()->after('tipo_atendimento');
        });
    }
};
