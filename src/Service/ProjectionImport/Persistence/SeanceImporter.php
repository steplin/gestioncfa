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
        foreach ($rows as $row) {
            if (!$row instanceof ProjectionSeanceRow || !$row->hasHours()) {
                continue;
            }

            $typeActivite = $this->resolveTypeActivite($context, $row->typeActiviteCode);
            if (!$typeActivite instanceof TypeActivite) {
                continue;
            }

            $typeClasse = $this->classeResolver->resolveTypeFromGroupeName($row->groupe);

            $classe = $this->classeResolver->resolve(
                $context,
                $row->classe,
                $typeClasse
            );

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
            $seance->setTypeActivite($typeActivite);
            $seance->setVolumeHeuresFormateur((string) $row->reel);
            $seance->setVolumeHeuresFormateurPrevisionnel((string) $row->previsionnel);
            $seance->setVolumeHeuresGroupe((string) $row->reel);
            $seance->setVolumeHeuresGroupePrevisionnel((string) $row->previsionnel);

            $context->getReport()->incrementSeancesCreees();

            if ($row->isMission()) {
                $context->getReport()->addMissionCopiee(sprintf(
                    '%s / %s / %s / %s',
                    $row->formateur,
                    $row->classe,
                    $row->groupe,
                    $row->matiere
                ));
            }

            if (!$context->isDryRun()) {
                $this->entityManager->persist($seance);
            }
        }
    }

    private function resolveTypeActivite(
        ProjectionImportContext $context,
        string $code
    ): ?TypeActivite {
        $code = trim($code);

        if ($code === '') {
            $context->getReport()->addError('TypeActivite vide. Ligne ignorée.');
            return null;
        }

        $cacheKey = $this->normalizeForCompare($code);

        $cached = $context->getTypeActivite($cacheKey);
        if ($cached instanceof TypeActivite) {
            return $cached;
        }

        /*
         * 1. Recherche exacte
         * Exemple : ACCOMPAGNEMENT, ACTION, REFERENT
         */
        $typeActivite = $this->typeActiviteRepository->findOneBy([
            'code' => $code,
        ]);

        if ($typeActivite instanceof TypeActivite) {
            $context->setTypeActivite($cacheKey, $typeActivite);
            return $typeActivite;
        }

        /*
         * 2. Recherche normalisée
         * Permet de faire correspondre :
         * ENTRETIEN_HAND
         * ENTRETIEN HAND
         * Entretien hand
         */
        foreach ($this->typeActiviteRepository->findAll() as $candidate) {
            if (!$candidate instanceof TypeActivite) {
                continue;
            }

            if (
                $this->normalizeForCompare((string) $candidate->getCode()) === $cacheKey
                || $this->normalizeForCompare((string) $candidate->getLibelle()) === $cacheKey
            ) {
                $context->setTypeActivite($cacheKey, $candidate);
                return $candidate;
            }
        }

        $context->getReport()->addError(sprintf(
            'TypeActivite "%s" introuvable. Ligne ignorée.',
            $code
        ));

        return null;
    }
    private function normalizeForCompare(string $value): string
    {
        $value = $this->normalize($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = mb_strtoupper((string) $value);

        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator !== null) {
            $value = $transliterator->transliterate($value);
        }

        return $value;
    }
}
