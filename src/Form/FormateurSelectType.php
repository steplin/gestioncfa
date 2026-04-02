<?php

namespace App\Form;

use App\Entity\Formateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormateurSelectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('formateur', EntityType::class, [
            'class' => Formateur::class,
            'choice_label' => fn(Formateur $f) => $f->getNom() . ' ' . $f->getPrenom(),
            'required' => false,
            'attr' => [
                'class' => 'select2 width-100',
                'onchange' => 'this.form.submit()'
            ]
        ])
            ->add('mode', choiceType::class, [
            'choices' => ['Réel' => 'reel', 'Previsionnel' => 'prev', 'Compare' => 'both'],
            'required' => false,
            'attr' => [
                'class' => 'select2 width-100',
                'onchange' => 'this.form.submit()'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'block_name' => 'formateur'
        ]);
    }

}
