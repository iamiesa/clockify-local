<?php

namespace App\Reports;

use App\Support\DateRange;

final class ReportFilters
{
    /**
     * Entity filters accept 0 to mean "without" (e.g. entries without a project).
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $groupIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $clientIds
     * @param  list<int>  $tagIds
     */
    public function __construct(
        public DateRange $range,
        public array $userIds = [],
        public array $groupIds = [],
        public array $projectIds = [],
        public array $clientIds = [],
        public array $tagIds = [],
        public ?bool $billable = null,
        public string $description = '',
    ) {}
}
