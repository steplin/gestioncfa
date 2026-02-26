<?php

namespace App\Entity;

use App\Repository\TypeActiviteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeActiviteRepository::class)]
#[ORM\Table(name: 'type_activite')]
class TypeActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private ?string $code = null;
    // COURS | DEMI_CLASSE | MISSION | ARRET | STAGE_COURT...

    #[ORM\Column(type: 'string', length: 120)]
    private ?string $libelle = null;

    #[ORM\Column(name: 'coefficient_defaut', type: 'decimal', precision: 5, scale: 2)]
    private ?string $coefficientDefaut = '1.00';

    #[ORM\Column(name: 'impact_face_a_face', type: 'boolean')]
    private bool $impactFaceAFace = true;

    #[ORM\Column(name: 'impact_temps_travail', type: 'boolean')]
    private bool $impactTempsTravail = true;

    #[ORM\Column(name: 'impact_classe', type: 'boolean')]
    private bool $impactClasse = true;

    #[ORM\Column(name: 'impact_formateur', type: 'boolean')]
    private bool $impactFormateur = true;

    #[ORM\Column(name: 'impact_budget', type: 'boolean')]
    private bool $impactBudget = true;

    #[ORM\Column(type: 'boolean')]
    private bool $actif = true;

    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'typeActivite')]
    private Collection $seances;

    public function __construct()
    {
        $this->seances = new ArrayCollection();
    }

    /* ================= GETTERS ================= */

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

    public function getCoefficientDefaut(): ?string
    {
        return $this->coefficientDefaut;
    }

    public function isImpactFaceAFace(): bool
    {
        return $this->impactFaceAFace;
    }

    public function isImpactTempsTravail(): bool
    {
        return $this->impactTempsTravail;
    }

    public function isImpactClasse(): bool
    {
        return $this->impactClasse;
    }

    public function isImpactFormateur(): bool
    {
        return $this->impactFormateur;
    }

    public function isImpactBudget(): bool
    {
        return $this->impactBudget;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function getSeances(): Collection
    {
        return $this->seances;
    }

    /* ================= SETTERS ================= */

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

    public function setCoefficientDefaut(string $coefficientDefaut): static
    {
        $this->coefficientDefaut = $coefficientDefaut;
        return $this;
    }

    public function setImpactFaceAFace(bool $impactFaceAFace): static
    {
        $this->impactFaceAFace = $impactFaceAFace;
        return $this;
    }

    public function setImpactTempsTravail(bool $impactTempsTravail): static
    {
        $this->impactTempsTravail = $impactTempsTravail;
        return $this;
    }

    public function setImpactClasse(bool $impactClasse): static
    {
        $this->impactClasse = $impactClasse;
        return $this;
    }

    public function setImpactFormateur(bool $impactFormateur): static
    {
        $this->impactFormateur = $impactFormateur;
        return $this;
    }

    public function setImpactBudget(bool $impactBudget): static
    {
        $this->impactBudget = $impactBudget;
        return $this;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    /* ================= RELATIONS ================= */

    public function addSeance(Seance $seance): static
    {
        if (!$this->seances->contains($seance)) {
            $this->seances->add($seance);
            $seance->setTypeActivite($this);
        }

        return $this;
    }

    public function removeSeance(Seance $seance): static
    {
        if ($this->seances->removeElement($seance)) {
            if ($seance->getTypeActivite() === $this) {
                $seance->setTypeActivite(null);
            }
        }

        return $this;
    }

    /* ================= METHODES METIER ================= */

    public function getDescriptionImpact(): string
    {
        return sprintf(
            'FAF: %s | Travail: %s | Classe: %s | Formateur: %s | Budget: %s',
            $this->impactFaceAFace ? 'Oui' : 'Non',
            $this->impactTempsTravail ? 'Oui' : 'Non',
            $this->impactClasse ? 'Oui' : 'Non',
            $this->impactFormateur ? 'Oui' : 'Non',
            $this->impactBudget ? 'Oui' : 'Non'
        );
    }
    public function __toString()
    {
        return $this->code;
    }
}
