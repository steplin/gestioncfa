<?php

namespace App\Dto\ProjectionImport;

final class ProjectionImportReport
{
    private array $classesCreees = [];
    private array $groupesCrees = [];
    private array $formateursCrees = [];
    private array $matieresCreees = [];

    private array $missionsCopiees = [];

    private array $warnings = [];
    private array $errors = [];

    private int $seancesSupprimees = 0;
    private int $seancesCreees = 0;

    public function addClasseCreee(string $classe): void
    {
        $this->classesCreees[] = $classe;
    }

    public function addGroupeCree(string $groupe): void
    {
        $this->groupesCrees[] = $groupe;
    }

    public function addFormateurCree(string $formateur): void
    {
        $this->formateursCrees[] = $formateur;
    }

    public function addMatiereCreee(string $matiere): void
    {
        $this->matieresCreees[] = $matiere;
    }

    public function addMissionCopiee(string $mission): void
    {
        $this->missionsCopiees[] = $mission;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function incrementSeancesSupprimees(int $nb = 1): void
    {
        $this->seancesSupprimees += $nb;
    }

    public function incrementSeancesCreees(int $nb = 1): void
    {
        $this->seancesCreees += $nb;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getSummary(): array
    {
        return [
            'classes_creees' => count($this->classesCreees),
            'groupes_crees' => count($this->groupesCrees),
            'formateurs_crees' => count($this->formateursCrees),
            'matieres_creees' => count($this->matieresCreees),
            'missions_copiees' => count($this->missionsCopiees),
            'seances_supprimees' => $this->seancesSupprimees,
            'seances_creees' => $this->seancesCreees,
            'warnings' => count($this->warnings),
            'errors' => count($this->errors),
        ];
    }

    public function getClassesCreees(): array
    {
        return $this->classesCreees;
    }

    public function getGroupesCrees(): array
    {
        return $this->groupesCrees;
    }

    public function getFormateursCrees(): array
    {
        return $this->formateursCrees;
    }

    public function getMatieresCreees(): array
    {
        return $this->matieresCreees;
    }

    public function getMissionsCopiees(): array
    {
        return $this->missionsCopiees;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSeancesSupprimees(): int
    {
        return $this->seancesSupprimees;
    }

    public function getSeancesCreees(): int
    {
        return $this->seancesCreees;
    }
}
