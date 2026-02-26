<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'session')]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;
    // reel | previsionnel

    #[ORM\Column(nullable: true)]
    private ?int $version = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = 'brouillon';
    // brouillon | valide | archive

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sessionsEnfants')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $sessionParente = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'sessionParente')]
    private Collection $sessionsEnfants;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\OneToMany(targetEntity: Classe::class, mappedBy: 'session')]
    private Collection $classes;

    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'session')]
    private Collection $seances;

    #[ORM\OneToMany(targetEntity: Groupe::class, mappedBy: 'session')]
    private Collection $groupes;

    public function __construct()
    {
        $this->sessionsEnfants = new ArrayCollection();
        $this->classes = new ArrayCollection();
        $this->seances = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
    }

    /* ================= GETTERS ================= */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getSessionParente(): ?self
    {
        return $this->sessionParente;
    }

    public function getSessionsEnfants(): Collection
    {
        return $this->sessionsEnfants;
    }

    public function getClasses(): Collection
    {
        return $this->classes;
    }

    public function getSeances(): Collection
    {
        return $this->seances;
    }

    /* ================= SETTERS ================= */

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function setDateFin(\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function setVersion(?int $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function setSessionParente(?self $sessionParente): static
    {
        $this->sessionParente = $sessionParente;
        return $this;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    /* ================= RELATIONS ================= */

    public function addSessionEnfant(self $session): static
    {
        if (!$this->sessionsEnfants->contains($session)) {
            $this->sessionsEnfants->add($session);
            $session->setSessionParente($this);
        }
        return $this;
    }

    public function addClasse(Classe $classe): static
    {
        if (!$this->classes->contains($classe)) {
            $this->classes->add($classe);
            $classe->setSession($this);
        }
        return $this;
    }

    public function addSeance(Seance $seance): static
    {
        if (!$this->seances->contains($seance)) {
            $this->seances->add($seance);
            $seance->setSession($this);
        }
        return $this;
    }

    /* ================= METHODES METIER ================= */

    public function getLibelle(): string
    {
        $libelle = $this->dateDebut?->format('d/m/Y') . ' - ' . $this->dateFin?->format('d/m/Y');

        if ($this->type === 'reel') {
            $libelle .= ' - Reel';
        }

        if ($this->type === 'previsionnel' && $this->version !== null) {
            $libelle .= ' - Previsionnel V' . $this->version;
        }

        return $libelle;
    }

    public function isReel(): bool
    {
        return $this->type === 'reel';
    }

    public function isPrevisionnel(): bool
    {
        return $this->type === 'previsionnel';
    }

    public function isValide(): bool
    {
        return $this->statut === 'valide';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s (%s → %s)',
            $this->nom ?? 'Session',
            $this->dateDebut?->format('d/m/Y'),
            $this->dateFin?->format('d/m/Y')
        );
    }
}
