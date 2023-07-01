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

namespace App\Command\Location;

use Exception;
use Ixnode\PhpApiVersionBundle\Utils\TypeCasting\TypeCastingHelper;
use Ixnode\PhpContainer\File;
use Ixnode\PhpException\ArrayType\ArrayKeyNotFoundException;
use Ixnode\PhpException\Case\CaseInvalidException;
use Ixnode\PhpException\Class\ClassInvalidException;
use Ixnode\PhpException\File\FileNotFoundException;
use Ixnode\PhpException\File\FileNotReadableException;
use Ixnode\PhpException\Type\TypeInvalidException;
use Ixnode\PhpTimezone\Constants\CountryUnknown;
use Ixnode\PhpTimezone\Country as IxnodeCountry;
use Ixnode\PhpTimezone\Timezone as IxnodeTimezone;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class CheckCommand
 *
 * @author Björn Hempel <bjoern@hempel.li>
 * @version 0.1.0 (2023-07-01)
 * @since 0.1.0 (2023-07-01) First version.
 * @example bin/console location:check [file]
 * @example bin/console location:import import/location/DE.txt
 * @download bin/console location:download [countryCode]
 * @see http://download.geonames.org/export/dump/
 */
class CheckCommand extends ImportCommand
{
    protected static $defaultName = 'location:check';

    private const TEXT_ROWS_CHECKED = '%d rows checked from data %s (%d checked): %.2fs';

    private const TEXT_UNKNOWN_TIMEZONE = 'Unknown timezone detected: "%s", file: %s:%d';

    private const TEXT_INVALID_TIMEZONE = 'Invalid timezone detected: "%s", file: %s:%d';

    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        $this
            ->setName(strval(self::$defaultName))
            ->setDescription('Checks locations from given file.')
            ->setDefinition([
                new InputArgument('file', InputArgument::REQUIRED, 'The file to be checked.'),
            ])
            ->setHelp(
                <<<'EOT'

The <info>import:check</info> command checks locations from a given file.

EOT
            );
    }

    /**
     * Checks timezone.
     *
     * @param array<string, mixed> $row
     * @param File $file
     * @param int $currentRow
     * @return void
     * @throws ClassInvalidException
     * @throws TypeInvalidException
     */
    private function checkTimezone(array $row, File $file, int $currentRow): void
    {
        $timezoneValue = (new TypeCastingHelper($row[self::FIELD_TIMEZONE]))->strval();

        /* Ignore empty timezones. */
        if ($timezoneValue === '') {
            return;
        }

        $countryCode = (new IxnodeTimezone($timezoneValue))->getCountryCode();
        $countryCodeChecked = (new IxnodeCountry($countryCode))->getCode();

        if ($countryCodeChecked === CountryUnknown::COUNTRY_CODE_IV) {
            $this->addInvalidTimezones($timezoneValue, $file, $currentRow);
            $this->printAndLog(sprintf(
                self::TEXT_INVALID_TIMEZONE,
                $timezoneValue,
                $file->getPath(),
                $currentRow
            ));
        }

        if ($countryCodeChecked === CountryUnknown::COUNTRY_CODE_UK) {
            $this->addUnknownTimezones($timezoneValue, $file, $currentRow);
            $this->printAndLog(sprintf(
                self::TEXT_UNKNOWN_TIMEZONE,
                $timezoneValue,
                $file->getPath(),
                $currentRow
            ));
        }
    }

    /**
     * Checks the imported data.
     *
     * @param array<int, array<string, mixed>> $data
     * @param File $file
     * @return int
     * @throws ClassInvalidException
     * @throws TypeInvalidException
     */
    protected function checkEntities(array $data, File $file): int
    {
        $rowsChecked = 0;

        /* Update or create entities. */
        foreach ($data as $row) {
            $rowsChecked++;

            $this->checkTimezone($row, $file, $rowsChecked + 1);

            /* @see \App\Command\Location\ImportCommand::saveEntities for more checks. */
        }

        return $rowsChecked;
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

        /* Imports the Location data */
        $this->printAndLog('Start checking Location entities.');
        $timeStart = microtime(true);
        $rows = $this->checkEntities($data, $file);
        $timeExecution = (microtime(true) - $timeStart);
        $this->printAndLog(sprintf(
            self::TEXT_ROWS_CHECKED,
            $rows,
            'location',
            count($data),
            $timeExecution
        ));
    }
}