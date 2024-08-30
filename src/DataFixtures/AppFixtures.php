<?php

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Animator;
use App\Entity\Stand;
use App\Entity\Team;
use App\Entity\User;
use App\Service\CodeGeneratorService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;
    private $codeGenerator;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, CodeGeneratorService $codeGenerator)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->codeGenerator = $codeGenerator;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createUsers($manager);
    }

    public function createUsers(ObjectManager $manager): void
    {
        // Standard User creation 
        $user = new User();
        $user->setLogin("user")
            ->setPassword($this->userPasswordHasher->hashPassword($user, "user"));
        $manager->persist($user);

        //  Admin User creation 
        $userAdmin = new User();
        $userAdmin->setLogin("admin")
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "admin"));
        $manager->persist($userAdmin);

        // Gamemaster User creation 
        $userGamemaster = new User();
        $userGamemaster->setLogin("gamemaster")
            ->setRoles(['ROLE_GAMEMASTER'])
            ->setPassword($this->userPasswordHasher->hashPassword($userGamemaster, "gamemaster"));
        $manager->persist($userGamemaster);

        $manager->flush();

        // Create Animators
        $this->createAnimators($manager, $userGamemaster);
    }

    public function createAnimators(ObjectManager $manager, User $user): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $animator = new Animator();
            $animator->setUser($user)
                ->setName("Animateur $i");
            $manager->persist($animator);
        }
        $manager->flush();
        $this->createTeams($manager, $user);
    }

    public function createTeams(ObjectManager $manager, User $user): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $team = new Team();
            $team->setUser($user)
                ->setName("Team $i");
            $manager->persist($team);
        }
        $manager->flush();
        $this->createActivities($manager, $user);
    }

    public function createActivities(ObjectManager $manager, User $user): void
    {
        // Générer les codes pour les participants et les animateurs
        $participantCode = $this->codeGenerator->generateUniqueCode('participantCode');
        $animatorCode = $this->codeGenerator->generateUniqueCode('animatorCode');

        for ($i = 0; $i <= 10; $i++) {
            $activity = new Activity();
            $activity->setUser($user)
                ->setName("Activité $i")
                ->setParticipantCode($participantCode)
                ->setAnimatorCode($animatorCode);
            $manager->persist($activity);
        }
        $manager->flush();
        $this->createStands($manager, $user, $activity);
    }

    public function createStands(ObjectManager $manager): void
    {
        for ($i = 0; $i <= 10; $i++) {
            $stand = new Stand();
            $stand->setName("Stand $i");
            $manager->persist($stand);
        }
        $manager->flush();
    }
}
