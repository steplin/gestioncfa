<?php

namespace App\Form;

use App\Entity\Session;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ProjectionImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sourceSession', EntityType::class, [
                'class' => Session::class,
                'choice_label' => static fn (Session $session) => (string) $session,
                'label' => 'Session source des missions',
                'placeholder' => 'Choisir la session précédente',
                'constraints' => [new NotBlank()],
            ])
            ->add('targetSession', EntityType::class, [
                'class' => Session::class,
                'choice_label' => static fn (Session $session) => (string) $session,
                'label' => 'Session cible projection',
                'placeholder' => 'Choisir la projection à importer',
                'constraints' => [new NotBlank()],
            ])
            ->add('file', FileType::class, [
                'label' => 'Fichier Excel toutes classes',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                    new File([
                        'mimeTypes' => [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'application/octet-stream',
                        ],
                        'mimeTypesMessage' => 'Merci de déposer un fichier Excel valide.',
                    ]),
                ],
            ])
            ->add('dryRun', CheckboxType::class, [
                'label' => 'Simulation uniquement',
                'required' => false,
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
