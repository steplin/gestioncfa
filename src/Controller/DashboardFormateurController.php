<?php

namespace App\Controller;

use App\Entity\Formateur;
use App\Entity\Session;
use App\Form\FormateurSelectType;
use App\Service\DashboardFormateurService;
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
        Request                   $request,
        EntityManagerInterface $em,
    ): Response
    {
        $formateurId = $request->query->getInt('formateur');
        $formateur = $em->getRepository(Formateur::class)->find($formateurId);

        $form = $this->createForm(FormateurSelectType::class, [
            'formateur' => $formateur
        ], [
            'method' => 'GET'
        ]);


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $formateur = $form->get('formateur')->getData();
            return $this->redirectToRoute('dashboard_formateur', [
                'formateur' => $formateur->getId(),
                'session' => $session->getId(),
            ]);
        }

        $data = $service->build($formateur, $session);

        return $this->render('dashboard/formateur.html.twig', [
            'formateur' => $formateur,
            'session' => $session,
            'data' => $data,
            'form' => $form->createView(),

        ]);
    }
}
