<?php

namespace App\Service;

use App\Entity\AffectationMission;
use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\ReferentielFormation;
use App\Entity\Session;
use App\Repository\AffectationMissionRepository;
use App\Repository\ClasseRepository;
use App\Repository\SeanceRepository;

readonly class MissionCalculatorService
{
    public function __construct(
        private SeanceRepository             $seanceRepository,
        private AffectationMissionRepository $affectationMissionRepository,
        private ClasseRepository             $classeRepository,
    )
    {
    }

    public function calculate(Formateur $formateur, Session $session, string $mode = 'reel'): array
    {
        $this->assertMode($mode);

        $classiques = $this->calculateClassiques($formateur, $session, $mode);

        $refClasse = $this->calculateRefClasse($formateur, $session, $mode);
        $refNiveau = $this->calculateRefNiveau($formateur, $session, $mode);
        $handicap = $this->calculateForfaitByCode($formateur, $session, 'HANDICAP');
        $autres = $this->calculateForfaitByCode($formateur, $session, 'AUTRE');
        $autres2 = $this->calculateForfaitByCode($formateur, $session, 'AUTRES');

        $totalSupplementaires = round(
            $refClasse['total']
            + $refNiveau['total']
            + $handicap['total']
            + $autres['total']
            + $autres2['total'],
            2
        );

        $faf = $this->calculateFaf($formateur, $session, $mode);

// ADAF classique
        $adafClassique = $classiques['adaf'];
        $evaluation = $classiques['evaluation'];

// ADAF supplémentaire
        $adafSupplementaire = $totalSupplementaires;

// PRE (pour l’instant neutre)
        $pre = 0.0;

// TOTAL GLOBAL
        $totalGeneral = round(
            $faf
            + $pre
            + $adafClassique
            + $evaluation
            + $adafSupplementaire,
            2
        );

        return [
            'classiques' => $classiques,

            'supplementaires' => [
                'ref_classe' => $refClasse,
                'ref_niveau' => $refNiveau,
                'handicap' => $handicap,
                'autres' => $autres,
                'total' => $totalSupplementaires,
            ],

            'totaux' => [
                'general' => $totalGeneral,
                'faf' => $faf,
                'pre' => $pre,
                'adaf_classique' => $adafClassique,
                'evaluation' => $evaluation,
                'adaf_supplementaire' => $adafSupplementaire,
            ],
        ];
    }

    private function assertMode(string $mode): void
    {
        if (!in_array($mode, ['reel', 'previsionnel'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Mode invalide "%s". Valeurs attendues : reel | previsionnel.',
                $mode
            ));
        }

    }

    private function getTempsTravailFaceAFace(Formateur $formateur, Session $session, string $mode = 'reel'): float
    {
        return round(
            $this->seanceRepository->getTotalTempsTravailFaceAFaceByFormateurAndSession(
                $formateur,
                $session,
                $mode
            ),
            2
        );
    }

    private function calculateClassiques(Formateur $formateur, Session $session, string $mode = 'reel'): array
    {
        $base = $this->getTempsTravailFaceAFace($formateur, $session, $mode);
        $adaf = round($base * 0.22, 2);
        $evaluation = round($base * 0.03, 2);
        $pre = round($base * 0.25, 2);

        return [
            'base_temps_travail_face_a_face' => $base,
            'pre' => $pre,
            'adaf' => $adaf,
            'evaluation' => $evaluation,
            'total' => round($adaf + $evaluation, 2),
        ];
    }

    private function getEffectifClasse(Classe $classe, string $mode = 'reel'): int
    {
        return $mode === 'previsionnel'
            ? (int)($classe->getEffectifPrevisionnel() ?? 0)
            : (int)($classe->getEffectifReel() ?? 0);
    }

    private function calculateRefClasseForClasse(Classe $classe, string $mode = 'reel'): array
    {
        $referentiel = $classe->getReferentielFormation();

        $effectif = $this->getEffectifClasse($classe, $mode);
        $nbSemaines = (int)($classe->getNbSemainesPresence() ?? 0);

        $coefReferentClasse = (float)($referentiel?->getCoefReferentClasse() ?? 0);
        $coefAccompagnement = (float)($referentiel?->getCoefAccompagnement() ?? 0);

        $volumeReferent = $effectif * $nbSemaines * $coefReferentClasse;
        $volumeAccompagnement = $coefAccompagnement * $nbSemaines;
        $total = $volumeReferent + $volumeAccompagnement;

        return [
            'classe' => $classe,
            'referentiel' => $referentiel,
            'effectif' => $effectif,
            'nb_semaines_presence' => $nbSemaines,
            'coef_referent_classe' => $coefReferentClasse,
            'coef_accompagnement' => $coefAccompagnement,
            'volume_referent' => round($volumeReferent, 2),
            'volume_accompagnement' => round($volumeAccompagnement, 2),
            'total' => round($total, 2),
        ];
    }

    private function calculateRefClasse(Formateur $formateur, Session $session, string $mode = 'reel'): array
    {
        $affectations = $this->affectationMissionRepository
            ->findActivesByFormateurAndSession($formateur, $session);

        $details = [];
        $total = 0.0;

        foreach ($affectations as $affectation) {
            if ($affectation->getCategorieMission()?->getCode() !== 'REF_CLASSE') {
                continue;
            }

            $classe = $affectation->getClasse();
            if (!$classe instanceof Classe) {
                continue;
            }

            $detail = $this->calculateRefClasseForClasse($classe, $mode);

            $details[] = [
                'affectation' => $affectation,
                'calcul' => $detail,
            ];

            $total += $detail['total'];
        }

        return [
            'details' => $details,
            'total' => round($total, 2),
        ];
    }

    private function calculateRefNiveauForReferentiel(
        ReferentielFormation $referentielFormation,
        Session              $session,
        string               $mode = 'reel'
    ): array
    {
        $classes = $this->classeRepository->findBy([
            'session' => $session,
            'referentielFormation' => $referentielFormation,
        ]);

        $effectifTotal = 0;

        foreach ($classes as $classe) {
            $effectifTotal += $this->getEffectifClasse($classe, $mode);
        }

        $coefReferentNiveau = (float)($referentielFormation->getCoefReferentNiveau() ?? 0);
        $total = $effectifTotal * $coefReferentNiveau;

        return [
            'referentiel' => $referentielFormation,
            'effectif_total' => $effectifTotal,
            'coef_referent_niveau' => $coefReferentNiveau,
            'total' => round($total, 2),
        ];
    }

    private function calculateRefNiveau(Formateur $formateur, Session $session, string $mode = 'reel'): array
    {
        $affectations = $this->affectationMissionRepository
            ->findActivesByFormateurAndSession($formateur, $session);

        $details = [];
        $total = 0.0;
        $referentielsTraites = [];

        foreach ($affectations as $affectation) {
            if ($affectation->getCategorieMission()?->getCode() !== 'REF_NIVEAU') {
                continue;
            }

            $referentiel = $affectation->getReferentielFormation();
            if (!$referentiel instanceof ReferentielFormation) {
                continue;
            }

            $key = (string)$referentiel->getId();
            if (isset($referentielsTraites[$key])) {
                continue;
            }

            $referentielsTraites[$key] = true;

            $detail = $this->calculateRefNiveauForReferentiel($referentiel, $session, $mode);

            $details[] = [
                'affectation' => $affectation,
                'calcul' => $detail,
            ];

            $total += $detail['total'];
        }

        return [
            'details' => $details,
            'total' => round($total, 2),
        ];
    }

    private function getValeurAffectation(AffectationMission $affectation): float
    {
        $valeurManuelle = $affectation->getValeurManuelle();

        if ($valeurManuelle !== null && $valeurManuelle !== '') {
            return round((float)$valeurManuelle, 2);
        }

        return round((float)($affectation->getCategorieMission()?->getValeurDefaut() ?? 0), 2);
    }

    private function calculateForfaitByCode(Formateur $formateur, Session $session, string $codeMission): array
    {
        $affectations = $this->affectationMissionRepository
            ->findActivesByFormateurAndSession($formateur, $session);

        $details = [];
        $total = 0.0;

        foreach ($affectations as $affectation) {
            if ($affectation->getCategorieMission()?->getCode() !== $codeMission) {
                continue;
            }

            $valeur = $this->getValeurAffectation($affectation);

            $details[] = [
                'affectation' => $affectation,
                'valeur' => $valeur,
            ];

            $total += $valeur;
        }

        return [
            'details' => $details,
            'total' => round($total, 2),
        ];
    }
    private function calculateFaf(Formateur $formateur, Session $session, string $mode): float
    {
        return round(
            $this->seanceRepository->getTotalFaceAFaceByFormateurAndSession($formateur, $session, $mode),
            2
        );
    }
}
