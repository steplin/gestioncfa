<?php

namespace App\Form;

use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Matiere;
use App\Entity\Seance;
use App\Entity\TypeActivite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $groupes= $options['groupes'];
        $builder
            ->add('formateur', EntityType::class, [
                'class' => Formateur::class,
                'label' => 'Formateur',
                'placeholder' => 'Selectionner',
                'attr' => ['class' => 'select2'],
            ])
            ->add('groupe', EntityType::class, [
                'class' => Groupe::class,
                'choices' => $groupes,
                'choice_label' => 'nom',
                'attr' => ['class' => 'select2'],
            ])
            ->add('matiere', EntityType::class, [
                'class' => Matiere::class,
                'choice_label' => 'libelle',
                'attr' => ['class' => 'select2'],
            ])
            ->add('typeActivite', EntityType::class, [
                'class' => TypeActivite::class,
                'choice_label' => 'libelle',
                'attr' => ['class' => 'select2'],
            ])
            ->add('volumeHeuresFormateurPrevisionnel', NumberType::class, [
                'scale' => 2,
                'html5' => true,
            ])
            ->add('volumeHeuresFormateur', NumberType::class, [
                'scale' => 2,
                'html5' => true,
            ])
            ->add('volumeHeuresGroupePrevisionnel', NumberType::class, [
                'scale' => 2,
                'html5' => true,
            ])
            ->add('volumeHeuresGroupe', NumberType::class, [
                'scale' => 2,
                'html5' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Seance::class,
            'groupes' => [],
        ]);
    }
}
