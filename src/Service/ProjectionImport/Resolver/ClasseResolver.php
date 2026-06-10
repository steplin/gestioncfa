<?php

namespace App\Service\ProjectionImport\Resolver;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Entity\Classe;
use App\Repository\ClasseRepository;
use App\Repository\ReferentielFormationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClasseResolver
{
    public function __construct(
        private readonly ClasseRepository $classeRepository,
        private readonly ReferentielFormationRepository $referentielFormationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(ProjectionImportContext $context, string $classeName, ?string $type = null
    ): ?Classe
    {
        $classeName = $this->clean($classeName);

        if ($classeName === '') {
            $context->getReport()->addError('Classe vide dans le fichier Excel.');
            return null;
        }

        $cached = $context->getClasse($classeName);
        if ($cached instanceof Classe) {
            return $cached;
        }

        $code = $this->buildCode($classeName);

        $classe = $this->classeRepository->findOneBy([
            'session' => $context->getTargetSession(),
            'code' => $code,
        ]);

        if (!$classe instanceof Classe) {
            $classe = $this->classeRepository->findOneBy([
                'session' => $context->getTargetSession(),
                'nom' => $classeName,
            ]);
        }

        if ($classe instanceof Classe) {
            $context->setClasse($classeName, $classe);
            return $classe;
        }

        $classe = $this->createClasse($context, $classeName, $code, $type);
        $context->setClasse($classeName, $classe);
        $context->getReport()->addClasseCreee($classe->getNom() ?? $classeName);

        return $classe;
    }



    private function createClasse(ProjectionImportContext $context, string $classeName, string $code, ?string $type
    ): Classe
    {
        $classe = new Classe();
        $classe->setSession($context->getTargetSession());
        $classe->setNom($classeName);
        $classe->setCode($code);
        $classe->setAbrege($this->buildAbrege($classeName));
        $classe->setType($type ?? 'FL');

        $sourceClasse = $this->findSourceClasse($context, $classeName, $code);

        if ($sourceClasse instanceof Classe) {
            $classe->setEffectifPrevisionnel($sourceClasse->getEffectifPrevisionnel());
            $classe->setEffectifReel($sourceClasse->getEffectifReel());
            $classe->setNbSemainesPresence($sourceClasse->getNbSemainesPresence());

            if ($sourceClasse->getReferentielFormation() !== null) {
                $classe->setReferentielFormation($sourceClasse->getReferentielFormation());
            }
        }

        if ($classe->getReferentielFormation() === null) {
            $referentielCode = $this->guessReferentielCode($classeName);

            if ($referentielCode !== null) {
                $referentiel = $this->referentielFormationRepository->findOneBy([
                    'code' => $referentielCode,
                ]);

                if ($referentiel !== null) {
                    $classe->setReferentielFormation($referentiel);
                } else {
                    $context->getReport()->addWarning(sprintf(
                        'Référentiel "%s" introuvable pour la classe "%s".',
                        $referentielCode,
                        $classeName
                    ));
                }
            }
        }

        if ($classe->getReferentielFormation() === null) {
            $referentiel = $this->referentielFormationRepository->findOneBy(['actif' => true]);

            if ($referentiel !== null) {
                $classe->setReferentielFormation($referentiel);
                $context->getReport()->addWarning(sprintf(
                    'Référentiel par défaut affecté à la classe "%s". À vérifier.',
                    $classeName
                ));
            } else {
                $context->getReport()->addError(sprintf(
                    'Impossible de créer la classe "%s" : aucun référentiel disponible.',
                    $classeName
                ));
            }
        }

        if (!$context->isDryRun()) {
            $this->entityManager->persist($classe);
        }

        return $classe;
    }

    private function findSourceClasse(ProjectionImportContext $context, string $classeName, string $code): ?Classe
    {
        $sourceClasse = $this->classeRepository->findOneBy([
            'session' => $context->getSourceSession(),
            'code' => $code,
        ]);

        if ($sourceClasse instanceof Classe) {
            return $sourceClasse;
        }

        $sourceClasse = $this->classeRepository->findOneBy([
            'session' => $context->getSourceSession(),
            'nom' => $classeName,
        ]);

        if ($sourceClasse instanceof Classe) {
            return $sourceClasse;
        }

        $sourceClasseName = $this->replaceTargetSuffixBySourceSuffix($classeName, $context);
        if ($sourceClasseName !== $classeName) {
            $sourceCode = $this->buildCode($sourceClasseName);

            $sourceClasse = $this->classeRepository->findOneBy([
                'session' => $context->getSourceSession(),
                'code' => $sourceCode,
            ]);

            if ($sourceClasse instanceof Classe) {
                return $sourceClasse;
            }

            $sourceClasse = $this->classeRepository->findOneBy([
                'session' => $context->getSourceSession(),
                'nom' => $sourceClasseName,
            ]);

            if ($sourceClasse instanceof Classe) {
                return $sourceClasse;
            }
        }

        return null;
    }

    private function resolveMissionClasseName(string $sourceClasseName, ProjectionImportContext $context): string
    {
        $sourceClasseName = $this->clean($sourceClasseName);

        if ($this->isDiversFl($sourceClasseName)) {
            return 'DIVERS (FL)';
        }

        return $this->replaceSessionSuffix($sourceClasseName, $context);
    }

    private function isDiversFl(string $classeName): bool
    {
        return $this->normalizeForCompare($classeName) === 'DIVERS FL';
    }

    private function replaceSessionSuffix(string $classeName, ProjectionImportContext $context): string
    {
        $sourceSuffix = $this->sessionSuffix($context->getSourceSession());
        $targetSuffix = $this->sessionSuffix($context->getTargetSession());

        if ($sourceSuffix === null || $targetSuffix === null) {
            return $classeName;
        }

        return preg_replace(
            '/' . preg_quote($sourceSuffix, '/') . '$/',
            $targetSuffix,
            $classeName
        ) ?: $classeName;
    }

    private function replaceTargetSuffixBySourceSuffix(string $classeName, ProjectionImportContext $context): string
    {
        $sourceSuffix = $this->sessionSuffix($context->getSourceSession());
        $targetSuffix = $this->sessionSuffix($context->getTargetSession());

        if ($sourceSuffix === null || $targetSuffix === null) {
            return $classeName;
        }

        return preg_replace(
            '/' . preg_quote($targetSuffix, '/') . '$/',
            $sourceSuffix,
            $classeName
        ) ?: $classeName;
    }

    private function sessionSuffix(\App\Entity\Session $session): ?string
    {
        $debut = $session->getDateDebut()?->format('y');
        $fin = $session->getDateFin()?->format('y');

        if (!$debut || !$fin) {
            return null;
        }

        return $debut . '-' . $fin;
    }

    private function guessReferentielCode(string $classeName): ?string
    {
        $normalized = $this->normalizeForCompare($classeName);

        return match (true) {
            str_contains($normalized, 'BP AP') => 'BP_AP',
            str_contains($normalized, 'BP REA') || str_contains($normalized, 'BPREA') => 'BP_REA',
            str_contains($normalized, 'BTSA AP') => 'BTSA_AP',
            str_contains($normalized, 'CAPA JP') => 'CAPA_JP',
            default => null,
        };
    }

    private function guessType(string $classeName): string
    {
        $normalized = $this->normalizeForCompare($classeName);

        if ($this->isDiversFl($classeName) || str_contains(' ' . $normalized . ' ', ' FL ')) {
            return 'FL';
        }

        if (
            str_contains($normalized, 'FPC')
            || str_contains($normalized, 'FC')
            || str_contains($normalized, 'CONTINUE')
        ) {
            return 'continue';
        }

        return 'apprentissage';
    }

    private function buildCode(string $classeName): string
    {
        $code = $this->normalize($classeName);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        $code = trim((string) $code, '_');

        return mb_substr($code !== '' ? $code : 'CLASSE', 0, 50);
    }

    private function buildAbrege(string $classeName): string
    {
        return mb_substr($this->clean($classeName), 0, 50);
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

    private function normalizeForCompare(string $value): string
    {
        $value = $this->normalize($value);
        $value = str_replace(['(', ')'], ' ', $value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }
    public function resolveTypeFromGroupeName(string $groupeName): string
    {
        $normalized = $this->normalizeForCompare($groupeName);

        if (
            str_contains($normalized, 'FPC')
            || str_contains($normalized, 'FC')
            || str_contains($normalized, 'FORMATION CONTINUE')
            || str_contains($normalized, 'CONTINUE')
        ) {
            return 'FC';
        }

        return 'FL';
    }
}
