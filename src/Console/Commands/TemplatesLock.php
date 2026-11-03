<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use QOpenPanel\Community\Services\TemplateCatalog;

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
