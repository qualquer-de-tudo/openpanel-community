<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BlindVault;
use App\Services\PanelAutomation;
use App\Services\PrivateFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

#[Signature('panel:vault-materialize {email} {resource} {output : New private runtime environment file}')]
#[Description('Human-only materialization of application environment into a private runtime file; never stdout')]
class VaultMaterialize extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Conta inválida.');

            return self::FAILURE;
        }Auth::setUser($user);
        try {
            $content = app(BlindVault::class)->environment((int) $this->argument('resource'));
            PrivateFile::write($this->argument('output'), $content);
            app(PanelAutomation::class)->audit('human_secret_materialize', 'success');
            $this->info('Ambiente gravado no arquivo privado. Nenhum valor foi exibido.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Exportação recusada. Nenhum valor foi exibido.');

            return self::FAILURE;
        }
    }
}
