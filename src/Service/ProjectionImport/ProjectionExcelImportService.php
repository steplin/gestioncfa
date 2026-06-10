<?php

namespace App\Service\ProjectionImport;

use App\Dto\ProjectionImport\ProjectionImportContext;
use App\Dto\ProjectionImport\ProjectionImportReport;
use App\Dto\ProjectionImport\ProjectionSeanceRow;
use App\Entity\Session;
use App\Service\ProjectionImport\Excel\ProjectionExcelReader;
use App\Service\ProjectionImport\Persistence\SeanceImporter;
use App\Service\ProjectionImport\Persistence\SessionCleaner;
use App\Service\ProjectionImport\Resolver\ClasseResolver;
use App\Service\ProjectionImport\Resolver\GroupeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectionExcelImportService
{
    public function __construct(
        private readonly ProjectionExcelReader $excelReader,
        private readonly ClasseResolver $classeResolver,
        private readonly GroupeResolver $groupeResolver,
        private readonly SessionCleaner $sessionCleaner,
        private readonly SeanceImporter $seanceImporter,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function import(
        Session $sourceSession,
        Session $targetSession,
        UploadedFile|string $file,
        bool $dryRun = true,
    ): ProjectionImportReport {
        $report = new ProjectionImportReport();
        $context = new ProjectionImportContext(
            sourceSession: $sourceSession,
            targetSession: $targetSession,
            dryRun: $dryRun,
            report: $report,
        );

        $rows = $this->excelReader->read($file);


        if ($rows === []) {
            $report->addError('Aucune ligne de séance exploitable trouvée dans le fichier Excel.');
            return $report;
        }

        $this->prepareClassesAndGroups($context, $rows);

        if ($report->hasErrors()) {
            return $report;
        }


        $this->sessionCleaner->cleanAllSeances($context);
        $this->seanceImporter->import($context, $rows);

        if (!$dryRun && !$report->hasErrors()) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * Crée / retrouve les classes et groupes avant la copie des missions.
     * Les missions peuvent ainsi être rattachées à des groupes déjà créés dans la session cible.
     *
     * @param ProjectionSeanceRow[] $rows
     */
    private function prepareClassesAndGroups(ProjectionImportContext $context, array $rows): void
    {
        foreach ($rows as $row) {
            if (!$row instanceof ProjectionSeanceRow) {
                continue;
            }

            $type = $this->classeResolver->resolveTypeFromGroupeName($row->groupe);

            $classe = $this->classeResolver->resolve(
                $context,
                $row->classe,
                $type
            );
            if ($classe === null) {
                continue;
            }

            $this->groupeResolver->resolve($context, $classe, $row->groupe);
        }
    }
}
