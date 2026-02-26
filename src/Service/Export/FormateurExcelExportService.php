<?php

namespace App\Service\Export;

use App\Entity\Formateur;
use App\Entity\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormateurExcelExportService
{
    private const COLOR_HEADER = 'FFF5F5F5';
    private const COLOR_SUBTOTAL = 'FFD9EDF7';
    private const COLOR_TOTAL = 'ABDB77';
    private const COLOR_GROUP = 'FFF5F5F5';


    public function exportClasseurFormateurs(Session $session, array $items): StreamedResponse
    {
        // $items = [
        //   ['formateur' => Formateur, 'data' => array],
        //   ...
        // ];

        $spreadsheet = new Spreadsheet();

        // Supprime la feuille vide créée par défaut
        $spreadsheet->removeSheetByIndex(0);

        $usedTitles = [];

        foreach ($items as $idx => $item) {
            /** @var Formateur $formateur */
            $formateur = $item['formateur'];
            $data = $item['data'];

            $sheet = new Worksheet($spreadsheet);
            $spreadsheet->addSheet($sheet);

            $nomComplet = trim($formateur->getNom() . ' ' . $formateur->getPrenom());
            $title = mb_substr($nomComplet, 0, 31);

            // éviter doublons titres (Excel n'accepte pas deux onglets avec même nom)
            $base = $title;
            $n = 2;
            while (in_array($title, $usedTitles, true)) {
                $suffix = " ($n)";
                $title = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
                $n++;
            }
            $usedTitles[] = $title;

            $sheet->setTitle($title);

            // IMPORTANT : on réutilise EXACTEMENT ton rendu, feuille par feuille
            $this->buildSheet($spreadsheet, $sheet, $formateur, $session, $data);

            if ($idx === 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }
        }

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'fiche_formateurs_' . preg_replace('/\s+/', '_', strtolower($session->getLibelle())) . '.xlsx';

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');

        return $response;
    }

    /**
     * Reprend TON export() actuel, mais :
     * - ne crée pas Spreadsheet
     * - n’écrit pas de StreamedResponse
     * - utilise $sheet fourni
     * - garde EXACTEMENT ton code (mêmes styles/bordures/hauteurs)
     */
    private function buildSheet(
        Spreadsheet $spreadsheet,
        Worksheet   $sheet,
        Formateur   $formateur,
        Session     $session,
        array       $data,

    ): void
    {

        // =========================
        // STYLE GLOBAL
        // =========================
        $spreadsheet->getDefaultStyle()->getFont()
            ->setName('Arial')
            ->setSize(10);

        // 🔥 CENTRAGE VERTICAL GLOBAL
        $spreadsheet->getDefaultStyle()
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);


        $row = 1;
        // impression
        // Mise à l'échelle impression : largeur page
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        // =========================
        // TITRE
        // =========================
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue(
            "A{$row}",
            $formateur->getNom() . ' ' . $formateur->getPrenom() . ' - Fiche horaire ' . $session->getLibelle()
        );
        $sheet->getStyle("A{$row}")
            ->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row += 2;

        // =========================
        // TABLEAU COURS
        // =========================
        $coursHeaderStart = $row;
        $row = $this->writeHeader5Cols($sheet, $row, 'ACTE DE FORMATION');
        $coursDataStart = $row;

        $subtotalRows = [];

        foreach (($data['classes'] ?? []) as $classe => $bloc) {

            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", $classe);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            $this->fill($sheet, "A{$row}:E{$row}", $this->toARGB($bloc['couleur'] ?? '#FFFFFF'));
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;

            $classDataStart = $row;

            foreach (($bloc['groupes'] ?? []) as $groupe => $lignes) {

                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->setCellValue("A{$row}", "  " . $groupe);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $this->fill($sheet, "A{$row}:E{$row}", self::COLOR_GROUP);
                $sheet->getRowDimension($row)->setRowHeight(18);
                $row++;

                foreach ($lignes as $ligne) {
                    $sheet->setCellValue("A{$row}", "    " . ($ligne['matiere'] ?? ''));
                    $sheet->setCellValue("B{$row}", (float)($ligne['ufa_face'] ?? 0));
                    $sheet->setCellValue("C{$row}", (float)($ligne['ufa_travail'] ?? 0));
                    $sheet->setCellValue("D{$row}", (float)($ligne['fpc_face'] ?? 0));
                    $sheet->setCellValue("E{$row}", (float)($ligne['fpc_travail'] ?? 0));
                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;
                }
            }

            $classDataEnd = $row - 1;

            $sheet->setCellValue("A{$row}", "Sous-total " . $classe);

            if ($classDataEnd >= $classDataStart) {
                $sheet->setCellValue("B{$row}", "=SUM(B{$classDataStart}:B{$classDataEnd})");
                $sheet->setCellValue("C{$row}", "=SUM(C{$classDataStart}:C{$classDataEnd})");
                $sheet->setCellValue("D{$row}", "=SUM(D{$classDataStart}:D{$classDataEnd})");
                $sheet->setCellValue("E{$row}", "=SUM(E{$classDataStart}:E{$classDataEnd})");
            }

            $this->fill($sheet, "A{$row}:E{$row}", self::COLOR_SUBTOTAL);
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);

            $subtotalRows[] = $row;
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }


        // TOTAL HEURES
        $coursTotalRow = $row;
        $sheet->setCellValue("A{$coursTotalRow}", "TOTAL HEURES");

        if (!empty($subtotalRows)) {
            $sheet->setCellValue("B{$coursTotalRow}", "=SUM(" . implode(',', array_map(fn($r) => "B{$r}", $subtotalRows)) . ")");
            $sheet->setCellValue("C{$coursTotalRow}", "=SUM(" . implode(',', array_map(fn($r) => "C{$r}", $subtotalRows)) . ")");
            $sheet->setCellValue("D{$coursTotalRow}", "=SUM(" . implode(',', array_map(fn($r) => "D{$r}", $subtotalRows)) . ")");
            $sheet->setCellValue("E{$coursTotalRow}", "=SUM(" . implode(',', array_map(fn($r) => "E{$r}", $subtotalRows)) . ")");
            $sheet->getRowDimension($coursTotalRow)->setRowHeight(18);
        }

        $this->fill($sheet, "A{$coursTotalRow}:E{$coursTotalRow}", self::COLOR_TOTAL);
        $sheet->getStyle("A{$coursTotalRow}:E{$coursTotalRow}")->getFont()->setBold(true);

        $sheet->getStyle("B{$coursDataStart}:E{$coursTotalRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');

        $this->applyBorders($sheet, "A{$coursHeaderStart}:E{$coursTotalRow}");


        $row = $coursTotalRow + 2;
        // =========================
        // MISSIONS
        // =========================
        $missions = $data['missions']['lignes'] ?? [];
        if (!empty($missions)) {

            $sheet->setCellValue("A{$row}", "MISSIONS / AUTRES ACTIVITÉS");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;

            $missionsHeaderStart = $row;
            $row = $this->writeHeader5Cols($sheet, $row, 'MISSIONS');
            $missionsDataStart = $row;

            foreach ($missions as $m) {
                $sheet->setCellValue("A{$row}", (string)($m['libelle'] ?? ''));
                $sheet->setCellValue("B{$row}", (float)($m['ufa_face'] ?? 0));
                $sheet->setCellValue("C{$row}", (float)($m['ufa_travail'] ?? 0));
                $sheet->setCellValue("D{$row}", (float)($m['fpc_face'] ?? 0));
                $sheet->setCellValue("E{$row}", (float)($m['fpc_travail'] ?? 0));
                $sheet->getRowDimension($row)->setRowHeight(18);
                $sheet->getStyle("B{$row}:E{$row}")
                    ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
                $row++;
            }

            $missionsTotalRow = $row;
            $sheet->setCellValue("A{$missionsTotalRow}", "TOTAL MISSIONS");
            $sheet->setCellValue("B{$missionsTotalRow}", "=SUM(B{$missionsDataStart}:B" . ($missionsTotalRow - 1) . ")");
            $sheet->setCellValue("C{$missionsTotalRow}", "=SUM(C{$missionsDataStart}:C" . ($missionsTotalRow - 1) . ")");
            $sheet->setCellValue("D{$missionsTotalRow}", "=SUM(D{$missionsDataStart}:D" . ($missionsTotalRow - 1) . ")");
            $sheet->setCellValue("E{$missionsTotalRow}", "=SUM(E{$missionsDataStart}:E" . ($missionsTotalRow - 1) . ")");

            $this->fill($sheet, "A{$missionsTotalRow}:E{$missionsTotalRow}", self::COLOR_SUBTOTAL);
            $sheet->getStyle("A{$missionsTotalRow}:E{$missionsTotalRow}")->getFont()->setBold(true);

            $this->applyBorders($sheet, "A{$missionsHeaderStart}:E{$missionsTotalRow}");
            $sheet->getRowDimension($missionsTotalRow)->setRowHeight(18);

            $sheet->getStyle("B{$missionsTotalRow}:E{$missionsTotalRow}")
                ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
            $row = $missionsTotalRow + 2;
        }

        // ====== FORMULES GLOBALES (cours + missions) - UNIQUEMENT CALCULS ======
        $volumeContractuel = (float)$formateur->getVolumeContractuel();
        $quotite = (float)$formateur->getQuotite();

        $travailExpr = "C{$coursTotalRow}+E{$coursTotalRow}";
        $faceExpr = "B{$coursTotalRow}+D{$coursTotalRow}";

        if (isset($missionsTotalRow)) {
            $travailExpr = "({$travailExpr})+(C{$missionsTotalRow}+E{$missionsTotalRow})";
            $faceExpr = "({$faceExpr})+(B{$missionsTotalRow}+D{$missionsTotalRow})";
        }

        $heuresPrevuesExpr = "({$volumeContractuel}*{$quotite})";

        // ========================= // TABLEAU 3 : ANALYTIQUE (A-C) // =========================
        $ana = $data['analytique'] ?? null;
        if (!empty($ana)) {
            $sheet->setCellValue("A{$row}", "ANALYTIQUE");
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;
            $anaHeaderStart = $row; // Header
            $sheet->setCellValue("A{$row}", "Indicateur");
            $sheet->setCellValue("B{$row}", "Prévisionnel");
            $sheet->setCellValue("C{$row}", "Réel");
            $this->headerStyle($sheet, "A{$row}:C{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
            // Pourcentage
            $sheet->setCellValue("A{$row}", "Pourcentage");
            $sheet->setCellValue("B{$row}", ((float)($ana['pourcentage_previsitionnel'] ?? 0)) / 100);
            $sheet->setCellValue("C{$row}", "=IF({$volumeContractuel}=0,0,({$travailExpr})/{$volumeContractuel})");
            $sheet->getStyle("B{$row}:C{$row}")
                ->getNumberFormat()
                ->setFormatCode('0.0%;-0.0%;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++; // Diff heures
            $sheet->setCellValue("A{$row}", "Différence d'heures");
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", "=({$travailExpr})-{$heuresPrevuesExpr}");
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // Répartition UFA/FC header
            $this->fill($sheet, "A{$row}:C{$row}", self::COLOR_HEADER);
            $sheet->setCellValue("A{$row}", "Répartition UFA / FPC");
            $sheet->setCellValue("B{$row}", "UFA");
            $sheet->setCellValue("C{$row}", "FC");
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // en %
            $sheet->setCellValue("A{$row}", "en %");
            $sheet->setCellValue("B{$row}", "=IF(({$travailExpr})=0,0,(C{$coursTotalRow}" . (isset($missionsTotalRow) ? "+C{$missionsTotalRow}" : "") . ")/({$travailExpr}))");
            $sheet->setCellValue("C{$row}", "=IF(({$travailExpr})=0,0,(E{$coursTotalRow}" . (isset($missionsTotalRow) ? "+E{$missionsTotalRow}" : "") . ")/({$travailExpr}))");
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('0.0%;-0.0%;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // Semaine
            $sheet->setCellValue("A{$row}", "Semaine (h)");
            $sheet->setCellValue("B{$row}", "=(C{$coursTotalRow}" . (isset($missionsTotalRow) ? "+C{$missionsTotalRow}" : "") . ")/52");
            $sheet->setCellValue("C{$row}", "=(E{$coursTotalRow}" . (isset($missionsTotalRow) ? "+E{$missionsTotalRow}" : "") . ")/52");
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // Mois
            $sheet->setCellValue("A{$row}", "Mois (h)");
            $sheet->setCellValue("B{$row}", "=(C{$coursTotalRow}" . (isset($missionsTotalRow) ? "+C{$missionsTotalRow}" : "") . ")/12");

// FPC (FC) mois = (temps travail cours + missions) / 12
            $sheet->setCellValue("C{$row}", "=(E{$coursTotalRow}" . (isset($missionsTotalRow) ? "+E{$missionsTotalRow}" : "") . ")/12");
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // Total / mois
            $this->fill($sheet, "A{$row}:C{$row}", self::COLOR_SUBTOTAL);
            $sheet->setCellValue("A{$row}", "Total / mois (h)");
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", "=(C{$coursTotalRow}+E{$coursTotalRow}" . (isset($missionsTotalRow) ? "+C{$missionsTotalRow}+E{$missionsTotalRow}" : "") . ")/12");
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
            $sheet->getRowDimension($row)->setRowHeight(20);
            $anaEndRow = $row;
            // Bordures analytique
            $this->applyBorders($sheet, "A{$anaHeaderStart}:C{$anaEndRow}");
            $row = $anaEndRow + 2;
        }


        // =========================
        // TOTAL GENERAL
        // =========================
        $panelStartRow = $row;

        $sheet->setCellValue("A{$row}", "TOTAL GENERAL");
        $sheet->mergeCells("A{$row}:E{$row}");
        $this->fill($sheet, "A{$row}:E{$row}", self::COLOR_HEADER);
        $sheet->getStyle("A{$row}:E{$row}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;
        $sheet->setCellValue("A{$row}", "Répartition des heures");
        $sheet->getStyle("A{$row}:A{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("B{$row}", "Face à face (h)");

        $sheet->setCellValue("C{$row}", "={$faceExpr}");

        $sheet->setCellValue("D{$row}", "Temps travail (h)");
        $sheet->setCellValue("E{$row}", "={$travailExpr}");

        $sheet->getStyle("B{$row}:E{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
        // Nombre d'heures total
        $sheet->setCellValue("A{$row}", "Nombre d'heures total");
        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->setCellValue("B{$row}", "={$travailExpr}");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getStyle("A{$row}:E{$row}")
            ->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        // 🔥 TAUX DE CHARGE AJOUTÉ
        $sheet->setCellValue("A{$row}", "Taux de charge");

        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->setCellValue("B{$row}", "=IF({$volumeContractuel}=0,0,({$travailExpr})/{$volumeContractuel})");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('0.0%;-0.0%;;@');
        $sheet->getStyle("A{$row}:E{$row}")
            ->getFont()->setItalic(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $panelEndRow = $row;

        $this->applyBorders($sheet, "A{$panelStartRow}:E{$panelEndRow}");

        // =========================
        // COLONNES
        // =========================
        $sheet->getColumnDimension('A')->setWidth(50);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);
            $sheet->getRowDimension($coursTotalRow)->setRowHeight(18);
            $sheet->getStyle("{$col}1:{$col}{$row}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        //  $sheet->freezePane('A5');


    }

    public function export(Formateur $formateur, Session $session, array $data): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $nomComplet = $formateur->getNom() . ' ' . $formateur->getPrenom();
        $sheet->setTitle(substr($nomComplet, 0, 31)); // Excel max 31 caractères

        $this->buildSheet($spreadsheet, $sheet, $formateur, $session, $data);
        // =========================
        // STREAM
        // =========================
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->headers->set(
            'Content-Disposition',
            'attachment;filename="' . $nomComplet . '.xlsx"'
        );

        return $response;
    }

    private function writeHeader5Cols($sheet, int $row, string $colA): int
    {
        $sheet->setCellValue("A{$row}", $colA);
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("B{$row}", "Apprentissage");
        $sheet->mergeCells("D{$row}:E{$row}");
        $sheet->setCellValue("D{$row}", "Continue");
        $this->headerStyle($sheet, "A{$row}:E{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        $sheet->setCellValue("B{$row}", "F à F");
        $sheet->setCellValue("C{$row}", "Temps travail");
        $sheet->setCellValue("D{$row}", "F à F");
        $sheet->setCellValue("E{$row}", "Temps travail");
        $this->headerStyle($sheet, "A{$row}:E{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        return $row;
    }

    private
    function headerStyle($sheet, string $range): void
    {
        $this->fill($sheet, $range, self::COLOR_HEADER);
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function fill($sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($argb);
    }

    private function applyBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    private function toARGB(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return 'FF' . strtoupper($hex ?: 'FFFFFF');
    }
}
