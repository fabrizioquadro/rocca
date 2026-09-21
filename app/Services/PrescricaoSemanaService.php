<?php

namespace App\Services;

use App\Enums\StatusParcela;
use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\StatusUsuario;
use App\Enums\TipoLogPrescricao;
use App\Enums\TipoMovimentacaoEstoque;
use App\Enums\TipoUsuario;
use App\Models\Combo;
use App\Models\EntradaItem;
use App\Models\EstoqueMovimentacao;
use App\Models\Financeiro;
use App\Models\FinanceiroParcela;
use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAtendimento;
use App\Models\PrescricaoSemanaItem;
use App\Models\User;
use App\Support\Numero;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Regras das semanas da prescrição: preparação dos itens vindos do
 * formulário, status inicial, numeração e sincronismo do financeiro.
 */
class PrescricaoSemanaService
{
    public function __construct(private FinanceiroPagamentoService $pagamentos)
    {
    }

    /**
     * Normaliza os itens vindos do formulário (o valor vem sempre do cadastro).
     * Devolve também se algum item exige anexo (prescrição médica).
     *
     * @param  array<int, array<string, mixed>>  $itens
     * @return array{itens: array<int, array<string, mixed>>, exige_anexo: bool}
     */
    public function prepararItens(array $itens): array
    {
        $preparados = [];
        $exigeAnexo = false;

        foreach ($itens as $item) {
            $quantidade = $this->normalizarNumero($item['quantidade'] ?? null);

            // Linha em branco (a tela sempre envia uma)
            if ($quantidade <= 0) {
                continue;
            }

            $tipo = ($item['tipo'] ?? 'medicamento') === 'combo' ? 'combo' : 'medicamento';

            if ($tipo === 'combo') {
                $combo = Combo::with('itens.medicamento')->find($item['combo_id'] ?? null);

                if (! $combo) {
                    continue;
                }

                // O combo gera aplicação se algum medicamento dele gerar
                $geraAplicacao = $combo->itens
                    ->contains(fn ($itemCombo) => (bool) $itemCombo->medicamento?->gera_aplicacao);

                if ($combo->exige_anexo) {
                    $exigeAnexo = true;
                }

                $preparados[] = [
                    'tipo' => 'combo',
                    'medicamento_id' => null,
                    'combo_id' => $combo->id,
                    'quantidade' => $quantidade,
                    'valor' => (float) $combo->valor_total,
                    'gera_aplicacao' => $geraAplicacao,
                    'status' => $geraAplicacao ? StatusSemanaItem::Aberto : StatusSemanaItem::Aplicado,
                ];

                continue;
            }

            $medicamento = Medicamento::find($item['medicamento_id'] ?? null);

            if (! $medicamento) {
                continue;
            }

            $geraAplicacao = (bool) $medicamento->gera_aplicacao;

            if ($medicamento->exige_anexo) {
                $exigeAnexo = true;
            }

            $preparados[] = [
                'tipo' => 'medicamento',
                'medicamento_id' => $medicamento->id,
                'combo_id' => null,
                'quantidade' => $quantidade,
                'valor' => (float) $medicamento->valor_venda,
                'gera_aplicacao' => $geraAplicacao,
                'status' => $geraAplicacao ? StatusSemanaItem::Aberto : StatusSemanaItem::Aplicado,
            ];
        }

        return ['itens' => $preparados, 'exige_anexo' => $exigeAnexo];
    }

    /**
     * Status inicial da semana: se algum item tem aplicação ela nasce
     * "Agendada"; se nenhum tem aplicação ela já nasce "Aplicada".
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    public function statusDaSemana(array $itens): StatusSemana
    {
        $temAplicacao = collect($itens)->contains(fn (array $item) => $item['gera_aplicacao']);

        return $temAplicacao ? StatusSemana::Agendada : StatusSemana::Aplicada;
    }

    /**
     * Converte o número digitado na tela para float.
     * Aceita "0.5" (quantidade) e "1.234,56" (valor).
     */
    public function normalizarNumero($valor): float
    {
        return Numero::paraFloat($valor);
    }

    /**
     * Renumera as semanas da prescrição (1..N) na ordem atual. Usado depois
     * de excluir uma semana, para o "1/9, 2/9..." não ficar com buracos.
     */
    public function renumerarSemanas(Prescricao $prescricao): void
    {
        $prescricao->load('semanas');

        $prescricao->semanas->values()->each(function (PrescricaoSemana $semana, int $indice) {
            if ($semana->numero !== $indice + 1) {
                $semana->update(['numero' => $indice + 1]);
            }
        });
    }

    /**
     * Uma semana gera cobrança quando tem aplicação e valor.
     */
    public function semanaCobra(PrescricaoSemana $semana): bool
    {
        return ! $semana->sem_aplicacao && $semana->valor_total > 0;
    }

    /**
     * Envia a semana para a fila de atendimento (aplicação).
     *
     * Regras: a aplicação é SEQUENCIAL (nenhuma semana anterior pode estar
     * ainda "Agendada" — dá para mandar várias em sequência, desde que a
     * anterior já tenha saído do agendamento) e a parcela da semana precisa
     * estar paga. Sem o pagamento, só é liberado com a autorização de um
     * administrador (email + senha), e fica gravado quem liberou e quando.
     */
    public function enviarParaFilaDeAtendimento(PrescricaoSemana $semana, ?string $email, ?string $senha): void
    {
        if ($semana->status !== StatusSemana::Agendada) {
            throw ValidationException::withMessages([
                'semana' => 'Só uma semana agendada pode ser enviada para a fila de atendimento.',
            ]);
        }

        // Aplicação sequencial: não passa a semana N com alguma anterior ainda agendada
        if (! $semana->pode_ir_para_fila) {
            throw ValidationException::withMessages([
                'semana' => $semana->motivo_bloqueio_fila,
            ]);
        }

        $parcela = $semana->parcelas()->first();

        // Parcela paga: segue direto, sem autorização
        if ($parcela && $parcela->esta_paga) {
            $semana->update([
                'status' => StatusSemana::FilaAplicacao,
                'chegada_em' => now(),
            ]);

            PrescricaoLog::registrar(
                $semana->prescricao_id,
                TipoLogPrescricao::EnvioFila,
                'Semana '.$semana->numero.' enviada para a fila de aplicação (parcela paga).',
                ['detalhes' => $semana->resumoParaLog()],
                $semana->id
            );

            return;
        }

        $administrador = $this->autorizarAdministrador($email, $senha);

        $semana->update([
            'status' => StatusSemana::FilaAplicacao,
            'chegada_em' => now(),
            'liberado_por_user_id' => $administrador->id,
            'liberado_em' => now(),
        ]);

        PrescricaoLog::registrar(
            $semana->prescricao_id,
            TipoLogPrescricao::EnvioFila,
            'Semana '.$semana->numero.' enviada para a fila de aplicação.',
            ['detalhes' => array_merge($semana->resumoParaLog(), [
                'Liberada sem pagamento por' => $administrador->nome,
            ])],
            $semana->id
        );
    }

    /**
     * Registra o início do atendimento da enfermagem e passa a semana para
     * "Em Atendimento". Vale para a primeira visita (fila) e para o paciente
     * que voltou depois de uma aplicação parcial.
     */
    public function iniciarAtendimento(PrescricaoSemana $semana): PrescricaoSemanaAtendimento
    {
        if (! in_array($semana->status, [StatusSemana::FilaAplicacao, StatusSemana::AplicacaoParcial], true)) {
            throw ValidationException::withMessages([
                'semana' => 'Só uma semana na fila de aplicação ou com aplicação parcial pode iniciar o atendimento.',
            ]);
        }

        if ($semana->atendimentos()->whereNull('finalizado_em')->exists()) {
            throw ValidationException::withMessages([
                'semana' => 'Já existe um atendimento em andamento para esta semana.',
            ]);
        }

        $atendimento = $semana->atendimentos()->create([
            'iniciado_em' => now(),
            'iniciado_por_user_id' => auth()->id(),
        ]);

        $semana->update([
            'status' => StatusSemana::Atendimento,
            // Semana que entrou na fila antes deste recurso não tem chegada;
            // na volta do paciente (aplicação parcial) a chegada é zerada no
            // fechamento e volta a ser registrada aqui.
            'chegada_em' => $semana->chegada_em ?? now(),
        ]);

        PrescricaoLog::registrar(
            $semana->prescricao_id,
            TipoLogPrescricao::AtendimentoIniciado,
            'Atendimento da semana '.$semana->numero.' iniciado.',
            ['detalhes' => [
                'Chegada' => $semana->chegada_em_formatada ?? '—',
                'Iniciado em' => $atendimento->iniciado_em_formatado ?? '—',
            ]],
            $semana->id
        );

        return $atendimento;
    }

    /**
     * Fecha o atendimento registrando o resultado de cada item da semana:
     * aplicado (com código de barras, lote e vencimento) ou pendente
     * (não aplicado). Cada aplicação dá baixa no estoque da clínica.
     *
     * @param  array{observacao?: string|null, itens?: array<int, array<string, mixed>>}  $dados
     */
    public function finalizarAtendimento(PrescricaoSemanaAtendimento $atendimento, array $dados): void
    {
        if (! $atendimento->em_andamento) {
            throw ValidationException::withMessages([
                'itens' => 'Este atendimento já foi finalizado.',
            ]);
        }

        // O atendimento tem um responsável: só quem iniciou registra a aplicação.
        if ((int) $atendimento->iniciado_por_user_id !== (int) auth()->id()) {
            throw ValidationException::withMessages([
                'itens' => 'Este atendimento está com '.($atendimento->iniciadoPor?->nome ?? 'outro usuário').
                    ' — só quem iniciou pode registrar a aplicação.',
            ]);
        }

        $semana = $atendimento->semana()
            ->with(['itens.medicamento', 'itens.combo.itens', 'prescricao'])
            ->first();

        $clinicaId = (int) $semana->prescricao->clinica_id;
        $informados = $dados['itens'] ?? [];

        // Só os itens que ainda não foram aplicados precisam ser decididos.
        // A "praxe" é marcar todos: aplicado ou pendente.
        $aMarcar = $semana->itens
            ->filter(fn (PrescricaoSemanaItem $item) => $item->gera_aplicacao
                && $item->status !== StatusSemanaItem::Aplicado);

        // Cada item vem da tela com o checkbox "Pendente": marcado = não
        // aplicado; desmarcado = aplicado (aí o código de barras é obrigatório).
        foreach ($aMarcar as $item) {
            $linha = $informados[$item->id] ?? [];

            $informados[$item->id]['situacao'] = filter_var($linha['pendente'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? 'pendente'
                : 'aplicado';
        }

        foreach ($aMarcar as $item) {
            $this->registrarItemDoAtendimento($atendimento, $semana, $item, $informados[$item->id], $clinicaId);
        }

        $atendimento->update([
            'finalizado_em' => now(),
            'finalizado_por_user_id' => auth()->id(),
            'observacao' => filled($dados['observacao'] ?? null) ? $dados['observacao'] : $atendimento->observacao,
        ]);

        $semana->load('itens');

        $comAplicacao = $semana->itens->filter(fn (PrescricaoSemanaItem $item) => $item->gera_aplicacao);

        // Semana fecha como Aplicada quando não sobrou nenhum medicamento para
        // aplicar; qualquer pendência deixa a semana como Aplicação Parcial
        // (o paciente volta e um novo atendimento é iniciado).
        $semana->update([
            'status' => $comAplicacao->every(fn (PrescricaoSemanaItem $item) => $item->status === StatusSemanaItem::Aplicado)
                ? StatusSemana::Aplicada
                : StatusSemana::AplicacaoParcial,
            // O paciente foi embora: a próxima visita registra uma nova chegada
            'chegada_em' => null,
        ]);

        PrescricaoLog::registrar(
            $semana->prescricao_id,
            TipoLogPrescricao::AtendimentoFinalizado,
            'Atendimento da semana '.$semana->numero.' finalizado como '.$semana->status->label().'.',
            ['detalhes' => [
                'Atendimento' => $atendimento->iniciado_em_formatado.' → '.now()->format('d/m/Y H:i'),
                'Situação da semana' => $semana->status->label(),
                'Observação do atendimento' => $semana->observacao ?? '—',
            ]],
            $semana->id
        );
    }

    /**
     * Registra um item do atendimento: aplicado (com lote) ou pendente.
     *
     * @param  array<string, mixed>  $linha
     */
    private function registrarItemDoAtendimento(
        PrescricaoSemanaAtendimento $atendimento,
        PrescricaoSemana $semana,
        PrescricaoSemanaItem $item,
        array $linha,
        int $clinicaId
    ): void {
        $observacao = filled($linha['observacao'] ?? null) ? $linha['observacao'] : null;

        if (($linha['situacao'] ?? '') !== 'aplicado') {
            $item->update([
                'status' => StatusSemanaItem::Pendente,
                'observacao' => $observacao ?? $item->observacao,
            ]);

            PrescricaoLog::registrar(
                $semana->prescricao_id,
                TipoLogPrescricao::ItemPendente,
                $item->nome.' marcado como pendente (não aplicado).',
                ['detalhes' => array_filter([
                    'Medicamento' => $item->nome,
                    'Quantidade prevista' => $item->quantidade_formatada,
                    'Motivo' => $observacao,
                ])],
                $semana->id
            );

            return;
        }

        $lote = $this->resolverLote($item, $linha['codigo_barras'] ?? null, $clinicaId);

        // A quantidade não é editável na tela: é a quantidade prescrita
        // (para ampola, já arredondada para cima em quantidade_cobranca).
        $quantidade = (int) $this->normalizarNumero($linha['quantidade'] ?? $item->quantidade_cobranca);

        // A data/hora da aplicação é sempre a da execução (hora de salvar).
        $aplicadoEm = $this->dataHora($linha['aplicado_em'] ?? null);

        if ($quantidade < 1) {
            throw ValidationException::withMessages([
                'itens' => 'Informe a quantidade aplicada de '.$item->nome.'.',
            ]);
        }

        if ($quantidade > (int) $lote->saldo_clinica) {
            throw ValidationException::withMessages([
                'itens' => 'O lote '.$lote->lote.' tem apenas '.(int) $lote->saldo_clinica
                    .' unidade(s) — não dá para aplicar '.$quantidade.' de '.$item->nome.'.',
            ]);
        }

        $atendimento->aplicacoes()->create([
            'prescricao_semana_item_id' => $item->id,
            'entrada_item_id' => $lote->id,
            'medicamento_id' => $lote->medicamento_id,
            'codigo_barras' => $lote->codigo_barras,
            'lote' => $lote->lote,
            'vencimento' => $lote->vencimento,
            'quantidade' => $quantidade,
            'aplicado_em' => $aplicadoEm,
            'user_id' => auth()->id(),
            'observacao' => $observacao,
        ]);

        EstoqueMovimentacao::create([
            'medicamento_id' => $lote->medicamento_id,
            'entrada_item_id' => $lote->id,
            'clinica_id' => $clinicaId,
            'tipo' => TipoMovimentacaoEstoque::Consumo,
            'quantidade' => -$quantidade,
            'user_id' => auth()->id(),
            'observacao' => 'Aplicação na prescrição #'.$semana->prescricao_id.' — semana '.$semana->numero,
        ]);

        $item->update([
            'status' => StatusSemanaItem::Aplicado,
            'observacao' => $observacao ?? $item->observacao,
        ]);

        PrescricaoLog::registrar(
            $semana->prescricao_id,
            TipoLogPrescricao::Aplicacao,
            $item->nome.' aplicado.',
            ['detalhes' => array_filter([
                'Medicamento' => $item->nome,
                'Quantidade aplicada' => (string) $quantidade,
                'Código de barras' => $lote->codigo_barras,
                'Lote' => $lote->lote,
                'Vencimento' => $lote->vencimento_formatado,
                'Aplicado em' => $aplicadoEm->format('d/m/Y H:i'),
                'Baixa no estoque' => (string) $quantidade.' unidade(s) na clínica',
                'Observação' => $observacao,
            ])],
            $semana->id
        );
    }

    /**
     * Descobre de qual lote saiu a aplicação pelo código de barras: precisa ter
     * saldo na clínica, ser do medicamento do item e não estar vencido.
     */
    private function resolverLote(PrescricaoSemanaItem $item, $codigo, int $clinicaId): EntradaItem
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'itens' => 'Informe o código de barras de '.$item->nome.'.',
            ]);
        }

        $lotes = EntradaItem::lotesComSaldo($codigo, $clinicaId);

        if ($lotes->isEmpty()) {
            throw ValidationException::withMessages([
                'itens' => 'Código de barras '.$codigo.' sem saldo nesta clínica.',
            ]);
        }

        $permitidos = $this->medicamentosDoItem($item);
        $doItem = $lotes->filter(fn (EntradaItem $lote) => $permitidos->contains($lote->medicamento_id));

        if ($doItem->isEmpty()) {
            throw ValidationException::withMessages([
                'itens' => 'O código de barras '.$codigo.' é de '
                    .($lotes->first()->medicamento?->nome ?? 'outro medicamento').', não de '.$item->nome.'.',
            ]);
        }

        // Lote vencido não pode ser aplicado — se todos estiverem vencidos, para tudo.
        $validos = $doItem->reject(fn (EntradaItem $lote) => $this->loteVencido($lote));

        if ($validos->isEmpty()) {
            $vencido = $doItem->first();

            throw ValidationException::withMessages([
                'itens' => 'O lote '.$vencido->lote.' do código '.$codigo.' está vencido (venc. '
                    .($vencido->vencimento_formatado ?? '—').') e não pode ser aplicado.',
            ]);
        }

        return $validos->first();
    }

    /**
     * Medicamentos que podem atender um item (no combo, qualquer componente).
     */
    private function medicamentosDoItem(PrescricaoSemanaItem $item): Collection
    {
        if ($item->tipo === 'combo') {
            return $item->combo?->itens->pluck('medicamento_id')->filter()->values() ?? collect();
        }

        return collect([$item->medicamento_id])->filter()->values();
    }

    /**
     * O lote está vencido? (comparação por dia: vence no próprio dia vale)
     */
    private function loteVencido(EntradaItem $lote): bool
    {
        return $lote->vencimento !== null
            && $lote->vencimento->copy()->startOfDay()->lt(now()->startOfDay());
    }

    /**
     * Data/hora informada na tela (datetime-local) — sem valor, usa agora.
     */
    private function dataHora($valor): Carbon
    {
        if (blank($valor)) {
            return now();
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable $e) {
            return now();
        }
    }

    /**
     * Devolve a semana para "Agendada" (paciente não compareceu). Só vale
     * enquanto nada foi aplicado: a semana volta para o agendamento e a
     * liberação sem pagamento é desfeita — para voltar à fila será exigida
     * uma nova liberação de administrador.
     */
    public function devolverParaAgendada(PrescricaoSemana $semana): void
    {
        if (! $semana->pode_voltar_para_agendada) {
            throw ValidationException::withMessages([
                'semana' => 'Só uma semana na fila de aplicação ou em atendimento, sem item aplicado, pode voltar para agendada.',
            ]);
        }

        // Enquanto alguém está atendendo, só essa pessoa mexe no atendimento.
        if (! $semana->atendimento_livre_para_mim) {
            throw ValidationException::withMessages([
                'semana' => 'O atendimento desta semana está com '
                    .($semana->atendimentoAberto?->iniciadoPor?->nome ?? 'outro usuário').'.',
            ]);
        }

        // Atendimento aberto sem nenhuma aplicação não deixa rastro no
        // histórico: a semana volta como se não tivesse começado. (Se houvesse
        // aplicação, o gate acima já teria bloqueado.)
        $semana->atendimentos()->whereNull('finalizado_em')->delete();

        // Guarda o que existia antes de limpar, só para o histórico
        $chegada = $semana->chegada_em_formatada;
        $liberacao = $semana->liberacao_descricao;

        $semana->update([
            'status' => StatusSemana::Agendada,
            'chegada_em' => null,
            'liberado_por_user_id' => null,
            'liberado_em' => null,
        ]);

        PrescricaoLog::registrar(
            $semana->prescricao_id,
            TipoLogPrescricao::Devolucao,
            'Semana '.$semana->numero.' devolvida para o agendamento.',
            ['detalhes' => [
                'Chegada anterior' => $chegada ?? '—',
                'Liberação sem pagamento' => $liberacao ?? '—',
            ]],
            $semana->id
        );
    }

    /**
     * Confere email e senha de um administrador ativo.
     */
    private function autorizarAdministrador(?string $email, ?string $senha): User
    {
        if (blank($email) || blank($senha)) {
            throw ValidationException::withMessages([
                'liberacao' => 'A parcela desta semana ainda não está paga. Informe o email e a senha de um administrador para liberar.',
            ]);
        }

        $administrador = User::where('email', $email)
            ->where('status', StatusUsuario::Ativo)
            ->where('tipo', TipoUsuario::Administrador)
            ->first();

        if (! $administrador || ! Hash::check($senha, $administrador->password)) {
            throw ValidationException::withMessages([
                'liberacao' => 'Email ou senha inválidos — ou o usuário não é um administrador ativo.',
            ]);
        }

        return $administrador;
    }

    /**
     * Mantém o financeiro coerente com as semanas atuais: cria/atualiza a
     * parcela de cada semana que cobra, remove as parcelas de semanas que
     * deixaram de cobrar e reajusta os totais.
     *
     * O desconto e o adicional informados são divididos igualmente entre as
     * parcelas (o valor de cada semana não muda a cota).
     *
     * No fim os pagamentos são redistribuídos (cascata da 1ª para a última
     * parcela), então o valor pago acompanha qualquer alteração daqui.
     *
     * @param  array{desconto_tipo?: string|null, desconto_valor?: float, adicional_valor?: float}  $ajustes
     */
    public function sincronizarFinanceiro(Prescricao $prescricao, array $ajustes = []): void
    {
        $prescricao->load(['semanas.itens.medicamento', 'semanas.itens.combo']);

        $cobraveis = $prescricao->semanas
            ->filter(fn (PrescricaoSemana $semana) => $this->semanaCobra($semana))
            ->values();

        $financeiro = Financeiro::withTrashed()
            ->where('prescricao_id', $prescricao->id)
            ->first();

        if (! $financeiro && $cobraveis->isEmpty()) {
            return;
        }

        if (! $financeiro) {
            $financeiro = new Financeiro([
                'prescricao_id' => $prescricao->id,
                'clinica_id' => $prescricao->clinica_id,
                'user_id' => auth()->id(),
            ]);
        } elseif ($financeiro->trashed()) {
            $financeiro->restore();
        }

        // Sem ajustes novos, o desconto/adicional já gravado é mantido
        $financeiro->fill([
            'clinica_id' => $prescricao->clinica_id,
            'valor_bruto' => round((float) $cobraveis->sum(fn (PrescricaoSemana $semana) => $semana->valor_total), 2),
            'desconto_tipo' => $ajustes['desconto_tipo'] ?? $financeiro->desconto_tipo,
            'desconto_valor' => $ajustes['desconto_valor'] ?? $financeiro->desconto_valor ?? 0,
            'adicional_valor' => $ajustes['adicional_valor'] ?? $financeiro->adicional_valor ?? 0,
        ])->save();

        // O desconto é definido no cadastro e CONGELA: mesmo em porcentagem,
        // alterar as semanas depois não muda o valor dele.
        if (array_key_exists('desconto_tipo', $ajustes) || array_key_exists('desconto_valor', $ajustes)) {
            $financeiro->desconto_aplicado = $financeiro->calcularDescontoDoCadastro();
            $financeiro->save();
        }

        $total = $this->valorFinal($financeiro);
        $valores = $this->ratiarIgualmente($cobraveis, $financeiro->valor_desconto, (float) $financeiro->adicional_valor);

        /** @var Collection<int, FinanceiroParcela> $parcelas */
        $parcelas = $financeiro->parcelas()->get()->keyBy('prescricao_semana_id');
        $idsCobraveis = $cobraveis->pluck('id')->all();

        // Semanas que deixaram de cobrar: a parcela sai e o dinheiro recebido
        // é redistribuído nas que restaram
        foreach ($parcelas as $parcela) {
            $continua = in_array($parcela->prescricao_semana_id, $idsCobraveis, true);

            if (! $continua) {
                $parcela->delete();
            }
        }

        foreach ($cobraveis as $indice => $semana) {
            $parcela = $parcelas->get($semana->id);
            $valoresSemana = $valores[$semana->id] ?? [
                'bruto' => $semana->valor_total,
                'desconto' => 0.0,
                'adicional' => 0.0,
                'valor' => $semana->valor_total,
            ];

            if (! $parcela) {
                $financeiro->parcelas()->create([
                    'prescricao_semana_id' => $semana->id,
                    'valor_bruto' => $valoresSemana['bruto'],
                    'valor_desconto' => $valoresSemana['desconto'],
                    'valor_adicional' => $valoresSemana['adicional'],
                    'numero' => $indice + 1,
                    'vencimento' => $semana->data_prevista,
                    'valor' => $valoresSemana['valor'],
                    'status' => StatusParcela::Aberta,
                ]);

                continue;
            }

            $parcela->numero = $indice + 1;
            $parcela->vencimento = $semana->data_prevista;
            $parcela->valor_bruto = $valoresSemana['bruto'];
            $parcela->valor_desconto = $valoresSemana['desconto'];
            $parcela->valor_adicional = $valoresSemana['adicional'];
            $parcela->valor = $valoresSemana['valor'];

            $parcela->save();
        }

        $restantes = $financeiro->parcelas()->get();

        // Sem parcelas em aberto o financeiro deixa de existir
        if ($restantes->isEmpty()) {
            $financeiro->delete();

            return;
        }

        $financeiro->update([
            'clinica_id' => $prescricao->clinica_id,
            'valor_total' => $restantes->sum(fn (FinanceiroParcela $parcela) => (float) $parcela->valor),
            'quantidade_parcelas' => $restantes->count(),
        ]);

        // O que já foi recebido é realocado nas parcelas novas
        $this->pagamentos->reprocessar($financeiro->refresh());
    }

    /**
     * Valor final do financeiro: bruto - desconto + adicional.
     * Valida o desconto (percentual acima de 100, desconto maior que o bruto
     * ou total zerado/negativo).
     */
    public function valorFinal(Financeiro $financeiro): float
    {
        $bruto = (float) $financeiro->valor_bruto;
        $desconto = (float) $financeiro->desconto_valor;
        $adicional = (float) $financeiro->adicional_valor;

        if ($bruto <= 0) {
            return 0.0;
        }

        if ($desconto < 0 || $adicional < 0) {
            throw ValidationException::withMessages([
                'desconto_valor' => 'Desconto e adicional não podem ser negativos.',
            ]);
        }

        if ($financeiro->desconto_eh_porcentagem && $desconto > 100) {
            throw ValidationException::withMessages([
                'desconto_valor' => 'O desconto em porcentagem não pode passar de 100%.',
            ]);
        }

        if ($financeiro->valor_desconto > $bruto) {
            throw ValidationException::withMessages([
                'desconto_valor' => 'O desconto aplicado no cadastro ('.$financeiro->valor_desconto_formatado.') ficaria maior que o valor das semanas ('
                    .$financeiro->valor_bruto_formatado.'). O desconto não pode ser alterado depois do cadastro.',
            ]);
        }

        $total = round($bruto - $financeiro->valor_desconto + $adicional, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'desconto_valor' => 'O desconto informado deixa o valor total zerado. Revise o desconto ou informe um adicional.',
            ]);
        }

        return $total;
    }

    /**
     * Rateia o desconto e o adicional IGUALMENTE entre as semanas, sem olhar o
     * valor de cada uma. A sobra de centavos vira 1 centavo a mais nas
     * primeiras parcelas, então cada coluna soma exatamente o total informado.
     *
     * Se alguma parcela for menor que a sua cota, o que não coube nela é
     * empurrado para as seguintes (nenhuma parcela fica negativa).
     *
     * @param  Collection<int, PrescricaoSemana>  $semanas
     * @return array<int, array{bruto: float, desconto: float, adicional: float, valor: float}>
     */
    private function ratiarIgualmente(Collection $semanas, float $desconto, float $adicional): array
    {
        $quantidade = $semanas->count();

        if ($quantidade === 0) {
            return [];
        }

        $cotasDesconto = $this->cotaIgualitaria($desconto, $quantidade);
        $cotasAdicional = $this->cotaIgualitaria($adicional, $quantidade);

        $valores = [];
        $reserva = 0;

        foreach ($semanas->values() as $indice => $semana) {
            $bruto = (int) round((float) $semana->valor_total * 100);
            $cota = $cotasDesconto[$indice] + $reserva;
            $aplicado = min($cota, $bruto);

            $reserva = $cota - $aplicado;

            $valores[$semana->id] = [
                'bruto' => $bruto,
                'desconto' => $aplicado,
                'adicional' => $cotasAdicional[$indice],
            ];
        }

        // Parcelas pequenas demais não comportaram a cota: distribui o que
        // sobrou nas que ainda têm espaço
        if ($reserva > 0) {
            foreach ($valores as $id => $linha) {
                if ($reserva <= 0) {
                    break;
                }

                $espaco = $linha['bruto'] - $linha['desconto'];
                $extra = min($reserva, $espaco);

                if ($extra <= 0) {
                    continue;
                }

                $valores[$id]['desconto'] += $extra;
                $reserva -= $extra;
            }
        }

        return array_map(fn (array $linha) => [
            'bruto' => round($linha['bruto'] / 100, 2),
            'desconto' => round($linha['desconto'] / 100, 2),
            'adicional' => round($linha['adicional'] / 100, 2),
            'valor' => round(($linha['bruto'] - $linha['desconto'] + $linha['adicional']) / 100, 2),
        ], $valores);
    }

    /**
     * Cota igualitária em centavos. O que não divide exato vira 1 centavo a
     * mais nas primeiras parcelas (diferença máxima de 1 centavo entre elas).
     *
     * @return array<int, int>
     */
    private function cotaIgualitaria(float $valor, int $quantidade): array
    {
        $centavos = (int) round($valor * 100);
        $base = intdiv($centavos, $quantidade);
        $resto = $centavos % $quantidade;

        return array_map(
            fn (int $indice) => $base + ($indice < $resto ? 1 : 0),
            range(0, $quantidade - 1)
        );
    }
}
