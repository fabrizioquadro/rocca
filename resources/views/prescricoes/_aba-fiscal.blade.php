{{-- Aba Fiscal: status do paciente (tomador) na Omie — pré-requisito da NFS-e --}}
@php
  $pacienteOmie = $prescricao->paciente;
  $integradoOmie = (bool) $pacienteOmie?->codigo_cliente_omie;
@endphp

<div class="card mb-4" data-fiscal-paciente>
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="d-flex align-items-center gap-3">
      @if ($integradoOmie)
        <i class="ri-checkbox-circle-fill text-success ri-24px"></i>

        <div>
          <div class="fw-semibold">Paciente integrado com a Omie</div>
          <small class="text-muted">Código do paciente na Omie</small>
        </div>

        <span class="badge bg-label-success">{{ $pacienteOmie->codigo_cliente_omie }}</span>
      @else
        <i class="ri-error-warning-line text-warning ri-24px"></i>

        <div>
          <div class="fw-semibold">Paciente não integrado com a Omie</div>
          <small class="text-muted">A integração é necessária para emitir a nota fiscal.</small>
        </div>
      @endif
    </div>

    @unless ($integradoOmie)
      <button
        type="button"
        class="btn btn-primary"
        data-fiscal-integrar
        data-url-feegow="{{ route('prescricoes.fiscal.paciente.feegow', $prescricao) }}"
        data-url-omie="{{ route('prescricoes.fiscal.paciente.omie', $prescricao) }}">
        <i class="ri-link me-1"></i>Integrar paciente
      </button>
    @endunless
  </div>

  @unless ($integradoOmie)
    <div class="card-body border-top d-none" data-fiscal-progresso>
      <div class="d-flex align-items-center gap-2 mb-2" data-fiscal-etapa="feegow">
        <span class="d-inline-flex" data-fiscal-icone><i class="ri-checkbox-blank-circle-line text-muted"></i></span>
        <span data-fiscal-texto>Buscando dados do paciente na Feegow</span>
      </div>

      <div class="d-flex align-items-center gap-2" data-fiscal-etapa="omie">
        <span class="d-inline-flex" data-fiscal-icone><i class="ri-checkbox-blank-circle-line text-muted"></i></span>
        <span data-fiscal-texto>Registrando paciente na Omie</span>
      </div>

      <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-fiscal-erro>
        <span data-fiscal-erro-texto></span>
      </div>
    </div>
  @endunless
</div>

@if ($integradoOmie)
  @php
    // Número da próxima nota da prescrição: compõe o código de integração da OS
    // na Omie ("{prescricao_id}/{numero}").
    $proximaNota = $prescricao->notasFiscais()->count() + 1;
  @endphp

  <div class="card mb-4" data-fiscal-emissao>
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="avatar avatar-sm">
          <span class="avatar-initial rounded-4 bg-label-primary">
            <i class="ri-file-paper-2-line ri-20px"></i>
          </span>
        </div>

        <div>
          <h6 class="mb-0">Emitir nota fiscal</h6>
          <small class="text-muted">NFS-e de serviço · {{ $pacienteOmie?->nome }}</small>
        </div>
      </div>

      <span class="badge bg-label-primary">Nota {{ $proximaNota }}</span>
    </div>

    <div class="card-body">
      <div class="row g-6">
        {{-- Formulário --}}
        <div class="col-lg-8">
          <div class="row g-4">
            <div class="col-sm-4">
              <label class="form-label" for="fiscal_codigo_os">Código da OS</label>
              <input
                type="text"
                class="form-control bg-lighter"
                id="fiscal_codigo_os"
                value="{{ $prescricao->id }}/{{ $proximaNota }}"
                readonly>
              <div class="form-text">Prescrição / número da nota</div>
            </div>

            <div class="col-sm-4">
              <label class="form-label" for="fiscal_data">Data</label>
              <input
                type="text"
                class="form-control bg-lighter"
                id="fiscal_data"
                value="{{ now()->format('d/m/Y') }}"
                readonly>
              <div class="form-text">Data de hoje</div>
            </div>

            <div class="col-sm-4">
              <label class="form-label">Cidade de prestação</label>
              <input type="text" class="form-control bg-lighter" value="São Paulo" readonly>
              <div class="form-text">Fixa para esta unidade</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="fiscal_servico">Serviço</label>
              <select
                id="fiscal_servico"
                name="n_cod_servico"
                class="form-select"
                data-fiscal-servicos
                data-url="{{ route('prescricoes.fiscal.servicos') }}">
                <option value="">Carregando serviços...</option>
              </select>
              <div class="form-text text-danger d-none" data-fiscal-servicos-erro></div>
            </div>

            <div class="col-sm-4">
              <label class="form-label" for="fiscal_quantidade">Quantidade</label>
              <input
                type="number"
                class="form-control"
                id="fiscal_quantidade"
                name="quantidade"
                step="0.01"
                min="0"
                value="1"
                data-fiscal-quantidade>
            </div>

            <div class="col-sm-4">
              <label class="form-label" for="fiscal_valor_unitario">Valor unitário</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input
                  type="text"
                  class="form-control"
                  id="fiscal_valor_unitario"
                  name="valor_unitario"
                  inputmode="decimal"
                  autocomplete="off"
                  value="0,00"
                  data-fiscal-valor-unitario>
              </div>
              <div class="form-text">Sugerido pelo cadastro na Omie</div>
            </div>

            <div class="col-sm-4">
              <label class="form-label" for="fiscal_valor_total">Valor total</label>
              <input
                type="text"
                class="form-control bg-lighter fw-semibold"
                id="fiscal_valor_total"
                value="R$ 0,00"
                readonly>
              <div class="form-text">Quantidade × valor unitário</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="fiscal_descricao">Descrição do serviço</label>
              <textarea
                class="form-control"
                id="fiscal_descricao"
                name="descricao"
                rows="3"
                maxlength="500"
                placeholder="Ex.: Consulta médica - Dr. Fulano"
                data-fiscal-descricao></textarea>
              <div class="form-text">Texto que sai no item da nota.</div>
            </div>

            <div class="col-md-8">
              <label class="form-label" for="fiscal_email">E-mail do paciente</label>
              <div class="input-group">
                <span class="input-group-text"><i class="ri-mail-line"></i></span>
                <input
                  type="email"
                  class="form-control"
                  id="fiscal_email"
                  name="email"
                  value="{{ $pacienteOmie?->email }}"
                  placeholder="email@exemplo.com"
                  data-fiscal-email>
              </div>
              <div class="form-text">Usado para enviar a nota ao paciente.</div>
            </div>
          </div>
        </div>

        {{-- Resumo --}}
        <div class="col-lg-4">
          <div class="rounded-4 border p-4 h-100 d-flex flex-column bg-lighter">
            <small class="text-muted text-uppercase">Total da nota</small>
            <div class="display-6 fw-semibold text-primary mb-4" data-fiscal-total-texto>R$ 0,00</div>

            <ul class="list-unstyled small mb-4">
              <li class="d-flex justify-content-between gap-2 py-2 border-bottom">
                <span class="text-muted">Nota</span>
                <span class="fw-semibold">{{ $proximaNota }}</span>
              </li>
              <li class="d-flex justify-content-between gap-2 py-2 border-bottom">
                <span class="text-muted">Serviço</span>
                <span class="fw-semibold text-end" data-fiscal-resumo-servico>—</span>
              </li>
              <li class="d-flex justify-content-between gap-2 py-2 border-bottom">
                <span class="text-muted">Quantidade</span>
                <span class="fw-semibold" data-fiscal-resumo-quantidade>1</span>
              </li>
              <li class="d-flex justify-content-between gap-2 py-2 border-bottom">
                <span class="text-muted">Data</span>
                <span class="fw-semibold">{{ now()->format('d/m/Y') }}</span>
              </li>
              <li class="d-flex justify-content-between gap-2 py-2">
                <span class="text-muted">Cidade</span>
                <span class="fw-semibold">São Paulo</span>
              </li>
            </ul>

            <div class="mt-auto">
              <button
                type="button"
                class="btn btn-primary w-100"
                data-fiscal-emitir
                data-url="{{ route('prescricoes.fiscal.notas.store', $prescricao) }}">
                <i class="ri-send-plane-line me-1"></i>Emitir nota
              </button>

              <small class="d-block text-muted text-center mt-2">
                A nota é faturada na Omie e enviada para o e-mail informado.
              </small>

              <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-fiscal-emissao-erro></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endif

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const botao = document.querySelector('[data-fiscal-integrar]');

      if (!botao) return;

      const progresso = document.querySelector('[data-fiscal-progresso]');
      const erro = document.querySelector('[data-fiscal-erro]');
      const erroTexto = document.querySelector('[data-fiscal-erro-texto]');
      const csrf = '{{ csrf_token() }}';

      const etapa = (nome) => document.querySelector('[data-fiscal-etapa="' + nome + '"]');

      const marcar = (elemento, estado) => {
        if (!elemento) return;

        const icone = elemento.querySelector('[data-fiscal-icone]');
        const texto = elemento.querySelector('[data-fiscal-texto]');

        if (estado === 'carregando') {
          icone.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>';
          texto.classList.add('fw-semibold');
        } else if (estado === 'ok') {
          icone.innerHTML = '<i class="ri-checkbox-circle-fill text-success"></i>';
          texto.classList.remove('fw-semibold');
        } else if (estado === 'erro') {
          icone.innerHTML = '<i class="ri-close-circle-fill text-danger"></i>';
          texto.classList.remove('fw-semibold');
        } else {
          icone.innerHTML = '<i class="ri-checkbox-blank-circle-line text-muted"></i>';
          texto.classList.remove('fw-semibold');
        }
      };

      // Cada etapa é uma chamada: o usuário vê exatamente onde o processo está.
      const chamar = async (url) => {
        const resposta = await fetch(url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
        });

        const dados = await resposta.json().catch(() => ({}));

        if (!resposta.ok || !dados.ok) {
          throw new Error(dados.mensagem || 'Não foi possível concluir a integração.');
        }

        return dados;
      };

      botao.addEventListener('click', async () => {
        botao.disabled = true;
        erro.classList.add('d-none');
        progresso.classList.remove('d-none');

        marcar(etapa('feegow'), 'carregando');
        marcar(etapa('omie'), 'aguardando');

        try {
          await chamar(botao.dataset.urlFeegow);
          marcar(etapa('feegow'), 'ok');

          marcar(etapa('omie'), 'carregando');
          await chamar(botao.dataset.urlOmie);
          marcar(etapa('omie'), 'ok');

          // Volta para a aba Fiscal já com o paciente integrado
          const destino = new URL(window.location.href);
          destino.searchParams.set('aba', 'fiscal');
          window.location.replace(destino.toString());
        } catch (e) {
          const emAndamento = etapa('feegow').querySelector('.spinner-border')
            ? etapa('feegow')
            : etapa('omie');

          marcar(emAndamento, 'erro');
          erroTexto.textContent = e.message;
          erro.classList.remove('d-none');
          botao.disabled = false;
        }
      });
    });
  </script>

  {{-- Formulário de emissão: serviços da Omie e cálculo do total --}}
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const formulario = document.querySelector('[data-fiscal-emissao]');

      if (!formulario) return;

      const select = formulario.querySelector('[data-fiscal-servicos]');
      const quantidade = formulario.querySelector('[data-fiscal-quantidade]');
      const valorUnitario = formulario.querySelector('[data-fiscal-valor-unitario]');
      const valorTotal = document.getElementById('fiscal_valor_total');
      const totalTexto = formulario.querySelector('[data-fiscal-total-texto]');
      const resumoServico = formulario.querySelector('[data-fiscal-resumo-servico]');
      const resumoQuantidade = formulario.querySelector('[data-fiscal-resumo-quantidade]');
      const erroServicos = formulario.querySelector('[data-fiscal-servicos-erro]');

      const moeda = (valor) => valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

      const formatarValor = (valor) => (Number(valor) || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

      // Lê "1.234,56" como número
      const valorNumerico = (texto) => {
        const limpo = String(texto || '').replace(/\./g, '').replace(',', '.');
        const valor = parseFloat(limpo);

        return Number.isFinite(valor) ? valor : 0;
      };

      // Máscara de real: os dígitos entram pela direita (centavos)
      const mascararValor = () => {
        const digitos = valorUnitario.value.replace(/\D+/g, '').replace(/^0+(?=\d)/, '');

        valorUnitario.value = formatarValor(parseInt(digitos || '0', 10) / 100);
      };

      const recalcular = () => {
        const total = (parseFloat(quantidade.value) || 0) * valorNumerico(valorUnitario.value);
        const texto = moeda(total);

        valorTotal.value = texto;
        totalTexto.textContent = texto;
        resumoQuantidade.textContent = quantidade.value || '0';
      };

      const preencherServicos = (lista) => {
        select.innerHTML = '';

        if (lista.length === 0) {
          select.innerHTML = '<option value="">Nenhum serviço cadastrado na Omie</option>';
          select.disabled = true;

          return;
        }

        const vazio = document.createElement('option');
        vazio.value = '';
        vazio.textContent = 'Selecione o serviço';
        select.appendChild(vazio);

        lista.forEach((servico) => {
          const opcao = document.createElement('option');
          opcao.value = servico.codigo;
          opcao.dataset.preco = servico.preco;
          opcao.dataset.descricao = servico.descricao;
          opcao.textContent =
            (servico.codigo_exibicao ? servico.codigo_exibicao + ' - ' : '') +
            servico.descricao +
            ' (' + moeda(servico.preco) + ')';

          select.appendChild(opcao);
        });
      };

      const carregarServicos = async () => {
        try {
          const resposta = await fetch(select.dataset.url, {
            headers: { 'Accept': 'application/json' },
          });

          const dados = await resposta.json().catch(() => ({}));

          if (!resposta.ok || !dados.ok) {
            throw new Error(dados.mensagem || 'Não foi possível carregar os serviços da Omie.');
          }

          preencherServicos(dados.servicos || []);
        } catch (e) {
          select.innerHTML = '<option value="">Não foi possível carregar</option>';
          select.disabled = true;
          erroServicos.textContent = e.message;
          erroServicos.classList.remove('d-none');
        }
      };

      // Ao escolher o serviço, sugere o preço cadastrado na Omie (editável)
      select.addEventListener('change', () => {
        const opcao = select.selectedOptions[0];
        const preco = parseFloat(opcao?.dataset.preco || 0);

        if (preco > 0) {
          valorUnitario.value = formatarValor(preco);
        }

        resumoServico.textContent = opcao?.dataset.descricao || '—';

        recalcular();
      });

      quantidade.addEventListener('input', recalcular);
      valorUnitario.addEventListener('input', () => {
        mascararValor();
        recalcular();
      });

      // Emissão: envia os campos para a Omie (IncluirOS + FaturarOS)
      const botaoEmitir = formulario.querySelector('[data-fiscal-emitir]');
      const erroEmissao = formulario.querySelector('[data-fiscal-emissao-erro]');
      const csrf = '{{ csrf_token() }}';

      const mostrarErro = (mensagem) => {
        erroEmissao.textContent = mensagem;
        erroEmissao.classList.remove('d-none');
      };

      botaoEmitir.addEventListener('click', async () => {
        erroEmissao.classList.add('d-none');

        if (!select.value) {
          mostrarErro('Selecione o serviço da nota.');

          return;
        }

        if (valorNumerico(valorUnitario.value) <= 0) {
          mostrarErro('Informe o valor unitário.');

          return;
        }

        const rotulo = botaoEmitir.innerHTML;

        botaoEmitir.disabled = true;
        botaoEmitir.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Emitindo...';

        const corpo = new FormData();
        corpo.append('n_cod_servico', select.value);
        corpo.append('quantidade', quantidade.value);
        corpo.append('valor_unitario', valorUnitario.value);
        corpo.append('descricao', formulario.querySelector('[data-fiscal-descricao]').value);
        corpo.append('email', formulario.querySelector('[data-fiscal-email]').value);

        try {
          const resposta = await fetch(botaoEmitir.dataset.url, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrf,
            },
            body: corpo,
          });

          const dados = await resposta.json().catch(() => ({}));

          if (!resposta.ok || !dados.ok) {
            const validacao = dados.errors ? Object.values(dados.errors).flat()[0] : null;

            throw new Error(dados.mensagem || validacao || 'Não foi possível emitir a nota.');
          }

          const destino = new URL(window.location.href);
          destino.searchParams.set('aba', 'fiscal');
          window.location.replace(destino.toString());
        } catch (e) {
          botaoEmitir.disabled = false;
          botaoEmitir.innerHTML = rotulo;
          mostrarErro(e.message);
        }
      });

      carregarServicos();
      recalcular();
    });
  </script>
@endpush
