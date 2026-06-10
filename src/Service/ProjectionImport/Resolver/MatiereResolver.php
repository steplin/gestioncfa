<?php

namespace App\Service\ProjectionImport\Resolver;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Entity\Matiere;
use App\Repository\MatiereRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MatiereResolver
{
    public function __construct(
        private readonly MatiereRepository $matiereRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(ProjectionImportContext $context, string $matiereName): ?Matiere
    {
        $matiereName = $this->clean($matiereName);

        if ($matiereName === '') {
            $context->getReport()->addError('Matière vide dans le fichier Excel.');
            return null;
        }

        $cached = $context->getMatiere($matiereName);
        if ($cached instanceof Matiere) {
            return $cached;
        }

        $code = $this->buildCode($matiereName);

        $matiere = $this->matiereRepository->findOneBy(['code' => $code]);

        if (!$matiere instanceof Matiere) {
            $matiere = $this->matiereRepository->findOneBy(['libelle' => $matiereName]);
        }

        if ($matiere instanceof Matiere) {
            $context->setMatiere($matiereName, $matiere);
            return $matiere;
        }

        $matiere = $this->createMatiere($context, $matiereName, $code);
        $context->setMatiere($matiereName, $matiere);
        $context->getReport()->addMatiereCreee($matiere->getLibelle() ?? $matiereName);

        return $matiere;
    }

    private function createMatiere(ProjectionImportContext $context, string $matiereName, string $code): Matiere
    {
        $matiere = new Matiere();
        $matiere->setCode($code);
        $matiere->setLibelle($matiereName);
        $matiere->setCoefficient('2.00');

        $context->getReport()->addWarning(sprintf(
            'Matière créée automatiquement : "%s". Coefficient à vérifier.',
            $matiereName
        ));

        if (!$context->isDryRun()) {
            $this->entityManager->persist($matiere);
        }

        return $matiere;
    }

    private function buildCode(string $matiereName): string
    {
        $code = $this->normalize($matiereName);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        $code = trim((string) $code, '_');

        return mb_substr($code !== '' ? $code : 'MATIERE', 0, 50);
    }

    private function clean(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper($this->clean($value));
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');

        return $transliterator !== null ? $transliterator->transliterate($value) : $value;
    }
}
