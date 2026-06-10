<?php

namespace App\Service\ProjectionImport\Persistence;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Dto\ProjectionImport\ProjectionSeanceRow;
use App\Entity\Seance;
use App\Entity\TypeActivite;
use App\Repository\TypeActiviteRepository;
use App\Service\ProjectionImport\Resolver\ClasseResolver;
use App\Service\ProjectionImport\Resolver\FormateurResolver;
use App\Service\ProjectionImport\Resolver\GroupeResolver;
use App\Service\ProjectionImport\Resolver\MatiereResolver;
use Doctrine\ORM\EntityManagerInterface;

final class SeanceImporter
{
    public function __construct(
        private readonly ClasseResolver $classeResolver,
        private readonly GroupeResolver $groupeResolver,
        private readonly FormateurResolver $formateurResolver,
        private readonly MatiereResolver $matiereResolver,
        private readonly TypeActiviteRepository $typeActiviteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param ProjectionSeanceRow[] $rows
     */
    public function import(ProjectionImportContext $context, array $rows): void
    {
        $typeCours = $this->resolveCoursType($context);
        if (!$typeCours instanceof TypeActivite) {
            return;
        }

        foreach ($rows as $row) {
            if (!$row instanceof ProjectionSeanceRow || !$row->hasHours()) {
                continue;
            }

            $classe = $this->classeResolver->resolve($context, $row->classe);
            if ($classe === null) {
                continue;
            }

            $groupe = $this->groupeResolver->resolve($context, $classe, $row->groupe);
            $formateur = $this->formateurResolver->resolve($context, $row->formateur);
            $matiere = $this->matiereResolver->resolve($context, $row->matiere);

            if ($groupe === null || $formateur === null || $matiere === null) {
                continue;
            }

            $seance = new Seance();
            $seance->setSession($context->getTargetSession());
            $seance->setClasse($classe);
            $seance->setGroupe($groupe);
            $seance->setFormateur($formateur);
            $seance->setMatiere($matiere);
            $seance->setTypeActivite($typeCours);
            $seance->setVolumeHeuresFormateur((string) $row->reel);
            $seance->setVolumeHeuresFormateurPrevisionnel((string) $row->previsionnel);
            $seance->setVolumeHeuresGroupe((string) $row->reel);
            $seance->setVolumeHeuresGroupePrevisionnel((string) $row->previsionnel);

            $context->getReport()->incrementSeancesCreees();

            if (!$context->isDryRun()) {
                $this->entityManager->persist($seance);
            }
        }
    }

    private function resolveCoursType(ProjectionImportContext $context): ?TypeActivite
    {
        $cached = $context->getTypeActivite('COURS');
        if ($cached instanceof TypeActivite) {
            return $cached;
        }

        $typeCours = $this->typeActiviteRepository->findOneBy(['code' => 'COURS']);

        if (!$typeCours instanceof TypeActivite) {
            $context->getReport()->addError('TypeActivite COURS introuvable. Import impossible.');
            return null;
        }

        $context->setTypeActivite('COURS', $typeCours);

        return $typeCours;
    }
}
