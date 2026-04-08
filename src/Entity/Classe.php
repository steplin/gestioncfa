<?php

namespace App\Entity;

use App\Repository\ClasseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClasseRepository::class)]
#[ORM\Table(name: 'classe')]
#[ORM\UniqueConstraint(name: 'uniq_classe_code_session', columns: ['code', 'session_id'])]
class Classe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $abrege = null;


    #[ORM\Column(type: 'string', length: 30)]
    private ?string $type = null;
    // apprentissage | continue

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $effectifPrevisionnel = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $effectifReel = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'classes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;


    #[ORM\ManyToMany(targetEntity: Groupe::class, inversedBy: 'classes')]
    #[ORM\JoinTable(name: 'classe_groupe')]
    private Collection $groupes;


    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pfreel = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $pfprev = null;

    #[ORM\ManyToOne(targetEntity: ReferentielFormation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferentielFormation $referentielFormation = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbSemainesPresence = null;
    public function __construct()
    {
        $this->groupes = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getEffectifPrevisionnel(): ?int
    {
        return $this->effectifPrevisionnel;
    }

    public function getEffectifReel(): ?int
    {
        return $this->effectifReel;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    /* ================= SETTERS ================= */

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function setEffectifPrevisionnel(?int $effectifPrevisionnel): static
    {
        $this->effectifPrevisionnel = $effectifPrevisionnel;
        return $this;
    }

    public function setEffectifReel(?int $effectifReel): static
    {
        $this->effectifReel = $effectifReel;
        return $this;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    /* ================= RELATIONS ================= */

    public function addGroupe(Groupe $groupe): static
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes->add($groupe);
            $groupe->addClasse($this);
        }

        return $this;
    }

    public function removeGroupe(Groupe $groupe): static
    {
        if ($this->groupes->removeElement($groupe)) {
            $groupe->removeClasse($this);
        }

        return $this;
    }

    /* ================= METHODES METIER ================= */

    public function getEffectifReference(): int
    {
        return $this->effectifReel ?? $this->effectifPrevisionnel ?? 0;
    }

    public function isApprentissage(): bool
    {
        return $this->type === 'apprentissage';
    }

    public function isContinue(): bool
    {
        return $this->type === 'continue';
    }

    public function isInitiale(): bool
    {
        return $this->type === 'initiale';
    }

    public function getLibelleComplet(): string
    {
        return sprintf('%s (%s)', $this->nom, $this->code);
    }

    public function setAbrege(?string $abrege): static
    {
        $this->abrege = $abrege;
        return $this;
    }

    public function getAbrege(): ?string
    {
        return $this->abrege;
    }
    public function updatePf(iterable $seances): array
    {
        $totaux = [
            'reel' => 0.0,
            'prev' => 0.0,
        ];

        foreach ($seances as $seance) {
            $totaux['reel'] += (float)($seance->getVolumeHeuresGroupe() ?? 0);
            $totaux['prev'] += (float)($seance->getVolumeHeuresGroupePrevisionnel() ?? 0);
        }
        $this->setPfreel($totaux['reel']);
        $this->setPfprev($totaux['prev']);
        return $totaux;
    }

    public function __toString(): string
    {
        return $this->getLibelleComplet();
    }

    public function getPfreel(): ?int
    {
        return $this->pfreel;
    }

    public function setPfreel(?int $pfreel): void
    {
        $this->pfreel = $pfreel;
    }

    public function getPfprev(): ?int
    {
        return $this->pfprev;
    }

    public function setPfprev(?int $pfprev): void
    {
        $this->pfprev = $pfprev;
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
    public function getNbSemainesPresence(): ?int
    {
        return $this->nbSemainesPresence;
    }

    public function setNbSemainesPresence(?int $nbSemainesPresence): static
    {
        $this->nbSemainesPresence = $nbSemainesPresence;
        return $this;
    }
}
