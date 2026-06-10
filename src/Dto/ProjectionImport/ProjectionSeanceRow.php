<?php

namespace App\Dto\ProjectionImport;

final readonly class ProjectionSeanceRow
{
    public function __construct(
        public string $classe,
        public string $groupe,
        public string $formateur,
        public string $matiere,
        public float  $reel,
        public float  $previsionnel,
        public string $typeActiviteCode = 'COURS',
    ) {
    }

    public function hasHours(): bool
    {
        return $this->reel > 0 || $this->previsionnel > 0;
    }

    public function isMission(): bool
    {
        return $this->typeActiviteCode !== 'COURS';
    }

    public function getKey(): string
    {
        return sprintf(
            '%s|%s|%s|%s|%s',
            $this->normalize($this->typeActiviteCode),
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
