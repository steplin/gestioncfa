<?php

namespace App\Entity;

use App\Repository\CategorieMissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieMissionRepository::class)]
class CategorieMission
{
    public const MODE_POURCENTAGE = 'pourcentage';
    public const MODE_FORMULE = 'formule';
    public const MODE_FORFAIT = 'forfait';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $libelle;

    #[ORM\Column]
    private int $niveau = 1;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column(length: 20)]
    private string $modeCalcul = self::MODE_POURCENTAGE;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $valeurDefaut = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'enfants')]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $enfants;

    public function __construct()
    {
        $this->enfants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getNiveau(): int
    {
        return $this->niveau;
    }

    public function setNiveau(int $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function getModeCalcul(): string
    {
        return $this->modeCalcul;
    }

    public function setModeCalcul(string $modeCalcul): self
    {
        $this->modeCalcul = $modeCalcul;
        return $this;
    }

    public function getValeurDefaut(): ?string
    {
        return $this->valeurDefaut;
    }

    public function setValeurDefaut(?string $valeurDefaut): self
    {
        $this->valeurDefaut = $valeurDefaut;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function getEnfants(): Collection
    {
        return $this->enfants;
    }
    public function __toString():string
    {
        return $this->libelle;
    }
}
