<!doctype html>

<html
  lang="pt-BR"
  class="light-style layout-wide customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{ asset('template/assets') }}/"
  data-template="horizontal-menu-template"
  data-style="light">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Login | {{ config('app.name', 'Rocca') }}</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('template/assets/img/favicon/favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&ampdisplay=swap"
      rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/fonts/remixicon/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Menu waves for no-customizer fix -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/node-waves/node-waves.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/rtl/theme-default.css') }}" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('template/assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/typeahead-js/typeahead.css') }}" />
    <!-- Vendor -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/libs/@form-validation/form-validation.css') }}" />

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="{{ asset('template/assets/vendor/css/pages/page-auth.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('template/assets/vendor/js/helpers.js') }}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <script src="{{ asset('template/assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('template/assets/js/config.js') }}"></script>
    <style>
      body {
        background-color: #FEFEFE;
      }
    </style>
  </head>

  <body>
    <!-- Content -->

    <div class="authentication-wrapper authentication-cover">
      <div class="authentication-inner row m-0">
        <!-- /Left Section -->
        <div
          class="d-none d-lg-flex col-lg-7 col-xl-8 p-3 position-relative overflow-hidden align-items-start justify-content-center"
          style="background-color: #FEFEFE;">
          <img
            src="{{ asset('template/assets/img/illustrations/cover.jpg') }}"
            alt="Capa"
            style="width: auto; height: auto; max-width: 90%; max-height: 520px; margin-top: 60px; border-radius: 0.5rem;" />
        </div>
        <!-- /Left Section -->

        <!-- Login -->
        <div
          class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg position-relative py-sm-12 px-12 py-6"
          style="background-color: #FEFEFE;">
          <div class="w-px-400 mx-auto pt-5 pt-lg-0 text-center">
            <img
              src="{{ asset('template/assets/img/logo-tight.png') }}"
              alt="Instituto Rocca"
              style="width: 100%; max-width: 360px; height: auto; margin-bottom: 15px;" />
            <p class="mb-5">Entre com suas credenciais para acessar o sistema</p>

            @if ($errors->any())
              <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <span class="alert-icon me-2"><i class="ri-error-warning-line"></i></span>
                <div>
                  @foreach ($errors->all() as $error)
                    <span>{{ $error }}</span>
                  @endforeach
                </div>
              </div>
            @endif

            <form id="formAuthentication" class="mb-5" method="POST" action="{{ route('login') }}">
              @csrf
              <div class="form-floating form-floating-outline mb-5">
                <input
                  type="email"
                  class="form-control @error('email') is-invalid @enderror"
                  id="email"
                  name="email"
                  placeholder="nome@exemplo.com"
                  value="{{ old('email') }}"
                  autofocus
                  autocomplete="username" />
                <label for="email">E-mail</label>
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-5">
                <div class="form-password-toggle">
                  <div class="input-group input-group-merge">
                    <div class="form-floating form-floating-outline">
                      <input
                        type="password"
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="password"
                        autocomplete="current-password" />
                      <label for="password">Senha</label>
                    </div>
                    <span class="input-group-text cursor-pointer"><i class="ri-eye-off-line"></i></span>
                  </div>
                  @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="mb-5 d-flex justify-content-between mt-5">
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                  <label class="form-check-label" for="remember-me"> Lembrar de mim </label>
                </div>
                <a href="{{ route('password.request') }}" class="float-end mb-1 mt-2">
                  <span>Esqueceu a senha?</span>
                </a>
              </div>
              <button type="submit" class="btn btn-primary d-grid w-100">Entrar</button>
            </form>
          </div>
        </div>
        <!-- /Login -->
      </div>
    </div>

    <!-- / Content -->

    <!-- Core JS -->
    <script src="{{ asset('template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/js/menu.js') }}"></script>

    <!-- Vendors JS -->
    <script src="{{ asset('template/assets/vendor/libs/@form-validation/popular.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/@form-validation/bootstrap5.js') }}"></script>
    <script src="{{ asset('template/assets/vendor/libs/@form-validation/auto-focus.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('template/assets/js/main.js') }}"></script>

    <!-- Page JS -->
    <script src="{{ asset('template/assets/js/pages-auth.js') }}"></script>
  </body>
</html>
