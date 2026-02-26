<?php

namespace App\Controller\Admin;

use App\Entity\Seance;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class SeanceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Seance::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Seance')
            ->setEntityLabelInPlural('Séances')
            ->setDefaultSort(['id' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('matiere')->autocomplete();
        yield NumberField::new('volumeHeuresFormateurPrevisionnel', 'Volume heures formateur prévisionnel')->onlyOnForms();
        yield NumberField::new('volumeHeuresFormateur', 'Volume heures formateur');
        yield NumberField::new('volumeHeuresGroupePrevisionnel', 'Volume heures groupe previsionnel')->onlyOnForms();
        yield NumberField::new('volumeHeuresGroupe', 'Volume heures groupe')->onlyOnForms();


        yield AssociationField::new('session')
            ->autocomplete();

        yield AssociationField::new('formateur')
            ->autocomplete();
        yield AssociationField::new('classe')
            ->autocomplete();

        yield AssociationField::new('groupe')
            ->autocomplete();

        yield AssociationField::new('typeActivite')
            ->autocomplete();


    }
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('session'))
            ->add(EntityFilter::new('formateur'))
            ->add(EntityFilter::new('classe'))
            ->add(EntityFilter::new('groupe'))
            ->add(EntityFilter::new('typeActivite'));
    }
}
