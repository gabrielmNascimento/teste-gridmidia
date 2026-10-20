<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Entrar — Rateio de despesas</title>
        <style>
            :root { color-scheme: light; font-family: system-ui, sans-serif; color: #172033; background: #f3f5f8; }
            body { min-height: 100vh; margin: 0; display: grid; place-items: center; }
            main { box-sizing: border-box; width: min(100% - 32px, 420px); padding: 28px; background: white; border: 1px solid #dce2ea; border-radius: 12px; }
            form { display: grid; gap: 16px; }
            label { display: grid; gap: 5px; font-weight: 600; }
            input, button { box-sizing: border-box; min-height: 42px; padding: 8px 10px; font: inherit; border: 1px solid #aeb8c7; border-radius: 6px; }
            button { color: white; background: #2357c6; border: 0; cursor: pointer; }
            .errors { padding: 12px 16px; color: #8f1d1d; background: #fff0f0; border-radius: 6px; }
        </style>
    </head>
    <body>
        <main>
            <h1>Entrar</h1>
            <p>Acesse o sistema de rateio de despesas.</p>

            @if ($errors->any())
                <div class="errors" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <label for="email">E-mail
                    <input id="email" type="email" name="email" autocomplete="username" value="{{ old('email') }}" required autofocus>
                </label>
                <label for="password">Senha
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit">Entrar</button>
            </form>
        </main>
    </body>
</html>
