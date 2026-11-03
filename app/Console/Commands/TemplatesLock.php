<?php

namespace App\Console\Commands;

use App\Services\TemplateCatalog;
use Illuminate\Console\Command;

class TemplatesLock extends Command
{
    protected $signature = 'panel:templates-lock';

    protected $description = 'Add new template versions to the lockfile; existing versions cannot change';

    public function handle(TemplateCatalog $catalog): int
    {
        try {
            $catalog->updateLock();
            $this->info('Template release lock: OK');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Cannot modify or remove a published version. Validate new manifests.');

            return self::FAILURE;
        }
    }
}
