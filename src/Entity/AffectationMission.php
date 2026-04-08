<?php

namespace App\Entity;

use App\Repository\AffectationMissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AffectationMissionRepository::class)]
class AffectationMission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formateur $formateur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CategorieMission $categorieMission = null;

    #[ORM\ManyToOne]
    private ?Classe $classe = null;

    #[ORM\ManyToOne]
    private ?ReferentielFormation $referentielFormation = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $valeurManuelle = null;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormateur(): ?Formateur
    {
        return $this->formateur;
    }

    public function setFormateur(?Formateur $formateur): static
    {
        $this->formateur = $formateur;
        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getCategorieMission(): ?CategorieMission
    {
        return $this->categorieMission;
    }

    public function setCategorieMission(?CategorieMission $categorieMission): static
    {
        $this->categorieMission = $categorieMission;
        return $this;
    }

    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function setClasse(?Classe $classe): static
    {
        $this->classe = $classe;
        return $this;
    }

    public function getReferentielFormation(): ?ReferentielFormation
    {
        return $this->referentielFormation;
    }

    public function setReferentielFormation(?ReferentielFormation $referentielFormation): static
    {
        $this->referentielFormation = $referentielFormation;
        return $this;
    }

    public function getValeurManuelle(): ?string
    {
        return $this->valeurManuelle;
    }

    public function setValeurManuelle(?string $valeurManuelle): static
    {
        $this->valeurManuelle = $valeurManuelle;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

}
