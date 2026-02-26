<?php

namespace App\Controller\Admin;

use App\Entity\Matiere;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MatiereCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Matiere::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Matiere')
            ->setEntityLabelInPlural('Matieres')
            ->setDefaultSort(['libelle' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Nom');
        yield TextField::new('libelle', 'Libellé');
        yield NumberField::new('coefficient', 'Coefficient');
    }


}
