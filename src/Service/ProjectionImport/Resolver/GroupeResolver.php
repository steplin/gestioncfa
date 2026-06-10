<?php

namespace App\Service\ProjectionImport\Resolver;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Entity\Classe;
use App\Entity\Groupe;
use App\Repository\GroupeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class GroupeResolver
{
    public function __construct(
        private readonly GroupeRepository $groupeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(ProjectionImportContext $context, Classe $classe, string $groupeName): ?Groupe
    {
        $groupeName = $this->clean($groupeName);

        if ($groupeName === '') {
            $context->getReport()->addError(sprintf('Groupe vide pour la classe "%s".', $classe->getNom()));
            return null;
        }

        $cacheKey = ($classe->getCode() ?? '') . '|' . $groupeName;
        $cached = $context->getGroupe($cacheKey);
        if ($cached instanceof Groupe) {
            return $cached;
        }

        $code = $this->buildCode($groupeName);

        $groupe = $this->groupeRepository->findOneBy([
            'session' => $context->getTargetSession(),
            'code' => $code,
        ]);

        if (!$groupe instanceof Groupe) {
            $groupe = $this->groupeRepository->findOneBy([
                'session' => $context->getTargetSession(),
                'nom' => $groupeName,
            ]);
        }

        if ($groupe instanceof Groupe) {
            $this->attachClasse($groupe, $classe, $context);
            $context->setGroupe($cacheKey, $groupe);
            return $groupe;
        }

        $groupe = $this->createGroupe($context, $classe, $groupeName, $code);
        $context->setGroupe($cacheKey, $groupe);
        $context->getReport()->addGroupeCree($groupe->getNom() ?? $groupeName);

        return $groupe;
    }

    private function createGroupe(ProjectionImportContext $context, Classe $classe, string $groupeName, string $code): Groupe
    {
        $groupe = new Groupe();
        $groupe->setSession($context->getTargetSession());
        $groupe->setNom($groupeName);
        $groupe->setCode($code);
        $groupe->setAbrege($this->buildAbrege($groupeName));
        $groupe->setNiveauDecoupage($groupe->determinerNiveauDecoupage($groupeName));
        $groupe->setPrioritaire($this->guessPrioritaire($groupeName));

        $this->attachClasse($groupe, $classe, $context);

        if (!$context->isDryRun()) {
            $this->entityManager->persist($groupe);
        }

        return $groupe;
    }

    private function attachClasse(Groupe $groupe, Classe $classe, ProjectionImportContext $context): void
    {
        if (!$groupe->getClasses()->contains($classe)) {
            $groupe->addClasse($classe);

            if (!$context->isDryRun()) {
                $this->entityManager->persist($groupe);
                $this->entityManager->persist($classe);
            }
        }
    }

    private function buildCode(string $groupeName): string
    {
        $code = $this->normalize($groupeName);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        $code = trim((string) $code, '_');

        return mb_substr($code !== '' ? $code : 'GROUPE', 0, 50);
    }

    private function buildAbrege(string $groupeName): string
    {
        return mb_substr($this->clean($groupeName), 0, 50);
    }

    private function guessPrioritaire(string $groupeName): bool
    {
        $normalized = mb_strtoupper($groupeName);

        return !(
            str_contains($normalized, 'INDIV')
            || preg_match('/\bIND\b/', $normalized)
            || preg_match('/\bINV\b/', $normalized)
        );
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
