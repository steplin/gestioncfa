<?php

namespace App\Repository;


use App\Entity\Classe;
use App\Entity\Seance;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Seance>
 */
class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    public function prioritaire(Session $session, Classe $classe, bool $prioritaireOnly = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s', 'g', 'f', 'm', 'ta')
            ->join('s.groupe', 'g')
            ->join('s.formateur', 'f')
            ->join('s.matiere', 'm')
            ->join('s.typeActivite', 'ta')
            ->andWhere('s.session = :session')
            ->andWhere('s.classe = :classe')
            ->andWhere('ta.code = :code')
            ->setParameter('session', $session)
            ->setParameter('classe', $classe)
            ->setParameter('code', 'COURS');

        if ($prioritaireOnly) {
            $qb->andWhere('g.prioritaire = true');
        }

        $qb->orderBy('g.nom', 'ASC')
            ->addOrderBy('f.nom', 'ASC')
            ->addOrderBy('f.prenom', 'ASC')
            ->addOrderBy('m.libelle', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
