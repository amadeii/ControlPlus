@extends('layouts.header_auth', ['title' => 'Login'])

@section('css')
    <style type="text/css">
        html,
        body.authentication-bg {
            min-height: 100%;
        }

        body.authentication-bg {
            width: 100vw;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url('/assets/images/bg-superstore-login.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #061526;
        }

        .superstore-login-page {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: 100vw;
            min-height: 100vh;
            padding: clamp(24px, 5vh, 56px) clamp(24px, 6vw, 96px);
        }

        .superstore-login-page::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(3, 12, 30, 0.08) 0%, rgba(3, 12, 30, 0.12) 52%, rgba(3, 12, 30, 0.36) 100%);
            pointer-events: none;
        }

        .superstore-login-card {
            position: relative;
            z-index: 1;
            width: min(100%, 460px);
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            padding: clamp(30px, 4vw, 44px);
            border-radius: 18px;
            background: #ffffff;
            color: #172033;
            box-shadow: 0 24px 70px rgba(3, 12, 30, 0.28);
        }

        .superstore-login-card .text-muted {
            color: #6d7890 !important;
        }

        .superstore-login-brand {
            margin-bottom: 28px;
            text-align: center;
        }

        .superstore-login-logo {
            display: block;
            width: min(100%, 320px);
            max-height: 118px;
            object-fit: contain;
            margin: 0 auto 10px;
        }

        .superstore-login-title {
            color: #172033;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .superstore-login-card .form-label {
            color: #26354d;
            font-weight: 600;
        }

        .superstore-login-card .form-control {
            min-height: 44px;
            border-color: #dce4f2;
            background-color: #f6f9ff;
            color: #172033;
        }

        .superstore-login-card .form-control::placeholder {
            color: #8b98ad;
        }

        .superstore-login-card .form-control:focus {
            border-color: #2b7cff;
            box-shadow: 0 0 0 0.18rem rgba(43, 124, 255, 0.15);
        }

        .superstore-password-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .superstore-password-head .form-label {
            margin-bottom: 0.5rem;
        }

        .superstore-forgot-link,
        .superstore-support-link {
            color: #265ed7;
            font-weight: 600;
            text-decoration: none;
        }

        .superstore-forgot-link:hover,
        .superstore-support-link:hover {
            color: #1647af;
            text-decoration: underline;
        }

        .superstore-login-submit {
            min-height: 44px;
            border: 0;
            background: linear-gradient(90deg, #2563eb 0%, #4254ba 100%);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
            font-weight: 700;
        }

        .superstore-login-submit:hover,
        .superstore-login-submit:focus {
            background: linear-gradient(90deg, #1d56d4 0%, #3546a4 100%);
        }

        .superstore-login-footer {
            position: static;
            margin-top: 28px;
            text-align: center;
        }

        .superstore-demo-card {
            border: 1px solid #dce4f2;
            border-radius: 14px;
            box-shadow: none;
        }

        @media (max-width: 991.98px) {
            .superstore-login-page {
                justify-content: center;
                padding: 24px;
            }

            .superstore-login-page::before {
                background: rgba(3, 12, 30, 0.22);
            }
        }

        @media (max-width: 575.98px) {
            .superstore-login-page {
                padding: 18px;
            }

            .superstore-login-card {
                width: 100%;
                max-height: calc(100vh - 36px);
                padding: 26px 20px;
                border-radius: 14px;
            }

            .superstore-login-logo {
                width: min(100%, 280px);
            }

            .superstore-password-head {
                align-items: flex-start;
                flex-direction: column;
                gap: 2px;
            }
        }
    </style>
@endsection

@section('content')
    @php
        $login = isset($_COOKIE['ckLogin']) ? base64_decode($_COOKIE['ckLogin']) : '';
        $pass = isset($_COOKIE['ckPass']) ? base64_decode($_COOKIE['ckPass']) : '';
        $remember = isset($_COOKIE['ckRemember']) ? $_COOKIE['ckRemember'] : '';
    @endphp

    <div class="superstore-login-page">
        <main class="superstore-login-card" aria-label="Login SUPERSTORE">
            <div class="auth-brand superstore-login-brand">
                <img class="superstore-login-logo" src="/superstore_logo.png" alt="SUPERSTORE">
            </div>

            @if (env('APP_ENV') == 'demo')
                <div class="card superstore-demo-card mb-4">
                    <div class="card-body">
                        <p>Clique nos bot&otilde;es abaixo para acessar os usu&aacute;rios pr&eacute; configurados!</p>
                        <div class="row">
                            <div class="col-12 col-lg-6 mt-1">
                                <button class="btn btn-success w-100" onclick="login('slym@slym.com', '123456')">
                                    SUPERADMIN
                                </button>
                            </div>
                            <div class="col-12 col-lg-6 mt-1">
                                <button class="btn btn-dark w-100" onclick="login('teste@teste.com', '123456')">
                                    EMPRESA TESTE
                                </button>
                            </div>
                        </div>
                        <br>
                        <a
                            href="https://api.whatsapp.com/send/?phone=5541985117177&text&type=phone_number&app_absent=0">WhatsApp
                            <strong>43920004769</strong></a>
                    </div>
                </div>
            @endif

            <h4 class="mt-0 superstore-login-title">Login</h4>
            <p class="text-muted mb-4">Digite seu endere&ccedil;o de email e senha para acessar a conta.</p>

            <form method="POST" action="{{ route('login') }}" id="form-login">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" id="email" required
                        value="{{ $login }}" placeholder="Digite seu email">
                </div>
                <div class="mb-3">
                    <div class="superstore-password-head">
                        <label for="password" class="form-label">Senha</label>
                        <a href="{{ route('password.request') }}" class="superstore-forgot-link"><small>Esqueceu sua
                                senha?</small></a>
                    </div>
                    <input class="form-control" type="password" name="password" required value="{{ $pass }}"
                        id="password" placeholder="Digite sua senha">
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input name="remember" type="checkbox" {{ $remember ? 'checked' : '' }}
                            class="form-check-input" id="checkbox-signin">
                        <label class="form-check-label" for="checkbox-signin">lembrar-me</label>
                    </div>
                </div>

                @if (Session::has('error'))
                    <div class="alert alert-danger">{{ Session::get('error') }}</div>
                @endif

                @if (Session::has('success'))
                    <div class="alert alert-success">{{ Session::get('success') }}</div>
                @endif
                <div class="d-grid mb-0 text-center">
                    <button class="btn btn-primary superstore-login-submit" type="submit"><i class="ri-login-box-line"></i>
                        Acessar</button>
                </div>
                <div class="mt-3">
                    <a class="superstore-support-link" target="_blank" href="https://wa.me/55{{ env('APP_FONE') }}"><i
                            class="ri-whatsapp-fill"></i>
                        Suporte</a>
                </div>
            </form>

            @if (request()->auto_cadastro)
                <footer class="footer footer-alt superstore-login-footer">
                    <p class="text-muted mb-0">N&atilde;o tem uma conta? <a href="{{ route('register') }}"
                            class="text-muted ms-1"><b>Inscrever-se</b></a></p>
                </footer>
            @endif
        </main>
    </div>
@endsection

@section('js')
<script type="text/javascript">
    function login(email, senha) {
        $('#email').val(email)
        $('#password').val(senha)
        $('#form-login').submit()
    }
    $('html').attr('data-bs-theme', '{{ __dataThemeDefault() }}')
</script>
@endsection
