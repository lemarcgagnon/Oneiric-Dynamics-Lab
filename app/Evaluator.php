<?php
declare(strict_types=1);
namespace ODLab;

interface Evaluator
{
    public function evaluate(array $memory, array $records, string $split): array;
    public function usage(): array;
    /** Returns all call-level events accumulated so far. */
    public function events(): array;
}
