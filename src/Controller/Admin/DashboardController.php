<?php

namespace App\Controller\Admin;

use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Matiere;
use App\Entity\Seance;
use App\Entity\Session;
use App\Entity\TypeActivite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class DashboardController extends AbstractDashboardController
{
    #[Route(path: '/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Gestion – CFA');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Pédagogie');

        yield MenuItem::linkToCrud('Sessions', 'fa fa-calendar', Session::class);
        yield MenuItem::linkToCrud('Classes', 'fa fa-users', Classe::class);
        yield MenuItem::linkToCrud('Groupes', 'fa fa-users', Groupe::class);
        yield MenuItem::linkToCrud('Formateurs', 'fa fa-chalkboard-teacher', Formateur::class);
        yield MenuItem::linkToCrud('Types d\'activité', 'fa fa-tags', TypeActivite::class);
        yield MenuItem::linkToCrud('Matieres', 'fa fa-clock', Matiere::class);
        yield MenuItem::linkToCrud('Seances', 'fa fa-clock', Seance::class);

        yield MenuItem::section('Outils');

        yield MenuItem::linkToRoute('Import YPareo', 'fa fa-upload', 'app_import_ypareo');

        yield MenuItem::section('Paramètres');

        yield MenuItem::linkToUrl('Retour site', 'fa fa-arrow-left', '/');
    }
}
