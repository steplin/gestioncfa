<?php

namespace App\Form;

use App\Entity\Classe;
use App\Entity\Formateur;
use App\Entity\Groupe;
use App\Entity\Matiere;
use App\Entity\Seance;
use App\Entity\TypeActivite;
use App\Repository\ClasseRepository;
use App\Repository\GroupeRepository;
use App\Repository\TypeActiviteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SeanceMissionType extends AbstractType
{
    public function __construct(
        private ClasseRepository $classeRepository,
        private GroupeRepository $groupeRepository
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formModifier = function (FormInterface $form, ?Classe $classe) {
            $groupes = [];

            if ($classe) {
                // ✅ le plus safe : requête repo (au lieu de getGroupes() si relation lazy / M:N)
                $groupes = $this->groupeRepository->createQueryBuilder('g')
                    ->innerJoin('g.classes', 'c')     // adapte si ton mapping diffère
                    ->andWhere('c = :classe')
                    ->setParameter('classe', $classe)
                    ->orderBy('g.nom', 'ASC')
                    ->getQuery()
                    ->getResult();
            }

            $form->add('groupe', EntityType::class, [
                'class' => Groupe::class,
                'choices' => $groupes,                 // ✅ c’est ça qui évite “invalid choice”
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un groupe',
                'attr' => ['class' => 'select2'],
                'required' => false,
            ]);
        };

        $builder
            ->add('formateur', EntityType::class, [
                'class' => Formateur::class,
                'label' => 'Formateur',
                'placeholder' => 'Selectionner',
                'attr' => ['class' => 'select2'],
            ])
            ->add('classe', EntityType::class, [
                'class' => Classe::class,
                'query_builder' => fn(ClasseRepository $er) => $er->createQueryBuilder('c')
                    ->orderBy('c.nom', 'ASC'),
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner une classe',
                'attr' => ['class' => 'select2 js-classe-select'],
            ])
            // groupe sera ajouté par $formModifier
            ->add('matiere', EntityType::class, [
                'class' => Matiere::class,
                'choice_label' => 'libelle',
                'attr' => ['class' => 'select2'],
            ])
            ->add('typeActivite', EntityType::class, [
                'class' => TypeActivite::class,
                'query_builder' => fn(TypeActiviteRepository $er) => $er->createQueryBuilder('a')
                    ->where('a.code<>:code')
                    ->setParameter('code','COURS')
                    ->orderBy('a.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'attr' => ['class' => 'select2'],
            ])
            ->add('volumeHeuresFormateurPrevisionnel', NumberType::class, ['scale' => 2, 'html5' => true])
            ->add('volumeHeuresFormateur', NumberType::class, ['scale' => 2, 'html5' => true])
            ->add('volumeHeuresGroupePrevisionnel', NumberType::class, ['scale' => 2, 'html5' => true])
            ->add('volumeHeuresGroupe', NumberType::class, ['scale' => 2, 'html5' => true]);

        // ✅ En édition : le champ groupe doit être cohérent avec la classe existante
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            /** @var Seance|null $seance */
            $seance = $event->getData();
            $classe = $seance?->getClasse();
            $formModifier($event->getForm(), $classe);
        });

        // ✅ Au submit : reconstruire le champ groupe selon classe envoyée
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData(); // array

            $classeId = $data['classe'] ?? null;
            $classe = $classeId ? $this->classeRepository->find($classeId) : null;

            $formModifier($event->getForm(), $classe);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Seance::class,
        ]);
    }
}
