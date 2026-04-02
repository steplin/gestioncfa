<?php

namespace App\Controller;

use App\Entity\Formateur;
use App\Entity\Session;
use App\Service\DashboardFormateurService;
use App\Service\Export\FormateurExcelExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FormateurExportController extends AbstractController
{
    #[Route('/export/formateur/{formateur}/{session}', name: 'export_formateur_excel')]
    public function export(
        Request                     $request,
        Formateur                   $formateur,
        Session                     $session,
        DashboardFormateurService   $dashboardService,
        FormateurExcelExportService $excelService
    ): Response
    {

        $mode = $request->query->get('mode', 'reel');
        $data = $dashboardService->build($formateur, $session);

        return $excelService->export($formateur, $session, $data, $mode);
    }

    #[Route('/export/formateurs/session/{id}', name: 'export_formateurs_session')]
    public function exportFormateursSession(
        Request                     $request,
        Session                     $session,
        DashboardFormateurService   $dashboard,
        FormateurExcelExportService $export,
        EntityManagerInterface      $em
    ): Response
    {
        $formateurs = $em->getRepository(Formateur::class)->findBy(['actif' => true], ['nom' => 'ASC']);
        $mode = $request->query->get('mode', 'reel');
        $items = [];
        foreach ($formateurs as $f) {
            $data = $dashboard->build($f, $session);
            $items[] = ['formateur' => $f, 'data' => $data];
        }

        return $export->exportClasseurFormateurs($session, $items, $mode);
    }
}
