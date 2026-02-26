<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'matiere')]
#[ORM\UniqueConstraint(name: 'uniq_matiere_code', columns: ['code'])]
class Matiere
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'matiere')]
    private Collection $seances;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $coefficient = '2.00';

    public function __construct()
    {
        $this->seances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getSeances(): Collection
    {
        return $this->seances;
    }

    public function __toString(): string
    {
        return $this->libelle;
    }

    public function getCoefficient(): ?string
    {
        return $this->coefficient;
    }

    public function setCoefficient(?string $coefficient): void
    {
        $this->coefficient = $coefficient;
    }
}
