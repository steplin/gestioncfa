<?php

namespace App\Controller;

use App\Entity\Session;
use App\Service\YpareoImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImportYpareoController extends AbstractController
{
    #[Route('/admin/import-ypareo/{id}', name: 'admin_import_ypareo')]
    public function import(
        Session             $session,
        Request             $request,
        YpareoImportService $service
    ): Response
    {
        $report = null;
        $delete = (bool) $request->get('delete');

        if ($request->isMethod('POST')) {

            $file = $request->files->get('file');

            if ($file) {
                $path = $file->getRealPath();
                $report = $service->importFromCsv($path, $session, $delete);
            }
        }

        return $this->render('import/ypareo.html.twig', [
            'session' => $session,
            'report' => $report,
            'delete' => $delete
        ]);
    }
}
