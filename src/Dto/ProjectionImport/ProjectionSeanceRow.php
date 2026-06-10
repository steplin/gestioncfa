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
            $this->normalize($this->classe),
            $this->normalize($this->groupe),
            $this->normalize($this->formateur),
            $this->normalize($this->matiere),
        );
    }

    private function normalize(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtolower(trim((string) $value));
    }
}
