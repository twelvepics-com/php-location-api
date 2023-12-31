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

namespace App\Command\AlternateName;

use App\Command\Base\BaseLocationImport;
use App\Constants\Key\KeyCamelCase;
use App\Entity\AlternateName;
use App\Entity\Country;
use App\Entity\Import;
use App\Entity\Location;
use App\Repository\ImportRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Ixnode\PhpApiVersionBundle\Utils\TypeCasting\TypeCastingHelper;
use Ixnode\PhpContainer\File;
use Ixnode\PhpException\ArrayType\ArrayKeyNotFoundException;
use Ixnode\PhpException\Case\CaseInvalidException;
use Ixnode\PhpException\File\FileNotFoundException;
use Ixnode\PhpException\File\FileNotReadableException;
use Ixnode\PhpException\Type\TypeInvalidException;
use Ixnode\PhpTimezone\Country as IxnodeCountry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

/**
 * Class ImportCommand
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-06-27)
 * @since 0.1.0 (2023-06-27) First version.
 * @example bin/console alternate-name:import [file]
 * @example bin/console alternate-name:import import/alternate-name/DE.txt
 * @download bin/console alternate-name:download [countryCode]
 * @see http://download.geonames.org/export/dump/
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class ImportCommand extends BaseLocationImport
{
    protected static $defaultName = 'alternate-name:import';

    private int $ignoredLines = 0;

    /** @var array<int, string> $ignoredLinesText */
    private array $ignoredLinesText = [];

    /** @var array<int, Location> $locations */
    private array $locations = [];

    /** @var array<string, Country> $countries */
    private array $countries = [];

    protected const FIELD_ALTERNATE_NAME_ID = 'alternate-name-id';

    protected const FIELD_GEONAME_ID = 'geoname-id';

    protected const FIELD_ISO_LANGUAGE = 'iso-language';

    protected const FIELD_ALTERNATE_NAME = 'alternate-name';

    protected const FIELD_IS_PREFERRED_NAME = 'is-preferred-name';

    protected const FIELD_IS_SHORT_NAME = 'is-short-name';

    protected const FIELD_IS_COLLOQUIAL = 'is-colloquial';

    protected const FIELD_IS_HISTORIC = 'is-historic';

    protected const FIELD_FROM = 'from';

    protected const FIELD_TO = 'to';

    protected const TEXT_IMPORT_START = 'Start importing "%s" - [%d/%d]. Please wait.';

    private const TEXT_ROWS_WRITTEN = '%d rows written to table %s (%d checked): %.2fs';

    protected const TEXT_WARNING_IGNORED_LINE = 'Ignored line: %s:%d';

    protected const OPTION_NAME_FORCE = 'force';

    protected bool $errorFound = false;

    protected float $timeStart;

    protected int $importedRows = 0;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        protected readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        $this
            ->setName(strval(self::$defaultName))
            ->setDescription('Imports locations from given file.')
            ->setDefinition([
                new InputArgument('file', InputArgument::REQUIRED, 'The file to be imported.'),
            ])
            ->addOption(self::OPTION_NAME_FORCE, 'f', InputOption::VALUE_NONE, 'Forces the import even if an import with the same given country code already exists.')
            ->setHelp(
                <<<'EOT'

The <info>import:location</info> command imports locations from a given file.

EOT
            );
    }

    /**
     * @inheritdoc
     *
     * alternateNameId   : the id of this alternate name, int
     * geonameid         : geonameId referring to id in table 'geoname', int
     * isolanguage       : iso 639 language code 2- or 3-characters, optionally followed by a hyphen and a countrycode for country specific variants (ex:zh-CN) or by a variant name (ex: zh-Hant); 4-characters 'post' for postal codes and 'iata','icao' and faac for airport codes, fr_1793 for French Revolution names,  abbr for abbreviation, link to a website (mostly to wikipedia), wkdt for the wikidataid, varchar(7)
     * alternate name    : alternate name or name variant, varchar(400)
     * isPreferredName   : '1', if this alternate name is an official/preferred name
     * isShortName       : '1', if this is a short name like 'California' for 'State of California'
     * isColloquial      : '1', if this alternate name is a colloquial or slang term. Example: 'Big Apple' for 'New York'.
     * isHistoric        : '1', if this alternate name is historic and was used in the past. Example 'Bombay' for 'Mumbai'.
     * from		         : from period when the name was used
     * to		         : to period when the name was used
     */
    protected function getFieldTranslation(): array
    {
        return [
            'AlternateNameId' => self::FIELD_ALTERNATE_NAME_ID,
            'GeonameId' => self::FIELD_GEONAME_ID,
            'IsoLanguage' => self::FIELD_ISO_LANGUAGE,
            'AlternateName' => self::FIELD_ALTERNATE_NAME,
            'IsPreferredName' => self::FIELD_IS_PREFERRED_NAME,
            'IsShortName' => self::FIELD_IS_SHORT_NAME,
            'IsColloquial' => self::FIELD_IS_COLLOQUIAL,
            'IsHistoric' => self::FIELD_IS_HISTORIC,
            'From' => self::FIELD_FROM,
            'To' => self::FIELD_TO,
        ];
    }

    /**
     * Returns some extra rows.
     *
     * @inheritdoc
     */
    protected function getExtraCsvRows(): array
    {
        return [];
    }

    /**
     * Translate given value according to given index name.
     *
     * @param bool|int|string $value
     * @param string $indexName
     * @return bool|string|int|float|DateTimeImmutable|null
     * @throws TypeInvalidException
     */
    protected function translateField(bool|int|string $value, string $indexName): bool|string|int|float|null|DateTimeImmutable
    {
        return match($indexName) {
            self::FIELD_ALTERNATE_NAME_ID,
            self::FIELD_GEONAME_ID => $this->trimInteger($value),

            self::FIELD_ALTERNATE_NAME,
            self::FIELD_ISO_LANGUAGE,
            self::FIELD_FROM,
            self::FIELD_TO => $this->trimString($value),

            self::FIELD_IS_PREFERRED_NAME,
            self::FIELD_IS_SHORT_NAME,
            self::FIELD_IS_COLLOQUIAL,
            self::FIELD_IS_HISTORIC => $this->trimBool($value),

            default => $value,
        };
    }

    /**
     * Add ignored line to log.
     *
     * @param File $csv
     * @param int $line
     * @return void
     */
    private function addIgnoredLine(File $csv, int $line): void
    {
        $this->ignoredLines++;
        $this->ignoredLinesText[] = sprintf('%s:%d', $csv->getPath(), $line);
        $this->printAndLog(sprintf(
            self::TEXT_WARNING_IGNORED_LINE,
            $csv->getPath(),
            $line
        ));
    }

    /**
     * Returns the converted row.
     *
     * @inheritdoc
     * @throws TypeInvalidException
     */
    protected function getDataRow(array $row, array $header, File $csv, int $line): ?array
    {
        if (count($row) !== count($header)) {
            $this->addIgnoredLine($csv, $line);
            return null;
        }

        $dataRow = [];

        foreach ($row as $index => $value) {
            $indexName = $header[$index];

            if ($indexName === null) {
                continue;
            }

            $dataRow[$indexName] = $this->translateField($value, $indexName);
        }

        return $dataRow;
    }

    /**
     * Returns or creates a new Country entity.
     *
     * @param string $code
     * @return Country
     * @throws ArrayKeyNotFoundException
     */
    protected function getCountry(string $code): Country
    {
        $index = $code;

        /* Use cache. */
        if (array_key_exists($index, $this->countries)) {
            return $this->countries[$index];
        }

        $repository = $this->entityManager->getRepository(Country::class);

        $country = $repository->findOneBy([
            KeyCamelCase::CODE => $code,
        ]);

        /* Create new entity. */
        if (!$country instanceof Country) {
            $country = (new Country())
                ->setCode($code)
                ->setName((new IxnodeCountry($code))->getName())
            ;
            $this->entityManager->persist($country);
        }

        $this->countries[$index] = $country;
        return $country;
    }

    /**
     * Returns the Import class.
     *
     * @param File $file
     * @return Import
     * @throws ArrayKeyNotFoundException
     */
    protected function getImport(File $file): Import
    {
        $countryCode = basename($file->getPath(), '.txt');

        $country = $this->getCountry($countryCode);

        $import = (new Import())
            ->setCountry($country)
            ->setPath($file->getPath())
            ->setExecutionTime(0)
            ->setRows(0)
        ;

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Returns an existing Location entity.
     *
     * @param int $geonameId
     * @return Location|null
     */
    protected function getLocation(int $geonameId): ?Location
    {
        $index = $geonameId;

        /* Use cache. */
        if (array_key_exists($index, $this->locations)) {
            return $this->locations[$index];
        }

        $repository = $this->entityManager->getRepository(Location::class);

        $location = $repository->findOneBy([
            KeyCamelCase::GEONAME_ID => $geonameId,
        ]);

        /* Create new entity. */
        if (!$location instanceof Location) {
            return null;
        }

        $this->locations[$index] = $location;

        return $location;
    }

    /**
     * Returns or creates a new AlternateName entity.
     *
     * @param int $alternateNameId
     * @param Location $location
     * @param string $isoLanguage
     * @param string $alternateNameValue
     * @param bool $isPreferredName
     * @param bool $isShortName
     * @param bool $isColloquial
     * @param bool $isHistoric
     * @return AlternateName
     * @throws Exception
     */
    protected function getAlternateName(
        int $alternateNameId,
        Location $location,
        string $isoLanguage,
        string $alternateNameValue,
        bool $isPreferredName,
        bool $isShortName,
        bool $isColloquial,
        bool $isHistoric
    ): AlternateName
    {
        $repository = $this->entityManager->getRepository(AlternateName::class);

        $alternateName = $repository->findOneBy([
            KeyCamelCase::ALTERNATE_NAME_ID => $alternateNameId,
        ]);

        /* Create new entity. */
        if (!$alternateName instanceof AlternateName) {
            $alternateName = (new AlternateName())
                ->setAlternateNameId($alternateNameId)
            ;
        }

        /* Update entity. */
        $alternateName
            ->setLocation($location)
            ->setIsoLanguage(empty($isoLanguage) ? null : $isoLanguage)
            ->setAlternateName($alternateNameValue)
            ->setPreferredName($isPreferredName)
            ->setShortName($isShortName)
            ->setColloquial($isColloquial)
            ->setHistoric($isHistoric)
        ;

        $this->entityManager->persist($alternateName);

        return $alternateName;
    }

    /**
     * Sets the update_at field of Import entity.
     *
     * @return void
     */
    private function updateImportEntity(): void
    {
        if (!isset($this->import)) {
            return;
        }

        $executionTime = (int) round(microtime(true) - $this->timeStart);

        $this->import
            ->setUpdatedAt(new DateTimeImmutable())
            ->setExecutionTime($executionTime)
            ->setRows($this->importedRows)
        ;
        $this->entityManager->persist($this->import);
        $this->entityManager->flush();
    }

    /**
     * Saves the data as entities.
     *
     * @param array<int, array<string, mixed>> $data
     * @param File $file
     * @return int
     * @throws TypeInvalidException
     * @throws Exception
     */
    protected function saveEntities(array $data, File $file): int
    {
        $writtenRows = 0;

        /* Update or create entities. */
        foreach ($data as $number => $row) {
            $alternateNameId = (new TypeCastingHelper($row[self::FIELD_ALTERNATE_NAME_ID]))->intval();
            $geonameIdValue = (new TypeCastingHelper($row[self::FIELD_GEONAME_ID]))->intval();

            $isoLanguage = (new TypeCastingHelper($row[self::FIELD_ISO_LANGUAGE]))->strval();
            $alternateNameValue = (new TypeCastingHelper($row[self::FIELD_ALTERNATE_NAME]))->strval();

            $isPreferredName = (bool) $row[self::FIELD_IS_PREFERRED_NAME];
            $isShortName = (bool) $row[self::FIELD_IS_SHORT_NAME];
            $isColloquial = (bool) $row[self::FIELD_IS_COLLOQUIAL];
            $isHistoric = (bool) $row[self::FIELD_IS_HISTORIC];

            $location = $this->getLocation($geonameIdValue);

            if (is_null($location)) {
                $this->addIgnoredLine($file, $number + 1);
                continue;
            }

            $this->getAlternateName(
                $alternateNameId,
                $location,
                $isoLanguage,
                $alternateNameValue,
                $isPreferredName,
                $isShortName,
                $isColloquial,
                $isHistoric
            );

            $writtenRows++;
        }

        $this->printAndLog('Start flushing AlternateName entities.');
        $this->entityManager->flush();

        return $writtenRows;
    }

    /**
     * Executes the given split file.
     *
     * @param File $file
     * @param int $numberCurrent
     * @param int $numberAll
     * @return void
     * @throws CaseInvalidException
     * @throws TypeInvalidException
     * @throws ArrayKeyNotFoundException
     * @throws FileNotFoundException
     * @throws FileNotReadableException
     * @throws Exception
     */
    protected function doExecute(File $file, int $numberCurrent, int $numberAll): void
    {
        $this->printAndLog('---');
        $this->printAndLog(sprintf(self::TEXT_IMPORT_START, $file->getPath(), $numberCurrent, $numberAll));

        /* Reads the given csv files. */
        $this->printAndLog(sprintf('Start reading CSV file "%s"', $file->getPath()));
        $timeStart = microtime(true);
        $data = $this->readDataFromCsv($file, "\t");
        $timeExecution = (microtime(true) - $timeStart);
        $this->printAndLog(sprintf('%d rows successfully read: %.2fs (CSV file)', count($data), $timeExecution));

        /* Imports the AlternateName data */
        $this->printAndLog('Start writing AlternateName entities.');
        $timeStart = microtime(true);
        $rows = $this->saveEntities($data, $file);
        $timeExecution = (microtime(true) - $timeStart);
        $this->printAndLog(sprintf(
            self::TEXT_ROWS_WRITTEN,
            $rows,
            'location',
            count($data),
            $timeExecution
        ));

        $this->importedRows += $rows;
    }

    /**
     * Returns if the current import was already done.
     *
     * @param string $countryCode
     * @param File $file
     * @return bool
     * @throws NonUniqueResultException
     * @throws TypeInvalidException
     */
    protected function hasImportByCountryCodeAndPath(string $countryCode, File $file): bool
    {
        $country = $this->entityManager->getRepository(Country::class)->findOneBy([
            'code' => strtoupper($countryCode),
        ]);

        if (!$country instanceof Country) {
            return false;
        }

        $repository = $this->entityManager->getRepository(Import::class);

        if (!$repository instanceof ImportRepository) {
            return false;
        }

        return $repository->getNumberOfImports($country, $file) > 0;
    }

    /**
     * Execute the commands.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->timeStart = microtime(true);

        $this->output = $output;
        $this->input = $input;

        $force = $input->hasOption(self::OPTION_NAME_FORCE) && (bool) $input->getOption(self::OPTION_NAME_FORCE);

        $file = $this->getCsvFile('file');

        if (is_null($file)) {
            $this->printAndLog(sprintf('The given CSV file for "%s" does not exist.', $file));
            return Command::INVALID;
        }

        $countryCode = basename($file->getPath(), '.txt');

        if (!$force && $this->hasImportByCountryCodeAndPath($countryCode, $file)) {
            $this->printAndLog(sprintf('The given country code "%s" was already imported. Use --force to ignore.', $countryCode));
            return Command::INVALID;
        }

        $type = sprintf('%s/csv-import', $countryCode);

        $this->createLogInstanceFromFile($file, $type);

        $this->clearTmpFolder($file, $countryCode);
        $this->setSplitLines(10000);
        $this->splitFile(
            $file,
            $countryCode,
            false,
            [
                'AlternateNameId',  /* 0 */
                'GeonameId',        /* 1 */
                'IsoLanguage',      /* 2 */
                'AlternateName',    /* 3 */
                'IsPreferredName',  /* 4 */
                'IsShortName',      /* 5 */
                'IsColloquial',     /* 6 */
                'IsHistoric',       /* 7 */
                'From',             /* 8 */
                'To',               /* 9 */
            ],
            "\t"
        );

        $this->import = $this->getImport($file);

        /* Get tmp files */
        $splittedFiles = $this->getFilesTmp($file, $countryCode);

        /* Execute all splitted files */
        foreach ($splittedFiles as $index => $splittedFile) {
            $this->doExecute(new File($splittedFile), $index + 1, count($splittedFiles));
        }

        $this->errorFound = false;

        /* Show ignored lines */
        if ($this->getIgnoredLines() > 0) {
            $this->printAndLog('---');
            $this->printAndLog(sprintf('Ignored lines: %d', $this->getIgnoredLines()));
            foreach ($this->getIgnoredLinesText() as $ignoredLine) {
                $this->printAndLog(sprintf('- %s', $ignoredLine));
            }
            $this->errorFound = true;
        }

        if (!$this->errorFound) {
            $this->printAndLog('---');
            $this->printAndLog('Finish. No error was found.');
        }

        /* Set last date to Import entity. */
        $this->updateImportEntity();

        /* Command successfully executed. */
        return Command::SUCCESS;
    }

    /**
     * Returns the number of ignored lines.
     *
     * @return int
     */
    public function getIgnoredLines(): int
    {
        return $this->ignoredLines;
    }

    /**
     * Returns the ignored lines.
     *
     * @return array<int, string>
     */
    public function getIgnoredLinesText(): array
    {
        return $this->ignoredLinesText;
    }
}
