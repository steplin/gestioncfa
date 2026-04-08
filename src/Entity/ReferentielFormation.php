<?php

namespace App\Entity;

use App\Repository\ReferentielFormationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReferentielFormationRepository::class)]
class ReferentielFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 150)]
    private string $libelle;

    #[ORM\Column]
    private int $ordre = 0;

    #[ORM\Column]
    private bool $actif = true;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $coefReferentClasse = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $coefReferentNiveau = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $coefAccompagnement = null;

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

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;
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
    public function getCoefReferentClasse(): ?string
    {
        return $this->coefReferentClasse;
    }

    public function setCoefReferentClasse(?string $coefReferentClasse): static
    {
        $this->coefReferentClasse = $coefReferentClasse;
        return $this;
    }

    public function getCoefReferentNiveau(): ?string
    {
        return $this->coefReferentNiveau;
    }

    public function setCoefReferentNiveau(?string $coefReferentNiveau): static
    {
        $this->coefReferentNiveau = $coefReferentNiveau;
        return $this;
    }

    public function getCoefAccompagnement(): ?string
    {
        return $this->coefAccompagnement;
    }

    public function setCoefAccompagnement(?string $coefAccompagnement): static
    {
        $this->coefAccompagnement = $coefAccompagnement;
        return $this;
    }
    public function __toString(): string
    {
        return $this->libelle;
    }
}
