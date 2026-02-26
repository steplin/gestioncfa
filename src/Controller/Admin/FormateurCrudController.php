<?php

namespace App\Controller\Admin;

use App\Entity\Formateur;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FormateurCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Formateur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Formateur')
            ->setEntityLabelInPlural('Formateurs')
            ->setDefaultSort(['nom' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom');
        yield TextField::new('prenom', 'Prenom');

        yield EmailField::new('email', 'Email');

        yield NumberField::new('quotite', 'Temps de travail')
            ->setNumDecimals(2);

        yield NumberField::new('volumeContractuel', 'Volume contractuel annuel')
            ->setNumDecimals(2)
            ->hideOnIndex();
    }
}
