<?php

namespace App\Service\Export;

use App\Entity\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class RecapHeuresFormateursExcelExportService
{
    // Bootstrap-ish
    private const RGB_HEADER  = 'D9EDF7'; // info
    private const RGB_WARNING = 'FCF8E3'; // warning
    private const RGB_ACTIVE  = 'F2F2F2'; // active
    private const RGB_SUCCESS = 'DFF0D8'; // success
    private const RGB_BORDER  = 'BFBFBF';

    public function export(Session $session, array $data, string $mode): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // ---------- helpers ----------
        $fill = function (string $range, string $rgb) use ($sheet): void {
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($rgb);
        };

        $borders = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setRGB(self::RGB_BORDER);
        };

        $bold = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getFont()->setBold(true);
        };

        $right = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        };

        $center = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        };

        $vmiddle = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        };

        $num0 = function (string $range) use ($sheet): void {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('# ##0');
        };

// ...

// ====== MISE EN PAGE IMPRESSION (A3 paysage, adapté largeur) ======
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A3);

// Fit to width = 1 page, height = auto
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

// (important) activer "fit to page"
        $sheet->getPageSetup()->setFitToPage(true);

// Centrer sur la page
        $sheet->getPageSetup()->setHorizontalCentered(true);

// Marges (optionnel, mais rend mieux)
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setBottom(0.4)
            ->setLeft(0.3)
            ->setRight(0.3);

        // ---------- build ----------
        $row = 1;
        $mode = $mode=='prev'? ' (Prévisionnel)' : ' (Réel)';
        $sheet->setCellValue("A{$row}", 'Récapitulatif heures formateurs'.$mode);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row ++;
        $sheet->setCellValue("A{$row}", $session->getLibelle());
        $sheet->getStyle("A{$row}")->getFont()->setSize(12);

        $row = $row + 2;
        // Header row index
        $headerRow = $row;

        // header labels
        $sheet->setCellValue("A{$row}", 'Classe');
        $sheet->setCellValue("B{$row}", 'Type');

        $colStartFormateurs = 3; // C
        $col = $colStartFormateurs;

        foreach ($data['formateurs'] as $formateur) {
            $sheet->setCellValue([$col, $row], $formateur->getInitiales());
            $col++;
        }

        $colTotal = $col;
        $sheet->setCellValue([$colTotal, $row], 'Total');

        $colTotalClasse = $colTotal + 1;
        $sheet->setCellValue([$colTotalClasse, $row], 'Total classe');

        $colFaF = $colTotalClasse + 1;
        $sheet->setCellValue([$colFaF, $row], 'Total FàF');

        $colPF = $colFaF + 1;
        $sheet->setCellValue([$colPF, $row], 'PF');

        $lastColIndex = $colPF;
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColIndex);

        // merge title on full width
        $sheet->mergeCells("A1:{$lastColLetter}1");

        // header styling
        $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";
        $fill($headerRange, self::RGB_HEADER);
        $bold($headerRange);
        $center($headerRange);
        $vmiddle($headerRange);
        $borders($headerRange);

        // filters
        $sheet->setAutoFilter($headerRange);

        $row++;
        $firstDataRow = $row;

        // fixed widths like web
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(14);

        // Data blocks
        foreach (['FL','FC'] as $categorie) {

            if (empty($data['data'][$categorie])) {
                continue;
            }

            // categorie row (active)
            $sheet->setCellValue("A{$row}", $categorie);
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $fill("A{$row}:{$lastColLetter}{$row}", self::RGB_ACTIVE);
            $bold("A{$row}:{$lastColLetter}{$row}");
            $borders("A{$row}:{$lastColLetter}{$row}");
            $row++;

            foreach ($data['data'][$categorie] as $bloc) {

                $classe = $bloc['classe'];
                $rowCours = $row;
                $rowMission = $row + 1;

                // Classe merged on 2 rows
                $sheet->setCellValue("A{$rowCours}", $classe->getNom());
                $sheet->mergeCells("A{$rowCours}:A{$rowMission}");
                $bold("A{$rowCours}:A{$rowMission}");
                $vmiddle("A{$rowCours}:A{$rowMission}");

                // Types
                $sheet->setCellValue("B{$rowCours}", 'COURS');
                $sheet->setCellValue("B{$rowMission}", 'MISSION');

                // COURS values
                $c = $colStartFormateurs;
                foreach ($data['formateurs'] as $formateur) {
                    $val = (float)($bloc['COURS'][$formateur->getId()] ?? 0);
                    $sheet->setCellValue([$c, $rowCours], $val > 0 ? $val : null);
                    $c++;
                }

                $firstFormLetter = Coordinate::stringFromColumnIndex($colStartFormateurs);
                $lastFormLetter  = Coordinate::stringFromColumnIndex($colTotal - 1);

                // Total COURS
                $sheet->setCellValue([$colTotal, $rowCours], "=SUM({$firstFormLetter}{$rowCours}:{$lastFormLetter}{$rowCours})");

                // MISSION values
                $c = $colStartFormateurs;
                foreach ($data['formateurs'] as $formateur) {
                    $val = (float)($bloc['MISSION'][$formateur->getId()] ?? 0);
                    $sheet->setCellValue([$c, $rowMission], $val > 0 ? $val : null);
                    $c++;
                }

                // Total MISSION
                $sheet->setCellValue([$colTotal, $rowMission], "=SUM({$firstFormLetter}{$rowMission}:{$lastFormLetter}{$rowMission})");

                // Mission row styling
                $fill("A{$rowMission}:{$lastColLetter}{$rowMission}", self::RGB_WARNING);

                // Total classe (merged 2 rows) = total cours + total mission
                $totalCoursCell = Coordinate::stringFromColumnIndex($colTotal) . $rowCours;
                $totalMissionCell = Coordinate::stringFromColumnIndex($colTotal) . $rowMission;

                $sheet->setCellValue([$colTotalClasse, $rowCours], "={$totalCoursCell}+{$totalMissionCell}");
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($colTotalClasse) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colTotalClasse) . $rowMission
                );
                $vmiddle(
                    Coordinate::stringFromColumnIndex($colTotalClasse) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colTotalClasse) . $rowMission
                );

                // FàF (merged 2 rows) = TotalCours / 2
                $sheet->setCellValue([$colFaF, $rowCours], "={$totalCoursCell}/2");
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($colFaF) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colFaF) . $rowMission
                );
                $vmiddle(
                    Coordinate::stringFromColumnIndex($colFaF) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colFaF) . $rowMission
                );

                // PF (merged 2 rows)
                $pf = method_exists($classe, 'getPfprev') ? $classe->getPfprev() : null;
                $sheet->setCellValue([$colPF, $rowCours], $pf !== null ? (float)$pf : null);
                $sheet->mergeCells(
                    Coordinate::stringFromColumnIndex($colPF) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colPF) . $rowMission
                );
                $vmiddle(
                    Coordinate::stringFromColumnIndex($colPF) . $rowCours . ':' .
                    Coordinate::stringFromColumnIndex($colPF) . $rowMission
                );

                // Align / format numbers
                $right("C{$rowCours}:{$lastColLetter}{$rowCours}");
                $right("C{$rowMission}:{$lastColLetter}{$rowMission}");
                $num0("C{$rowCours}:{$lastColLetter}{$rowCours}");
                $num0("C{$rowMission}:{$lastColLetter}{$rowMission}");

                // Borders on both rows
                $borders("A{$rowCours}:{$lastColLetter}{$rowMission}");

                $row += 2;
            }
        }

        $lastDataRow = $row - 1;

        // ---------- Totals block (with formulas) ----------
        $rowTotalGeneral = $row;
        $rowContrat      = $row + 1;
        $rowDifference   = $row + 2;

        // Labels merged
        $sheet->setCellValue("A{$rowTotalGeneral}", 'Total général');
        $sheet->mergeCells("A{$rowTotalGeneral}:B{$rowTotalGeneral}");

        $sheet->setCellValue("A{$rowContrat}", 'Contrat');
        $sheet->mergeCells("A{$rowContrat}:B{$rowContrat}");

        $sheet->setCellValue("A{$rowDifference}", 'Différence');
        $sheet->mergeCells("A{$rowDifference}:B{$rowDifference}");

        // Total général per formateur = SUM(col over data rows)
        for ($c = $colStartFormateurs; $c <= $colTotal - 1; $c++) {
            $L = Coordinate::stringFromColumnIndex($c);
            $sheet->setCellValue([$c, $rowTotalGeneral], "=SUM({$L}{$firstDataRow}:{$L}{$lastDataRow})");
        }
        // Total général "Total" = sum of totals per formateur
        $startTG = Coordinate::stringFromColumnIndex($colStartFormateurs) . $rowTotalGeneral;
        $endTG   = Coordinate::stringFromColumnIndex($colTotal - 1) . $rowTotalGeneral;
        $sheet->setCellValue([$colTotal, $rowTotalGeneral], "=SUM({$startTG}:{$endTG})");

        // Total classe = Total
        $sheet->setCellValue([$colTotalClasse, $rowTotalGeneral], "=" . Coordinate::stringFromColumnIndex($colTotal) . $rowTotalGeneral);

        // Total FàF global = SUM(col FaF sur les lignes de données
        $Lfaf = Coordinate::stringFromColumnIndex($colFaF);
        $sheet->setCellValue([$colFaF, $rowTotalGeneral], "=SUM({$Lfaf}{$firstDataRow}:{$Lfaf}{$lastDataRow})");

        // PF empty
        $sheet->setCellValue([$colPF, $rowTotalGeneral], null);

        // Contrat per formateur (values) + total
        $heuresContratsTotal = 0.0;
        $c = $colStartFormateurs;
        foreach ($data['formateurs'] as $formateur) {
            $hc = (float)$formateur->getVolumeContractuel() * (float)$formateur->getQuotite();
            $heuresContratsTotal += $hc;
            $sheet->setCellValue([$c, $rowContrat], $hc);
            $c++;
        }
        $sheet->setCellValue([$colTotal, $rowContrat], $heuresContratsTotal);
        $sheet->setCellValue([$colTotalClasse, $rowContrat], null);
        $sheet->setCellValue([$colFaF, $rowContrat], null);
        $sheet->setCellValue([$colPF, $rowContrat], null);

        // Différence per formateur = Total général - Contrat
        for ($c = $colStartFormateurs; $c <= $colTotal - 1; $c++) {
            $tg = Coordinate::stringFromColumnIndex($c) . $rowTotalGeneral;
            $ct = Coordinate::stringFromColumnIndex($c) . $rowContrat;
            $sheet->setCellValue([$c, $rowDifference], "={$tg}-{$ct}");
        }
        $tgTot = Coordinate::stringFromColumnIndex($colTotal) . $rowTotalGeneral;
        $ctTot = Coordinate::stringFromColumnIndex($colTotal) . $rowContrat;
        $sheet->setCellValue([$colTotal, $rowDifference], "={$tgTot}-{$ctTot}");

        $sheet->setCellValue([$colTotalClasse, $rowDifference], null);
        $sheet->setCellValue([$colFaF, $rowDifference], null);
        $sheet->setCellValue([$colPF, $rowDifference], null);
        $start = Coordinate::stringFromColumnIndex($colStartFormateurs) . $rowDifference;
        $end   = Coordinate::stringFromColumnIndex($colTotal) . $rowDifference;
        $range = "{$start}:{$end}";
        $cond = new Conditional();
        $cond->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0');
        $cond->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);

        $existing = $sheet->getStyle($range)->getConditionalStyles();
        $existing[] = $cond;
        $sheet->getStyle($range)->setConditionalStyles($existing);
        $cond = new Conditional();
        $cond->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0');
        $cond->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);

        $existing = $sheet->getStyle($range)->getConditionalStyles();
        $existing[] = $cond;
        $sheet->getStyle($range)->setConditionalStyles($existing);


        // Style totals (success)
        $fill("A{$rowTotalGeneral}:{$lastColLetter}{$rowDifference}", self::RGB_SUCCESS);
        $bold("A{$rowTotalGeneral}:{$lastColLetter}{$rowDifference}");
        $right("C{$rowTotalGeneral}:{$lastColLetter}{$rowDifference}");
        $num0("C{$rowTotalGeneral}:{$lastColLetter}{$rowDifference}");
        $borders("A{$rowTotalGeneral}:{$lastColLetter}{$rowDifference}");

        // ---------- Freeze panes ----------
        // Geler header + colonnes A/B (classe/type)
        $sheet->freezePane('C' . ($headerRow + 1));

        // ---------- Auto-size robust (works after Z) ----------
        for ($i = 3; $i <= $lastColIndex; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // (Optionnel) un peu plus de hauteur pour le header
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        $writer = new Xlsx($spreadsheet);

        $fileName = sprintf(
            'recap_heures_formateurs_%s_%s.xlsx',
            preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$session->getLibelle()),
            $mode
        );

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
