<?php

namespace Tests\Feature;

use App\Enums\TipoLogPrescricao;
use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\PrescricaoAnexo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Envio dos anexos da prescrição para o prontuário do paciente na Feegow
 * (POST /patient/upload-base64), com controle de envio único por arquivo.
 */
class FeegowAnexosTest extends TestCase
{
    use DatabaseTransactions;

    /** PNG 1x1 usado como arquivo de teste. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    /** @var array<int, string> */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $caminho) {
            File::delete(public_path($caminho));
        }

        $this->temporarios = [];

        parent::tearDown();
    }

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    /**
     * Prescrição de um paciente já integrado à Feegow, sem anexos, para o
     * teste controlar exatamente o que será enviado.
     *
     * @return array{0: User, 1: Prescricao}
     */
    private function cenario(): array
    {
        $usuario = $this->usuario();

        $prescricao = Prescricao::whereHas('paciente', fn ($query) => $query->where('paciente_id', '>', 0))->first();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição de paciente com cadastro na Feegow.');
        }

        // Os anexos reais não existem nesta máquina: o teste trabalha com o seu.
        $prescricao->anexos()->delete();

        return [$usuario, $prescricao->fresh()];
    }

    /**
     * Cria um anexo com arquivo de verdade em public/ (removido no tearDown).
     */
    private function anexo(Prescricao $prescricao, array $atributos = []): PrescricaoAnexo
    {
        $relativo = 'uploads/prescricoes/teste_feegow_'.uniqid().'.png';

        File::ensureDirectoryExists(public_path('uploads/prescricoes'));
        File::put(public_path($relativo), base64_decode(self::PNG));
        $this->temporarios[] = $relativo;

        return PrescricaoAnexo::create([
            'prescricao_id' => $prescricao->id,
            'nome' => 'exame_teste.png',
            'arquivo' => $relativo,
            'mime' => 'image/png',
            'tamanho' => strlen(base64_decode(self::PNG)),
            'user_id' => $this->usuario()?->id,
        ] + $atributos);
    }

    public function test_envia_o_anexo_em_base64_e_grava_o_fileid(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        Http::fake([
            '*/patient/upload-base64*' => Http::response(['success' => true, 'fileId' => 4321, 'content' => 'Anexo criado'], 200),
        ]);

        $anexo = $this->anexo($prescricao);

        $this->actingAs($usuario)
            ->post(route('prescricoes.anexos.feegow', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('success');

        $anexo->refresh();

        $this->assertTrue($anexo->enviado_para_feegow);
        $this->assertSame('4321', (string) $anexo->feegow_file_id);
        $this->assertNull($anexo->feegow_erro);

        Http::assertSent(function ($request) use ($prescricao) {
            $dados = $request->data();

            $prefixo = 'data:image/png;base64,';

            return str_contains($request->url(), '/patient/upload-base64')
                && ! str_contains($request->url(), 'base64_file')            // carga grande não vai na query string
                && $request->hasHeader('X-Access-Token')
                && ($dados['paciente_id'] ?? null) === (int) $prescricao->paciente->paciente_id
                && ($dados['arquivo_descricao'] ?? null) === 'exame_teste.png'
                && str_starts_with((string) ($dados['base64_file'] ?? ''), $prefixo)
                && base64_decode(substr($dados['base64_file'], strlen($prefixo))) === base64_decode(self::PNG);
        });

        $this->assertDatabaseHas('prescricao_logs', [
            'prescricao_id' => $prescricao->id,
            'acao' => TipoLogPrescricao::FeegowEnviado->value,
        ]);
    }

    public function test_nao_reenvia_anexo_ja_enviado(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        Http::fake();

        $this->anexo($prescricao, ['feegow_enviado_em' => now(), 'feegow_file_id' => '999']);

        $this->actingAs($usuario)
            ->post(route('prescricoes.anexos.feegow', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('info');

        Http::assertNothingSent();
    }

    public function test_registra_a_falha_da_feegow_e_permite_reenviar(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        Http::fake([
            '*/patient/upload-base64*' => Http::response(['success' => false, 'content' => 'Paciente não encontrado'], 422),
        ]);

        $anexo = $this->anexo($prescricao);

        $this->actingAs($usuario)
            ->post(route('prescricoes.anexos.feegow', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('error');

        $anexo->refresh();

        $this->assertFalse($anexo->enviado_para_feegow);
        $this->assertStringContainsString('422', (string) $anexo->feegow_erro);

        $this->assertDatabaseHas('prescricao_logs', [
            'prescricao_id' => $prescricao->id,
            'acao' => TipoLogPrescricao::FeegowErro->value,
        ]);
    }

    public function test_anexo_sem_arquivo_no_servidor_registra_erro_sem_chamar_a_feegow(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        Http::fake();

        $anexo = $this->anexo($prescricao);
        File::delete(public_path($anexo->arquivo));

        $this->actingAs($usuario)
            ->post(route('prescricoes.anexos.feegow', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('error');

        $this->assertStringContainsString('não encontrado', (string) $anexo->fresh()->feegow_erro);

        Http::assertNothingSent();
    }
}
