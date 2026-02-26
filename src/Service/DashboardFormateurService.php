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
            ],
            'analytique' => []
        ];

        foreach ($seances as $seance) {

            $type = $seance->getTypeActivite();
            $groupe = $seance->getGroupe();
            $classe = $seance->getClasse();


            $volume = (float)$seance->getVolumeHeuresFormateur();
            $pondereForm = (float)$seance->getVolumePondereFormateur();

            $face = $type->isImpactFaceAFace() ? $volume : 0.0;
            $travail = $type->isImpactTempsTravail() ? $pondereForm : 0.0;

            if ($seance->getVolumeHeuresGroupe() == 0) {
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
                    'groupe' => $seance->getGroupe(),
                    'groupe_id' => $seance->getGroupe()->getId(),
                    'classe_id' => $classe->getId(),
                    'id' => $seance->getId(),

                ];

                // Optionnel : comptabiliser dans totaux globaux
                $data['totaux']['mission'] += $travail;
                $data['totaux']['face_face'] += $face;
                if ($isUfa) {
                    $data['missions']['totaux']['ufa_travail'] += $travail;
                    $data['missions']['totaux']['ufa_face'] += $face;
                } else {
                    $data['missions']['totaux']['fpc_travail'] += $travail;
                    $data['missions']['totaux']['fpc_face'] += $face;
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
                'id' => $seance->getId(),
            ];

            if ($isUfa) {
                $data['totaux']['ufa_face'] += $face;
                $data['totaux']['ufa_travail'] += $travail;
            } else {
                $data['totaux']['fpc_face'] += $face;
                $data['totaux']['fpc_travail'] += $travail;
            }
            $data['totaux']['face_face'] += $face;
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


        if ($totalTravail > 0) {
            $ufaPrc = ($data['totaux']['ufa_travail'] + $data['missions']['totaux']['ufa_travail']) * 100 / $totalTravail;
            $fpcPrc = ($data['totaux']['fpc_travail'] + $data['missions']['totaux']['fpc_travail']) * 100 / $totalTravail;
            $data['analytique'] = [
                'pourcentage' => $totalTravail * 100 / ($formateur->getVolumeContractuel()),
                'pourcentage_previsitionnel' => $formateur->getQuotite() * 100,
                'diff_heures' => ($formateur->getQuotite() * $formateur->getVolumeContractuel()) - $totalTravail,
                'ufa_percent' => $ufaPrc,
                'fpc_percent' => $fpcPrc,
                'ufa_semaine' => ($data['totaux']['ufa_travail'] + $data['missions']['totaux']['ufa_travail']) / 52,
                'fpc_semaine' => ($data['totaux']['fpc_travail'] + $data['missions']['totaux']['fpc_travail']) / 52,
                'ufa_mois' => ($data['totaux']['ufa_travail'] + $data['missions']['totaux']['ufa_travail']) / 12,
                'fpc_mois' => ($data['totaux']['fpc_travail'] + $data['missions']['totaux']['fpc_travail']) / 12,
                'total_mois' => $totalTravail / 12,
            ];
        }

        return $data;
    }

}
