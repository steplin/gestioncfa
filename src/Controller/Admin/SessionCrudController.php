<?php

namespace App\Controller\Admin;

use App\Entity\Session;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SessionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Session::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Session')
            ->setEntityLabelInPlural('Sessions')
            ->setDefaultSort(['dateDebut' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('nom', 'Nom');

        yield DateField::new('dateDebut', 'Date debut');
        yield DateField::new('dateFin', 'Date fin');

        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Reel' => 'reel',
                'Previsionnel' => 'previsionnel',
            ]);

        yield IntegerField::new('version')
            ->hideOnIndex();

        yield ChoiceField::new('statut', 'Statut')
            ->setChoices([
                'Brouillon' => 'brouillon',
                'Valide' => 'valide',
                'Archive' => 'archive',
            ]);

        yield AssociationField::new('sessionParente')
            ->autocomplete()
            ->hideOnIndex();
    }
}
