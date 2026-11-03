<?php

namespace QOpenPanel\Community\Console\Commands;

use Illuminate\Console\Command;
use QOpenPanel\Community\Services\TemplateCatalog;

class TemplatesCheck extends Command
{
    protected $signature = 'panel:templates-check';

    protected $description = 'Validate the template catalog and immutable release digests';

    public function handle(TemplateCatalog $catalog): int
    {
        try {
            $releases = $catalog->releases();
            $this->info(count($releases).' template releases: OK');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Invalid catalog or changed release.');

            return self::FAILURE;
        }
    }
}
