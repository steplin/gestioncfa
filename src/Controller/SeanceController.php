<?php

namespace App\Controller;

use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Seance;
use App\Entity\Session;
use App\Form\SeanceMissionType;
use App\Form\SeanceNewType;
use App\Form\SeanceType;
use App\Repository\TypeActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SeanceController extends AbstractController
{
    #[Route('/seance/{id}/edit', name: 'seance_edit')]
    public function editModal(
        Seance $seance,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        $form = $this->createForm(SeanceType::class, $seance,[
            'groupes' => $seance->getClasse()->getGroupes()->toArray(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('dashboard_formateur', [
                'session' => $seance->getSession()->getId(),
                'formateur' => $seance->getFormateur()->getId(),
            ]);
        }

        return $this->render('seance/edit.html.twig', [
            'form' => $form->createView(),
            'seance' => $seance,

        ]);
    }
    #[Route('/seance/{id}/delete', name: 'seance_delete', methods: ['POST'])]
    public function delete(
        Seance $seance,
        Request $request,
        EntityManagerInterface $em
    ): Response {

        if ($request->isXmlHttpRequest()) {

            $em->remove($seance);
            $em->flush();

            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('dashboard_formateur');
    }
    #[Route('/seance/new/{formateur}/{session}/{classe}/{groupe}', name: 'seance_new')]
    public function new(
        Formateur $formateur,
        Session $session,
        Groupe $groupe,
        Classe $classe,
        Request $request,
        EntityManagerInterface $em,
        TypeActiviteRepository $typeActiviteRepository
    ): Response {

        $seance = new Seance();
        $seance->setFormateur($formateur);
        $seance->setSession($session);
        $seance->setGroupe($groupe);
        $seance->setClasse($classe);
        $seance->setTypeActivite($typeActiviteRepository->findOneBy(['code' => 'COURS']));

        $form = $this->createForm(SeanceNewType::class, $seance);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($seance);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('dashboard_formateur', [
                'session' => $session->getId(),
                'formateur' => $formateur->getId(),
            ]);
        }

        return $this->render('seance/new.html.twig', [
            'form' => $form->createView(),
            'seance' => $seance,
            'formateur' => $formateur,
            'session' => $session,
            'groupe' => $groupe,
            'classe' => $classe,
        ]);
    }
    #[Route('/seance/mission/new/{formateur}/{session}', name: 'seance_new_mission')]
    public function newMission(
        Formateur $formateur,
        Session $session,
        Request $request,
        EntityManagerInterface $em,
        TypeActiviteRepository $typeActiviteRepository
    ): Response {

        $seance = new Seance();
        $seance->setFormateur($formateur);
        $seance->setSession($session);
        $form = $this->createForm(SeanceMissionType::class, $seance);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($seance);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            return $this->redirectToRoute('dashboard_formateur', [
                'session' => $session->getId(),
                'formateur' => $formateur->getId(),
            ]);
        }

        return $this->render('seance/newMission.html.twig', [
            'form' => $form->createView(),
            'seance' => $seance,
            'formateur' => $formateur,
            'session' => $session,
        ]);
    }
    #[Route('/ajax/classe/{id}/groupes', name: 'ajax_classe_groupes', methods: ['GET'])]
    public function groupes(Classe $classe): Response
    {
        $out = [];
        foreach ($classe->getGroupes() as $g) {
            $out[] = [
                'id' => $g->getId(),
                'nom' => $g->getNom(),
            ];
        }

        return $this->json($out);
    }
}
