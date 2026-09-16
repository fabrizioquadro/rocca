<?php

namespace App\Http\Controllers;

use App\Models\Clinica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClinicaController extends Controller
{
    /**
     * Lista as clínicas cadastradas.
     */
    public function index()
    {
        $clinicas = Clinica::query()
            ->orderBy('nome')
            ->get();

        return view('clinicas.index', compact('clinicas'));
    }

    /**
     * Busca as clínicas (matriz + unidades) na Feegow e importa/atualiza localmente.
     */
    public function buscarFeegow(Request $request)
    {
        $baseUrl = config('services.feegow.base_url');
        $token = config('services.feegow.token');

        if (! $baseUrl || ! $token) {
            return back()->with('error', 'Integração com a Feegow ainda não está configurada (endpoint/token).');
        }

        try {
            $response = Http::withHeaders([
                'X-Access-Token' => $token,
            ])
                ->timeout(30)
                ->get(rtrim($baseUrl, '/').'/company/list-unity');

            if (! $response->successful()) {
                return back()->with('error', 'A Feegow retornou erro: HTTP '.$response->status().'.');
            }

            $content = $response->json('content');

            // Matriz + unidades = clínicas
            $empresas = array_merge(
                is_array($content['matriz'] ?? null) ? $content['matriz'] : [],
                is_array($content['unidades'] ?? null) ? $content['unidades'] : []
            );

            $contador = 0;
            foreach ($empresas as $empresa) {
                if (empty($empresa['nome_fantasia'])) {
                    continue;
                }

                Clinica::updateOrCreate(
                    ['id_feegow' => $empresa['unidade_id'] ?? 0],
                    [
                        'nome' => $empresa['nome_fantasia'],
                        'cnpj' => $empresa['cnpj'] ?? null,
                    ]
                );

                $contador++;
            }

            $mensagem = $contador > 0
                ? $contador.' clínica(s) importada(s)/atualizada(s) da Feegow com sucesso.'
                : 'Nenhuma clínica nova foi encontrada na Feegow.';

            return back()->with('success', $mensagem);
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível conectar na Feegow: '.$e->getMessage());
        }
    }
}
