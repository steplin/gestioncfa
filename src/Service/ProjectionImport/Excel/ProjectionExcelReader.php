<?php

namespace App\Service\ProjectionImport\Excel;

use App\Dto\ProjectionImport\ProjectionSeanceRow;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectionExcelReader
{
    public function __construct(
        private readonly ProjectionWorksheetParser $worksheetParser,
    ) {
    }

    /**
     * @return ProjectionSeanceRow[]
     */
    public function read(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getPathname() : $file;
        $spreadsheet = IOFactory::load($path);

        $rows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->shouldIgnoreSheet($sheet->getTitle())) {
                continue;
            }

            foreach ($this->worksheetParser->parse($sheet) as $row) {
                $rows[] = $row;
            }
        }

        $spreadsheet->disconnectWorksheets();

        return $this->deduplicateRows($rows);
    }

    /**
     * @param ProjectionSeanceRow[] $rows
     *
     * @return ProjectionSeanceRow[]
     */
    private function deduplicateRows(array $rows): array
    {
        $merged = [];

        foreach ($rows as $row) {
            $key = $row->getKey();

            if (!isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            $existing = $merged[$key];

            $merged[$key] = new ProjectionSeanceRow(
                classe: $existing->classe,
                groupe: $existing->groupe,
                formateur: $existing->formateur,
                matiere: $existing->matiere,
                reel: $existing->reel + $row->reel,
                previsionnel: $existing->previsionnel + $row->previsionnel,
            );
        }

        return array_values($merged);
    }

    private function shouldIgnoreSheet(string $title): bool
    {
        $title = mb_strtolower(trim($title));

        return $title === ''
            || str_starts_with($title, 'synthese')
            || str_starts_with($title, 'synthèse')
            || str_starts_with($title, 'recap')
            || str_starts_with($title, 'récap')
            || str_starts_with($title, 'total');
    }
}
