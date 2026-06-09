<?php

namespace App\Dto\ProjectionImport;

final class ProjectionSeanceRow
{
    public function __construct(
        public readonly string $classe,
        public readonly string $groupe,
        public readonly string $formateur,
        public readonly string $matiere,
        public readonly float $reel,
        public readonly float $previsionnel,
    ) {
    }

    public function hasHours(): bool
    {
        return $this->reel > 0 || $this->previsionnel > 0;
    }

    public function getKey(): string
    {
        return sprintf(
            '%s|%s|%s|%s',
            mb_strtolower(trim($this->classe)),
            mb_strtolower(trim($this->groupe)),
            mb_strtolower(trim($this->formateur)),
            mb_strtolower(trim($this->matiere))
        );
    }
}
