<?php

namespace App\Service\ProjectionImport\Resolver;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Entity\Formateur;
use App\Repository\FormateurRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FormateurResolver
{
    public function __construct(
        private readonly FormateurRepository $formateurRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(
        ProjectionImportContext $context,
        string $formateurName
    ): ?Formateur {
        $formateurName = $this->clean($formateurName);

        if ($formateurName === '') {
            $context->getReport()->addError('Formateur vide dans le fichier Excel.');
            return null;
        }

        $cached = $context->getFormateur($formateurName);
        if ($cached instanceof Formateur) {
            return $cached;
        }

        $formateur = $this->findExistingFormateur($formateurName);

        if ($formateur instanceof Formateur) {
            $context->setFormateur($formateurName, $formateur);
            return $formateur;
        }

        $formateur = $this->createFormateur($context, $formateurName);

        $context->setFormateur($formateurName, $formateur);
        $context->getReport()->addFormateurCree($formateur->getNomComplet());

        return $formateur;
    }

    private function findExistingFormateur(string $formateurName): ?Formateur
    {
        $wanted = $this->normalizeForCompare($formateurName);

        foreach ($this->formateurRepository->findAll() as $formateur) {
            if (!$formateur instanceof Formateur) {
                continue;
            }

            $prenom = $formateur->getPrenom() ?? '';
            $nom = $formateur->getNom() ?? '';
            $code = $formateur->getCode() ?? '';
            $initiales = $formateur->getInitiales() ?? '';
            $nomComplet = $formateur->getNomComplet();

            $candidates = [
                $nomComplet,
                trim($prenom . ' ' . $nom),
                trim($nom . ' ' . $prenom),
                trim($code),
                trim($initiales),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                if ($wanted === $this->normalizeForCompare($candidate)) {
                    return $formateur;
                }
            }
        }

        return null;
    }

    private function createFormateur(
        ProjectionImportContext $context,
        string $formateurName
    ): Formateur {
        [$prenom, $nom] = $this->splitNomComplet($formateurName);

        $formateur = new Formateur();
        $formateur->setPrenom($prenom !== '' ? $prenom : 'À compléter');
        $formateur->setNom($nom !== '' ? $nom : $formateurName);
        $formateur->setCode($this->buildCode($formateurName));
        $formateur->setEmail($this->buildTemporaryEmail($formateurName));
        $formateur->setActif(true);
        $formateur->setInitiales($formateur->generateInitiales());

        $context->getReport()->addWarning(sprintf(
            'Formateur créé automatiquement : "%s". Email provisoire à vérifier.',
            $formateurName
        ));

        if (!$context->isDryRun()) {
            $this->entityManager->persist($formateur);
        }

        return $formateur;
    }

    /**
     * Utilisé uniquement si le formateur n'existe pas.
     *
     * "Alain LE GOFF" devient :
     * prénom = Alain
     * nom = LE GOFF
     */
    private function splitNomComplet(string $formateurName): array
    {
        $parts = preg_split('/\s+/', $this->clean($formateurName));

        if (!$parts || count($parts) === 1) {
            return ['', $formateurName];
        }

        $prenom = array_shift($parts);
        $nom = implode(' ', $parts);

        return [
            mb_convert_case((string) $prenom, MB_CASE_TITLE, 'UTF-8'),
            mb_strtoupper((string) $nom),
        ];
    }

    private function buildCode(string $formateurName): string
    {
        $code = $this->normalize($formateurName);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code);
        $code = trim((string) $code, '_');

        return mb_substr($code !== '' ? $code : 'FORMATEUR', 0, 50);
    }

    private function buildTemporaryEmail(string $formateurName): string
    {
        $slug = $this->buildCode($formateurName);
        $slug = mb_strtolower($slug);
        $slug = str_replace('_', '.', $slug);

        return $slug . '@temp.local';
    }

    private function clean(string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function normalize(string $value): string
    {
        $value = $this->clean($value);
        $value = mb_strtoupper($value);

        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator !== null) {
            $value = $transliterator->transliterate($value);
        }

        return $value;
    }

    private function normalizeForCompare(string $value): string
    {
        $value = $this->normalize($value);

        $value = str_replace(
            ["'", '’', '-', '_', '.', ',', ';', ':', '/', '\\', '(', ')'],
            ' ',
            $value
        );

        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);

        return trim((string) $value);
    }
}
