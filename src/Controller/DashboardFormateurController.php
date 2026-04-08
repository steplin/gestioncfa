<?php

namespace App\Controller;

use App\Entity\Formateur;
use App\Entity\Session;
use App\Form\FormateurSelectType;
use App\Service\DashboardFormateurService;
use App\Service\MissionCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardFormateurController extends AbstractController
{
    #[Route('/dashboard/session/{session}', name: 'dashboard_formateur')]
    public function index(
        Session                   $session,
        DashboardFormateurService $service,
        MissionCalculatorService  $calculator,
        Request                   $request,
        EntityManagerInterface    $em,
    ): Response
    {

        $mode = $request->query->get('mode', 'reel');
        $formateurId = $request->query->getInt('formateur');
        $formateur = $em->getRepository(Formateur::class)->find($formateurId);

        $form = $this->createForm(FormateurSelectType::class, [
            'formateur' => $formateur,
            'mode' => $mode,
        ], [
            'method' => 'GET'
        ]);


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formateur = $form->get('formateur')->getData();
            $mode = $form->get('mode')->getData();
            return $this->redirectToRoute('dashboard_formateur', [
                'formateur' => $formateur->getId(),
                'mode' => $mode,
                'session' => $session->getId(),
            ]);
        }

        $data = $service->build($formateur, $session);
        dd($calculator->calculate($formateur,$session));
        return $this->render('dashboard/formateur.html.twig', [
            'formateur' => $formateur,
            'session' => $session,
            'data' => $data,
            'mode' => $mode,
            'form' => $form->createView(),

        ]);
    }
}
