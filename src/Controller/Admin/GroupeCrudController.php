<?php

namespace App\Controller\Admin;

use App\Entity\Groupe;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GroupeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Groupe::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Groupe')
            ->setEntityLabelInPlural('Groupes')
            ->setDefaultSort(['code' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code');
        yield TextField::new('abrege', 'Abrégé');
        yield TextField::new('nom', 'Nom');

        yield AssociationField::new('session')
            ->autocomplete();

        yield AssociationField::new('classes')
            ->setFormTypeOption('by_reference', false)
            ->autocomplete();
        yield BooleanField::new('prioritaire', 'Prioritaire');
        yield IntegerField::new('niveauDecoupage', 'Niveau decoupage')
            ->setHelp('1 = normal | 2 ou 3 = dedoublement | 0 = technique');
    }
}
