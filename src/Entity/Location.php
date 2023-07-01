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

use App\DBAL\GeoLocation\ValueObject\Point;
use App\Entity\Trait\TimestampsTrait;
use App\Repository\LocationRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Location
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-06-27)
 * @since 0.1.0 (2023-06-27) First version.
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
#[ORM\Entity(repositoryClass: LocationRepository::class)]
#[ORM\Index(columns: ['coordinate'], flags: ['gist'])]
#[ORM\HasLifecycleCallbacks]
class Location
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $geonameId = null;

    #[ORM\Column(length: 1024)]
    private ?string $name = null;

    #[ORM\Column(length: 1024)]
    private ?string $asciiName = null;

    #[ORM\Column(length: 4096)]
    private ?string $alternateNames = null;

    #[ORM\Column(type: 'point')]
    private ?Point $coordinate = null;

    #[ORM\Column(length: 200)]
    private ?string $cc2 = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $population = null;

    #[ORM\Column(nullable: true)]
    private ?int $elevation = null;

    #[ORM\Column(nullable: true)]
    private ?int $dem = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?DateTimeInterface $modificationDate = null;

    #[ORM\ManyToOne(inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FeatureClass $featureClass = null;

    #[ORM\ManyToOne(inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FeatureCode $featureCode = null;

    #[ORM\ManyToOne(inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $country = null;

    #[ORM\ManyToOne(inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Timezone $timezone = null;

    #[ORM\ManyToOne(inversedBy: 'locations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdminCode $adminCode = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getGeonameId(): ?int
    {
        return $this->geonameId;
    }

    /**
     * @param int $geonameId
     * @return $this
     */
    public function setGeonameId(int $geonameId): static
    {
        $this->geonameId = $geonameId;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAsciiName(): ?string
    {
        return $this->asciiName;
    }

    /**
     * @param string $asciiName
     * @return $this
     */
    public function setAsciiName(string $asciiName): static
    {
        $this->asciiName = $asciiName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAlternateNames(): ?string
    {
        return $this->alternateNames;
    }

    /**
     * @param string $alternateNames
     * @return $this
     */
    public function setAlternateNames(string $alternateNames): static
    {
        $this->alternateNames = $alternateNames;

        return $this;
    }

    /**
     * @return Point|null
     */
    public function getCoordinate(): ?Point
    {
        return $this->coordinate;
    }

    /**
     * @param Point $coordinate
     * @return $this
     */
    public function setCoordinate(Point $coordinate): static
    {
        $this->coordinate = $coordinate;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCc2(): ?string
    {
        return $this->cc2;
    }

    /**
     * @param string $cc2
     * @return $this
     */
    public function setCc2(string $cc2): static
    {
        $this->cc2 = $cc2;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPopulation(): ?string
    {
        return $this->population;
    }

    /**
     * @param string|null $population
     * @return $this
     */
    public function setPopulation(?string $population): static
    {
        $this->population = $population;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getElevation(): ?int
    {
        return $this->elevation;
    }

    /**
     * @param int|null $elevation
     * @return $this
     */
    public function setElevation(?int $elevation): static
    {
        $this->elevation = $elevation;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getDem(): ?int
    {
        return $this->dem;
    }

    /**
     * @param int|null $dem
     * @return $this
     */
    public function setDem(?int $dem): static
    {
        $this->dem = $dem;

        return $this;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getModificationDate(): ?DateTimeInterface
    {
        return $this->modificationDate;
    }

    /**
     * @param DateTimeInterface $modificationDate
     * @return $this
     */
    public function setModificationDate(DateTimeInterface $modificationDate): static
    {
        $this->modificationDate = $modificationDate;

        return $this;
    }

    /**
     * @return FeatureClass|null
     */
    public function getFeatureClass(): ?FeatureClass
    {
        return $this->featureClass;
    }

    /**
     * @param FeatureClass|null $featureClass
     * @return $this
     */
    public function setFeatureClass(?FeatureClass $featureClass): static
    {
        $this->featureClass = $featureClass;

        return $this;
    }

    /**
     * @return FeatureCode|null
     */
    public function getFeatureCode(): ?FeatureCode
    {
        return $this->featureCode;
    }

    /**
     * @param FeatureCode|null $featureCode
     * @return $this
     */
    public function setFeatureCode(?FeatureCode $featureCode): static
    {
        $this->featureCode = $featureCode;

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

    /**
     * @return Timezone|null
     */
    public function getTimezone(): ?Timezone
    {
        return $this->timezone;
    }

    /**
     * @param Timezone|null $timezone
     * @return $this
     */
    public function setTimezone(?Timezone $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getAdminCode(): ?AdminCode
    {
        return $this->adminCode;
    }

    public function setAdminCode(?AdminCode $adminCode): static
    {
        $this->adminCode = $adminCode;

        return $this;
    }
}