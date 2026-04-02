<?php

namespace App\Controller;

use App\Repository\ClasseRepository;
use App\Repository\SessionRepository;
use App\Service\DashboardClasseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ClasseDashboardController extends AbstractController
{
    #[Route('/classe/dashboard', name: 'classe_dashboard', methods: ['GET'])]
    public function dashboard(
        Request                $request,
        SessionRepository      $sessionRepository,
        ClasseRepository       $classeRepository,
        DashboardClasseService $dashboardClasseService
    ): Response
    {

        $mode = (string)$request->query->get('mode', 'both');
        $view = (string)$request->query->get('view', 'formateur');

        $sessionId = $request->query->get('session');
        $session = $sessionId ? $sessionRepository->find((int)$sessionId) : null;

        $classeId = $request->query->get('classe');
        $classe = $classeId ? $classeRepository->find((int)$classeId) : null;
        $prioritaireOnly = (bool)$request->query->get('prioritaire', false);

        $sessions = $sessionRepository->findBy([], [
            'dateDebut' => 'DESC',
            'type' => 'ASC',
            'version' => 'DESC',
        ]);

        if (!$classe) {
            $classe = $classeRepository->find(1);
            $session = $classe->getSession();
        }
        $data = $dashboardClasseService->build($classe, $session, $mode, $view, $prioritaireOnly);

        return $this->render('classe/dashboard.html.twig', [
            'classe' => $classe,
            'session' => $session,
            'sessions' => $sessions,
            'classes' => $classeRepository->findBy(['session' => $session], ['nom' => 'ASC']),
            'mode' => $mode,
            'view' => $view,
            'prioritaire' => $prioritaireOnly,
            'data' => $data,
        ]);
    }

}
