<?php

namespace App\Controller;

use App\Form\ProjectionImportType;
use App\Service\ProjectionImport\ProjectionExcelImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ProjectionImportController extends AbstractController
{
    #[Route('/projection/import', name: 'projection_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        ProjectionExcelImportService $projectionExcelImportService,
    ): Response {
        $form = $this->createForm(ProjectionImportType::class);
        $form->handleRequest($request);

        $report = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $sourceSession = $form->get('sourceSession')->getData();
            $targetSession = $form->get('targetSession')->getData();
            $file = $form->get('file')->getData();
            $dryRun = (bool) $form->get('dryRun')->getData();

            $report = $projectionExcelImportService->import(
                sourceSession: $sourceSession,
                targetSession: $targetSession,
                file: $file,
                dryRun: $dryRun,
            );

            if ($report->hasErrors()) {
                $this->addFlash('danger', 'Import impossible : des erreurs doivent être corrigées.');
            } elseif ($dryRun) {
                $this->addFlash('info', 'Simulation terminée. Aucune donnée n’a été modifiée.');
            } else {
                $this->addFlash('success', 'Import terminé avec succès.');
            }
        }

        return $this->render('projection_import/import.html.twig', [
            'form' => $form->createView(),
            'report' => $report,
        ]);
    }
}
