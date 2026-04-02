<?php

namespace App\Controller;

use App\Entity\Session;
use App\Repository\ClasseRepository;
use App\Repository\SeanceRepository;
use App\Repository\SessionRepository;
use App\Service\Export\RecapHeuresFormateursExcelExportService;
use App\Service\RecapHeuresClasseFormateurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecapHeuresController extends AbstractController
{
    #[Route('/recap-heures', name: 'recap_heures')]
    public function index(
        Request                           $request,
        SessionRepository                 $sessionRepository,
        RecapHeuresClasseFormateurService $service,
        ClasseRepository                  $classeRepository,
        SeanceRepository                  $seanceRepository,
        EntityManagerInterface            $em
    ): Response
    {

        $sessionId = $request->query->get('session', 1);
        $mode = $request->query->get('mode', 'reel');

        $session = $sessionId
            ? $sessionRepository->find($sessionId)
            : $sessionRepository->findOneBy([], ['id' => 'DESC']);

        if (!$session) {
            throw $this->createNotFoundException('Session introuvable');
        }

        $data = $service->build($session, $mode);
        $classes = $classeRepository->findBy(['session' => $session], ['nom' => 'ASC']);

        foreach ($classes as $classe) {
            $seances = $seanceRepository->prioritaire($session, $classe, true);
            $classe->updatePf($seances);
        }
        $em->flush();
        return $this->render('recap_heures/index.html.twig', [
            'session' => $session,
            'data' => $data,
            'mode' => $mode
        ]);
    }
    #[Route('/recap-heures/excel/session/{session}', name: 'recap_heures_excel')]
    public function excel(
        Request $request,
        Session $session,
        RecapHeuresClasseFormateurService $service,
        RecapHeuresFormateursExcelExportService $excel
    ) {
        $mode = $request->query->get('mode', 'reel');
        $data = $service->build($session, $mode);

        return $excel->export($session, $data, $mode);
    }
}
