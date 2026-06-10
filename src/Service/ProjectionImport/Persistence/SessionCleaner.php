<?php

namespace App\Service\ProjectionImport\Persistence;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Repository\SeanceRepository;

final readonly class SessionCleaner
{
    public function __construct(
        private SeanceRepository $seanceRepository
    )
    {
    }

    public function cleanAllSeances(ProjectionImportContext $context): void
    {
        $ids = $this->seanceRepository->createQueryBuilder('s')
            ->select('s.id')
            ->andWhere('s.session = :session')
            ->setParameter('session', $context->getTargetSession())
            ->getQuery()
            ->getSingleColumnResult();

        $context->getReport()->incrementSeancesSupprimees(count($ids));

        if ($context->isDryRun() || $ids === []) {
            return;
        }

        $this->seanceRepository->createQueryBuilder('s')
            ->delete()
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
