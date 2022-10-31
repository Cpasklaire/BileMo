<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Phone;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
 
    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {

        // Création d'un user "normal"
        $user = new User();
        $user->setEmail("user@mail.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $user->setCreatedAt(new \DateTimeImmutable);
        $user->setCompany("client");
        $manager->persist($user);

        // Création d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@mail.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $userAdmin->setCreatedAt(new \DateTimeImmutable);
        $userAdmin->setCompany("BileMo");
        $manager->persist($userAdmin);


        for ($i=0; $i < 20; $i++) { 
            $phone = new Phone();
            $phone->setName("Nom " . $i);
            $phone->setDescription("C'est un jolie téléphone : " . $i);
            $phone->setAuthor($userAdmin);
            $phone->setPrice($i . "euro");
            $phone->setCreatedAt(new \DateTimeImmutable);
            $manager->persist($phone);
        }

        $manager->flush();
    }
}
