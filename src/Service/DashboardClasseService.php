<?php

namespace App\Service;

use App\Entity\AffectationMission;
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

            $gid = (int) $g->getId();
            $fid = (int) $f->getId();
            $mid = (int) $m->getId();

            if (!isset($data['groupes'][$gid])) {
                $data['groupes'][$gid] = [
                    'groupe' => $g,
                    'formateurs' => [],
                    'matieres' => [],
                    'totaux' => ['reel' => 0.0, 'prev' => 0.0],
                ];
            }

            $real = (float) ($seance->getVolumeHeuresGroupe() ?? 0);
            $prev = (float) ($seance->getVolumeHeuresGroupePrevisionnel() ?? 0);

            if ($view === 'matiere' && $prioritaireOnly === false) {
                if (!isset($data['groupes'][$gid]['matieres'][$mid])) {
                    $data['groupes'][$gid]['matieres'][$mid] = [
                        'matiere' => $m,
                        'reel' => 0.0,
                        'prev' => 0.0,
                    ];
                }

                $data['groupes'][$gid]['matieres'][$mid]['reel'] += $real;
                $data['groupes'][$gid]['matieres'][$mid]['prev'] += $prev;
            } elseif ($view === 'matiere' && $prioritaireOnly === true) {
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
            } else {
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

            $data['groupes'][$gid]['totaux']['reel'] += $real;
            $data['groupes'][$gid]['totaux']['prev'] += $prev;

            $data['totaux']['reel'] += $real;
            $data['totaux']['prev'] += $prev;
        }

        uasort($data['groupes'], fn ($a, $b) => strcasecmp($a['groupe']->getNom(), $b['groupe']->getNom()));

        if ($view === 'matiere' && $prioritaireOnly === true) {
            uasort($data['matieres'], fn ($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
        }

        foreach ($data['groupes'] as &$gBlock) {
            if ($view === 'matiere') {
                uasort($gBlock['matieres'], fn ($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
                continue;
            }

            uasort($gBlock['formateurs'], function ($a, $b) {
                $fa = $a['formateur'];
                $fb = $b['formateur'];
                $c = strcasecmp($fa->getNom(), $fb->getNom());
                return $c !== 0 ? $c : strcasecmp($fa->getPrenom(), $fb->getPrenom());
            });

            foreach ($gBlock['formateurs'] as &$fBlock) {
                uasort($fBlock['lignes'], fn ($a, $b) => strcasecmp($a['matiere']->getLibelle(), $b['matiere']->getLibelle()));
            }
        }

        return $data;
    }

    public function buildExportData(
        Classe $classe,
        Session $session,
        string $mode = 'both',
        string $view = 'formateur',
        bool $prioritaireOnly = false
    ): array {
        $data = $this->build($classe, $session, $mode, $view, $prioritaireOnly);

        return [
            'meta' => [
                'classe' => $classe,
                'session' => $session,
                'mode' => $data['mode'],
                'view' => $data['view'],
                'prioritaire' => $prioritaireOnly,
            ],
            'lignes' => $this->buildExportRows($data, $data['view'], $prioritaireOnly),
            'missions' => $this->buildExportMissions($classe, $session, $data['mode']),
            'totaux' => $data['totaux'],
        ];
    }

    private function buildExportRows(array $data, string $view, bool $prioritaireOnly): array
    {
        if ($view === 'matiere') {
            return $this->buildExportRowsMatiere($data, $prioritaireOnly);
        }

        return $this->buildExportRowsFormateur($data);
    }

    private function buildExportRowsFormateur(array $data): array
    {
        $rows = [];

        foreach ($data['groupes'] as $gBlock) {
            $groupe = $gBlock['groupe'];

            $rows[] = [
                'type' => 'groupe',
                'groupe' => $groupe->getNom(),
                'formateur' => '',
                'matiere' => '',
                'reel' => null,
                'prev' => null,
            ];

            foreach ($gBlock['formateurs'] as $fBlock) {
                $formateur = $fBlock['formateur'];

                $rows[] = [
                    'type' => 'formateur',
                    'groupe' => $groupe->getNom(),
                    'formateur' => $formateur->getNomComplet(),
                    'matiere' => '',
                    'reel' => null,
                    'prev' => null,
                ];

                foreach ($fBlock['lignes'] as $ligne) {
                    $matiere = $ligne['matiere'];

                    $rows[] = [
                        'type' => 'ligne',
                        'groupe' => $groupe->getNom(),
                        'formateur' => '',
                        'matiere' => $matiere->getLibelle(),
                        'reel' => (float) ($ligne['reel'] ?? 0),
                        'prev' => (float) ($ligne['prev'] ?? 0),
                    ];
                }

                $rows[] = [
                    'type' => 'sous_total_formateur',
                    'groupe' => $groupe->getNom(),
                    'formateur' => 'Sous-total ' . $formateur->getNomComplet(),
                    'matiere' => '',
                    'reel' => (float) ($fBlock['totaux']['reel'] ?? 0),
                    'prev' => (float) ($fBlock['totaux']['prev'] ?? 0),
                ];
            }

            $rows[] = [
                'type' => 'total_groupe',
                'groupe' => $groupe->getNom(),
                'formateur' => 'Total groupe',
                'matiere' => $groupe->getNom(),
                'reel' => (float) ($gBlock['totaux']['reel'] ?? 0),
                'prev' => (float) ($gBlock['totaux']['prev'] ?? 0),
            ];

            $rows[] = [
                'type' => 'empty',
                'groupe' => '',
                'formateur' => '',
                'matiere' => '',
                'reel' => null,
                'prev' => null,
            ];
        }

        return $rows;
    }

    private function buildExportRowsMatiere(array $data, bool $prioritaireOnly): array
    {
        $rows = [];

        if ($prioritaireOnly) {
            $rows[] = [
                'type' => 'groupe',
                'groupe' => 'Prioritaire',
                'matiere' => '',
                'reel' => null,
                'prev' => null,
            ];

            foreach ($data['matieres'] as $mBlock) {
                $matiere = $mBlock['matiere'];

                $rows[] = [
                    'type' => 'ligne',
                    'groupe' => 'Prioritaire',
                    'matiere' => $matiere->getLibelle(),
                    'reel' => (float) ($mBlock['reel'] ?? 0),
                    'prev' => (float) ($mBlock['prev'] ?? 0),
                ];
            }

            return $rows;
        }

        foreach ($data['groupes'] as $gBlock) {
            $groupe = $gBlock['groupe'];

            $rows[] = [
                'type' => 'groupe',
                'groupe' => $groupe->getNom(),
                'matiere' => '',
                'reel' => null,
                'prev' => null,
            ];

            foreach ($gBlock['matieres'] as $mBlock) {
                $matiere = $mBlock['matiere'];

                $rows[] = [
                    'type' => 'ligne',
                    'groupe' => $groupe->getNom(),
                    'matiere' => $matiere->getLibelle(),
                    'reel' => (float) ($mBlock['reel'] ?? 0),
                    'prev' => (float) ($mBlock['prev'] ?? 0),
                ];
            }

            $rows[] = [
                'type' => 'total_groupe',
                'groupe' => $groupe->getNom(),
                'matiere' => 'Total groupe ' . $groupe->getNom(),
                'reel' => (float) ($gBlock['totaux']['reel'] ?? 0),
                'prev' => (float) ($gBlock['totaux']['prev'] ?? 0),
            ];

            $rows[] = [
                'type' => 'empty',
                'groupe' => '',
                'matiere' => '',
                'reel' => null,
                'prev' => null,
            ];
        }

        return $rows;
    }

    private function buildExportMissions(Classe $classe, Session $session, string $mode): array
    {
        $seances = $this->em
            ->getRepository(Seance::class)
            ->createQueryBuilder('s')
            ->join('s.typeActivite', 'ta')
            ->addSelect('ta')
            ->join('s.formateur', 'f')
            ->addSelect('f')
            ->join('s.matiere', 'm')
            ->addSelect('m')
            ->andWhere('s.session = :session')
            ->andWhere('s.classe = :classe')
            ->andWhere('ta.code <> :cours')
            ->setParameter('session', $session)
            ->setParameter('classe', $classe)
            ->setParameter('cours', 'COURS')
            ->orderBy('ta.code', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->addOrderBy('f.prenom', 'ASC')
            ->addOrderBy('m.libelle', 'ASC')
            ->getQuery()
            ->getResult();

        $rows = [];

        foreach ($seances as $seance) {
            $typeActivite = $seance->getTypeActivite();
            $matiere = $seance->getMatiere();

            $missionLabel = trim(sprintf(
                '%s - %s',
                $typeActivite?->getCode() ?? '',
                $matiere?->getLibelle() ?? ''
            ));

            $reel = round((float) ($seance->getVolumeHeuresFormateur() ?? 0), 2);
            $prev = round((float) ($seance->getVolumeHeuresFormateurPrevisionnel() ?? 0), 2);

            $rows[] = [
                'type' => 'mission',
                'formateur' => $seance->getFormateur()?->getNomComplet() ?? '',
                'mission' => $missionLabel,
                'code_mission' => $typeActivite?->getCode() ?? '',
                'commentaire' => '',
                'reel' => $reel,
                'prev' => $prev,
            ];
        }

        return $rows;
    }
}
