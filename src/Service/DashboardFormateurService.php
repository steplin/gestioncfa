<?php

namespace App\Service;

use App\Entity\Formateur;
use App\Entity\Seance;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;

class DashboardFormateurService
{
    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function build(?Formateur $formateur, Session $session): array
    {
        if (!$formateur) {
            return [];
        }
        $seances = $this->em->getRepository(Seance::class)
            ->findBy([
                'formateur' => $formateur,
                'session' => $session
            ]);

        $data = [
            'classes' => [],
            'missions' => [
                'lignes' => [],
                'totaux' => [
                    'ufa_face' => 0.0,
                    'ufa_travail' => 0.0,
                    'fpc_face' => 0.0,
                    'fpc_travail' => 0.0,
                    'ufa_face_prev' => 0.0,
                    'ufa_travail_prev' => 0.0,
                    'fpc_face_prev' => 0.0,
                    'fpc_travail_prev' => 0.0,
                ]
            ],
            'totaux' => [
                'ufa_face' => 0.0,
                'ufa_travail' => 0.0,
                'fpc_face' => 0.0,
                'fpc_travail' => 0.0,
                'mission' => 0.0,
                'total_travail' => 0.0,
                'face_face' => 0.0,
                'ufa_face_prev' => 0.0,
                'ufa_travail_prev' => 0.0,
                'fpc_face_prev' => 0.0,
                'fpc_travail_prev' => 0.0,
                'face_face_prev' => 0.0,
                'mission_prev' => 0.0,
            ],
            'analytique' => []
        ];

        foreach ($seances as $seance) {

            $type = $seance->getTypeActivite();
            $groupe = $seance->getGroupe();
            $classe = $seance->getClasse();


            $volume = (float)$seance->getVolumeHeuresFormateur();
            $volumePrev = (float)$seance->getVolumeHeuresFormateurPrevisionnel();
            $pondereForm = (float)$seance->getVolumePondereFormateur();
            $pondereFormPrev = (float)$seance->getVolumePondereFormateurPrev();

            $face = $type->isImpactFaceAFace() ? $volume : 0.0;
            $travail = $type->isImpactTempsTravail() ? $pondereForm : 0.0;
            $face_prev = $type->isImpactFaceAFace() ? $volumePrev : 0.0;
            $travail_prev = $type->isImpactTempsTravail() ? $pondereFormPrev : 0.0;

            if ($seance->getVolumeHeuresGroupe() == 0 and $seance->getVolumeHeuresGroupePrevisionnel() == 0) {
                continue;
            }
            $isUfa = $classe->getType() === 'FL';
            /*
   |--------------------------------------------------------------------------
   | 🔥 MISSIONS (ACTION)
   |--------------------------------------------------------------------------
   */
            if ($type->getCode() !== 'COURS') {

                $libelle = str_replace("KE -", "", $seance->getMatiere()?->getLibelle() ?? 'Mission');
                $data['missions']['lignes'][] = [
                    'libelle' => $libelle . " - " . $seance->getClasse()?->getNom(),
                    'ufa_face' => $isUfa ? $face : 0,
                    'ufa_travail' => $isUfa ? $travail : 0,
                    'fpc_face' => !$isUfa ? $face : 0,
                    'fpc_travail' => !$isUfa ? $travail : 0,
                    'ufa_face_prev' => $isUfa ? $face_prev : 0,
                    'ufa_travail_prev' => $isUfa ? $travail_prev : 0,
                    'fpc_face_prev' => !$isUfa ? $face_prev : 0,
                    'fpc_travail_prev' => !$isUfa ? $travail_prev : 0,
                    'groupe' => $seance->getGroupe(),
                    'groupe_id' => $seance->getGroupe()->getId(),
                    'classe_id' => $classe->getId(),
                    'id' => $seance->getId(),

                ];

                // Optionnel : comptabiliser dans totaux globaux
                $data['totaux']['mission'] += $travail;
                $data['totaux']['mission_prev'] += $travail_prev;
                $data['totaux']['face_face'] += $face;
                $data['totaux']['face_face_prev'] += $face_prev;
                if ($isUfa) {
                    $data['missions']['totaux']['ufa_travail'] += $travail;
                    $data['missions']['totaux']['ufa_face'] += $face;
                    $data['missions']['totaux']['ufa_travail_prev'] += $travail_prev;
                    $data['missions']['totaux']['ufa_face_prev'] += $face_prev;

                } else {
                    $data['missions']['totaux']['fpc_travail'] += $travail;
                    $data['missions']['totaux']['fpc_face'] += $face;
                    $data['missions']['totaux']['fpc_travail_prev'] += $travail_prev;
                    $data['missions']['totaux']['fpc_face_prev'] += $face_prev;
                }
                continue;
            }


            $classeNom = str_replace("KE ", "", $classe->getNom());

            if (!isset($data['classes'][$classeNom])) {
                $data['classes'][$classeNom] = [
                    'couleur' => method_exists($classe, 'getCouleur') ? $classe->getCouleur() : '#ffe699',
                    'groupe_id' => $seance->getGroupe()->getId(),
                    'classe_id' => $classe->getId(),
                    'groupes' => [],

                ];
            }

            $groupeNom = str_replace("KE ", "", $groupe->getNom());

            if (!isset($data['classes'][$classeNom]['groupes'][$groupeNom])) {
                $data['classes'][$classeNom]['groupes'][$groupeNom] = [];
            }
            $matiere = str_replace("KE - ", "", $seance->getMatiere()->getLibelle());
            $data['classes'][$classeNom]['groupes'][$groupeNom][] = [
                'matiere' => $matiere,
                'ufa_face' => $isUfa ? $face : 0,
                'ufa_travail' => $isUfa ? $travail : 0,
                'fpc_face' => !$isUfa ? $face : 0,
                'fpc_travail' => !$isUfa ? $travail : 0,
                'ufa_face_prev' => $isUfa ? $face_prev : 0,
                'ufa_travail_prev' => $isUfa ? $travail_prev : 0,
                'fpc_face_prev' => !$isUfa ? $face_prev : 0,
                'fpc_travail_prev' => !$isUfa ? $travail_prev : 0,
                'id' => $seance->getId(),
            ];

            if ($isUfa) {
                $data['totaux']['ufa_face'] += $face;
                $data['totaux']['ufa_travail'] += $travail;
                $data['totaux']['ufa_face_prev'] += $face_prev;
                $data['totaux']['ufa_travail_prev'] += $travail_prev;
            } else {
                $data['totaux']['fpc_face'] += $face;
                $data['totaux']['fpc_travail'] += $travail;
                $data['totaux']['fpc_face_prev'] += $face_prev;
                $data['totaux']['fpc_travail_prev'] += $travail_prev;
            }
            $data['totaux']['face_face'] += $face;
            $data['totaux']['face_face_prev'] += $face_prev;
        }

        // 🔥 TRI

        ksort($data['classes']);
        foreach ($data['classes'] as &$classe) {
            ksort($classe['groupes']);
            foreach ($classe['groupes'] as &$lignes) {
                usort($lignes, function ($a, $b) {
                    return strcasecmp($a['matiere'], $b['matiere']);
                });
            }
        }
        // 🔥 TRI MISSIONS
        usort($data['missions']['lignes'], function ($a, $b) {
            return strcasecmp($a['libelle'], $b['libelle']);
        });

        // 🔥 ANALYTIQUE
        $data['totaux']['total_travail'] = $totalTravail = $data['totaux']['ufa_travail'] + $data['totaux']['fpc_travail'] + $data['totaux']['mission'];
        $data['totaux']['total_travail_prev'] = $totalTravail_prev = $data['totaux']['ufa_travail_prev'] + $data['totaux']['fpc_travail_prev'] + $data['totaux']['mission_prev'];


        if ($totalTravail > 0) {

            $ufaPrc = ($data['totaux']['ufa_travail'] + $data['missions']['totaux']['ufa_travail']) / $formateur->getVolumeContractuel();
            $fpcPrc = ($data['totaux']['fpc_travail'] + $data['missions']['totaux']['fpc_travail']) / $formateur->getVolumeContractuel();
            $ufaPrc_prev = ($data['totaux']['ufa_travail_prev'] + $data['missions']['totaux']['ufa_travail_prev']) / $formateur->getVolumeContractuel();
            $fpcPrc_prev = ($data['totaux']['fpc_travail_prev'] + $data['missions']['totaux']['fpc_travail_prev']) / $formateur->getVolumeContractuel();
            $data['analytique'] = [
                'temps_travail_prev' => $totalTravail_prev,
                'temps_travail' => $totalTravail,
                'pourcentage' => $totalTravail * 100 / ($formateur->getVolumeContractuel()),
                'pourcentage_prev' => $totalTravail_prev * 100 / ($formateur->getVolumeContractuel()),
                'pourcentage_contrat' => $formateur->getQuotite() * 100,
                'temps_contrat' => $formateur->getVolumeContractuel() * $formateur->getQuotite(),
                'diff_heures' => ($formateur->getQuotite() * $formateur->getVolumeContractuel()) - $totalTravail,
                'diff_heures_prev' => ($formateur->getQuotite() * $formateur->getVolumeContractuel()) - $totalTravail_prev,
                'ufa_percent' => $ufaPrc,
                'fpc_percent' => $fpcPrc,
                'ufa_percent_prev' => $ufaPrc_prev,
                'fpc_percent_prev' => $fpcPrc_prev,
                'ufa_semaine' => $ufaPrc * 35,
                'fpc_semaine' => $fpcPrc * 35,
                'ufa_semaine_prev' => $ufaPrc_prev * 35,
                'fpc_semaine_prev' => $fpcPrc_prev * 35,
                'ufa_mois' => $ufaPrc * 151.67,
                'fpc_mois' => $fpcPrc * 151.67,
                'ufa_mois_prev' => $ufaPrc_prev * 151.67,
                'fpc_mois_prev' => $fpcPrc_prev * 151.67,
                'total_mois' => ($ufaPrc + $fpcPrc) * 151.67,
                'total_mois_prev' => ($ufaPrc_prev +$fpcPrc_prev) * 151.67,
            ];
        }
        return $data;
    }

}
