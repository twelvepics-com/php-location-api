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

namespace App\Service\Base\Helper;

use App\ApiPlatform\Resource\Location;
use App\Constants\DB\FeatureClass;
use App\Constants\DB\FeatureCode;
use App\Constants\Language\CountryCode;
use App\Constants\Language\LanguageCode;
use App\Entity\Location as LocationEntity;
use App\Repository\AlternateNameRepository;
use App\Repository\LocationRepository;
use App\Service\Entity\LocationEntityHelper;
use App\Service\LocationContainer;
use App\Service\LocationServiceConfig;
use App\Service\LocationServiceAlternateName;
use Ixnode\PhpApiVersionBundle\Utils\Version\Version;
use Ixnode\PhpCoordinate\Coordinate;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BaseHelperLocationService
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-07-31)
 * @since 0.1.0 (2023-07-31) First version.
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
abstract class BaseHelperLocationService
{
    protected ?string $error = null;

    protected float $timeStart;

    protected int|null $limit = null;

    protected int|null $limitSave = null;

    protected bool $filterAfterQuery = false;

    protected int|null $distanceMeter = null;

    /** @var array<int, string>|string|null $featureClass */
    protected array|string|null $featureClass = null;

    /** @var array<int, string>|string|null $featureCode */
    protected array|string|null $featureCode = null;



    protected Coordinate $coordinate;

    private string $isoLanguage = LanguageCode::EN;

    private string $country = CountryCode::US;

    private bool $nextPlaces = false;



    protected LocationContainer $locationContainer;

    /** @var LocationContainer[] $locationContainerHolder */
    protected array $locationContainerHolder = [];

    protected LocationServiceAlternateName $locationServiceAlternateName;

    protected LocationEntityHelper $locationEntityHelper;

    protected FeatureClass $featureClassService;

    protected FeatureCode $featureCodeService;

    /**
     * @param Version $version
     * @param ParameterBagInterface $parameterBag
     * @param RequestStack $request
     * @param LocationRepository $locationRepository
     * @param AlternateNameRepository $alternateNameRepository
     * @param TranslatorInterface $translator
     * @param LocationServiceConfig $locationServiceConfig
     */
    public function __construct(
        protected Version $version,
        protected ParameterBagInterface $parameterBag,
        protected RequestStack $request,
        protected LocationRepository $locationRepository,
        protected AlternateNameRepository $alternateNameRepository,
        protected TranslatorInterface $translator,
        protected LocationServiceConfig $locationServiceConfig
    )
    {
        $this->setTimeStart(microtime(true));

        $this->locationServiceAlternateName = new LocationServiceAlternateName($alternateNameRepository);
        $this->locationEntityHelper = new LocationEntityHelper(new LocationContainer($this->locationServiceAlternateName));

        $this->featureClassService = new FeatureClass($translator);
        $this->featureCodeService = new FeatureCode($translator);
    }

    /**
     * @return Coordinate
     */
    public function getCoordinate(): Coordinate
    {
        return $this->coordinate;
    }

    /**
     * @param Coordinate $coordinate
     * @return self
     */
    public function setCoordinate(Coordinate $coordinate): self
    {
        $this->coordinate = $coordinate;

        return $this;
    }

    /**
     * @return string
     */
    protected function getIsoLanguage(): string
    {
        return $this->isoLanguage;
    }

    /**
     * @param string $isoLanguage
     * @return self
     */
    protected function setIsoLanguage(string $isoLanguage): self
    {
        $this->isoLanguage = $isoLanguage;

        return $this;
    }

    /**
     * @return string
     */
    protected function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @param string $country
     * @return self
     */
    protected function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return bool
     */
    protected function isNextPlaces(): bool
    {
        return $this->nextPlaces;
    }

    /**
     * @param bool $nextPlaces
     * @return self
     */
    protected function setNextPlaces(bool $nextPlaces): self
    {
        $this->nextPlaces = $nextPlaces;

        return $this;
    }

    /**
     * Gets an error of this resource.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Checks if an error occurred.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return is_string($this->error);
    }

    /**
     * Sets an error of this resource.
     *
     * @param string|null $error
     * @return self
     */
    protected function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Returns an empty Location entity.
     *
     * @param int|null $geonameId
     * @return Location
     */
    protected function getEmptyLocation(int|null $geonameId = null): Location
    {
        $location = new Location();

        if (!is_null($geonameId)) {
            $location->setGeonameId($geonameId);
        }

        return $location;
    }

    /**
     * @return float
     */
    public function getTimeStart(): float
    {
        return $this->timeStart;
    }

    /**
     * @param float $timeStart
     * @return self
     */
    public function setTimeStart(float $timeStart): self
    {
        $this->timeStart = $timeStart;

        return $this;
    }

    /**
     * @return LocationEntity|null
     */
    public function getDistrictEntity(): ?LocationEntity
    {
        return $this->locationContainer->getDistrict();
    }

    /**
     * @return LocationEntity|null
     */
    public function getBoroughEntity(): ?LocationEntity
    {
        return $this->locationContainer->getBorough();
    }

    /**
     * @return LocationEntity|null
     */
    public function getCityEntity(): ?LocationEntity
    {
        return $this->locationContainer->getCity();
    }

    /**
     * @return LocationEntity|null
     */
    public function getStateEntity(): ?LocationEntity
    {
        return $this->locationContainer->getState();
    }

    /**
     * @return LocationEntity|null
     */
    public function getCountryEntity(): ?LocationEntity
    {
        return $this->locationContainer->getCountry();
    }

    /**
     * Do "Before query" tasks.
     *
     * @param int|null $limit
     * @param int|null $distanceMeter
     * @param array<int, string>|string|null $featureClass
     * @param array<int, string>|string|null $featureCode
     * @return void
     */
    protected function doBeforeQueryTasks(
        int|null $limit,
        int|null $distanceMeter,
        array|string|null $featureClass,
        array|string|null $featureCode,
    ): void
    {
        $this->filterAfterQuery = match ($this->locationEntityHelper->toString($featureCode)) {
            FeatureCode::AIRP => true,
            default => false,
        };

        $this->limitSave = $this->filterAfterQuery ? $limit : null;
        $this->limit = $this->filterAfterQuery ? null : $this->limitSave;

        $this->distanceMeter = $distanceMeter;
        $this->featureClass = $featureClass;
        $this->featureCode = $featureCode;
    }

    /**
     * Do "After query" tasks.
     *
     * @param array<int, LocationEntity> $locationEntities
     * @return void
     */
    protected function doAfterQueryTasks(array &$locationEntities): void
    {
        if (!$this->filterAfterQuery) {
            return;
        }

        $this->limit = $this->limitSave;
        $this->limitSave = null;

        $locationEntities = match ($this->locationEntityHelper->toString($this->featureCode)) {
            FeatureCode::AIRP => array_filter($locationEntities, fn(LocationEntity $locationEntity) => $this->locationEntityHelper->setLocation($locationEntity)->hasAirportCodeIata()),
            default => $locationEntities,
        };

        if (count($locationEntities) > $this->limit) {
            $locationEntities = array_slice($locationEntities, 0, $this->limit);
        }
    }

    /**
     * Updates the given parameter.
     *
     * @param Coordinate $coordinate
     * @param string $isoLanguage
     * @param string $country
     * @param bool $nextPlaces
     * @return void
     */
    protected function update(
        Coordinate $coordinate,
        string $isoLanguage,
        string $country,
        bool $nextPlaces,
    ): void
    {
        $this->setCoordinate($coordinate);
        $this->setIsoLanguage($isoLanguage);
        $this->setCountry($country);
        $this->setNextPlaces($nextPlaces);
        $this->featureClassService->setLocaleByLanguageAndCountry($isoLanguage, $country);
        $this->featureCodeService->setLocaleByLanguageAndCountry($isoLanguage, $country);
    }
}
