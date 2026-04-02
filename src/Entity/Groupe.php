<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
#[ORM\Table(name: 'groupe')]
class Groupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $abrege = null;

    #[ORM\Column(type: 'string', length: 150)]
    private ?string $nom = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'groupes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    /**
     * Many Groupes <-> Many Classes
     */
    #[ORM\ManyToMany(targetEntity: Classe::class, mappedBy: 'groupes')]
    private Collection $classes;

    /**
     * 1 = normal
     * 2 = dedoublement en 2
     * 3 = dedoublement en 3
     * 0 = groupe technique (non pedagogique)
     */
    #[ORM\Column(type: 'integer')]
    private int $niveauDecoupage = 1;

    #[ORM\Column(type: 'boolean')]
    private bool $prioritaire = true;


    /**
     * Seances rattachees a ce groupe
     */
    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'groupe', orphanRemoval: true)]
    private Collection $seances;

    public function __construct()
    {
        $this->classes = new ArrayCollection();
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    /**
     * @return Collection<int, Classe>
     */
    public function getClasses(): Collection
    {
        return $this->classes;
    }

    public function getNiveauDecoupage(): int
    {
        return $this->niveauDecoupage;
    }

    /**
     * @return Collection<int, Seance>
     */
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

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function setNiveauDecoupage(int $niveauDecoupage): static
    {
        $this->niveauDecoupage = $niveauDecoupage;
        return $this;
    }

    /* ================= RELATIONS ================= */

    public function addClasse(Classe $classe): static
    {
        if (!$this->classes->contains($classe)) {
            $this->classes->add($classe);
            $classe->addGroupe($this);
        }

        return $this;
    }

    public function removeClasse(Classe $classe): static
    {
        if ($this->classes->removeElement($classe)) {
            $classe->removeGroupe($this);
        }

        return $this;
    }

    public function addSeance(Seance $seance): static
    {
        if (!$this->seances->contains($seance)) {
            $this->seances->add($seance);
            $seance->setGroupe($this);
        }

        return $this;
    }

    public function removeSeance(Seance $seance): static
    {
        if ($this->seances->removeElement($seance)) {
            if ($seance->getGroupe() === $this) {
                $seance->setGroupe(null);
            }
        }

        return $this;
    }

    /* ================= METHODES METIER ================= */

    public function getNombreClasses(): int
    {
        return $this->classes->count();
    }

    public function isRegroupement(): bool
    {
        return $this->getNombreClasses() > 1;
    }

    public function isDedoublement(): bool
    {
        return $this->getNombreClasses() === 1 && $this->niveauDecoupage > 1;
    }

    public function isTechnique(): bool
    {
        return $this->niveauDecoupage === 0;
    }
    public function determinerNiveauDecoupage(string $nom): int
    {


        // 🔹 Cas INDIV / IND / INV → niveau 0
        if (preg_match('/\b(INDIV|IND|INV)\b/', $nom)) {
            return 0;
        }

        // 🔹 Cas G1, G2, G 1, G 2...
        if (preg_match('/\bG\s*[0-9]+\b/', $nom)) {
            return 2;
        }

        // 🔹 Sinon groupe normal
        return 1;
    }

    public function getTotalFaceAFace(): float
    {
        $total = 0.0;

        foreach ($this->seances as $seance) {
            $total += $seance->getVolumeFaceAFace();
        }

        return $total;
    }

    public function getTotalTempsTravail(): float
    {
        $total = 0.0;

        foreach ($this->seances as $seance) {
            $total += $seance->getVolumeTempsTravail();
        }

        return $total;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }

    public function setClasses(Collection $classes): void
    {
        $this->classes = $classes;
    }

    public function getAbrege(): ?string
    {
        return $this->abrege;
    }

    public function setAbrege(?string $abrege): static
    {
        $this->abrege = $abrege;
        return $this;
    }

    public function isPrioritaire(): bool
    {
        return $this->prioritaire;
    }

    public function setPrioritaire(bool $prioritaire): void
    {
        $this->prioritaire = $prioritaire;
    }
}
