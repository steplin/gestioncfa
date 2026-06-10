<?php

namespace App\Service\ProjectionImport\Excel;

use App\Dto\ProjectionImport\ProjectionSeanceRow;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ProjectionWorksheetParser
{
    private const COLOR_GROUP = 'FFFFE699';
    private const COLOR_FORMATEUR = 'FFDDEBF7';

    /**
     * @return ProjectionSeanceRow[]
     */
    public function parse(Worksheet $sheet): array
    {
        $rows = [];

        $classe = $this->readClasseName($sheet);
        $currentGroupe = null;
        $currentFormateur = null;
        $inMissionBlock = false;

        $highestRow = $sheet->getHighestDataRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $colA = $this->clean($sheet->getCell("A{$row}")->getFormattedValue());
            $colB = $this->clean($sheet->getCell("B{$row}")->getFormattedValue());
            $colC = $this->clean($sheet->getCell("C{$row}")->getCalculatedValue());
            $colD = $this->clean($sheet->getCell("D{$row}")->getCalculatedValue());

            if ($this->isEmptyRow($colA, $colB, $colC, $colD)) {
                continue;
            }

            if ($this->isMissionBlockTitle($colA)) {
                $inMissionBlock = true;
                $currentGroupe = null;
                $currentFormateur = null;
                continue;
            }

            if ($inMissionBlock) {
                if ($this->isMissionHeaderRow($colA, $colB, $colC, $colD)) {
                    continue;
                }

                if ($this->isTotalMissionRow($colA)) {
                    continue;
                }

                if ($this->isMissionRow($colA, $colB, $colC, $colD)) {
                    [$typeActiviteCode, $matiere] = $this->parseMissionCell($colB);

                    $rows[] = new ProjectionSeanceRow(
                        classe: $classe,
                        groupe: $classe,
                        formateur: $colA,
                        matiere: $matiere,
                        reel: $this->toFloat($colC),
                        previsionnel: $this->toFloat($colD),
                        typeActiviteCode: $typeActiviteCode,
                    );
                }

                continue;
            }

            if ($this->isHeaderRow($colB, $colC, $colD)) {
                continue;
            }

            if ($this->isTotalGroupRow($colA, $colB)) {
                $currentFormateur = null;
                continue;
            }

            if ($this->isIgnoredRow($colA, $colB)) {
                continue;
            }

            if ($this->isSingleMergedLabelRow($colA, $colB, $colC, $colD)) {
                if ($this->isGroupColor($sheet, $row) || $currentGroupe === null) {
                    $currentGroupe = $colA;
                    $currentFormateur = null;
                    continue;
                }

                if ($this->isFormateurColor($sheet, $row) || $currentGroupe !== null) {
                    $currentFormateur = $colA;
                    continue;
                }
            }

            if ($currentGroupe === null || $currentFormateur === null) {
                continue;
            }

            if ($this->isMatiereRow($colB, $colC, $colD)) {
                $rows[] = new ProjectionSeanceRow(
                    classe: $classe,
                    groupe: $currentGroupe,
                    formateur: $currentFormateur,
                    matiere: $colB,
                    reel: $this->toFloat($colC),
                    previsionnel: $this->toFloat($colD),
                    typeActiviteCode: 'COURS',
                );
            }
        }

        return array_values(array_filter(
            $rows,
            static fn (ProjectionSeanceRow $row) => $row->hasHours()
        ));
    }

    private function readClasseName(Worksheet $sheet): string
    {
        $title = $this->clean($sheet->getCell('A1')->getFormattedValue());

        return $title !== '' ? $title : $sheet->getTitle();
    }

    private function isHeaderRow(string $colB, string $colC, string $colD): bool
    {
        return mb_strtolower($colB) === 'matière'
            && mb_strtolower($colC) === 'réel'
            && mb_strtolower($colD) === 'prévisionnel';
    }

    private function isMissionBlockTitle(string $colA): bool
    {
        $a = mb_strtolower($colA);

        return str_starts_with($a, 'missions');
    }

    private function isMissionHeaderRow(string $colA, string $colB, string $colC, string $colD): bool
    {
        return mb_strtolower($colA) === 'formateur'
            && mb_strtolower($colB) === 'mission'
            && mb_strtolower($colC) === 'réel'
            && mb_strtolower($colD) === 'prévisionnel';
    }

    private function isMissionRow(string $colA, string $colB, string $colC, string $colD): bool
    {
        return $colA !== ''
            && $colB !== ''
            && ($colC !== '' || $colD !== '');
    }

    private function isTotalMissionRow(string $colA): bool
    {
        return str_starts_with(mb_strtolower($colA), 'total missions');
    }

    private function isSingleMergedLabelRow(string $colA, string $colB, string $colC, string $colD): bool
    {
        return $colA !== '' && $colB === '' && $colC === '' && $colD === '';
    }

    private function isMatiereRow(string $colB, string $colC, string $colD): bool
    {
        return $colB !== '' && ($colC !== '' || $colD !== '');
    }

    private function isTotalGroupRow(string $colA, string $colB): bool
    {
        $a = mb_strtolower($colA);
        $b = mb_strtolower($colB);

        return str_starts_with($a, 'total groupe') || str_starts_with($b, 'total groupe');
    }

    private function isIgnoredRow(string $colA, string $colB): bool
    {
        $a = mb_strtolower($colA);
        $b = mb_strtolower($colB);

        return str_starts_with($a, 'vue')
            || str_starts_with($a, 'session')
            || str_starts_with($a, 'mode')
            || str_starts_with($a, 'prioritaire')
            || str_starts_with($a, 'sous-total')
            || str_starts_with($a, 'total général')
            || str_starts_with($b, 'sous-total')
            || str_starts_with($b, 'total général');
    }

    private function isEmptyRow(string ...$values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function isGroupColor(Worksheet $sheet, int $row): bool
    {
        return strtoupper($sheet->getStyle("A{$row}")->getFill()->getStartColor()->getARGB()) === self::COLOR_GROUP;
    }

    private function isFormateurColor(Worksheet $sheet, int $row): bool
    {
        return strtoupper($sheet->getStyle("A{$row}")->getFill()->getStartColor()->getARGB()) === self::COLOR_FORMATEUR;
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function toFloat(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        $value = str_replace("\xc2\xa0", '', $value);
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }
    private function parseMissionCell(string $value): array
    {
        $value = $this->clean($value);

        if (str_contains($value, ' - ')) {
            [$typeActiviteCode, $matiere] = explode(' - ', $value, 2);

            return [
                $this->normalizeCode($typeActiviteCode),
                $this->clean($matiere),
            ];
        }

        return [
            $this->normalizeCode($value),
            $value,
        ];
    }

    private function normalizeCode(string $value): string
    {
        $value = $this->clean($value);
        $value = mb_strtoupper($value);

        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator !== null) {
            $value = $transliterator->transliterate($value);
        }

        $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
        $value = trim((string) $value, '_');

        return $value;
    }
}
