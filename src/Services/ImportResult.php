<?php
declare(strict_types=1);

namespace App\Services;

class ImportResult
{
    public function __construct(
        public readonly int   $personsCount,
        public readonly int   $relationshipsCount,
        public readonly int   $skippedCount,
        public readonly array $errors = [],
    ) {}
}
