<?php

namespace App\Service\Export;

use App\Entity\Classe;
use App\Entity\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClasseExcelExportService
{
    private const COLOR_HEADER = 'FFF2F2F2';     // Gris clair : en-têtes
    private const COLOR_GROUP = 'FFFFE699';      // Jaune clair : groupe / total groupe
    private const COLOR_FORMATEUR = 'FFDDEBF7';  // Bleu clair : formateur
    private const COLOR_SUBTOTAL = 'FFFFFFFF';   // Blanc : sous-total formateur
    private const COLOR_TOTAL = 'FFB7E1A1';      // Vert clair : total général

    public function export(
        Classe $classe,
        Session $session,
        array $exportData,
        string $mode = 'both'
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $title = $classe->getAbrege() ?: $classe->getNom() ?: 'Classe';
        $sheet->setTitle(mb_substr($title, 0, 31));

        $this->buildSheet($spreadsheet, $sheet, $classe, $session, $exportData, $mode);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'plan_de_formation_'
            . $this->slug((string) $classe->getNom())
            . '_'
            . $this->slug($session->getLibelle())
            . '.xlsx';

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="' . $filename . '"'
        );

        return $response;
    }

    private function buildSheet(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        Classe $classe,
        Session $session,
        array $exportData,
        string $mode
    ): void {
        $spreadsheet->getDefaultStyle()
            ->getFont()
            ->setName('Arial')
            ->setSize(10);

        $spreadsheet->getDefaultStyle()
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        $view = $exportData['meta']['view'] ?? 'formateur';
        $prioritaire = (bool) ($exportData['meta']['prioritaire'] ?? false);

        $lastColumn = $view === 'formateur' ? 'D' : 'C';

        $row = 1;

        // =========================
        // TITRE
        // =========================
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->setCellValue("A{$row}", $classe->getNom());

        $sheet->getStyle("A{$row}")
            ->getFont()
            ->setBold(true)
            ->setSize(14);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(26);

        $row += 2;

        // =========================
        // META
        // =========================
        $metaStartRow = $row;

        $sheet->setCellValue("A{$row}", 'Vue');
        $sheet->setCellValue("B{$row}", $this->formatView($view));
        $row++;

        $sheet->setCellValue("A{$row}", 'Session');
        $sheet->setCellValue("B{$row}", $session->getLibelle());
        $row++;

        $sheet->setCellValue("A{$row}", 'Mode');
        $sheet->setCellValue("B{$row}", $this->formatMode($mode));
        $row++;

        if ($prioritaire) {
            $sheet->setCellValue("A{$row}", 'Prioritaire uniquement');
            $sheet->setCellValue("B{$row}", 'Oui');
            $row++;
        }

        $sheet->getStyle("A{$metaStartRow}:A" . ($row - 1))
            ->getFont()
            ->setBold(true);

        $row++;

        // =========================
        // EN-TÊTES
        // =========================
        $headerStart = $row;

        if ($view === 'formateur') {
            $row = $this->writeHeaderFormateur($sheet, $row);
        } else {
            $row = $this->writeHeaderMatiere($sheet, $row);
        }

        $dataStart = $row;

        $groupeSubtotalRows = [];

        $currentGroupStartRow = null;
        $currentGroupSubtotalRows = [];
        $currentFormateurStartRow = null;

        // =========================
        // LIGNES
        // =========================
        foreach ($exportData['lignes'] as $ligne) {
            $type = $ligne['type'] ?? 'ligne';

            if ($type === 'empty') {
                $row++;
                continue;
            }

            // Nouvelle section groupe
            if ($type === 'groupe') {
                $currentGroupStartRow = null;
                $currentGroupSubtotalRows = [];
                $currentFormateurStartRow = null;

                if ($view === 'formateur') {
                    $this->writeRowFormateur($sheet, $row, $ligne);
                    $range = "A{$row}:D{$row}";
                } else {
                    $this->writeRowMatiere($sheet, $row, $ligne);
                    $range = "A{$row}:C{$row}";
                }

                $this->styleDataRow($sheet, $range, $type);
                $sheet->getRowDimension($row)->setRowHeight(20);

                $row++;
                continue;
            }

            // Nouvelle section formateur
            if ($type === 'formateur') {
                $currentFormateurStartRow = null;

                $this->writeRowFormateur($sheet, $row, $ligne);
                $range = "A{$row}:D{$row}";

                $this->styleDataRow($sheet, $range, $type);
                $sheet->getRowDimension($row)->setRowHeight(18);

                $row++;
                continue;
            }

            // Ligne détail
            if ($type === 'ligne') {
                if ($currentGroupStartRow === null) {
                    $currentGroupStartRow = $row;
                }

                if ($view === 'formateur' && $currentFormateurStartRow === null) {
                    $currentFormateurStartRow = $row;
                }

                if ($view === 'formateur') {
                    $this->writeRowFormateur($sheet, $row, $ligne);
                    $range = "A{$row}:D{$row}";
                } else {
                    $this->writeRowMatiere($sheet, $row, $ligne);
                    $range = "A{$row}:C{$row}";
                }
                $sheet->getRowDimension($headerStart)->setRowHeight(28);
                $this->styleDataRow($sheet, $range, $type);
                $sheet->getRowDimension($row)->setRowHeight(18);

                $row++;
                continue;
            }

            // Sous-total formateur = formule sur les lignes détail du formateur
            if ($type === 'sous_total_formateur') {
                if ($view === 'formateur') {
                    $this->writeSubtotalFormateurFormula(
                        $sheet,
                        $row,
                        $ligne,
                        $currentFormateurStartRow,
                        $row - 1
                    );

                    $currentGroupSubtotalRows[] = $row;

                    $range = "A{$row}:D{$row}";
                    $this->styleDataRow($sheet, $range, $type);
                    $sheet->getRowDimension($row)->setRowHeight(18);

                    $currentFormateurStartRow = null;

                    $row++;
                }

                continue;
            }

            // Total groupe = formule
            if ($type === 'total_groupe') {
                if ($view === 'formateur') {
                    $this->writeTotalGroupeFormateurFormula(
                        $sheet,
                        $row,
                        $ligne,
                        $currentGroupSubtotalRows
                    );

                    $range = "A{$row}:D{$row}";
                } else {
                    $this->writeTotalGroupeMatiereFormula(
                        $sheet,
                        $row,
                        $ligne,
                        $currentGroupStartRow,
                        $row - 1
                    );

                    $range = "A{$row}:C{$row}";
                }

                $groupeSubtotalRows[] = $row;

                $this->styleDataRow($sheet, $range, $type);
                $sheet->getRowDimension($row)->setRowHeight(18);

                $currentGroupStartRow = null;
                $currentGroupSubtotalRows = [];
                $currentFormateurStartRow = null;

                $row++;
                continue;
            }
        }

        // =========================
        // TOTAL GÉNÉRAL = formule
        // =========================
        $totalRow = $row;

        if ($view === 'formateur') {
            $sheet->setCellValue("A{$totalRow}", 'TOTAL GÉNÉRAL');

            if (!empty($groupeSubtotalRows)) {
                $sheet->setCellValue(
                    "C{$totalRow}",
                    '=SUM(' . implode(',', array_map(fn (int $r) => "C{$r}", $groupeSubtotalRows)) . ')'
                );

                $sheet->setCellValue(
                    "D{$totalRow}",
                    '=SUM(' . implode(',', array_map(fn (int $r) => "D{$r}", $groupeSubtotalRows)) . ')'
                );
            } else {
                $sheet->setCellValue("C{$totalRow}", 0);
                $sheet->setCellValue("D{$totalRow}", 0);
            }

            $this->fill($sheet, "A{$totalRow}:D{$totalRow}", self::COLOR_TOTAL);
            $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getFont()->setBold(true);
            $this->applyBorders($sheet, "A{$headerStart}:D{$totalRow}");
        } else {
            $sheet->setCellValue("A{$totalRow}", 'TOTAL GÉNÉRAL');

            if (!empty($groupeSubtotalRows)) {
                $sheet->setCellValue(
                    "B{$totalRow}",
                    '=SUM(' . implode(',', array_map(fn (int $r) => "B{$r}", $groupeSubtotalRows)) . ')'
                );

                $sheet->setCellValue(
                    "C{$totalRow}",
                    '=SUM(' . implode(',', array_map(fn (int $r) => "C{$r}", $groupeSubtotalRows)) . ')'
                );
            } else {
                $detailRows = $this->getDetailRows($exportData['lignes'], $dataStart);

                if (!empty($detailRows)) {
                    $first = min($detailRows);
                    $last = max($detailRows);

                    $sheet->setCellValue("B{$totalRow}", "=SUM(B{$first}:B{$last})");
                    $sheet->setCellValue("C{$totalRow}", "=SUM(C{$first}:C{$last})");
                } else {
                    $sheet->setCellValue("B{$totalRow}", 0);
                    $sheet->setCellValue("C{$totalRow}", 0);
                }
            }

            $this->fill($sheet, "A{$totalRow}:C{$totalRow}", self::COLOR_TOTAL);
            $sheet->getStyle("A{$totalRow}:C{$totalRow}")->getFont()->setBold(true);
            $this->applyBorders($sheet, "A{$headerStart}:C{$totalRow}");
        }

        // =========================
        // FORMAT NUMÉRIQUE
        // =========================
        if ($view === 'formateur') {
            $sheet->getStyle("C{$dataStart}:D{$totalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00;-#,##0.00;;@');
        } else {
            $sheet->getStyle("B{$dataStart}:C{$totalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00;-#,##0.00;;@');
        }

        // =========================
        // LARGEURS / GEL VOLET
        // =========================
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A' . ($headerStart + 1));
    }

    public function exportClasseurClasses(
        Session $session,
        array $items,
        string $mode = 'both'
    ): StreamedResponse {
        // $items = [
        //     ['classe' => Classe, 'data' => array],
        //     ...
        // ];

        $spreadsheet = new Spreadsheet();

        // Supprime la feuille vide créée par défaut
        $spreadsheet->removeSheetByIndex(0);

        $usedTitles = [];

        foreach ($items as $idx => $item) {
            /** @var Classe $classe */
            $classe = $item['classe'];
            $data = $item['data'];

            $sheet = new Worksheet($spreadsheet);
            $spreadsheet->addSheet($sheet);

            $title = $this->buildSheetTitle($classe, $usedTitles);
            $usedTitles[] = $title;

            $sheet->setTitle($title);

            $this->buildSheet(
                $spreadsheet,
                $sheet,
                $classe,
                $session,
                $data,
                $mode
            );

            if ($idx === 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'plan_de_formation_toutes_classes_'
            . $this->slug($session->getLibelle())
            . '.xlsx';

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="' . $filename . '"'
        );

        return $response;
    }
    private function buildSheetTitle(Classe $classe, array $usedTitles): string
    {
        $title = $classe->getNom()
            ?: $classe->getCode()
                ?: $classe->getName()
                    ?: 'Classe';

        $title = $this->sanitizeSheetTitle($title);
        $title = mb_substr($title, 0, 31);

        $base = $title;
        $index = 2;

        while (in_array($title, $usedTitles, true)) {
            $suffix = ' (' . $index . ')';
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $index++;
        }

        return $title;
    }

    private function sanitizeSheetTitle(string $title): string
    {
        // Excel interdit : \ / ? * [ ] :
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]\\:]/', ' ', $title);
        $title = trim((string) $title);

        return $title !== '' ? $title : 'Classe';
    }

    private function writeHeaderFormateur(Worksheet $sheet, int $row): int
    {
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'Matière');
        $sheet->setCellValue("C{$row}", 'Réel');
        $sheet->setCellValue("D{$row}", 'Prévisionnel');

        $this->styleHeader($sheet, "A{$row}:D{$row}");

        return $row + 1;
    }

    private function writeHeaderMatiere(Worksheet $sheet, int $row): int
    {
        $sheet->setCellValue("A{$row}", 'Matière');
        $sheet->setCellValue("B{$row}", 'Réel');
        $sheet->setCellValue("C{$row}", 'Prévisionnel');

        $this->styleHeader($sheet, "A{$row}:C{$row}");

        return $row + 1;
    }

    private function writeRowFormateur(Worksheet $sheet, int $row, array $ligne): void
    {
        $type = $ligne['type'] ?? 'ligne';

        if ($type === 'groupe') {
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", $ligne['groupe'] ?? '');

            return;
        }

        if ($type === 'formateur') {
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", $ligne['formateur'] ?? '');

            return;
        }

        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", $ligne['matiere'] ?? '');

        if (($ligne['reel'] ?? null) !== null) {
            $sheet->setCellValue("C{$row}", (float) $ligne['reel']);
        }

        if (($ligne['prev'] ?? null) !== null) {
            $sheet->setCellValue("D{$row}", (float) $ligne['prev']);
        }
    }

    private function writeRowMatiere(Worksheet $sheet, int $row, array $ligne): void
    {
        $type = $ligne['type'] ?? 'ligne';

        if ($type === 'groupe') {
            $sheet->mergeCells("A{$row}:C{$row}");
            $sheet->setCellValue("A{$row}", $ligne['groupe'] ?? '');

            return;
        }

        $sheet->setCellValue("A{$row}", $ligne['matiere'] ?? '');

        if (($ligne['reel'] ?? null) !== null) {
            $sheet->setCellValue("B{$row}", (float) $ligne['reel']);
        }

        if (($ligne['prev'] ?? null) !== null) {
            $sheet->setCellValue("C{$row}", (float) $ligne['prev']);
        }
    }

    private function writeSubtotalFormateurFormula(
        Worksheet $sheet,
        int $row,
        array $ligne,
        ?int $startRow,
        int $endRow
    ): void {
        $sheet->setCellValue("A{$row}", $ligne['formateur'] ?? 'Sous-total formateur');
        $sheet->setCellValue("B{$row}", '');

        if ($startRow !== null && $endRow >= $startRow) {
            $sheet->setCellValue("C{$row}", "=SUM(C{$startRow}:C{$endRow})");
            $sheet->setCellValue("D{$row}", "=SUM(D{$startRow}:D{$endRow})");
        } else {
            $sheet->setCellValue("C{$row}", 0);
            $sheet->setCellValue("D{$row}", 0);
        }
    }

    private function writeTotalGroupeFormateurFormula(
        Worksheet $sheet,
        int $row,
        array $ligne,
        array $subtotalRows
    ): void {
        $sheet->setCellValue("A{$row}", 'Total groupe');
        $sheet->setCellValue("B{$row}", $ligne['matiere'] ?? '');

        if (!empty($subtotalRows)) {
            $sheet->setCellValue(
                "C{$row}",
                '=SUM(' . implode(',', array_map(fn (int $r) => "C{$r}", $subtotalRows)) . ')'
            );

            $sheet->setCellValue(
                "D{$row}",
                '=SUM(' . implode(',', array_map(fn (int $r) => "D{$r}", $subtotalRows)) . ')'
            );
        } else {
            $sheet->setCellValue("C{$row}", 0);
            $sheet->setCellValue("D{$row}", 0);
        }
    }

    private function writeTotalGroupeMatiereFormula(
        Worksheet $sheet,
        int $row,
        array $ligne,
        ?int $startRow,
        int $endRow
    ): void {
        $sheet->setCellValue("A{$row}", $ligne['matiere'] ?? 'Total groupe');

        if ($startRow !== null && $endRow >= $startRow) {
            $sheet->setCellValue("B{$row}", "=SUM(B{$startRow}:B{$endRow})");
            $sheet->setCellValue("C{$row}", "=SUM(C{$startRow}:C{$endRow})");
        } else {
            $sheet->setCellValue("B{$row}", 0);
            $sheet->setCellValue("C{$row}", 0);
        }
    }

    private function getDetailRows(array $lignes, int $dataStart): array
    {
        $rows = [];
        $currentRow = $dataStart;

        foreach ($lignes as $ligne) {
            $type = $ligne['type'] ?? 'ligne';

            if ($type === 'empty') {
                $currentRow++;
                continue;
            }

            if ($type === 'ligne') {
                $rows[] = $currentRow;
            }

            $currentRow++;
        }

        return $rows;
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $this->fill($sheet, $range, self::COLOR_HEADER);

        $sheet->getStyle($range)
            ->getFont()
            ->setBold(true);

        $sheet->getStyle($range)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function styleDataRow(Worksheet $sheet, string $range, string $type): void
    {
        if ($type === 'groupe') {
            $this->fill($sheet, $range, self::COLOR_GROUP);

            $sheet->getStyle($range)
                ->getFont()
                ->setBold(true);

            return;
        }

        if ($type === 'formateur') {
            $this->fill($sheet, $range, self::COLOR_FORMATEUR);

            $sheet->getStyle($range)
                ->getFont()
                ->setBold(true);

            return;
        }

        if ($type === 'sous_total_formateur') {
            $this->fill($sheet, $range, self::COLOR_SUBTOTAL);

            $sheet->getStyle($range)
                ->getFont()
                ->setBold(true);

            return;
        }

        if ($type === 'total_groupe') {
            $this->fill($sheet, $range, self::COLOR_GROUP);

            $sheet->getStyle($range)
                ->getFont()
                ->setBold(true);

            return;
        }
    }

    private function fill(Worksheet $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB($argb);
    }

    private function applyBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    private function slug(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value);
        $value = trim((string) $value, '_');

        return $value !== '' ? $value : 'export';
    }

    private function formatMode(string $mode): string
    {
        return match ($mode) {
            'reel' => 'Réel',
            'prev' => 'Prévisionnel',
            'both' => 'Comparé',
            default => ucfirst($mode),
        };
    }

    private function formatView(string $view): string
    {
        return match ($view) {
            'formateur' => 'Formateur',
            'matiere' => 'Matière',
            default => ucfirst($view),
        };
    }
}
