<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use QOpenPanel\Community\Models\User;

class DesignateOperator extends Command
{
    protected $signature = 'qopenpanel:operator {email} {--revoke}';

    protected $description = 'Designate or revoke a server maintenance operator from the local administrator terminal';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Account not found.');

            return self::FAILURE;
        }
        $user->forceFill(['is_operator' => ! $this->option('revoke')])->save();
        $this->info('Operator permission updated.');

        return self::SUCCESS;
    }
}
