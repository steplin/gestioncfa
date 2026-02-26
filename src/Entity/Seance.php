<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'seance')]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private ?string $volumeHeuresFormateur = null;
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private ?string $volumeHeuresFormateurPrevisionnel = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private ?string $volumeHeuresGroupe = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private ?string $volumeHeuresGroupePrevisionnel = null;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formateur $formateur = null;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Groupe $groupe = null;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classe $classe = null;
    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Matiere $matiere = null;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeActivite $typeActivite = null;


    /* ================= GETTERS ================= */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVolumeHeuresFormateur(): ?string
    {
        return $this->volumeHeuresFormateur;
    }

    public function getVolumeHeuresGroupe(): ?string
    {
        return $this->volumeHeuresGroupe;
    }


    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function getFormateur(): ?Formateur
    {
        return $this->formateur;
    }

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }
    public function getClasse(): ?Classe
    {
        return $this->classe;
    }

    public function getMatiere(): ?Matiere
    {
        return $this->matiere;
    }

    public function getTypeActivite(): ?TypeActivite
    {
        return $this->typeActivite;
    }

    /* ================= SETTERS ================= */

    public function setVolumeHeuresFormateur(?string $volumeHeures): static
    {
        $this->volumeHeuresFormateur = $volumeHeures;
        return $this;
    }

    public function setVolumeHeuresGroupe(?string $volumeHeures): static
    {
        $this->volumeHeuresGroupe = $volumeHeures;
        return $this;
    }


    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function setFormateur(?Formateur $formateur): static
    {
        $this->formateur = $formateur;
        return $this;
    }

    public function setGroupe(?Groupe $groupe): static
    {
        $this->groupe = $groupe;
        return $this;
    }

    public function setClasse(?Classe $classe): static
    {
        $this->classe = $classe;
        return $this;
    }


    public function setMatiere(?Matiere $matiere): static
    {
        $this->matiere = $matiere;
        return $this;
    }

    public function setTypeActivite(?TypeActivite $typeActivite): static
    {
        $this->typeActivite = $typeActivite;
        return $this;
    }

    public function __toString(): string
    {
        return $this->matiere->getLibelle();
    }

    /* ================= MÉTIER ================= */

    public function getVolumePondereFormateur(): float
    {
        return (float)$this->volumeHeuresFormateur * (float)$this->typeActivite->getCoefficientDefaut();
    }

    public function getVolumePondereGroupe(): float
    {
        return (float)$this->volumeHeuresGroupe * (float)$this->typeActivite->getCoefficientDefaut();
    }

    public function getVolumeHeuresFormateurPrevisionnel(): ?string
    {
        return $this->volumeHeuresFormateurPrevisionnel;
    }

    public function setVolumeHeuresFormateurPrevisionnel(?string $volumeHeuresFormateurPrevisionnel): static
    {
        $this->volumeHeuresFormateurPrevisionnel = $volumeHeuresFormateurPrevisionnel;
        return $this;
    }

    public function getVolumeHeuresGroupePrevisionnel(): ?string
    {
        return $this->volumeHeuresGroupePrevisionnel;
    }

    public function setVolumeHeuresGroupePrevisionnel(?string $volumeHeuresGroupePrevisionnel): static
    {
        $this->volumeHeuresGroupePrevisionnel = $volumeHeuresGroupePrevisionnel;
        return $this;
    }

}
