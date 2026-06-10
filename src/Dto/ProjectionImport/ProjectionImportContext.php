<?php

namespace App\Dto\ProjectionImport;

use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Matiere;
use App\Entity\Session;
use App\Entity\TypeActivite;

final class ProjectionImportContext
{
    /** @var array<string, Classe> */
    private array $classes = [];

    /** @var array<string, Groupe> */
    private array $groupes = [];

    /** @var array<string, Formateur> */
    private array $formateurs = [];

    /** @var array<string, Matiere> */
    private array $matieres = [];

    /** @var array<string, TypeActivite> */
    private array $typesActivite = [];

    public function __construct(
        public readonly Session $sourceSession,
        public readonly Session $targetSession,
        public readonly bool $dryRun,
        public readonly ProjectionImportReport $report,
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function getReport(): ProjectionImportReport
    {
        return $this->report;
    }

    public function getSourceSession(): Session
    {
        return $this->sourceSession;
    }

    public function getTargetSession(): Session
    {
        return $this->targetSession;
    }

    public function setClasse(string $key, Classe $classe): void
    {
        $this->classes[$this->normalizeKey($key)] = $classe;
    }

    public function getClasse(string $key): ?Classe
    {
        return $this->classes[$this->normalizeKey($key)] ?? null;
    }

    public function setGroupe(string $key, Groupe $groupe): void
    {
        $this->groupes[$this->normalizeKey($key)] = $groupe;
    }

    public function getGroupe(string $key): ?Groupe
    {
        return $this->groupes[$this->normalizeKey($key)] ?? null;
    }

    public function setFormateur(string $key, Formateur $formateur): void
    {
        $this->formateurs[$this->normalizeKey($key)] = $formateur;
    }

    public function getFormateur(string $key): ?Formateur
    {
        return $this->formateurs[$this->normalizeKey($key)] ?? null;
    }

    public function setMatiere(string $key, Matiere $matiere): void
    {
        $this->matieres[$this->normalizeKey($key)] = $matiere;
    }

    public function getMatiere(string $key): ?Matiere
    {
        return $this->matieres[$this->normalizeKey($key)] ?? null;
    }

    public function setTypeActivite(string $key, TypeActivite $typeActivite): void
    {
        $this->typesActivite[$this->normalizeKey($key)] = $typeActivite;
    }

    public function getTypeActivite(string $key): ?TypeActivite
    {
        return $this->typesActivite[$this->normalizeKey($key)] ?? null;
    }

    private function normalizeKey(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtolower(trim((string) $value));
    }
}
