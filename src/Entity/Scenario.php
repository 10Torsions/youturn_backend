<?php

namespace App\Entity;

use App\Repository\ScenarioRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScenarioRepository::class)]
class Scenario
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?array $base_scenario = null;

    #[ORM\Column(nullable: true)]
    private ?array $current_scenario = null;

    #[ORM\OneToOne(inversedBy: 'scenario', cascade: ['persist', 'remove'])]
    private ?Activity $activity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBaseScenario(): ?array
    {
        return $this->base_scenario;
    }

    public function setBaseScenario(?array $base_scenario): static
    {
        $this->base_scenario = $base_scenario;

        return $this;
    }

    public function getCurrentScenario(): ?array
    {
        return $this->current_scenario;
    }

    public function setCurrentScenario(?array $current_scenario): static
    {
        $this->current_scenario = $current_scenario;

        return $this;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): static
    {
        $this->activity = $activity;

        return $this;
    }
}
