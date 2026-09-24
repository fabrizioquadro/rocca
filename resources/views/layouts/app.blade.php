<!doctype html>

<html
  lang="pt-BR"
  class="light-style layout-menu-fixed layout-compact"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{ asset('template/assets') }}/"
  data-template="horizontal-menu-template"
  data-style="light">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'Instituto Rocca') }}</title>

    <meta name="description" content="@yield('meta_description', '')" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('template/assets/img/favicon/favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

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

    <style>
      /* Garante que table-sm realmente fique compacta (o template sobrescreve o padding) */
      .table-sm > :not(caption) > * > * {
        padding: 0.4rem 0.75rem;
      }
      .table.table-sm thead tr th {
        padding-block: 0.6rem;
      }
    </style>

    @stack('styles')

    <!-- Helpers -->
    <script src="{{ asset('template/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('template/assets/js/config.js') }}"></script>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
      <div class="layout-container">
        <!-- Navbar -->
        <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="container-xxl">
            <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-6">
              <a href="{{ route('home') }}" class="app-brand-link gap-2">
                <img
                  src="{{ asset('template/assets/img/logo-tight.png') }}"
                  alt="{{ config('app.name', 'Instituto Rocca') }}"
                  style="height: 44px; width: auto;" />
              </a>

              <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                <i class="ri-close-fill align-middle"></i>
              </a>
            </div>

            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="ri-menu-fill ri-22px"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
              <ul class="navbar-nav flex-row align-items-center ms-auto">
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                      <img
                        src="{{ auth()->user()?->imagem ? asset(auth()->user()->imagem) : asset('template/assets/img/avatars/avatar-unisex.svg') }}"
                        alt="Avatar"
                        class="rounded-circle" />
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item waves-effect" href="javascript:void(0);">
                        <div class="d-flex">
                          <div class="flex-shrink-0 me-2">
                            <div class="avatar avatar-online">
                              <img
                                src="{{ auth()->user()?->imagem ? asset(auth()->user()->imagem) : asset('template/assets/img/avatars/avatar-unisex.svg') }}"
                                alt="Avatar"
                                class="rounded-circle" />
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <span class="fw-medium d-block small">{{ auth()->user()->nome }}</span>
                            <small class="text-muted">{{ auth()->user()->tipo?->label() }}</small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item waves-effect" href="{{ route('perfil.edit') }}">
                        <i class="ri-user-3-line ri-22px me-3"></i><span class="align-middle">Perfil</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item waves-effect" href="{{ route('perfil.senha') }}">
                        <i class="ri-key-2-line ri-22px me-3"></i><span class="align-middle">Alterar senha</span>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <div class="d-grid px-4 pt-2 pb-1">
                        <form method="POST" action="{{ route('logout') }}">
                          @csrf
                          <button
                            type="submit"
                            class="btn btn-sm btn-danger w-100 d-flex align-items-center justify-content-center waves-effect waves-light">
                            <small class="align-middle">Sair</small>
                            <i class="ri-logout-box-r-line ms-2 ri-16px"></i>
                          </button>
                        </form>
                      </div>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
            </div>
          </div>
        </nav>
        <!-- / Navbar -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0">
              <div class="container-xxl d-flex h-100">
                <ul class="menu-inner">
                  <li class="menu-item">
                    <a href="{{ route('home') }}" class="menu-link">
                      <i class="menu-icon tf-icons ri-dashboard-3-line"></i>
                      <div data-i18n="Dashboard">Dashboard</div>
                    </a>
                  </li>

                  <!-- Cadastros -->
                  <li class="menu-item">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                      <i class="menu-icon tf-icons ri-file-add-line"></i>
                      <div data-i18n="Cadastros">Cadastros</div>
                    </a>
                    <ul class="menu-sub">
                      <li class="menu-item">
                        <a href="{{ route('clinicas.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-building-2-line"></i>
                          <div data-i18n="Clínicas">Clínicas</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('usuarios.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-user-settings-line"></i>
                          <div data-i18n="Usuários">Usuários</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('fornecedores.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-truck-line"></i>
                          <div data-i18n="Fornecedores">Fornecedores</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('grupos.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-group-line"></i>
                          <div data-i18n="Grupos">Grupos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('medicamentos.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-capsule-line"></i>
                          <div data-i18n="Medicamentos">Medicamentos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('combos.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-gift-line"></i>
                          <div data-i18n="Combos">Combos</div>
                        </a>
                      </li>
                    </ul>
                  </li>

                  <!-- Estoque -->
                  <li class="menu-item">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                      <i class="menu-icon tf-icons ri-archive-2-line"></i>
                      <div data-i18n="Estoque">Estoque</div>
                    </a>
                    <ul class="menu-sub">
                      <li class="menu-item">
                        <a href="{{ route('estoque.entradas.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-login-box-line"></i>
                          <div data-i18n="Entradas">Entradas</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('estoque.baixas.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-logout-box-line"></i>
                          <div data-i18n="Baixas">Baixas</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('estoque.baixas-abertos.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-logout-box-r-line"></i>
                          <div data-i18n="Baixa de Abertos">Baixa de Abertos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('estoque.transferencias.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-arrow-left-right-line"></i>
                          <div data-i18n="Transferências">Transferências</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('estoque.saldo.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-stack-line"></i>
                          <div data-i18n="Saldo">Saldo</div>
                        </a>
                      </li>
                    </ul>
                  </li>

                  <!-- Pacientes -->
                  <li class="menu-item">
                    <a href="{{ route('pacientes.index') }}" class="menu-link">
                      <i class="menu-icon tf-icons ri-user-heart-line"></i>
                      <div data-i18n="Pacientes">Pacientes</div>
                    </a>
                  </li>

                  <!-- Prescrições -->
                  <li class="menu-item">
                    <a href="{{ route('prescricoes.index') }}" class="menu-link">
                      <i class="menu-icon tf-icons ri-file-list-2-line"></i>
                      <div data-i18n="Prescrições">Prescrições</div>
                    </a>
                  </li>

                  <!-- Relatórios -->
                  <li class="menu-item">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                      <i class="menu-icon tf-icons ri-bar-chart-box-line"></i>
                      <div data-i18n="Relatórios">Relatórios</div>
                    </a>
                    <ul class="menu-sub">
                      <li class="menu-item">
                        <a href="{{ route('relatorios.index') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-list-check-2"></i>
                          <div data-i18n="Todos os relatórios">Todos os relatórios</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.aplicacoes') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-syringe-line"></i>
                          <div data-i18n="Aplicações">Aplicações</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.aplicacoes-por-medicamento') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-bar-chart-2-line"></i>
                          <div data-i18n="Por medicamento">Por medicamento</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.vasilhames-abertos') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-archive-2-line"></i>
                          <div data-i18n="Vasilhames abertos">Vasilhames abertos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.baixas-abertos') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-logout-box-r-line"></i>
                          <div data-i18n="Baixas de abertos">Baixas de abertos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.movimentacoes') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-swap-box-line"></i>
                          <div data-i18n="Movimentações">Movimentações</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.posicao-estoque') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-stack-line"></i>
                          <div data-i18n="Posição de estoque">Posição de estoque</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.itens-pendentes') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-error-warning-line"></i>
                          <div data-i18n="Itens pendentes">Itens pendentes</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.contas-receber') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-hand-coin-line"></i>
                          <div data-i18n="Contas a receber">Contas a receber</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.recebimentos') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-money-dollar-circle-line"></i>
                          <div data-i18n="Recebimentos">Recebimentos</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('relatorios.prescricoes') }}" class="menu-link">
                          <i class="menu-icon tf-icons ri-file-list-2-line"></i>
                          <div data-i18n="Prescrições">Prescrições</div>
                        </a>
                      </li>
                    </ul>
                  </li>
                </ul>
              </div>
            </aside>
            <!-- / Menu -->

            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              @yield('content')
            </div>
            <!--/ Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl">
                <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                  <div class="text-body mb-2 mb-md-0">
                    © {{ date('Y') }} {{ config('app.name', 'Instituto Rocca') }}. Todos os direitos reservados.
                  </div>
                </div>
              </div>
            </footer>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!--/ Content wrapper -->
        </div>
        <!--/ Layout container -->
      </div>
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>
    <!--/ Layout wrapper -->

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

    <!-- Main JS -->
    <script src="{{ asset('template/assets/js/main.js') }}"></script>

    @stack('scripts')
  </body>
</html>
