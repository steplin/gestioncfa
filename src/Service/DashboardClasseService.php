<?php

namespace App\Service;

use App\Entity\Classe;
use App\Entity\Seance;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;

class DashboardClasseService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function build(Classe $classe, Session $session, string $mode, $view = 'formateur', bool $prioritaireOnly = false): array
    {
        $mode = in_array($mode, ['reel', 'prev', 'both'], true) ? $mode : 'both';
        $view = in_array($view, ['formateur', 'matiere'], true) ? $view : 'formateur';

        $seances = $this->em->getRepository(Seance::class)->prioritaire($session, $classe, $prioritaireOnly);

        $data = [
            'classe' => $classe,
            'session' => $session,
            'mode' => $mode,
            'view' => $view,
            'matieres' => [],
            'groupes' => [],
            'totaux' => [
                'reel' => 0.0,
                'prev' => 0.0,
            ],
        ];

        foreach ($seances as $seance) {

            $g = $seance->getGroupe();
            $f = $seance->getFormateur();
            $m = $seance->getMatiere();

            $gid = (int)$g->getId();
            $fid = (int)$f->getId();
            $mid = (int)$m->getId();

            if (!isset($data['groupes'][$gid])) {
                $data['groupes'][$gid] = [
                    'groupe' => $g,
                    'formateurs' => [],
                    'matieres' => [],
                    'totaux' => ['reel' => 0.0, 'prev' => 0.0],
                ];
            }

            $real = (float)($seance->getVolumeHeuresGroupe() ?? 0);
            $prev = (float)($seance->getVolumeHeuresGroupePrevisionnel() ?? 0);

            // ===============================
            // VIEW MATIERE (sans formateur)
            // ===============================
            if ($view === 'matiere' and $prioritaireOnly === false) {

                if (!isset($data['groupes'][$gid]['matieres'][$mid])) {
                    $data['groupes'][$gid]['matieres'][$mid] = [
                        'matiere' => $m,
                        'reel' => 0.0,
                        'prev' => 0.0,
                    ];
                }

                $data['groupes'][$gid]['matieres'][$mid]['reel'] += $real;
                $data['groupes'][$gid]['matieres'][$mid]['prev'] += $prev;
            }
            // ===============================
            // VIEW MATIERE (sans formateur) + prioritaire
            // ======
            elseif ($view === 'matiere' and $prioritaireOnly === true) {
                if (!isset($data['matieres'][$mid])) {
                    $data['matieres'][$mid] = [
                        'matiere' => $m,
                        'reel' => 0.0,
                        'prev' => 0.0,
                    ];
                }
                $data['matieres'][$mid]['reel'] += $real;
                $data['matieres'][$mid]['prev'] += $prev;
                $data['totaux']['reel'] += $real;
                $data['totaux']['prev'] += $prev;
                continue;
            }

            // ===============================
            // VIEW FORMATEUR (ton système actuel)
            // ===============================
            else {

                if (!isset($data['groupes'][$gid]['formateurs'][$fid])) {
                    $data['groupes'][$gid]['formateurs'][$fid] = [
                        'formateur' => $f,
                        'lignes' => [],
                        'totaux' => ['reel' => 0.0, 'prev' => 0.0],
                    ];
                }

                if (!isset($data['groupes'][$gid]['formateurs'][$fid]['lignes'][$mid])) {
                    $data['groupes'][$gid]['formateurs'][$fid]['lignes'][$mid] = [
                        'matiere' => $m,
                        'id' => $seance->getId(),
                        'reel' => 0.0,
                        'prev' => 0.0,
                    ];
                }

                $data['groupes'][$gid]['formateurs'][$fid]['lignes'][$mid]['reel'] += $real;
                $data['groupes'][$gid]['formateurs'][$fid]['lignes'][$mid]['prev'] += $prev;

                $data['groupes'][$gid]['formateurs'][$fid]['totaux']['reel'] += $real;
                $data['groupes'][$gid]['formateurs'][$fid]['totaux']['prev'] += $prev;
            }

            // Totaux groupe
            $data['groupes'][$gid]['totaux']['reel'] += $real;
            $data['groupes'][$gid]['totaux']['prev'] += $prev;

            // Total général
            $data['totaux']['reel'] += $real;
            $data['totaux']['prev'] += $prev;
        }

        // Tri
        // Tri des groupes par nom
        uasort($data['groupes'], fn($a, $b) => strcasecmp($a['groupe']->getNom(), $b['groupe']->getNom()));
        if ($view === 'matiere' and $prioritaireOnly === true) {
            uasort($data['matieres'], fn($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
        }
        foreach ($data['groupes'] as &$gBlock) {

            if ($view === 'matiere') {
                // Tri matières par libellé
                uasort($gBlock['matieres'], fn($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
                continue;
            }

            // Tri formateurs par nom puis prénom
            uasort($gBlock['formateurs'], function ($a, $b) {
                $fa = $a['formateur'];
                $fb = $b['formateur'];
                $c = strcasecmp($fa->getNom(), $fb->getNom());
                return $c !== 0 ? $c : strcasecmp($fa->getPrenom(), $fb->getPrenom());
            });

            // Tri lignes (matières) par libellé
            foreach ($gBlock['formateurs'] as &$fBlock) {
                uasort($fBlock['lignes'], fn($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
            }
        }
        return $data;

    }
}
