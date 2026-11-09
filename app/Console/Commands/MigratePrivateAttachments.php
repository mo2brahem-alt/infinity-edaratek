<?php

namespace App\Console\Commands;

use App\Services\Support\PrivateAttachmentMigrator;
use Illuminate\Console\Command;

class MigratePrivateAttachments extends Command
{
    protected $signature = 'attachments:migrate-private {--apply : Apply only in a separately approved data scope} {--limit=100 : Maximum files and pending records, 1..500}';
    protected $description = 'Inventory private legacy files; dry-run by default, aggregate-only output';

    public function handle(PrivateAttachmentMigrator $migrator): int
    {
        try {
            $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
            if ($limit === false) { return self::INVALID; }
            $counts = $migrator->run((bool) $this->option('apply'), $limit);
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
            return ($counts['failed'] || $counts['pending'] || $counts['quarantined']) ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable) {
            $this->error('PRIVATE_FILE_MIGRATION_REQUIRES_REVIEW'); return self::FAILURE;
        }
    }
}
