<?php

namespace Tests\Feature;

use App\Enums\TipoLogPrescricao;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Anexos em vários campos de arquivo (botão "Adicionar mais arquivos"), no
 * cadastro e na edição da prescrição: os campos usam o mesmo name (anexos[]),
 * então os arquivos de todos eles chegam juntos para o controller.
 *
 * @see \App\Enums\TipoLogPrescricao::AnexoEnviado
 */
class PrescricaoAnexosMultiplosTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, string> */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $relativo) {
            File::delete(public_path($relativo));
        }

        $this->temporarios = [];

        parent::tearDown();
    }

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    private function prescricao(): ?Prescricao
    {
        return Prescricao::orderByDesc('id')->first();
    }

    /**
     * Anexo já gravado na prescrição, com arquivo de verdade em public/.
     */
    private function anexoAtual(Prescricao $prescricao, string $nome = 'receita_antiga.pdf'): void
    {
        $relativo = 'uploads/prescricoes/teste_anexo_'.uniqid().'.pdf';

        File::ensureDirectoryExists(public_path('uploads/prescricoes'));
        File::put(public_path($relativo), '%PDF-1.4 teste');
        $this->temporarios[] = $relativo;

        $prescricao->anexos()->create([
            'nome' => $nome,
            'arquivo' => $relativo,
            'mime' => 'application/pdf',
            'tamanho' => File::size(public_path($relativo)),
            'user_id' => $this->usuario()?->id,
        ]);
    }

    public function test_pagina_de_cadastro_tem_o_campo_de_anexos_com_o_botao_de_mais_arquivos(): void
    {
        $usuario = $this->usuario();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário na base.');
        }

        // Sem a Feegow o cadastro abre do mesmo jeito (médicos ficam em branco)
        Http::fake();

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.create'));

        $pagina->assertOk()
            ->assertSee('Adicionar mais arquivos')
            ->assertSee('name="anexos[]"', false)
            ->assertSee('data-anexo-campo', false)
            ->assertSee('data-anexo-adicionar', false)
            // O modelo do campo novo (usado pelo botão) precisa estar na página
            ->assertSee('data-anexo-template', false)
            ->assertSee('anexos[]', false)
            // A regra do anexo obrigatório (ampola/miligrama) continua na tela
            ->assertSee('data-anexo-obrigatorio', false)
            ->assertSee('data-aviso-anexo', false);
    }

    public function test_pagina_de_edicao_tem_o_campo_de_anexos_e_lista_os_atuais(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        Http::fake();

        $prescricao->anexos()->delete();
        $this->anexoAtual($prescricao, 'exame_antigo.pdf');

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.edit', $prescricao));

        $pagina->assertOk()
            ->assertSee('Adicionar mais arquivos')
            ->assertSee('name="anexos[]"', false)
            ->assertSee('data-anexo-adicionar', false)
            // O formulário precisa aceitar arquivo (enctype)
            ->assertSee('enctype="multipart/form-data"', false)
            // O que já está anexado aparece para o usuário não subir de novo
            ->assertSee('exame_antigo.pdf')
            ->assertSee('Já anexados nesta prescrição');
    }

    public function test_edicao_salva_os_anexos_de_varios_campos_e_registra_no_historico(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();
        $clinica = Clinica::orderByDesc('id')->first();

        if (! $usuario || ! $prescricao || ! $clinica) {
            $this->markTestSkipped('Sem usuário, prescrição ou clínica na base.');
        }

        Http::fake();

        $prescricao->anexos()->delete();

        $resposta = $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'medico_id' => $prescricao->medico_id,
            'medico_nome' => $prescricao->medico_nome,
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            'agendamento' => $prescricao->agendamento,
            'observacoes' => $prescricao->observacoes,
            // Dois campos de arquivo: o primeiro com 2 arquivos, o segundo com 1
            'anexos' => [
                UploadedFile::fake()->create('exame_sangue.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('receita_medica.pdf', 30, 'application/pdf'),
                UploadedFile::fake()->create('documento_pessoal.pdf', 10, 'application/pdf'),
            ],
        ]);

        $resposta->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('success', 'Prescrição atualizada e 3 anexo(s) enviado(s).');

        $anexos = $prescricao->anexos()->get();

        $this->assertCount(3, $anexos, 'Os três arquivos deveriam ser gravados.');

        foreach (['exame_sangue.pdf', 'receita_medica.pdf', 'documento_pessoal.pdf'] as $nome) {
            $this->assertContains($nome, $anexos->pluck('nome')->all());
        }

        foreach ($anexos as $anexo) {
            $this->temporarios[] = $anexo->arquivo;

            $this->assertTrue(File::exists(public_path($anexo->arquivo)), 'O arquivo deveria estar em public/.');
            $this->assertSame($usuario->id, (int) $anexo->user_id);
        }

        // Um registro de histórico por arquivo
        foreach (['exame_sangue.pdf', 'receita_medica.pdf', 'documento_pessoal.pdf'] as $nome) {
            $this->assertDatabaseHas('prescricao_logs', [
                'prescricao_id' => $prescricao->id,
                'descricao' => 'Anexo enviado: '.$nome.'.',
            ]);
        }
    }

    public function test_edicao_sem_arquivos_mantem_os_anexos_atuais(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();
        $clinica = Clinica::orderByDesc('id')->first();

        if (! $usuario || ! $prescricao || ! $clinica) {
            $this->markTestSkipped('Sem usuário, prescrição ou clínica na base.');
        }

        Http::fake();

        $prescricao->anexos()->delete();
        $this->anexoAtual($prescricao);

        $logsAntes = PrescricaoLog::where('prescricao_id', $prescricao->id)
            ->where('acao', TipoLogPrescricao::AnexoEnviado->value)
            ->count();

        $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'medico_id' => $prescricao->medico_id,
            'medico_nome' => $prescricao->medico_nome,
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            'agendamento' => 'Sexta-feira às 10h',
            'observacoes' => $prescricao->observacoes,
        ])->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('success', 'Prescrição atualizada com sucesso.');

        $this->assertSame(1, $prescricao->anexos()->count(), 'Nenhum anexo deveria ser apagado.');

        $this->assertSame(
            $logsAntes,
            PrescricaoLog::where('prescricao_id', $prescricao->id)
                ->where('acao', TipoLogPrescricao::AnexoEnviado->value)
                ->count(),
            'Salvar sem anexo não deveria criar histórico de anexo enviado.'
        );
    }

    public function test_anexo_com_formato_nao_permitido_e_recusado(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();
        $clinica = Clinica::orderByDesc('id')->first();

        if (! $usuario || ! $prescricao || ! $clinica) {
            $this->markTestSkipped('Sem usuário, prescrição ou clínica na base.');
        }

        $prescricao->anexos()->delete();

        $this->actingAs($usuario)
            ->from(route('prescricoes.edit', $prescricao))
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $clinica->id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'anexos' => [UploadedFile::fake()->create('script.exe', 5, 'application/x-msdownload')],
            ])
            ->assertRedirect(route('prescricoes.edit', $prescricao))
            ->assertSessionHasErrors('anexos.0');

        $this->assertSame(0, $prescricao->anexos()->count());
    }
}
