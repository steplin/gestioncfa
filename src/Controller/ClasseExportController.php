<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Session;
use App\Repository\ClasseRepository;
use App\Service\DashboardClasseService;
use App\Service\Export\ClasseExcelExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClasseExportController extends AbstractController
{
    #[Route('/export/classe/{classe}/{session}', name: 'export_classe_excel')]
    public function export(
        Request $request,
        Classe $classe,
        Session $session,
        DashboardClasseService $dashboardService,
        ClasseExcelExportService $excelService
    ): Response {
        $mode = (string) $request->query->get('mode', 'both');
        $view = (string) $request->query->get('view', 'formateur');
        $prioritaireOnly = (bool) $request->query->get('prioritaire', false);

        $data = $dashboardService->buildExportData(
            $classe,
            $session,
            $mode,
            $view,
            $prioritaireOnly
        );

        return $excelService->export($classe, $session, $data, $mode);
    }
    #[Route('/export/classes/session/{id}', name: 'export_classes_session_excel')]
    public function exportClassesSession(
        Request $request,
        Session $session,
        ClasseRepository $classeRepository,
        DashboardClasseService $dashboardService,
        ClasseExcelExportService $excelService
    ): Response {
        $mode = (string) $request->query->get('mode', 'both');
        $view = (string) $request->query->get('view', 'formateur');
        $prioritaireOnly = (bool) $request->query->get('prioritaire', false);

        $classes = $classeRepository->findBy(
            ['session' => $session],
            ['nom' => 'ASC']
        );

        $items = [];

        foreach ($classes as $classe) {
            $data = $dashboardService->buildExportData(
                $classe,
                $session,
                $mode,
                $view,
                $prioritaireOnly
            );

            $items[] = [
                'classe' => $classe,
                'data' => $data,
            ];
        }

        return $excelService->exportClasseurClasses(
            $session,
            $items,
            $mode
        );
    }
}
