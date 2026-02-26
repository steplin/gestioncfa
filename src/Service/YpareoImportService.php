<?php

namespace App\Service;

use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Matiere;
use App\Entity\Seance;
use App\Entity\Session;
use App\Entity\TypeActivite;
use Doctrine\ORM\EntityManagerInterface;

class YpareoImportService
{
    private array $formateurCache = [];
    private array $classeCache = [];
    private array $groupeCache = [];
    private array $matiereCache = [];

    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function importFromCsv(string $filePath, Session $session, bool $delete, $delimiter = ';'): array
    {
        $report = [
            'created' => 0,
            'errors' => [],
        ];

        if (!is_readable($filePath)) {
            $report['errors'][] = "Fichier illisible: {$filePath}";
            return $report;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $report['errors'][] = "Impossible d'ouvrir le fichier: {$filePath}";
            return $report;
        }

        // Header
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || count($header) === 0) {
            fclose($handle);
            $report['errors'][] = "Header CSV introuvable ou vide";
            return $report;
        }

        // Nettoyage BOM + trim
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $header = array_map(static fn($h) => trim((string)$h), $header);

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            // Ignore lignes vides
            if (count(array_filter($data, static fn($v) => trim((string)$v) !== '')) === 0) {
                continue;
            }

            // Si colonnes incohérentes
            if (count($data) !== count($header)) {
                $report['errors'][] = "Ligne {$line}: nombre de colonnes incorrect (" . count($data) . " au lieu de " . count($header) . ")";
                continue;
            }

            $row = array_combine($header, $data);
            if ($row === false) {
                $report['errors'][] = "Ligne {$line}: array_combine a échoué";
                continue;
            }

            // Trim valeurs
            foreach ($row as $k => $v) {
                $row[$k] = is_string($v) ? trim($v) : $v;
            }

            $rows[] = $row;
        }

        fclose($handle);

        // Import logique métier
        $logicReport = $this->import($rows, $session, $delete);// Merge erreurs + totaux

        $report['created'] = $logicReport['created'] ?? 0;
        $report['errors'] = array_merge($report['errors'], $logicReport['errors'] ?? []);

        return $report;
    }

    public function import(array $rows, Session $session, bool $delete = false): array
    {

        $report = [
            'created' => 0,
            'errors' => []
        ];


        if ($delete) {
            $this->em->createQuery(
                'DELETE FROM App\Entity\Seance s
                     WHERE s.session = :session
                     AND s.matiere IN (
                         SELECT m FROM App\Entity\Matiere m WHERE m.code = :code
                     )'
            )
                ->setParameter('session', $session)
                ->setParameter('code', 'COURS')
                ->execute();
        }
        $grouped = [];

        // 🔥 REGROUPEMENT DES LIGNES
        foreach ($rows as $row) {

            $key = implode('|', [
                $row['CODE_PERSONNEL'],
                $row['NOM_GROUPE_EDT'],
                $row['CODE_GROUPE'],
                $row['CODE_MATIERE'],
                $row['TYPE_SEANCE']
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'volumeForm' => 0,
                    'volumeGroupe' => 0,
                    'row' => $row
                ];
            }

            $grouped[$key]['volumeGroupe'] += (float)str_replace(',', '.', $row['DUREE_SEANCE']);
            $grouped[$key]['volumeForm'] += (float)str_replace(',', '.', $row['PLANIFIE']);
        }

        foreach ($grouped as $data) {

            $row = $data['row'];

            $formateur = $this->findOrCreateFormateur($row);
            $classe = $this->findOrCreateClasse($row, $session);
            $groupe = $this->findOrCreateGroupe($row, $session, $classe);
            $matiere = $this->findOrCreateMatiere($row);

            $typeActivite = $this->em->getRepository(TypeActivite::class)
                ->findOneBy(['code' => $row['TYPE_SEANCE']]);

            if ($typeActivite->getCode() == "ACTION") {
                continue;
            }

            if ($row['CODE_MATIERE'] == '2723420') {
                $typeActivite = $this->em->getRepository(TypeActivite::class)
                    ->findOneBy(['code' => 'ACCOMPAGNEMENT']);
            }
            if (!$typeActivite) {
                $report['errors'][] = "TypeActivite introuvable : " . $row['TYPE_SEANCE'];
                continue;
            }
            if ($data['volumeForm'] == 0 and $data['volumeGroupe'] == 0) {
                continue;
            }
            $seance = $this->em->getRepository(Seance::class)->findOneBy([
                'formateur' => $formateur,
                'classe' => $classe,
                'groupe' => $groupe,
                'matiere' => $matiere
            ]);

            if (!$seance) {
                $seance = new Seance();
                $seance
                    ->setSession($session)
                    ->setFormateur($formateur)
                    ->setGroupe($groupe)
                    ->setClasse($classe)
                    ->setMatiere($matiere)
                    ->setTypeActivite($typeActivite)
                    ->setVolumeHeuresFormateurPrevisionnel((string)$data['volumeForm'])
                    ->setVolumeHeuresGroupePrevisionnel((string)$data['volumeGroupe']);

            }

            $seance
                ->setVolumeHeuresFormateur((string)$data['volumeForm'])
                ->setVolumeHeuresGroupe((string)$data['volumeGroupe']);

            $this->em->persist($seance);
            $report['created']++;
        }

        $this->em->flush();

        return $report;
    }

    /* ================= FORMATEUR ================= */

    private function findOrCreateFormateur(array $row): Formateur
    {
        $code = $row['CODE_PERSONNEL'];

        if (isset($this->formateurCache[$code])) {
            return $this->formateurCache[$code];
        }

        $formateur = $this->em->getRepository(Formateur::class)
            ->findOneBy(['code' => $code]);

        if (!$formateur) {
            $formateur = new Formateur();
            $formateur
                ->setCode($code)
                ->setNom($row['NOM_FORMATEUR'])
                ->setPrenom($row['PRENOM_FORMATEUR'])
                ->setEmail($row['EMAIL_FORMATEUR'] ?? null)
                ->setVolumeContractuel(1530)
                ->setQuotite(1.00)
                ->setActif(true)
                ->setTypeContrat('CDI')
                ->setTauxHoraire(13.20)
            ;

            $this->em->persist($formateur);
        }

        $this->formateurCache[$code] = $formateur;

        return $formateur;
    }

    /* ================= CLASSE ================= */

    private function findOrCreateClasse(array $row, Session $session): Classe
    {
        $key = $row['CODE_GROUPE'] . '_' . $session->getId();

        if (isset($this->classeCache[$key])) {
            return $this->classeCache[$key];
        }

        $classe = $this->em->getRepository(Classe::class)
            ->findOneBy([
                'code' => $row['CODE_GROUPE'],
                'session' => $session
            ]);

        if (!$classe) {

            $classe = new Classe();
            $classe
                ->setCode($row['CODE_GROUPE'])
                ->setType($row['TYPE_GROUPE_INSCRIPTION'] === 'FC' ? 'FC' : 'FL')
                ->setSession($session);

            $this->em->persist($classe);
        }
        //Update nom classe
        $classeNom = $this->nettoyerNom($row['NOM_GROUPE']);
        $classe->setNom($classeNom);

        $this->classeCache[$key] = $classe;

        return $classe;
    }

    /* ================= GROUPE ================= */

    private function findOrCreateGroupe(array $row, Session $session, Classe $classe): Groupe
    {
        $key = $row['NOM_GROUPE_EDT'] . '_' . $session->getId();

        if (isset($this->groupeCache[$key])) {
            $groupe = $this->groupeCache[$key];
            $groupe->addClasse($classe);
            return $groupe;
        }

        $groupe = $this->em->getRepository(Groupe::class)
            ->findOneBy([
                'code' => $row['NOM_GROUPE_EDT'],
                'session' => $session
            ]);

        if (!$groupe) {
            $groupe = new Groupe();
            $groupe
                ->setCode($row['NOM_GROUPE_EDT'])
                ->setSession($session);

            $this->em->persist($groupe);
        }
        $niveau = $groupe->determinerNiveauDecoupage($row['NOM_GROUPE_EDT']);
        $groupe->setNiveauDecoupage($niveau);
        //Update nom groupe
        $groupeNom = $this->nettoyerNom( $row['NOM_GROUPE_EDT']);
        $groupe->setNom($groupeNom);

        $groupe->addClasse($classe);

        $this->groupeCache[$key] = $groupe;

        return $groupe;
    }

    /* ================= MATIERE ================= */

    private function findOrCreateMatiere(array $row): Matiere
    {
        $code = $row['CODE_MATIERE'];

        if (isset($this->matiereCache[$code])) {
            return $this->matiereCache[$code];
        }

        $matiere = $this->em->getRepository(Matiere::class)
            ->findOneBy(['code' => $code]);

        if (!$matiere) {

            $matiere = new Matiere();
            $matiere
                ->setCode($code)
                ->setCoefficient($row['COEFF_PROF'] ?: '2.00');

            $this->em->persist($matiere);
        }
        //update nom matiere
        $matiereNom = $this->nettoyerNom($row['NOM_MATIERE']);
        $matiere->setLibelle($matiereNom);
        $this->matiereCache[$code] = $matiere;

        return $matiere;
    }
    private function nettoyerNom(string $nom): string
    {
        $nom = strtoupper($nom);

        // 🔹 Supprimer années type 25-26, 25 - 26, 2025-2026, 25/26...
        $nom = preg_replace('/\b\d{2,4}\s*[-\/]\s*\d{2,4}\b/', '', $nom);

        // 🔹 Supprimer préfixe "KE -", "KE-", "KE "
        $nom = preg_replace('/^KE\s*-\s*/', '', $nom);
        $nom = preg_replace('/^KE\s+/', '', $nom);

        // 🔹 Supprimer KE isolé
        $nom = preg_replace('/\bKE\b/', '', $nom);

        // 🔹 Nettoyage espaces multiples
        $nom = preg_replace('/\s+/', ' ', $nom);

        // 🔹 Supprimer tirets ou espaces en fin de chaîne
        $nom = trim($nom, " -");

        return trim($nom);
    }
}
