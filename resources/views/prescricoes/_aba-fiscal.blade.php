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
@endpush
