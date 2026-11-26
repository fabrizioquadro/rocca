<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\User;
use App\Services\RelatorioExportacaoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Filtro por médico nos relatórios: aparece onde o dado tem vínculo com a
 * prescrição (origem do médico) e funciona na tela e na exportação.
 */
class FiltrosMedicoRelatoriosTest extends TestCase
{
    use DatabaseTransactions;

    private const MEDICO_A = 'Dr. Alfa Médico Teste';

    private const MEDICO_B = 'Dra. Beta Médico Teste';

    /**
     * Relatórios cujo dado vem da prescrição — logo, aceitam o filtro.
     */
    private const COM_MEDICO = [
        'aplicacoes',
        'aplicacoes-por-medicamento',
        'itens-pendentes',
        'contas-receber',
        'prescricoes',
        'recebimentos',
    ];

    /**
     * Relatórios de estoque: nenhum vínculo com médico.
     */
    private const SEM_MEDICO = [
        'vasilhames-abertos',
        'baixas-abertos',
        'movimentacoes',
        'posicao-estoque',
    ];

    private function usuario(): User
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first()
            ?? User::orderBy('id')->first();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário para autenticar.');
        }

        return $usuario;
    }

    /**
     * Duas prescrições no mês atual, cada uma com um médico distinto.
     *
     * @return array{0: Prescricao, 1: Prescricao}
     */
    private function cenario(): array
    {
        $prescricoes = Prescricao::orderBy('id')->take(2)->get();

        if ($prescricoes->count() < 2) {
            $this->markTestSkipped('Sem duas prescrições na base.');
        }

        [$a, $b] = [$prescricoes[0], $prescricoes[1]];

        // created_at não é fillable: altera pela query para trazer as duas para
        // o período padrão do relatório (mês atual)
        Prescricao::whereKey($a->id)->update(['medico_nome' => self::MEDICO_A, 'created_at' => now()]);
        Prescricao::whereKey($b->id)->update(['medico_nome' => self::MEDICO_B, 'created_at' => now()]);

        return [$a->refresh(), $b->refresh()];
    }

    public function test_filtro_de_medico_aparece_nos_relatorios_com_prescricao(): void
    {
        $usuario = $this->usuario();
        $this->cenario();

        foreach (self::COM_MEDICO as $slug) {
            $pagina = $this->actingAs($usuario)->get(route("relatorios.{$slug}"));

            $pagina->assertOk();
            $pagina->assertSee('name="medico"', false);
            $pagina->assertSee(self::MEDICO_A);
        }
    }

    public function test_relatorios_de_estoque_nao_exibem_filtro_de_medico(): void
    {
        $usuario = $this->usuario();
        $this->cenario();

        foreach (self::SEM_MEDICO as $slug) {
            $this->actingAs($usuario)
                ->get(route("relatorios.{$slug}"))
                ->assertOk()
                ->assertDontSee('name="medico"', false);
        }
    }

    public function test_prescricoes_filtra_por_medico(): void
    {
        $usuario = $this->usuario();
        [$a, $b] = $this->cenario();

        $pagina = $this->actingAs($usuario)
            ->get(route('relatorios.prescricoes', ['medico' => self::MEDICO_A]));

        $pagina->assertOk()
            ->assertSee(route('prescricoes.show', $a), false)
            ->assertDontSee(route('prescricoes.show', $b), false);

        // O select mantém o médico escolhido e oferece os demais
        $pagina->assertSee('option value="'.self::MEDICO_A.'" selected', false)
            ->assertSee('option value="'.self::MEDICO_B.'"', false);
    }

    public function test_exportacao_respeita_o_filtro_de_medico(): void
    {
        $usuario = $this->usuario();
        $this->cenario();

        $html = $this->actingAs($usuario)
            ->get(route('relatorios.prescricoes', ['medico' => self::MEDICO_A]))
            ->getContent();

        $dados = app(RelatorioExportacaoService::class)->extrair($html);
        $texto = collect($dados['linhas'])->flatten()->implode(' ').' '.implode(' ', $dados['filtros']);

        $this->assertContains(self::MEDICO_A, $dados['filtros']);
        $this->assertStringNotContainsString(self::MEDICO_B, $texto);
    }
}
