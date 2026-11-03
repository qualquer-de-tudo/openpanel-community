<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use QOpenPanel\Community\Models\User;

Artisan::command('panel:account {email} {--name=} {--disk=10240} {--memory=2048} {--projects=5} {--databases=5}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error(__('E-mail inválido.'));

        return 1;
    }
    $limits = [];
    foreach (['disk', 'memory', 'projects', 'databases'] as $key) {
        $value = filter_var($this->option($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1048576]]);
        if ($value === false) {
            $this->error(__('Limite inválido: :key', ['key' => $key]));

            return 1;
        }$limits[$key] = $value;
    }
    $password = $this->secret(__('Senha da conta (mínimo de 12 caracteres)'));
    if (strlen($password ?? '') < 12) {
        $this->error(__('Senha muito curta.'));

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error(__('Esta conta já existe.'));

        return 1;
    }
    DB::transaction(function () use ($email, $password, $limits) {
        $user = User::create(['name' => $this->option('name') ?: strstr($email, '@', true), 'email' => $email, 'password' => Hash::make($password)]);
        DB::table('qopenpanel_accounts')->insert(['user_id' => $user->id, 'disk_mb' => $limits['disk'], 'memory_mb' => $limits['memory'], 'project_limit' => $limits['projects'], 'database_limit' => $limits['databases'], 'created_at' => now(), 'updated_at' => now()]);
    });
    $this->info(__('Conta criada com workspace isolado e cotas.'));

    return 0;
})->purpose(__('Criar uma conta de hospedagem com cotas próprias'));
