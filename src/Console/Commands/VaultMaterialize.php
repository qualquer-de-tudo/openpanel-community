<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use QOpenPanel\Community\Models\User;
use QOpenPanel\Community\Services\BlindVault;
use QOpenPanel\Community\Services\PanelAutomation;
use QOpenPanel\Community\Services\PrivateFile;

#[Signature('panel:vault-materialize {email} {resource} {output : New private runtime environment file}')]
#[Description('Human-only materialization of application environment into a private runtime file; never stdout')]
class VaultMaterialize extends Command
{
    public function getDescription(): string
    {
        return __(parent::getDescription());
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error(__('Conta inválida.'));

            return self::FAILURE;
        }Auth::guard('qopenpanel')->setUser($user);
        try {
            $content = app(BlindVault::class)->environment((int) $this->argument('resource'));
            PrivateFile::write($this->argument('output'), $content);
            app(PanelAutomation::class)->audit('human_secret_materialize', 'success');
            $this->info(__('Ambiente gravado no arquivo privado. Nenhum valor foi exibido.'));

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error(__('Exportação recusada. Nenhum valor foi exibido.'));

            return self::FAILURE;
        }
    }
}
