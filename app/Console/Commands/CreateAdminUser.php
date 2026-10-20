<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Cria uma conta administrativa sem habilitar cadastro público';

    public function handle(): int
    {
        $name = trim($this->ask('Nome do administrador') ?? '');
        $email = Str::lower(trim($this->ask('E-mail do administrador') ?? ''));
        $password = $this->secret('Senha (mínimo de 12 caracteres)');
        $passwordConfirmation = $this->secret('Confirme a senha');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], [
            'email.unique' => 'Já existe uma conta com esse e-mail.',
            'password.confirmed' => 'A confirmação da senha não corresponde.',
            'password.min' => 'A senha deve ter ao menos 12 caracteres.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Conta administrativa criada para {$email}.");

        return self::SUCCESS;
    }
}
