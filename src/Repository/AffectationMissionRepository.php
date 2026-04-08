<?php

namespace App\Repository;

use App\Entity\AffectationMission;
use App\Entity\Formateur;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AffectationMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AffectationMission::class);
    }

    /**
     * @return AffectationMission[]
     */
    public function findActivesByFormateurAndSession(Formateur $formateur, Session $session): array
    {
        return $this->createQueryBuilder('am')
            ->leftJoin('am.categorieMission', 'cm')->addSelect('cm')
            ->leftJoin('am.classe', 'c')->addSelect('c')
            ->leftJoin('am.referentielFormation', 'rf')->addSelect('rf')
            ->andWhere('am.formateur = :formateur')
            ->andWhere('am.session = :session')
            ->andWhere('am.actif = :actif')
            ->setParameter('formateur', $formateur)
            ->setParameter('session', $session)
            ->setParameter('actif', true)
            ->orderBy('am.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
