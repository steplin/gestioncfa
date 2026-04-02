<?php

namespace App\Service\Export;

use App\Entity\Formateur;
use App\Entity\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
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


    public function exportClasseurFormateurs(Session $session, array $items, $mode): StreamedResponse
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
            $this->buildSheet($spreadsheet, $sheet, $formateur, $session, $data, $mode);

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
        string      $mode,

    ): void
    {
        if ($mode === 'both') {
            $this->buildSheetBoth($spreadsheet, $sheet, $formateur, $session, $data);
            return;
        }
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
        $ligneMissions = 0;
        // impression
        // Mise à l'échelle impression : largeur page
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        // =========================
        // TITRE
        // =========================
        if ($mode == 'both') {
            $sheet->mergeCells("A{$row}:M{$row}");
        } else {
            $sheet->mergeCells("A{$row}:E{$row}");
        }
        if ($mode == 'reel') {
            $titre = $formateur->getNom() . ' ' . $formateur->getPrenom() . ' - Fiche horaire ' . $session->getLibelle();
        } else {
            $titre = $formateur->getNom() . ' ' . $formateur->getPrenom() . ' - Fiche horaire prévisionnelle ' . $session->getLibelle();
        }
        $sheet->setCellValue("A{$row}", $titre);
        $sheet->getStyle("A{$row}")
            ->getFont()->setBold(true)->setSize(14);

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
                    if ($mode === 'reel') {
                        $sheet->setCellValue("B{$row}", (float)($ligne['ufa_face'] ?? 0));
                        $sheet->setCellValue("C{$row}", (float)($ligne['ufa_travail'] ?? 0));
                        $sheet->setCellValue("D{$row}", (float)($ligne['fpc_face'] ?? 0));
                        $sheet->setCellValue("E{$row}", (float)($ligne['fpc_travail'] ?? 0));
                    } else {
                        $sheet->setCellValue("B{$row}", (float)($ligne['ufa_face_prev'] ?? 0));
                        $sheet->setCellValue("C{$row}", (float)($ligne['ufa_travail_prev'] ?? 0));
                        $sheet->setCellValue("D{$row}", (float)($ligne['fpc_face_prev'] ?? 0));
                        $sheet->setCellValue("E{$row}", (float)($ligne['fpc_travail_prev'] ?? 0));
                    }


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
                if ($mode == 'reel') {
                    $sheet->setCellValue("B{$row}", (float)($m['ufa_face'] ?? 0));
                    $sheet->setCellValue("C{$row}", (float)($m['ufa_travail'] ?? 0));
                    $sheet->setCellValue("D{$row}", (float)($m['fpc_face'] ?? 0));
                    $sheet->setCellValue("E{$row}", (float)($m['fpc_travail'] ?? 0));
                } else {
                    $sheet->setCellValue("B{$row}", (float)($m['ufa_face_prev'] ?? 0));
                    $sheet->setCellValue("C{$row}", (float)($m['ufa_travail_prev'] ?? 0));
                    $sheet->setCellValue("D{$row}", (float)($m['fpc_face_prev'] ?? 0));
                    $sheet->setCellValue("E{$row}", (float)($m['fpc_travail_prev'] ?? 0));
                }
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
            $ligneMissions = $row;
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
            $sheet->setCellValue("A{$row}", "Indicateurs");
            $sheet->setCellValue("B{$row}", "Contrat");
            $sheet->setCellValue("C{$row}", "Réel");
            $this->headerStyle($sheet, "A{$row}:C{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
            // Pourcentage
            $sheet->setCellValue("A{$row}", "Taux de charge");

            if ($mode == 'reel') {
                $sheet->setCellValue("B{$row}", ((float)($ana['pourcentage_prev'] ?? 0)) / 100);
            } else {
                $sheet->setCellValue("B{$row}", ((float)($ana['pourcentage_contrat'] ?? 0)) / 100);
            }


            $sheet->setCellValue("C{$row}", "=IF({$volumeContractuel}=0,0,({$travailExpr})/{$volumeContractuel})");
            $sheet->getStyle("B{$row}:C{$row}")
                ->getNumberFormat()
                ->setFormatCode('0.00%;-0.00%;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++; // Diff heures
            $sheet->setCellValue("A{$row}", "Différence d'heures");
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", "=({$travailExpr})-{$heuresPrevuesExpr}");
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $this->applyBorders($sheet, "A{$anaHeaderStart}:C{$row}");
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $this->fill($sheet, "A{$row}:C{$row}", self::COLOR_HEADER);
            $row = $row + 2;
            $anaHeaderStart = $row;

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
            $sheet->setCellValue(
                "B{$row}",
                "=(C{$coursTotalRow}" . (isset($missionsTotalRow) ? "+C{$missionsTotalRow}" : "") . ")/{$volumeContractuel}"
            );
            $sheet->setCellValue(
                "C{$row}",
                "=(E{$coursTotalRow}" . (isset($missionsTotalRow) ? "+E{$missionsTotalRow}" : "") . ")/{$volumeContractuel}"
            );
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $anaPctRow = $row;
            $row++;

            // Semaine (h)
            $sheet->setCellValue("A{$row}", "Semaine (h)");
            $sheet->setCellValue("B{$row}", "=B{$anaPctRow}*35");
            $sheet->setCellValue("C{$row}", "=C{$anaPctRow}*35");
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
            // Mois (h)

            $sheet->setCellValue("A{$row}", "Mois (h)");
            $sheet->setCellValue("B{$row}", "=B{$anaPctRow}*151.67");
            $sheet->setCellValue("C{$row}", "=C{$anaPctRow}*151.67");
            $sheet->getStyle("B{$row}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $anaMoisRow = $row;
            $row++;
            $sheet->setCellValue("A{$row}", "Total / mois (h)");
            $sheet->mergeCells("B{$row}:C{$row}");
            $sheet->setCellValue("B{$row}", "=B{$anaMoisRow}+C{$anaMoisRow}");
            $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:C{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$row}:C{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $this->fill($sheet, "A{$row}:C{$row}", self::COLOR_HEADER);
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
        $sheet->mergeCells("A{$row}:B{$row}");
        $this->fill($sheet, "A{$row}:B{$row}", self::COLOR_HEADER);
        $sheet->getStyle("A{$row}:B{$row}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:B{$row}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;
        $sheet->setCellValue("A{$row}", "Face à face (h)");
        $sheet->getStyle("A{$row}:A{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("B{$row}", "={$faceExpr}");
        $sheet->getStyle("B{$row}:B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        // Nombre d'heures PRE
        $sheet->setCellValue("A{$row}", "PRE");
        $sheet->setCellValue("B{$row}", "=({$faceExpr})*50%");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getStyle("A{$row}:A{$row}")
            ->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
        // Nombre d'heures Adaf
        $sheet->setCellValue("A{$row}", "ADAF");
        $sheet->setCellValue("B{$row}", "=({$faceExpr})*50%");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getStyle("A{$row}:A{$row}")
            ->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
        // Nombre d'heures Missions
        $sheet->setCellValue("A{$row}", "Missions");
        $sheet->setCellValue("B{$row}", "=C{$ligneMissions}+E{$ligneMissions}");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getStyle("A{$row}:A{$row}")
            ->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;
        // Nombre d'heures total
        $sheet->setCellValue("A{$row}", "Temps travail (h)");
        $sheet->setCellValue("B{$row}", "={$travailExpr}");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $sheet->getStyle("A{$row}:B{$row}")
            ->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $this->fill($sheet, "A{$row}:B{$row}", self::COLOR_HEADER);
        $row++;

        // 🔥 TAUX DE CHARGE AJOUTÉ
        $sheet->setCellValue("A{$row}", "Taux de charge");

        $sheet->setCellValue("B{$row}", "=IF({$volumeContractuel}=0,0,({$travailExpr})/{$volumeContractuel})");
        $sheet->getStyle("B{$row}")
            ->getNumberFormat()->setFormatCode('0.00%;-0.00%;;@');
        $sheet->getStyle("A{$row}:B{$row}")
            ->getFont()->setItalic(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $panelEndRow = $row;

        $this->applyBorders($sheet, "A{$panelStartRow}:B{$panelEndRow}");

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

    public function export(Formateur $formateur, Session $session, array $data, $mode): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $nomComplet = $formateur->getNom() . ' ' . $formateur->getPrenom();
        $sheet->setTitle(substr($nomComplet, 0, 31)); // Excel max 31 caractères

        $this->buildSheet($spreadsheet, $sheet, $formateur, $session, $data, $mode);
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

    private function headerStyle($sheet, string $range): void
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

    private function buildSheetBoth(
        Spreadsheet $spreadsheet,
        Worksheet   $sheet,
        Formateur   $formateur,
        Session     $session,
        array       $data
    ): void
    {

        // =========================
        // STYLE GLOBAL (identique)
        // =========================
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Impression (identique)
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setOrientation(PageSetup::ORIENTATION_PORTRAIT);

        $row = 1;

        // =========================
        // TITRE (A..M)
        // =========================
        $sheet->mergeCells("A{$row}:M{$row}");
        $titre = $formateur->getNom() . ' ' . $formateur->getPrenom() . ' - Fiche horaire Réel / Prévisionnel ' . $session->getLibelle();
        $sheet->setCellValue("A{$row}", $titre);

        $sheet->getStyle("A{$row}:M{$row}")->getFont()->setBold(true)->setSize(14);


        $sheet->getRowDimension($row)->setRowHeight(26);
        $row += 2;

        // =========================================================
        // ====================== COURS ============================
        // =========================================================
        $coursHeaderStart = $row;
        $sheet->mergeCells("A{$row}:A" . ($row + 2));
        $row = $this->writeHeader13ColsBoth($sheet, $row, 'ACTE DE FORMATION');


        $coursDataStart = $row;

        $subtotalRows = []; // rows des sous-totaux classe (pour total global cours)

        foreach (($data['classes'] ?? []) as $classe => $bloc) {

            // Ligne classe
            $sheet->mergeCells("A{$row}:M{$row}");
            $sheet->setCellValue("A{$row}", $classe);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            $this->fill($sheet, "A{$row}:M{$row}", $this->toARGB($bloc['couleur'] ?? '#FFFFFF'));
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;

            $classDataStart = $row;

            foreach (($bloc['groupes'] ?? []) as $groupe => $lignes) {

                // Ligne groupe
                $sheet->mergeCells("A{$row}:M{$row}");
                $sheet->setCellValue("A{$row}", "  " . $groupe);
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $this->fill($sheet, "A{$row}:M{$row}", self::COLOR_GROUP);
                $sheet->getRowDimension($row)->setRowHeight(18);
                $row++;

                foreach ($lignes as $ligne) {

                    $sheet->setCellValue("A{$row}", "    " . ($ligne['matiere'] ?? ''));

                    // Réel / Prév / Écart (Écart = Réel - Prév)
                    $sheet->setCellValue("B{$row}", (float)($ligne['ufa_face'] ?? 0));
                    $sheet->setCellValue("C{$row}", (float)($ligne['ufa_face_prev'] ?? 0));
                    $sheet->setCellValue("D{$row}", "=B{$row}-C{$row}");

                    $sheet->setCellValue("E{$row}", (float)($ligne['ufa_travail'] ?? 0));
                    $sheet->setCellValue("F{$row}", (float)($ligne['ufa_travail_prev'] ?? 0));
                    $sheet->setCellValue("G{$row}", "=E{$row}-F{$row}");

                    $sheet->setCellValue("H{$row}", (float)($ligne['fpc_face'] ?? 0));
                    $sheet->setCellValue("I{$row}", (float)($ligne['fpc_face_prev'] ?? 0));
                    $sheet->setCellValue("J{$row}", "=H{$row}-I{$row}");

                    $sheet->setCellValue("K{$row}", (float)($ligne['fpc_travail'] ?? 0));
                    $sheet->setCellValue("L{$row}", (float)($ligne['fpc_travail_prev'] ?? 0));
                    $sheet->setCellValue("M{$row}", "=K{$row}-L{$row}");

                    $sheet->getRowDimension($row)->setRowHeight(18);
                    $row++;
                }
            }

            $classDataEnd = $row - 1;

            // Sous-total classe (même style)
            $sheet->setCellValue("A{$row}", "Sous-total " . $classe);

            if ($classDataEnd >= $classDataStart) {
                foreach (range('B', 'M') as $col) {
                    $sheet->setCellValue("{$col}{$row}", "=SUM({$col}{$classDataStart}:{$col}{$classDataEnd})");
                }
            }

            $this->fill($sheet, "A{$row}:M{$row}", self::COLOR_SUBTOTAL);
            $sheet->getStyle("A{$row}:M{$row}")->getFont()->setBold(true);
            $sheet->getRowDimension($row)->setRowHeight(18);

            $subtotalRows[] = $row;
            $row++;
        }

        // TOTAL HEURES cours (basé sur sous-totaux comme ta méthode)
        $coursTotalRow = $row;
        $sheet->setCellValue("A{$coursTotalRow}", "TOTAL HEURES");

        if (!empty($subtotalRows)) {
            foreach (range('B', 'M') as $col) {
                $sheet->setCellValue(
                    "{$col}{$coursTotalRow}",
                    "=SUM(" . implode(',', array_map(fn($r) => "{$col}{$r}", $subtotalRows)) . ")"
                );
            }
        }

        $this->fill($sheet, "A{$coursTotalRow}:M{$coursTotalRow}", self::COLOR_TOTAL);
        $sheet->getStyle("A{$coursTotalRow}:M{$coursTotalRow}")->getFont()->setBold(true);
        $sheet->getRowDimension($coursTotalRow)->setRowHeight(18);

        $this->formatNumbers13($sheet, $coursDataStart, $coursTotalRow);
        $this->stylePrevColumnsBold($sheet, $coursDataStart, $coursTotalRow);

        $this->applyBorders($sheet, "A{$coursHeaderStart}:M{$coursTotalRow}");
        // Cond. format sur les colonnes Écart (cours)
        $this->addConditionalEcart($sheet, "D{$coursDataStart}:D{$coursTotalRow}");
        $this->addConditionalEcart($sheet, "G{$coursDataStart}:G{$coursTotalRow}");
        $this->addConditionalEcart($sheet, "J{$coursDataStart}:J{$coursTotalRow}");
        $this->addConditionalEcart($sheet, "M{$coursDataStart}:M{$coursTotalRow}");
        $row = $coursTotalRow + 2;

        // =========================================================
        // ====================== MISSIONS =========================
        // =========================================================
        $missions = $data['missions']['lignes'] ?? [];
        $missionsTotalRow = null;
        $missionsReel = '0';
        $missionsPrev = '0';
        if (!empty($missions)) {


            $missionsHeaderStart = $row;
            $sheet->mergeCells("A{$row}:A" . ($row + 2));
            $row = $this->writeHeader13ColsBoth($sheet, $row, 'MISSIONS / AUTRES ACTIVITÉS');
            $missionsDataStart = $row;

            foreach ($missions as $m) {
                $sheet->setCellValue("A{$row}", (string)($m['libelle'] ?? ''));

                $sheet->setCellValue("B{$row}", (float)($m['ufa_face'] ?? 0));
                $sheet->setCellValue("C{$row}", (float)($m['ufa_face_prev'] ?? 0));
                $sheet->setCellValue("D{$row}", "=B{$row}-C{$row}");

                $sheet->setCellValue("E{$row}", (float)($m['ufa_travail'] ?? 0));
                $sheet->setCellValue("F{$row}", (float)($m['ufa_travail_prev'] ?? 0));
                $sheet->setCellValue("G{$row}", "=E{$row}-F{$row}");

                $sheet->setCellValue("H{$row}", (float)($m['fpc_face'] ?? 0));
                $sheet->setCellValue("I{$row}", (float)($m['fpc_face_prev'] ?? 0));
                $sheet->setCellValue("J{$row}", "=H{$row}-I{$row}");

                $sheet->setCellValue("K{$row}", (float)($m['fpc_travail'] ?? 0));
                $sheet->setCellValue("L{$row}", (float)($m['fpc_travail_prev'] ?? 0));
                $sheet->setCellValue("M{$row}", "=K{$row}-L{$row}");

                $sheet->getRowDimension($row)->setRowHeight(18);
                $row++;
            }

            $missionsTotalRow = $row;
            $sheet->setCellValue("A{$missionsTotalRow}", "TOTAL MISSIONS");
            foreach (range('B', 'M') as $col) {
                $sheet->setCellValue("{$col}{$missionsTotalRow}", "=SUM({$col}{$missionsDataStart}:{$col}" . ($missionsTotalRow - 1) . ")");
            }

            $this->fill($sheet, "A{$missionsTotalRow}:M{$missionsTotalRow}", self::COLOR_SUBTOTAL);
            $sheet->getStyle("A{$missionsTotalRow}:M{$missionsTotalRow}")->getFont()->setBold(true);
            $sheet->getRowDimension($missionsTotalRow)->setRowHeight(18);

            $this->formatNumbers13($sheet, $missionsDataStart, $missionsTotalRow);
            $this->stylePrevColumnsBold($sheet, $missionsDataStart, $missionsTotalRow);

            $this->applyBorders($sheet, "A{$missionsHeaderStart}:M{$missionsTotalRow}");
            if ($missionsTotalRow) {
                $this->addConditionalEcart($sheet, "D{$missionsDataStart}:D{$missionsTotalRow}");
                $this->addConditionalEcart($sheet, "G{$missionsDataStart}:G{$missionsTotalRow}");
                $this->addConditionalEcart($sheet, "J{$missionsDataStart}:J{$missionsTotalRow}");
                $this->addConditionalEcart($sheet, "M{$missionsDataStart}:M{$missionsTotalRow}");
            }
            $row = $missionsTotalRow + 2;
        }

        // =========================================================
        // ====================== FORMULES GLOBALES =================
        // =========================================================
        // Face à face Réel/Prev = (UFA face + FPC face) cours + missions
        $fafReel = "(B{$coursTotalRow}+H{$coursTotalRow})";
        $fafPrev = "(C{$coursTotalRow}+I{$coursTotalRow})";

        $travailReelExpr = $fafTpReel = "(E{$coursTotalRow}+K{$coursTotalRow})";
        $travailPrevExpr = $fafTpPrev = "(F{$coursTotalRow}+L{$coursTotalRow})";

        if ($missionsTotalRow) {
            $travailReelExpr .= "+(E{$missionsTotalRow}+K{$missionsTotalRow})";
            $travailPrevExpr .= "+(F{$missionsTotalRow}+L{$missionsTotalRow})";
            $missionsReel = "(E{$missionsTotalRow}+K{$missionsTotalRow})";
            $missionsPrev = "(F{$missionsTotalRow}+L{$missionsTotalRow})";
        }

        $volumeContractuel = (float)$formateur->getVolumeContractuel();

        // =========================================================
        // ====================== ANALYTIQUE ========================
        // =========================================================
       $ana = $data['analytique'] ?? null;
        if (!empty($ana)) {

            $anaHeaderStart = $row;


            // 1) Pourcentage (Contrat : X %)
            $contratPct = (float)($ana['pourcentage_contrat'] ?? 0);

            // 3) Répartition UFA/FPC (header)
            $this->fill($sheet, "A{$row}:J{$row}", self::COLOR_HEADER);
            $sheet->setCellValue("A{$row}", "Répartition UFA / FPC");
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->setCellValue("B{$row}", "UFA");
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->setCellValue("E{$row}", "FC");
            $sheet->mergeCells("H{$row}:J{$row}");
            $sheet->setCellValue("H{$row}", "");
            $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;

            // 4) en %
            // UFA travail reel = E cours (+ E missions)
            $ufaTravailReelExpr = "E{$coursTotalRow}" . ($missionsTotalRow ? "+E{$missionsTotalRow}" : "");
            $fpcTravailReelExpr = "K{$coursTotalRow}" . ($missionsTotalRow ? "+K{$missionsTotalRow}" : "");


            $sheet->setCellValue("A{$row}", "en %");
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->mergeCells("H{$row}:J{$row}");

            // Ici on reproduit l’affichage de ta capture : Réel = %UFA, Prévisionnel = %FC (comme ton tableau actuel)
            // Si tu veux “Réel: UFA% | Prévisionnel: FC%” c’est exactement ça :
            $sheet->setCellValue("B{$row}", "=IF(({$travailReelExpr})=0,0,({$ufaTravailReelExpr})/({$volumeContractuel}))");
            $sheet->setCellValue("E{$row}", "=IF(({$travailReelExpr})=0,0,({$fpcTravailReelExpr})/({$volumeContractuel}))");

            $sheet->getStyle("B{$row}:G{$row}")->getNumberFormat()->setFormatCode('0.00%;-0.00%;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $anaPctRow = $row;
            $row++;

            // 5) Semaine
            $sheet->setCellValue("A{$row}", "Semaine");
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->mergeCells("H{$row}:J{$row}");
            $sheet->setCellValue("B{$row}", "=B{$anaPctRow}*35");
            $sheet->setCellValue("E{$row}", "=E{$anaPctRow}*35");
            $sheet->getStyle("B{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;

            // 6) Mois
            $sheet->setCellValue("A{$row}", "Mois");
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->mergeCells("H{$row}:J{$row}");
            $sheet->setCellValue("B{$row}", "=B{$anaPctRow}*151.67");
            $sheet->setCellValue("E{$row}", "=E{$anaPctRow}*151.67");
            $sheet->getStyle("B{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;

            // 7) Total / mois
            $this->fill($sheet, "A{$row}:J{$row}", self::COLOR_SUBTOTAL);
            $sheet->setCellValue("A{$row}", "Total / mois");
            $sheet->mergeCells("B{$row}:J{$row}");
            $sheet->setCellValue("B{$row}", "=(B{$anaPctRow}+E{$anaPctRow})*151.67");
            $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
            $sheet->getRowDimension($row)->setRowHeight(20);

            $anaEndRow = $row;

            $this->applyBorders($sheet, "A{$anaHeaderStart}:J{$anaEndRow}");
            // Cond. format sur la colonne "Écart" analytique (H:J)
            $this->addConditionalEcart($sheet, "H{$anaHeaderStart}:J{$anaEndRow}");
            $row = $anaEndRow + 2;
        }

        // =========================================================
        // ====================== TOTAL GENERAL =====================
        // =========================================================

        $panelStartRow = $row;

// =========================
// TOTAL GENERAL (TABLE 3 LIGNES)
// =========================

// Ligne 1 : headers groupés
        $sheet->setCellValue("A{$row}", "TOTAL GENERAL");

// Style header (gris + gras + centré)
        $this->headerStyle($sheet, "A{$row}:D{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        // Ligne 2 : sous headers
        $sheet->setCellValue("B{$row}", "Réel");
        $sheet->setCellValue("C{$row}", "Prév.");
        $sheet->setCellValue("D{$row}", "Écart");
        $this->headerStyle($sheet, "B{$row}:D{$row}");
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

// Ligne 3 : valeurs


// Face à face
        $valuesRow = $row;
        $startRow = $row;
        $sheet->setCellValue("A{$valuesRow}", "Face à Face");
        $sheet->setCellValue("B{$valuesRow}", "={$fafReel}");
        $sheet->setCellValue("C{$valuesRow}", "={$fafPrev}");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
// PRE
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "Préparation/Recherche");
        $sheet->setCellValue("B{$valuesRow}", "=({$fafTpReel})*25%");
        $sheet->setCellValue("C{$valuesRow}", "=({$fafTpPrev})*25%");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
// ADAF
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "ADAF");
        $sheet->setCellValue("B{$valuesRow}", "=({$fafTpReel})*22%");
        $sheet->setCellValue("C{$valuesRow}", "=({$fafTpPrev})*22%");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
// Evaluation
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "Evaluations");
        $sheet->setCellValue("B{$valuesRow}", "=({$fafTpReel})*3%");
        $sheet->setCellValue("C{$valuesRow}", "=({$fafTpPrev})*3%");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
// Missions
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "Missions");
        $sheet->setCellValue("B{$valuesRow}", "={$missionsReel}");
        $sheet->setCellValue("C{$valuesRow}", "={$missionsPrev}");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
// Temps travail
        $endRow = $valuesRow;
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "Temps de travail");
        $sheet->setCellValue("B{$valuesRow}", "=SUM(B{$startRow}:B{$endRow})");
        $sheet->setCellValue("C{$valuesRow}", "=SUM(C{$startRow}:C{$endRow})");
        $sheet->setCellValue("D{$valuesRow}", "=SUM(D{$startRow}:D{$endRow})");
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;;@');
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
        $sheet->getStyle("A{$valuesRow}:D{$valuesRow}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$valuesRow}:D{$valuesRow}", self::COLOR_SUBTOTAL);
// Taux de charge
        $valuesRow++;
        $sheet->setCellValue("A{$valuesRow}", "Taux de charge (Contrat : " . number_format($contratPct, 2, '.', '') . " %)");
        $sheet->setCellValue("B{$valuesRow}", "=IF({$volumeContractuel}=0,0,({$travailReelExpr})/{$volumeContractuel})");
        $sheet->setCellValue("C{$valuesRow}", "=IF({$volumeContractuel}=0,0,({$travailPrevExpr})/{$volumeContractuel})");
        $sheet->setCellValue("D{$valuesRow}", "=B{$valuesRow}-C{$valuesRow}");
        $sheet->getStyle("B{$valuesRow}:D{$valuesRow}")
            ->getNumberFormat()->setFormatCode('0.00%;-0.00%;;@');
        $sheet->getStyle("C{$valuesRow}")->getFont()->setBold(true);
        $this->addConditionalEcart($sheet, "D{$valuesRow}:D{$valuesRow}");
        $sheet->getRowDimension($valuesRow)->setRowHeight(20);
        $sheet->getStyle("A{$valuesRow}:D{$valuesRow}")->getFont()->setItalic(true);
        $row = $valuesRow+2;

// Signature
        $sheet->setCellValue("A{$row}", "Sous réserve de modification");
        $sheet->setCellValue("H{$row}", "=TODAY()");
        $sheet->getStyle("H{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row = $row+2;
        $sheet->setCellValue("A{$row}", "Date et Signature");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->mergeCells("C{$row}:H{$row}");
        $sheet->getRowDimension($row)->setRowHeight(40);
        $this->applyBorders($sheet, "A{$row}:H{$row}");
// Alignements
        $sheet->getStyle("B{$panelStartRow}:D{$valuesRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$valuesRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// Bordures du panel
        $this->applyBorders($sheet, "A{$panelStartRow}:D{$valuesRow}");
        $sheet->getStyle("A1:M1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
// Hauteur ligne valeurs
        $sheet->getRowDimension($valuesRow)->setRowHeight(22);

// Largeurs proches de ta capture
        $sheet->getColumnDimension('A')->setWidth(18);
        foreach (range('B', 'J') as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }

        $panelEndRow = $valuesRow;
        // =========================================================
        // ====================== LARGEURS + ALIGN ==================
        // =========================================================
        $sheet->getColumnDimension('A')->setWidth(60); // plus proche capture
        foreach (range('B', 'M') as $col) {
            $sheet->getColumnDimension($col)->setWidth(10.5);
            $sheet->getStyle("{$col}1:{$col}{$panelEndRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }


    }

    private function writeHeader13ColsBoth(Worksheet $sheet, int $row, string $colA): int
    {
        // Ligne 1
        $sheet->setCellValue("A{$row}", $colA);

        $sheet->mergeCells("B{$row}:G{$row}");
        $sheet->setCellValue("B{$row}", "Apprentissage");

        $sheet->mergeCells("H{$row}:M{$row}");
        $sheet->setCellValue("H{$row}", "Continue");

        $this->headerStyle($sheet, "A{$row}:M{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Ligne 2
        $sheet->mergeCells("B{$row}:D{$row}");
        $sheet->setCellValue("B{$row}", "F à F");
        $sheet->mergeCells("E{$row}:G{$row}");
        $sheet->setCellValue("E{$row}", "Temps travail");

        $sheet->mergeCells("H{$row}:J{$row}");
        $sheet->setCellValue("H{$row}", "F à F");
        $sheet->mergeCells("K{$row}:M{$row}");
        $sheet->setCellValue("K{$row}", "Temps travail");

        $this->headerStyle($sheet, "A{$row}:M{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Ligne 3 : Réel / Prév / Écart (x4)
        $sheet->setCellValue("B{$row}", "Réel");
        $sheet->setCellValue("C{$row}", "Prév.");
        $sheet->setCellValue("D{$row}", "Écart");

        $sheet->setCellValue("E{$row}", "Réel");
        $sheet->setCellValue("F{$row}", "Prév.");
        $sheet->setCellValue("G{$row}", "Écart");

        $sheet->setCellValue("H{$row}", "Réel");
        $sheet->setCellValue("I{$row}", "Prév.");
        $sheet->setCellValue("J{$row}", "Écart");

        $sheet->setCellValue("K{$row}", "Réel");
        $sheet->setCellValue("L{$row}", "Prév.");
        $sheet->setCellValue("M{$row}", "Écart");

        $this->headerStyle($sheet, "A{$row}:M{$row}");
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        return $row;
    }

    private function stylePrevColumnsBold(Worksheet $sheet, int $startRow, int $endRow): void
    {
        // Colonnes Prév : C, F, I, L
        foreach (['C', 'F', 'I', 'L'] as $col) {
            $sheet->getStyle("{$col}{$startRow}:{$col}{$endRow}")->getFont()->setBold(true);
        }
    }

    private function formatNumbers13(Worksheet $sheet, int $startRow, int $endRow): void
    {
        $sheet->getStyle("B{$startRow}:M{$endRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00;-#,##0.00;;@');
    }

    private function addConditionalEcart(Worksheet $sheet, string $range): void
    {
        $existing = $sheet->getStyle($range)->getConditionalStyles();

        // Rouge si > 0 (dépassement)
        $condPos = new Conditional();
        $condPos->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
            ->addCondition('0');
        $condPos->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_DARKGREEN);

        // Vert si < 0 (sous-consommation)
        $condNeg = new Conditional();
        $condNeg->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0');
        $condNeg->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);

        $sheet->getStyle($range)->setConditionalStyles(array_merge($existing, [$condPos, $condNeg]));
    }

}
