<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class CoreController extends AbstractController
{
    #[Route('/core', name: 'app_home')]
    public function index(Security $security): RedirectResponse
    {
        return match (true) {
            $security->isGranted('ROLE_ADMIN') => $this->redirectToRoute('admin'),
            default => $this->redirectToRoute('app_login'),
        };

    }

}
