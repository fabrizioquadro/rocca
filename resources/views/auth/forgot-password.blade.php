<!doctype html>

<html lang="pt-BR" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-default" data-style="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />

    <title>Esqueceu a senha | {{ config('app.name', 'Instituto Rocca') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('template/assets/img/favicon/favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/rtl/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/rtl/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('template/assets/css/demo.css') }}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/pages/page-auth.css') }}" />

    <style>
      .authentication-wrapper.authentication-basic {
        min-height: 100vh;
      }
    </style>
  </head>

  <body>
    <div class="authentication-wrapper authentication-basic">
      <div class="authentication-inner">
        <div class="card">
          <div class="card-body">
            <!-- Logo -->
            <div class="text-center mb-4">
              <img
                src="{{ asset('template/assets/img/logo-tight.png') }}"
                alt="{{ config('app.name', 'Instituto Rocca') }}"
                style="width: 100%; max-width: 220px; height: auto;" />
            </div>

            <h4 class="mb-1 text-center">Esqueceu sua senha? 🔒</h4>
            <p class="mb-4 text-center">Informe seu e-mail cadastrado e enviaremos um link para redefinir a senha.</p>

            @if (session('status'))
              <div class="alert alert-success" role="alert">
                {{ session('status') }}
              </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
              @csrf

              <div class="form-floating form-floating-outline mb-3">
                <input
                  type="email"
                  id="email"
                  class="form-control @error('email') is-invalid @enderror"
                  name="email"
                  placeholder="nome@exemplo.com"
                  value="{{ old('email') }}"
                  autocomplete="email"
                  autofocus />
                <label for="email">E-mail</label>
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              <button type="submit" class="btn btn-primary d-grid w-100">Enviar link de redefinição</button>
            </form>

            <p class="text-center mt-3 mb-0">
              <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
                <i class="ri-arrow-left-line me-1"></i>Voltar para o login
              </a>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/js/bootstrap.js') }}"></script>
  </body>
</html>
