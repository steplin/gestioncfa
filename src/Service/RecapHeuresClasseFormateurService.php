<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\Seance;
use Doctrine\ORM\EntityManagerInterface;

class RecapHeuresClasseFormateurService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function build(Session $session, string $mode = 'reel'): array
    {
        $seances = $this->em->getRepository(Seance::class)
            ->findBy(['session' => $session]);

        $data = [
            'FL' => [],
            'FC' => [],
        ];

        $formateurs = [];

        // ✅ Totaux globaux
        $totalFormateurs = []; // [formateurId => float]
        $grandTotal = 0.0;

        foreach ($seances as $seance) {

            $classe = $seance->getClasse();
            $formateur = $seance->getFormateur();
            $typeCode = $seance->getTypeActivite()->getCode();
            if($seance->getTypeActivite()->isImpactFormateur()===false){
                continue;
            }
            // 🔹 Choix du champ selon mode
            $heures = $mode === 'prev'
                ? (float) $seance->getVolumePondereFormateurPrev()
                : (float) $seance->getVolumePondereFormateur();

            if (!$heures) {
                continue;
            }

            $categorie = $classe->getType() === 'FL' ? 'FL' : 'FC';
            $classeId = $classe->getId();
            $formateurId = $formateur->getId();

            $formateurs[$formateurId] = $formateur;

            if (!isset($data[$categorie][$classeId])) {
                $data[$categorie][$classeId] = [
                    'classe' => $classe,
                    'COURS' => [],
                    'MISSION' => [],
                    // ✅ sous-totaux par classe
                    'totaux' => [
                        'cours' => 0.0,
                        'mission' => 0.0,
                        'total' => 0.0,
                    ],
                ];
            }

            $bloc = $typeCode === 'COURS' ? 'COURS' : 'MISSION';

            // Matrice
            $data[$categorie][$classeId][$bloc][$formateurId] =
                ($data[$categorie][$classeId][$bloc][$formateurId] ?? 0) + $heures;

            // ✅ Sous-totaux classe
            if ($bloc === 'COURS') {
                $data[$categorie][$classeId]['totaux']['cours'] += $heures;
            } else {
                $data[$categorie][$classeId]['totaux']['mission'] += $heures;
            }
            $data[$categorie][$classeId]['totaux']['total'] += $heures;

            // ✅ Totaux formateurs + grandTotal
            $totalFormateurs[$formateurId] = ($totalFormateurs[$formateurId] ?? 0) + $heures;
            $grandTotal += $heures;
        }

        // 🔹 Tri classes alphabétique
        foreach (['FL', 'FC'] as $cat) {
            uasort($data[$cat], fn($a, $b) =>
            strcmp($a['classe']->getNom(), $b['classe']->getNom())
            );
        }

        // 🔹 Tri formateurs alphabétique
        uasort($formateurs, fn($a, $b) =>
        strcmp($a->getNom(), $b->getNom())
        );

        return [
            'data' => $data,
            'formateurs' => $formateurs,
            'mode' => $mode,
            'totalFormateurs' => $totalFormateurs,
            'grandTotal' => $grandTotal,
        ];
    }
}
