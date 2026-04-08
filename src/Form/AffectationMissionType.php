<?php

namespace App\Form;

use App\Entity\AffectationMission;
use App\Entity\Formateur;
use App\Entity\Session;
use App\Entity\Classe;
use App\Entity\CategorieMission;
use App\Entity\ReferentielFormation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AffectationMissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('formateur', EntityType::class, [
                'class' => Formateur::class,
                'attr' => ['class' => 'select2'],
                'choice_label' => 'nom',
            ])
            ->add('session', EntityType::class, [
                'class' => Session::class,
                'choice_label' => 'libelle',
            ])
            ->add('categorieMission', EntityType::class, [
                'class' => CategorieMission::class,
                'attr' => ['class' => 'select2'],
                'choice_label' => 'libelle',
            ])
            ->add('classe', EntityType::class, [
                'class' => Classe::class,
                'choice_label' => 'nom',
                'attr' => ['class' => 'select2'],
                'required' => false,
                'placeholder' => '--- aucune ---',
            ])
            ->add('referentielFormation', EntityType::class, [
                'class' => ReferentielFormation::class,
                'choice_label' => 'libelle',
                'required' => false,
                'attr' => ['class' => 'select2'],
                'placeholder' => '--- aucun ---',
            ])
            ->add('valeurManuelle', null, [
                'required' => false,
                'label' => 'Valeur manuelle (heures)',
            ])
            ->add('actif')
            ->add('commentaire', null, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AffectationMission::class,
        ]);
    }
}
