<?php

namespace App\Controller\Admin;

use App\Entity\TypeActivite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TypeActiviteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TypeActivite::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Type activite')
            ->setEntityLabelInPlural('Types activites');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code');
        yield TextField::new('libelle', 'Libelle');

        yield NumberField::new('coefficientDefaut', 'Coef defaut');

        yield BooleanField::new('impactFaceAFace', 'Impact FAF');
        yield BooleanField::new('impactTempsTravail', 'Impact travail');
        yield BooleanField::new('impactClasse', 'Impact classe');
        yield BooleanField::new('impactFormateur', 'Impact formateur');
        yield BooleanField::new('impactBudget', 'Impact budget');

        yield BooleanField::new('actif', 'Actif');
    }
}
