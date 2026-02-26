<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {

        // admin
        $formateur = new User();
        $formateur->setEmail('stephane.briere@kerplouz.com');
        $formateur->setRoles(['ROLE_ADMIN']);
        $formateur->setUsername('brieres');
        $formateur->setPrenom('Stephane');
        $formateur->setNom('BRIERE');
        $formateur->setPassword(
            $this->passwordHasher->hashPassword($formateur, 'potita')
        );
        $manager->persist($formateur);


        $manager->flush();
    }
}
