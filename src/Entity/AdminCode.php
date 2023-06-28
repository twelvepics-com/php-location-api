<?php

/*
 * This file is part of the twelvepics-com/php-location-api project.
 *
 * (c) Björn Hempel <https://www.hempel.li/>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampsTrait;
use App\Repository\AdminCodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Class AdminCode
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-06-27)
 * @since 0.1.0 (2023-06-27) First version.
 */
#[ORM\Entity(repositoryClass: AdminCodeRepository::class)]
#[UniqueEntity(
    fields: ['admin1Code', 'admin2Code', 'admin3Code', 'admin4Code', 'country'],
    message: 'The admin code combination is already used with this country.',
    errorPath: 'country',
    ignoreNull: ['admin1Code', 'admin2Code', 'admin3Code', 'admin4Code']
)]
#[ORM\UniqueConstraint(columns: ['admin1_code', 'admin2_code', 'admin3_code', 'admin4_code', 'country_id'])]
#[ORM\Index(columns: ['country_id'])]
#[ORM\HasLifecycleCallbacks]
class AdminCode
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $admin1Code = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $admin2Code = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $admin3Code = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $admin4Code = null;

    /** @var Collection<int, Location> $locations */
    #[ORM\OneToMany(mappedBy: 'adminCode', targetEntity: Location::class, orphanRemoval: true)]
    private Collection $locations;

    #[ORM\ManyToOne(inversedBy: 'adminCodes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $country = null;

    /**
     *
     */
    public function __construct()
    {
        $this->locations = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getAdmin1Code(): ?string
    {
        return $this->admin1Code;
    }

    /**
     * @param string|null $admin1Code
     * @return $this
     */
    public function setAdmin1Code(?string $admin1Code): static
    {
        $this->admin1Code = $admin1Code;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdmin2Code(): ?string
    {
        return $this->admin2Code;
    }

    /**
     * @param string|null $admin2Code
     * @return $this
     */
    public function setAdmin2Code(?string $admin2Code): static
    {
        $this->admin2Code = $admin2Code;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdmin3Code(): ?string
    {
        return $this->admin3Code;
    }

    /**
     * @param string|null $admin3Code
     * @return $this
     */
    public function setAdmin3Code(?string $admin3Code): static
    {
        $this->admin3Code = $admin3Code;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdmin4Code(): ?string
    {
        return $this->admin4Code;
    }

    /**
     * @param string|null $admin4Code
     * @return $this
     */
    public function setAdmin4Code(?string $admin4Code): static
    {
        $this->admin4Code = $admin4Code;

        return $this;
    }

    /**
     * @return Collection<int, Location>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    /**
     * @param Location $location
     * @return $this
     */
    public function addLocation(Location $location): static
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
            $location->setAdminCode($this);
        }

        return $this;
    }

    /**
     * @param Location $location
     * @return $this
     */
    public function removeLocation(Location $location): static
    {
        if ($this->locations->removeElement($location)) {
            /* Set the owning side to null (unless already changed). */
            if ($location->getAdminCode() === $this) {
                $location->setAdminCode(null);
            }
        }

        return $this;
    }

    /**
     * @return Country|null
     */
    public function getCountry(): ?Country
    {
        return $this->country;
    }

    /**
     * @param Country|null $country
     * @return $this
     */
    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }
}
