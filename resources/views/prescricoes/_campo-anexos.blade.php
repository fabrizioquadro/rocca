{{--
  Campo de anexos com o botão "Adicionar mais arquivos": cada clique cria um
  campo de arquivo novo, porque o seletor do sistema abre sempre na pasta do
  último arquivo escolhido — com um campo só, o usuário não consegue juntar
  arquivos que estão em pastas diferentes.

  Todos os campos usam o mesmo name (anexos[]), então o Laravel recebe tudo
  num array só; os campos que ficarem vazios são descartados.

  Variáveis: $prescricao (opcional, para listar o que já está anexado) e
  $avisoObrigatorio (true no cadastro, quando ampola/miligrama com aplicação
  exige a prescrição médica anexada).
--}}
@php
  $anexosGravados = isset($prescricao) ? $prescricao->anexos : collect();
  $anexoComErro = $errors->has('anexos') || $errors->has('anexos.*');
@endphp

<div class="mb-3">
  <label class="form-label" for="anexos">
    Anexos (exames, receitas, documentos e etc.)
    @if (! empty($avisoObrigatorio))
      <span class="text-danger d-none" data-anexo-obrigatorio>obrigatório</span>
    @endif
  </label>

  <div data-anexos>
    <div class="input-group mb-2" data-anexo-linha>
      <input
        type="file"
        id="anexos"
        name="anexos[]"
        class="form-control {{ $anexoComErro ? 'is-invalid' : '' }}"
        multiple
        accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.csv"
        data-anexo-campo />
      <button
        type="button"
        class="btn btn-icon btn-text-danger d-none"
        data-anexo-remover
        title="Remover este campo">
        <i class="ri-close-line"></i>
      </button>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center gap-2">
    <button type="button" class="btn btn-sm btn-outline-primary" data-anexo-adicionar>
      <i class="ri-add-line me-1"></i>Adicionar mais arquivos
    </button>
    <small class="text-muted">PDF, imagem, XML ou CSV — máx. 10MB por arquivo</small>
  </div>

  {{-- Soma o que foi escolhido em todos os campos --}}
  <ul class="list-unstyled small text-body-secondary mt-2 mb-0 d-none" data-anexo-escolhidos></ul>

  @if (! empty($avisoObrigatorio))
    <div class="text-danger small d-none" data-aviso-anexo>
      Esta prescrição tem medicamento do tipo <strong>ampola</strong> ou <strong>miligrama</strong>
      com aplicação, então o anexo da prescrição médica é obrigatório.
    </div>
  @endif

  @error('anexos')
    <div class="invalid-feedback d-block">{{ $message }}</div>
  @enderror
  @error('anexos.*')
    <div class="invalid-feedback d-block">{{ $message }}</div>
  @enderror

  @if ($anexosGravados->isNotEmpty())
    <div class="mt-3">
      <small class="text-muted d-block">Já anexados nesta prescrição:</small>

      <ul class="list-unstyled small mb-0">
        @foreach ($anexosGravados as $anexo)
          <li class="d-flex align-items-center gap-2">
            <i class="{{ $anexo->eh_imagem ? 'ri-image-line' : 'ri-file-text-line' }}"></i>
            <a href="{{ $anexo->url }}" target="_blank" rel="noopener">{{ $anexo->nome }}</a>
            <span class="text-body-secondary">{{ $anexo->tamanho_formatado }}</span>
          </li>
        @endforeach
      </ul>

      <small class="text-muted">Para remover um anexo, use a tela da prescrição.</small>
    </div>
  @endif
</div>

{{-- Modelo do campo criado pelo botão (o de cima é o primeiro, já visível) --}}
<template data-anexo-template>
  <div class="input-group mb-2" data-anexo-linha>
    <input
      type="file"
      name="anexos[]"
      class="form-control"
      multiple
      accept=".pdf,.jpg,.jpeg,.png,.webp,.xml,.csv"
      data-anexo-campo />
    <button type="button" class="btn btn-icon btn-text-danger" data-anexo-remover title="Remover este campo">
      <i class="ri-close-line"></i>
    </button>
  </div>
</template>
