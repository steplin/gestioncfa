<?php

namespace App\Controller;

use App\Entity\AffectationMission;
use App\Form\AffectationMissionType;
use App\Repository\AffectationMissionRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/affectation-mission')]
class AffectationMissionController extends AbstractController
{
    #[Route('/', name: 'app_affectation_mission_index', methods: ['GET'])]
    public function index(Request $request, AffectationMissionRepository $repo, SessionRepository $sessionRepo): Response
    {
        $sessionId = $request->query->get('session');

        if ($sessionId) {
            $affectations = $repo->findBy(['session' => $sessionId], ['id' => 'DESC']);
        } else {
            $affectations = $repo->findBy([], ['id' => 'DESC']);
        }

        return $this->render('affectation_mission/index.html.twig', [
            'affectations' => $affectations,
            'sessions' => $sessionRepo->findAll(),
            'sessionId' => $sessionId,
        ]);
    }

    #[Route('/new', name: 'app_affectation_mission_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $affectation = new AffectationMission();
        $form = $this->createForm(AffectationMissionType::class, $affectation);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($affectation);
            $em->flush();

            return $this->redirectToRoute('app_affectation_mission_index');
        }

        return $this->render('affectation_mission/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_affectation_mission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, AffectationMission $affectation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AffectationMissionType::class, $affectation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->flush();

            return $this->redirectToRoute('app_affectation_mission_index');
        }

        return $this->render('affectation_mission/edit.html.twig', [
            'form' => $form,
            'affectation' => $affectation,
        ]);
    }

    #[Route('/{id}', name: 'app_affectation_mission_delete', methods: ['POST'])]
    public function delete(Request $request, AffectationMission $affectation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$affectation->getId(), $request->request->get('_token'))) {
            $em->remove($affectation);
            $em->flush();
        }

        return $this->redirectToRoute('app_affectation_mission_index');
    }
}
