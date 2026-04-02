<?php

namespace App\Controller\Admin;

use App\Entity\Classe;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ClasseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Classe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Classe')
            ->setEntityLabelInPlural('Classes');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code');
        yield TextField::new('abrege', 'Abrégé');
        yield TextField::new('nom', 'Nom');
        yield TextField::new('type', 'Type');

        yield AssociationField::new('session')
            ->autocomplete();

        yield IntegerField::new('effectifPrevisionnel', 'Effectif prev');
        yield IntegerField::new('effectifReel', 'Effectif reel');
    }
}
