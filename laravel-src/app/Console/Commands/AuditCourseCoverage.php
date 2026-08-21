<?php

namespace App\Console\Commands;

use App\Services\Curriculum\CoverageAuditService;
use Illuminate\Console\Command;

class AuditCourseCoverage extends Command
{
    protected $signature = 'course:audit-coverage {--json : Emit machine-readable JSON}';

    protected $description = 'Report source-page, segment, duplicate and content-mapping coverage';

    public function handle(CoverageAuditService $audit): int
    {
        $report = $audit->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Metric', 'Value'], collect($report)->map(fn ($value, $key): array => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : $value])->values()->all());
        }

        return $report['passes_machine_coverage'] ? self::SUCCESS : self::FAILURE;
    }
}
