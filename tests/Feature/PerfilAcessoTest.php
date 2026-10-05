<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Http\Middleware\RestringirPorPerfil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PerfilAcessoTest extends TestCase
{
    private function usuario(TipoUsuario $tipo, string $nome = 'Teste'): User
    {
        $usuario = new User();
        $usuario->nome = $nome;
        $usuario->email = strtolower($nome) . '@example.com';
        $usuario->tipo = $tipo;

        return $usuario;
    }

    public function test_rota_inicial_por_perfil(): void
    {
        $this->assertSame('home', $this->usuario(TipoUsuario::Administrador)->rotaInicial());
        $this->assertSame('secretaria.index', $this->usuario(TipoUsuario::Secretaria)->rotaInicial());
        $this->assertSame('enfermagem.index', $this->usuario(TipoUsuario::Enfermagem)->rotaInicial());
    }

    public function test_dashboard_restringe_secretaria_e_enfermagem(): void
    {
        $this->actingAs($this->usuario(TipoUsuario::Secretaria))
            ->get('/home')
            ->assertForbidden();

        $this->actingAs($this->usuario(TipoUsuario::Enfermagem))
            ->get('/home')
            ->assertForbidden();
    }

    public function test_secretaria_restringe_enfermagem(): void
    {
        $this->actingAs($this->usuario(TipoUsuario::Enfermagem))
            ->get('/secretaria')
            ->assertForbidden();
    }

    public function test_areas_iniciais_seguem_as_regras_do_perfil(): void
    {
        // Dashboard: apenas administrador.
        $this->assertTrue($this->permitida(TipoUsuario::Administrador, 'perfil:administrador', 'home'));
        $this->assertFalse($this->permitida(TipoUsuario::Secretaria, 'perfil:administrador', 'home'));
        $this->assertFalse($this->permitida(TipoUsuario::Enfermagem, 'perfil:administrador', 'home'));

        // Secretaria: administrador e secretaria.
        $this->assertTrue($this->permitida(TipoUsuario::Administrador, 'perfil:administrador,secretaria', 'secretaria.index'));
        $this->assertTrue($this->permitida(TipoUsuario::Secretaria, 'perfil:administrador,secretaria', 'secretaria.index'));
        $this->assertFalse($this->permitida(TipoUsuario::Enfermagem, 'perfil:administrador,secretaria', 'secretaria.index'));
    }

    public function test_demais_areas_continuam_normais_para_todos_os_perfis(): void
    {
        foreach (['cadastros', 'estoque', 'pacientes', 'prescricoes', 'relatorios'] as $area) {
            foreach ([TipoUsuario::Administrador, TipoUsuario::Secretaria, TipoUsuario::Enfermagem] as $tipo) {
                $this->assertTrue(
                    $this->permitida($tipo, 'auth', "{$area}.index"),
                    "{$tipo->value} deveria continuar acessando {$area}."
                );
            }
        }

        // A área de enfermagem é liberada para todos os perfis.
        foreach ([TipoUsuario::Administrador, TipoUsuario::Secretaria, TipoUsuario::Enfermagem] as $tipo) {
            $this->assertTrue($this->permitida($tipo, 'auth', 'enfermagem.index'));
        }
    }

    public function test_menu_do_administrador_exibe_tudo(): void
    {
        $this->actingAs($this->usuario(TipoUsuario::Administrador, 'Admin'));
        $html = $this->menuHtml();

        foreach (['Dashboard', 'Secretária', 'Enfermagem', 'Cadastros', 'Estoque', 'Pacientes', 'Prescrições', 'Relatórios'] as $item) {
            $this->assertStringContainsString($item, $html);
        }
    }

    public function test_menu_da_secretaria_oculta_apenas_o_dashboard(): void
    {
        $this->actingAs($this->usuario(TipoUsuario::Secretaria));
        $html = $this->menuHtml();

        foreach (['Secretária', 'Enfermagem', 'Cadastros', 'Estoque', 'Pacientes', 'Prescrições', 'Relatórios'] as $item) {
            $this->assertStringContainsString($item, $html);
        }

        $this->assertStringNotContainsString('Dashboard', $html);
    }

    public function test_menu_da_enfermagem_oculta_dashboard_e_secretaria(): void
    {
        $this->actingAs($this->usuario(TipoUsuario::Enfermagem));
        $html = $this->menuHtml();

        foreach (['Enfermagem', 'Cadastros', 'Estoque', 'Pacientes', 'Prescrições', 'Relatórios'] as $item) {
            $this->assertStringContainsString($item, $html);
        }

        $this->assertStringNotContainsString('Dashboard', $html);
        $this->assertStringNotContainsString('Secretária', $html);
    }

    /**
     * Executa o middleware com os perfis informados e diz se a rota foi liberada.
     */
    private function permitida(TipoUsuario $tipo, string $perfis, string $nomeRota): bool
    {
        $rota = new Route(['GET'], '/teste', fn () => null);
        $rota->name($nomeRota);

        // Sem o middleware 'perfil' (apenas 'auth'), a rota não tem restrição.
        if (! str_contains($perfis, ':')) {
            return true;
        }

        $request = Request::create('/teste', 'GET');
        $request->setRouteResolver(fn () => $rota);
        $request->setUserResolver(fn () => $this->usuario($tipo));

        [, $listaPerfis] = array_pad(explode(':', $perfis, 2), 2, '');

        try {
            (new RestringirPorPerfil())->handle(
                $request,
                fn () => response('ok'),
                ...array_filter(explode(',', $listaPerfis))
            );
        } catch (HttpException $e) {
            return $e->getStatusCode() !== 403;
        }

        return true;
    }

    /**
     * Extrai apenas o bloco do menu lateral para não confundir com o <title>.
     */
    private function menuHtml(): string
    {
        $html = view('layouts.app')->render();

        $inicio = strpos($html, '<aside id="layout-menu"');
        $fim = strpos($html, '</aside>', $inicio);

        return substr($html, $inicio, $fim - $inicio);
    }
}
