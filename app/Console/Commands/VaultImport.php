<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BlindVault;
use App\Services\PanelAutomation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

#[Signature('panel:vault-import-github {email} {resource} {file : Private file containing a GitHub token}')]
#[Description('Import a provider-issued GitHub token without displaying it, pinned to one resource and repository')]
class VaultImport extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $path = $this->argument('file');
        if (! $user || ! is_file($path) || is_link($path) || filesize($path) > 4096 || (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0077) !== 0)) {
            $this->error('Conta ou arquivo privado inválido. No Linux use chmod 600.');

            return self::FAILURE;
        }
        $value = trim(file_get_contents($path));
        if (! preg_match('/^[A-Za-z0-9_]{20,255}$/D', $value)) {
            $this->error('Formato de token inválido.');

            return self::FAILURE;
        }
        Auth::setUser($user);
        try {
            $metadata = app(BlindVault::class)->importGithub((int) $this->argument('resource'), $value);
            app(PanelAutomation::class)->audit('human_github_import', 'success');
            $this->info('Credencial importada. Referência: '.$metadata['reference']);

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Importação recusada. Nenhuma credencial foi exibida.');

            return self::FAILURE;
        }
    }
}
