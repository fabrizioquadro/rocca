<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ClinicaController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstoqueBaixaController;
use App\Http\Controllers\EstoqueBaixaVasilhameController;
use App\Http\Controllers\EstoqueBuscaController;
use App\Http\Controllers\EstoqueEntradaController;
use App\Http\Controllers\EstoqueEtiquetaController;
use App\Http\Controllers\EstoqueSaldoController;
use App\Http\Controllers\EstoqueTransferenciaController;
use App\Http\Controllers\FinanceiroController;
use App\Http\Controllers\FinanceiroPagamentoController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\MedicamentoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PrescricaoController;
use App\Http\Controllers\PrescricaoSemanaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Página pública de login (raiz do sistema)
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }

    return view('auth.login');
});

// Área logada (dashboard com as áreas operacionais)
Route::get('/home', [DashboardController::class, 'index'])->middleware('auth')->name('home');

// Autenticação
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Esqueci minha senha
    Route::get('/esqueceu-senha', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/esqueceu-senha', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    // Redefinir senha
    Route::get('/resetar-senha/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/resetar-senha', [ResetPasswordController::class, 'reset'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Área logada
Route::middleware('auth')->group(function () {
    // Perfil do usuário logado
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');

    // Alterar senha
    Route::get('/perfil/alterar-senha', [PerfilController::class, 'editSenha'])->name('perfil.senha');
    Route::put('/perfil/alterar-senha', [PerfilController::class, 'updateSenha'])->name('perfil.senha.update');

    Route::get('/clinicas', [ClinicaController::class, 'index'])->name('clinicas.index');
    Route::post('/clinicas/buscar-feegow', [ClinicaController::class, 'buscarFeegow'])->name('clinicas.buscarFeegow');

    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/criar', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->name('usuarios.show');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');

    // Fornecedores
    Route::get('/fornecedores', [FornecedorController::class, 'index'])->name('fornecedores.index');
    Route::get('/fornecedores/criar', [FornecedorController::class, 'create'])->name('fornecedores.create');
    Route::post('/fornecedores', [FornecedorController::class, 'store'])->name('fornecedores.store');
    Route::get('/fornecedores/{fornecedor}', [FornecedorController::class, 'show'])->name('fornecedores.show');
    Route::get('/fornecedores/{fornecedor}/editar', [FornecedorController::class, 'edit'])->name('fornecedores.edit');
    Route::put('/fornecedores/{fornecedor}', [FornecedorController::class, 'update'])->name('fornecedores.update');
    Route::delete('/fornecedores/{fornecedor}', [FornecedorController::class, 'destroy'])->name('fornecedores.destroy');

    // Grupos
    Route::get('/grupos', [GrupoController::class, 'index'])->name('grupos.index');
    Route::get('/grupos/criar', [GrupoController::class, 'create'])->name('grupos.create');
    Route::post('/grupos', [GrupoController::class, 'store'])->name('grupos.store');
    Route::get('/grupos/{grupo}', [GrupoController::class, 'show'])->name('grupos.show');
    Route::get('/grupos/{grupo}/editar', [GrupoController::class, 'edit'])->name('grupos.edit');
    Route::put('/grupos/{grupo}', [GrupoController::class, 'update'])->name('grupos.update');
    Route::delete('/grupos/{grupo}', [GrupoController::class, 'destroy'])->name('grupos.destroy');

    // Medicamentos
    Route::get('/medicamentos', [MedicamentoController::class, 'index'])->name('medicamentos.index');
    Route::get('/medicamentos/criar', [MedicamentoController::class, 'create'])->name('medicamentos.create');
    Route::post('/medicamentos', [MedicamentoController::class, 'store'])->name('medicamentos.store');
    Route::get('/medicamentos/{medicamento}', [MedicamentoController::class, 'show'])->name('medicamentos.show');
    Route::get('/medicamentos/{medicamento}/editar', [MedicamentoController::class, 'edit'])->name('medicamentos.edit');
    Route::put('/medicamentos/{medicamento}', [MedicamentoController::class, 'update'])->name('medicamentos.update');
    Route::delete('/medicamentos/{medicamento}', [MedicamentoController::class, 'destroy'])->name('medicamentos.destroy');

    // Combos
    Route::get('/combos', [ComboController::class, 'index'])->name('combos.index');
    Route::get('/combos/criar', [ComboController::class, 'create'])->name('combos.create');
    Route::post('/combos', [ComboController::class, 'store'])->name('combos.store');
    Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');
    Route::get('/combos/{combo}/editar', [ComboController::class, 'edit'])->name('combos.edit');
    Route::put('/combos/{combo}', [ComboController::class, 'update'])->name('combos.update');
    Route::delete('/combos/{combo}', [ComboController::class, 'destroy'])->name('combos.destroy');

    // Estoque — Entradas
    Route::prefix('estoque')->name('estoque.')->group(function () {
        Route::get('/entradas', [EstoqueEntradaController::class, 'index'])->name('entradas.index');
        Route::get('/entradas/criar', [EstoqueEntradaController::class, 'create'])->name('entradas.create');
        Route::get('/entradas/gerar-codigo-barras', [EstoqueEntradaController::class, 'gerarCodigoBarras'])->name('entradas.gerarCodigoBarras');
        Route::get('/entradas/verificar-codigo-barras', [EstoqueEntradaController::class, 'verificarCodigoBarras'])->name('entradas.verificarCodigoBarras');
        Route::post('/entradas', [EstoqueEntradaController::class, 'store'])->name('entradas.store');
        Route::get('/entradas/{entrada}', [EstoqueEntradaController::class, 'show'])->name('entradas.show');
        Route::delete('/entradas/{entrada}', [EstoqueEntradaController::class, 'destroy'])->name('entradas.destroy');

        // Etiquetas (código de barras) da entrada — bobina 100mm, 3 etiquetas de 30x15mm por linha
        Route::get('/entradas/{entrada}/etiquetas', [EstoqueEtiquetaController::class, 'entrada'])->name('entradas.etiquetas');
        Route::get('/entradas/{entrada}/itens/{entrada_item}/etiquetas', [EstoqueEtiquetaController::class, 'item'])->name('entradas.itens.etiquetas');

        // Anexos da entrada (nota fiscal, recibo e etc.)
        Route::post('/entradas/{entrada}/anexos', [EstoqueEntradaController::class, 'storeAnexo'])->name('entradas.anexos.store');
        Route::delete('/entradas/{entrada}/anexos/{anexo}', [EstoqueEntradaController::class, 'destroyAnexo'])->name('entradas.anexos.destroy');

        // Baixas (perda, avaria, quebra, vencimento e etc.)
        Route::get('/baixas', [EstoqueBaixaController::class, 'index'])->name('baixas.index');
        Route::get('/baixas/criar', [EstoqueBaixaController::class, 'create'])->name('baixas.create');
        Route::post('/baixas', [EstoqueBaixaController::class, 'store'])->name('baixas.store');
        Route::get('/baixas/{baixa}', [EstoqueBaixaController::class, 'show'])->name('baixas.show');
        Route::delete('/baixas/{baixa}', [EstoqueBaixaController::class, 'destroy'])->name('baixas.destroy');

        // Transferências entre clínicas
        Route::get('/transferencias', [EstoqueTransferenciaController::class, 'index'])->name('transferencias.index');
        Route::get('/transferencias/criar', [EstoqueTransferenciaController::class, 'create'])->name('transferencias.create');
        Route::post('/transferencias', [EstoqueTransferenciaController::class, 'store'])->name('transferencias.store');
        Route::get('/transferencias/{transferencia}', [EstoqueTransferenciaController::class, 'show'])->name('transferencias.show');
        Route::delete('/transferencias/{transferencia}', [EstoqueTransferenciaController::class, 'destroy'])->name('transferencias.destroy');

        // Busca de código de barras (medicamento, lote, vencimento e saldo)
        Route::get('/codigo-barras', [EstoqueBuscaController::class, 'buscarCodigoBarras'])->name('buscarCodigoBarras');

        // Situação do vasilhame (medicamento miligrama) para a tela de aplicação
        Route::get('/vasilhame', [EstoqueBuscaController::class, 'buscarVasilhame'])->name('buscarVasilhame');

        // Baixa de medicamentos ABERTOS (vasilhames em uso) — não tem exclusão
        Route::get('/baixas-abertos', [EstoqueBaixaVasilhameController::class, 'index'])->name('baixas-abertos.index');
        Route::get('/baixas-abertos/criar', [EstoqueBaixaVasilhameController::class, 'create'])->name('baixas-abertos.create');
        Route::post('/baixas-abertos', [EstoqueBaixaVasilhameController::class, 'store'])->name('baixas-abertos.store');
        Route::get('/baixas-abertos/{baixa_vasilhame}', [EstoqueBaixaVasilhameController::class, 'show'])->name('baixas-abertos.show');

        // Saldo de estoque (posição atual por medicamento, código de barras e lote)
        Route::get('/saldo', [EstoqueSaldoController::class, 'index'])->name('saldo.index');
        Route::get('/inventario/{entrada_item}', [EstoqueSaldoController::class, 'inventario'])->name('saldo.inventario');
        Route::get('/saldo/{medicamento}', [EstoqueSaldoController::class, 'show'])->name('saldo.show');
    });

    // Pacientes (importados da Feegow)
    Route::get('/pacientes', [PacienteController::class, 'index'])->name('pacientes.index');
    Route::post('/pacientes/sincronizar', [PacienteController::class, 'sincronizar'])->name('pacientes.sincronizar');
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show'])->name('pacientes.show');
    Route::post('/pacientes/{paciente}/atualizar', [PacienteController::class, 'atualizarDados'])->name('pacientes.atualizar');

    // Observação interna do paciente (não vem da Feegow)
    Route::put('/pacientes/{paciente}/observacao', [PacienteController::class, 'atualizarObservacao'])->name('pacientes.observacao.update');

    // Prescrições
    Route::get('/prescricoes', [PrescricaoController::class, 'index'])->name('prescricoes.index');
    Route::get('/prescricoes/criar', [PrescricaoController::class, 'create'])->name('prescricoes.create');
    Route::get('/prescricoes/pacientes', [PrescricaoController::class, 'buscarPacientes'])->name('prescricoes.pacientes');
    Route::post('/prescricoes', [PrescricaoController::class, 'store'])->name('prescricoes.store');
    Route::get('/prescricoes/{prescricao}', [PrescricaoController::class, 'show'])->name('prescricoes.show');
    Route::delete('/prescricoes/{prescricao}', [PrescricaoController::class, 'destroy'])->name('prescricoes.destroy');

    // Anexos da prescrição (exames, receitas, documentos e etc.)
    Route::post('/prescricoes/{prescricao}/anexos', [PrescricaoController::class, 'storeAnexo'])->name('prescricoes.anexos.store');
    Route::delete('/prescricoes/{prescricao}/anexos/{anexo}', [PrescricaoController::class, 'destroyAnexo'])->name('prescricoes.anexos.destroy');

    // Observações da prescrição (linha do tempo com autor e data/hora)
    Route::post('/prescricoes/{prescricao}/observacoes', [PrescricaoController::class, 'storeObservacao'])->name('prescricoes.observacoes.store');

    // Semanas da prescrição (acessar, editar e excluir)
    Route::get('/prescricoes/{prescricao}/semanas/{semana}', [PrescricaoSemanaController::class, 'show'])->name('prescricoes.semanas.show');
    Route::get('/prescricoes/{prescricao}/semanas/{semana}/editar', [PrescricaoSemanaController::class, 'edit'])->name('prescricoes.semanas.edit');
    Route::put('/prescricoes/{prescricao}/semanas/{semana}', [PrescricaoSemanaController::class, 'update'])->name('prescricoes.semanas.update');
    Route::delete('/prescricoes/{prescricao}/semanas/{semana}', [PrescricaoSemanaController::class, 'destroy'])->name('prescricoes.semanas.destroy');

    // Registro/reenvio da aplicação da semana como agendamento na Feegow
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/feegow', [PrescricaoSemanaController::class, 'registrarNaFeegow'])->name('prescricoes.semanas.feegow');

    // Envio da semana para a fila de atendimento (exige parcela paga ou autorização de administrador)
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/fila-atendimento', [PrescricaoSemanaController::class, 'enviarParaFila'])->name('prescricoes.semanas.fila');

    // Volta da semana para o agendamento (paciente não compareceu)
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/devolver-agendada', [PrescricaoSemanaController::class, 'devolverParaAgendada'])->name('prescricoes.semanas.devolver');

    // Atendimento da enfermagem (início do atendimento da semana)
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/iniciar-atendimento', [PrescricaoSemanaController::class, 'iniciarAtendimento'])->name('prescricoes.semanas.atendimento.iniciar');

    // Registro da aplicação (código de barras, lote, vencimento e observações)
    Route::get('/prescricoes/{prescricao}/semanas/{semana}/aplicar', [PrescricaoSemanaController::class, 'formAplicacao'])->name('prescricoes.semanas.aplicar.form');
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/aplicar', [PrescricaoSemanaController::class, 'aplicar'])->name('prescricoes.semanas.aplicar');

    // Abertura do vasilhame (medicamento miligrama) durante a aplicação
    Route::post('/prescricoes/{prescricao}/semanas/{semana}/vasilhames', [PrescricaoSemanaController::class, 'abrirVasilhame'])->name('prescricoes.semanas.vasilhames.store');

    // Pagamentos do financeiro (alocados da 1ª para a última parcela)
    Route::post('/prescricoes/{prescricao}/pagamentos', [FinanceiroPagamentoController::class, 'store'])->name('prescricoes.pagamentos.store');
    Route::delete('/prescricoes/{prescricao}/pagamentos/{pagamento}', [FinanceiroPagamentoController::class, 'destroy'])->name('prescricoes.pagamentos.destroy');

    // Ajustes do financeiro (desconto, adicional e observação)
    Route::put('/prescricoes/{prescricao}/financeiro', [FinanceiroController::class, 'update'])->name('prescricoes.financeiro.update');

    // Relatórios (estoque e vasilhames abertos, aplicações, pendências e financeiro).
    // Cada relatório ganha também as rotas de exportação .pdf e .xlsx.
    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/', [RelatorioController::class, 'index'])->name('index');

        foreach (array_keys(RelatorioController::RELATORIOS) as $slug) {
            Route::get("/{$slug}", [RelatorioController::class, 'exibir'])
                ->name($slug)
                ->defaults('relatorio', $slug);

            Route::get("/{$slug}/pdf", [RelatorioController::class, 'pdf'])
                ->name("{$slug}.pdf")
                ->defaults('relatorio', $slug);

            Route::get("/{$slug}/xlsx", [RelatorioController::class, 'xlsx'])
                ->name("{$slug}.xlsx")
                ->defaults('relatorio', $slug);
        }
    });
});
