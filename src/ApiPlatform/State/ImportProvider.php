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

namespace App\ApiPlatform\State;

use App\ApiPlatform\Resource\Import;
use App\ApiPlatform\Route\ImportRoute;
use App\ApiPlatform\State\Base\BaseProvider;
use App\Entity\Country;
use App\Entity\Import as ImportEntity;
use App\Repository\ImportRepository;
use App\Repository\LocationRepository;
use DateTimeImmutable;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Ixnode\PhpApiVersionBundle\ApiPlatform\Resource\Base\BasePublicResource;
use Ixnode\PhpApiVersionBundle\ApiPlatform\State\Base\Wrapper\BaseResourceWrapperProvider;
use Ixnode\PhpApiVersionBundle\Utils\Version\Version;
use Ixnode\PhpException\Case\CaseUnsupportedException;
use Ixnode\PhpException\Class\ClassInvalidException;
use Ixnode\PhpException\Type\TypeInvalidException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ImportProvider
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-07-22)
 * @since 0.1.0 (2023-07-22) First version.
 */
final class ImportProvider extends BaseProvider
{
    /**
     * @param Version $version
     * @param ParameterBagInterface $parameterBag
     * @param RequestStack $request
     * @param ImportRepository $importRepository
     * @param LocationRepository $locationRepository
     * @param TranslatorInterface $translator
     */
    public function __construct(
        protected Version               $version,
        protected ParameterBagInterface $parameterBag,
        protected RequestStack          $request,
        protected ImportRepository      $importRepository,
        protected LocationRepository    $locationRepository,
        protected TranslatorInterface   $translator
    )
    {
        parent::__construct($version, $parameterBag, $request);
    }

    /**
     * Returns the route properties according to current class.
     *
     * @return array<string, array<string, int|string|string[]>>
     */
    protected function getRouteProperties(): array
    {
        return ImportRoute::PROPERTIES;
    }

    /**
     * Translates an Import entity to an Import resource.
     *
     * @param ImportEntity $importEntity
     * @return Import
     * @throws ClassInvalidException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws TypeInvalidException
     */
    private function getImport(ImportEntity $importEntity): Import
    {
        $country = $importEntity->getCountry();

        if (!$country instanceof Country) {
            throw new ClassInvalidException(Country::class, Country::class);
        }

        $createdAt = $importEntity->getCreatedAt();

        if (!$createdAt instanceof DateTimeImmutable) {
            throw new ClassInvalidException(DateTimeImmutable::class, DateTimeImmutable::class);
        }

        $updatedAt = $importEntity->getUpdatedAt();

        if (!$updatedAt instanceof DateTimeImmutable) {
            throw new ClassInvalidException(DateTimeImmutable::class, DateTimeImmutable::class);
        }

        return (new Import())
            ->setCountry((string) $country->getName())
            ->setPath((string) $importEntity->getPath())
            ->setNumberOfLocations($this->locationRepository->getNumberOfLocations($country))
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
            ->setDuration($updatedAt->getTimestamp() - $createdAt->getTimestamp())
        ;
    }

    /**
     * Returns a collection of location resources that matches the given coordinate.
     *
     * @return BasePublicResource[]
     * @throws ClassInvalidException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws TypeInvalidException
     */
    private function doProvideGetCollection(): array
    {
        $imports = [];
        $importEntities = $this->importRepository->findAll();

        foreach ($importEntities as $importEntity) {

            if (!$importEntity instanceof ImportEntity) {
                continue;
            }

            $imports[] = $this->getImport($importEntity);
        }

        return $imports;
    }

    /**
     * Do the provided job.
     *
     * @return BasePublicResource|BasePublicResource[]
     * @throws CaseUnsupportedException
     * @throws ClassInvalidException
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws TypeInvalidException
     */
    protected function doProvide(): BasePublicResource|array
    {
        return match($this->getRequestMethod()) {
            BaseResourceWrapperProvider::METHOD_GET_COLLECTION => $this->doProvideGetCollection(),
            default => throw new CaseUnsupportedException('Unsupported mode from api endpoint /api/v1/import.'),
        };
    }
}
