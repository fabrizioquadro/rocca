<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\FeegowService;
use Illuminate\Support\Carbon;

class PacienteController extends Controller
{
    public function __construct(private FeegowService $feegow)
    {
    }

    /**
     * Lista os pacientes trazidos da Feegow.
     */
    public function index()
    {
        $pacientes = Paciente::query()
            ->orderBy('nome')
            ->get();

        return view('pacientes.index', compact('pacientes'));
    }

    /**
     * Importa/atualiza os pacientes da Feegow.
     */
    public function sincronizar()
    {
        try {
            $lista = $this->feegow->listarPacientes();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($lista)) {
            return back()->with('error', 'A Feegow não retornou pacientes.');
        }

        $novos = 0;
        $atualizados = 0;

        foreach ($lista as $dados) {
            $pacienteId = $dados['patient_id'] ?? null;

            if (! $pacienteId) {
                continue;
            }

            $paciente = Paciente::firstOrNew(['paciente_id' => $pacienteId]);
            $jaExiste = $paciente->exists;

            $paciente->fill($this->mapear($dados, $paciente));
            $paciente->save();

            $jaExiste ? $atualizados++ : $novos++;
        }

        return redirect()
            ->route('pacientes.index')
            ->with('success', $novos.' paciente(s) importado(s) e '.$atualizados.' atualizado(s) da Feegow.');
    }

    /**
     * Detalhes de um paciente.
     */
    public function show(Paciente $paciente)
    {
        return view('pacientes.show', compact('paciente'));
    }

    /**
     * Busca os dados completos do paciente na Feegow (CPF, telefone e etc.).
     */
    public function atualizarDados(Paciente $paciente)
    {
        try {
            $dados = $this->feegow->buscarPaciente((int) $paciente->paciente_id);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if (empty($dados)) {
            return back()->with('error', 'A Feegow não retornou os dados deste paciente.');
        }

        $paciente->fill($this->mapear($dados, $paciente));

        $paciente->save();

        return back()->with('success', 'Dados do paciente atualizados pela Feegow.');
    }

    /**
     * Converte o JSON da Feegow nos campos do paciente.
     * Mantém o que já existe quando a Feegow não traz o dado.
     */
    private function mapear(array $dados, ?Paciente $atual = null): array
    {
        return [
            'nome' => $this->primeiroValor($dados['nome'] ?? null) ?? $atual?->nome ?? 'Sem nome',
            'nascimento' => $this->converterData($this->primeiroValor($dados['nascimento'] ?? null))
                ?? $atual?->nascimento?->format('Y-m-d'),
            'cpf' => $this->somenteDigitos($this->primeiroValor($dados['cpf'] ?? ($dados['documento'] ?? null)))
                ?? $atual?->cpf,
            'telefone' => $this->somenteDigitos($this->primeiroValor(
                $dados['telefone'] ?? ($dados['telefones'] ?? ($dados['fone'] ?? null))
            )) ?? $atual?->telefone,
            'celular' => $this->somenteDigitos($this->primeiroValor($dados['celular'] ?? ($dados['celulares'] ?? null)))
                ?? $atual?->celular,
            'email' => $this->primeiroValor($dados['email'] ?? null) ?? $atual?->email,
            'dados' => array_merge($atual?->dados ?? [], $dados),
        ];
    }

    /**
     * Pega o primeiro valor preenchido (a Feegow devolve alguns campos como array/objeto).
     */
    private function primeiroValor($valor): ?string
    {
        if (is_array($valor)) {
            foreach ($valor as $item) {
                if (is_scalar($item) && filled($item)) {
                    return (string) $item;
                }
            }

            return null;
        }

        return is_scalar($valor) && filled($valor) ? (string) $valor : null;
    }

    /**
     * Converte a data recebida da Feegow (d/m/Y, d-m-Y ou Y-m-d) para o banco.
     */
    private function converterData($valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $valor = trim((string) $valor);

        try {
            if (preg_match('/^\d{2}[\/-]\d{2}[\/-]\d{4}$/', $valor)) {
                return Carbon::createFromFormat('d/m/Y', str_replace('-', '/', $valor))->format('Y-m-d');
            }

            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Mantém apenas os dígitos de um valor (CPF/telefone).
     */
    private function somenteDigitos($valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', (string) $valor);

        return $digitos === '' ? null : $digitos;
    }
}
