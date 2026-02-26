<?php

namespace App\Entity;

use App\Repository\FormateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormateurRepository::class)]
#[ORM\Table(name: 'formateur')]
class Formateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;
    // CODE_PERSONNEL YPareo

    #[ORM\Column(length: 80)]
    private ?string $nom = null;

    #[ORM\Column(length: 80)]
    private ?string $prenom = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $tauxHoraire = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?string $quotite = null;
    // 1.00 = temps plein, 0.80 = 80%

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $typeContrat = null;
    // CDI | CDD | Vacataire

    #[ORM\Column]
    private bool $actif = true;

    /**
     * @var Collection<int, Seance>
     */
    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'formateur')]
    private Collection $seances;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $volumeContractuel = null;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTauxHoraire(): ?string
    {
        return $this->tauxHoraire;
    }

    public function getQuotite(): ?string
    {
        return $this->quotite;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function isActif(): bool
    {
        return $this->actif;
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

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function setTauxHoraire(?string $tauxHoraire): static
    {
        $this->tauxHoraire = $tauxHoraire;
        return $this;
    }

      public function setQuotite(?string $quotite): static
    {
        $this->quotite = $quotite;
        return $this;
    }

    public function setTypeContrat(?string $typeContrat): static
    {
        $this->typeContrat = $typeContrat;
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
            $seance->setFormateur($this);
        }

        return $this;
    }

    public function removeSeance(Seance $seance): static
    {
        if ($this->seances->removeElement($seance)) {
            if ($seance->getFormateur() === $this) {
                $seance->setFormateur(null);
            }
        }

        return $this;
    }

    /* ================= MÉTHODES MÉTIER ================= */

    public function getNomComplet(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }


    public function getVolumeContractuel(): ?string
    {
        return $this->volumeContractuel;
    }

    public function setVolumeContractuel(?string $volumeContractuel): static
    {
        $this->volumeContractuel = $volumeContractuel;
        return $this;
    }
    public function __toString(): string
    {
        return $this->getNomComplet();
    }

}
