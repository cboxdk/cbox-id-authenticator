<?php

declare(strict_types=1);

namespace App\Events;

final readonly class BackupFileResult
{
    public function __construct(public string $id, public string $result, public string $contents = '') {}
}
