<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[HasLifecycleCallbacks]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getActivity", "getActivityNameAndId", "getStands", "getAnimators", "getTeams", "getStopwatch", "getScenario"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["getActivity", "getActivityNameAndId"])]
    #[Assert\NotBlank(message: "Le champ nom est obligatoire")]
    #[Assert\Length(min: 2, max: 255, minMessage: "Le nom doit faire au moins {{ limit }} caractères", maxMessage: "Le nom ne doit pas faire plus de {{ limit }} caractères ")]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["getActivity"])]
    private ?\DateTimeImmutable $activity_date = null;

    #[ORM\Column]
    #[Groups(["getActivity"])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["getActivity"])]
    private ?int $nb_teams = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(["getActivity"])]
    private ?int $global_duration = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(["getActivity"])]
    private ?int $rotation_duration = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(["getActivity"])]
    private ?int $stand_duration = null;

    #[ORM\ManyToOne(inversedBy: 'activity')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'activity', cascade: ['persist', 'remove'])]
    private ?Stopwatch $stopwatch = null;

    #[ORM\OneToOne(mappedBy: 'activity', cascade: ['persist', 'remove'])]
    private ?Scenario $scenario = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["getActivity"])]
    private ?array $stands = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["getActivity"])]
    private ?array $teams = null;

    #[ORM\Column(length: 6)]
    #[Groups(["getActivity"])]
    private string $participantCode;

    #[ORM\Column(length: 6)]
    #[Groups(["getActivity"])]
    private string $animatorCode;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getActivityDate(): ?\DateTimeImmutable
    {
        return $this->activity_date;
    }

    public function setActivityDate(?\DateTimeImmutable $activity_date): static
    {
        $this->activity_date = $activity_date;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getNbTeams(): ?int
    {
        return $this->nb_teams;
    }

    public function setNbTeams(?int $nb_teams): static
    {
        $this->nb_teams = $nb_teams;

        return $this;
    }

    public function getGlobalDuration(): ?int
    {
        return $this->global_duration;
    }

    public function setGlobalDuration(?int $global_duration): static
    {
        $this->global_duration = $global_duration;

        return $this;
    }

    public function getRotationDuration(): ?int
    {
        return $this->rotation_duration;
    }

    public function setRotationDuration(?int $rotation_duration): static
    {
        $this->rotation_duration = $rotation_duration;

        return $this;
    }

    public function getStandDuration(): ?int
    {
        return $this->stand_duration;
    }

    public function setStandDuration(?int $stand_duration): static
    {
        $this->stand_duration = $stand_duration;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStopwatch(): ?Stopwatch
    {
        return $this->stopwatch;
    }

    public function setStopwatch(?Stopwatch $stopwatch): static
    {
        // unset the owning side of the relation if necessary
        if ($stopwatch === null && $this->stopwatch !== null) {
            $this->stopwatch->setActivity(null);
        }

        // set the owning side of the relation if necessary
        if ($stopwatch !== null && $stopwatch->getActivity() !== $this) {
            $stopwatch->setActivity($this);
        }

        $this->stopwatch = $stopwatch;

        return $this;
    }

    public function getScenario(): ?Scenario
    {
        return $this->scenario;
    }

    public function setScenario(?Scenario $scenario): static
    {
        // unset the owning side of the relation if necessary
        if ($scenario === null && $this->scenario !== null) {
            $this->scenario->setActivity(null);
        }

        // set the owning side of the relation if necessary
        if ($scenario !== null && $scenario->getActivity() !== $this) {
            $scenario->setActivity($this);
        }

        $this->scenario = $scenario;

        return $this;
    }

    public function getStands(): ?array
    {
        return $this->stands;
    }

    public function setStands(?array $stands): static
    {
        $this->stands = $stands;

        return $this;
    }

    public function getTeams(): ?array
    {
        return $this->teams;
    }

    public function setTeams(?array $teams): static
    {
        $this->teams = $teams;

        return $this;
    }

    public function getParticipantCode(): ?string
    {
        return $this->participantCode;
    }

    public function setParticipantCode(string $participantCode): self
    {
        $this->participantCode = $participantCode;
        return $this;
    }

    public function getAnimatorCode(): ?string
    {
        return $this->animatorCode;
    }

    public function setAnimatorCode(string $animatorCode): self
    {
        $this->animatorCode = $animatorCode;
        return $this;
    }
}
