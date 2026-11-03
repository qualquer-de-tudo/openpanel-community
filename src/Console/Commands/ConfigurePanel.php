<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ConfigurePanel extends Command
{
    protected $signature = 'qopenpanel:configure {--name=} {--email=}';

    protected $description = 'Choose the first account identity; no password in environment variables';

    public function handle(): int
    {
        $data = ['name' => $this->option('name'), 'email' => $this->option('email')];
        if (Validator::make($data, ['name' => 'required|string|max:80', 'email' => 'required|email|max:255'])->fails()) {
            $this->error('Invalid name or email.');

            return self::FAILURE;
        }
        $path = storage_path('app/qopenpanel-setup.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
        chmod($path, 0600);
        $this->info('Initial identity configured. Choose the password on the private first-access page.');

        return self::SUCCESS;
    }
}
