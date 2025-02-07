<?php

namespace PowerComponents\LivewirePowerGrid\Helpers;

use Exception;
use Illuminate\Support\Facades\{DB, Schema};

class SqlSupport
{
    /**
     * @var array<int,string>|string[]
     */
    private static array $sortStringNumberTypes = ['string', 'varchar', 'char'];

    public static function like(): string
    {
        $driverName = self::getDatabaseDriverName();

        /*
        |--------------------------------------------------------------------------
        |  Search 'LIKE' (drivers)
        |--------------------------------------------------------------------------
        | PowerGrid needs to sort with "case-insensitive".
        | Here, we adapt the 'LIKE' syntax for each database driver.
        */

        $likeSyntax = [
            'pgsql' => 'ILIKE',
        ];

        return $likeSyntax[$driverName] ?? 'LIKE';
    }

    /**
     * @throws Exception
     */
    public static function sortStringAsNumber(string $sortField): string
    {
        $driverName              = self::getDatabaseDriverName();
        $driverVersion           = self::getDatabaseVersion();

        return self::getSortSqlByDriver($sortField, $driverName, $driverVersion);
    }

    /**
     * @throws Exception
     */
    public static function getSortSqlByDriver(string $sortField, string $driverName = '', string $driverVersion = ''): string
    {
        if (empty($sortField) || empty($driverName) || empty($driverVersion)) {
            throw new Exception('sortField, driverName and driverVersion must be informed');
        }

        /*
        |--------------------------------------------------------------------------
        |  Supported Databases (drivers)
        |--------------------------------------------------------------------------
        | PowerGrid needs to sort string as number. I.e: Rooms  1, 1a, 1b, 2, 3... 10.
        | Here, we adapt the SQL sorting syntax between databases and versions.
        |  0     => default,
        |  x.x.x => version which the syntax was implemented.
        */

        $default = "$sortField+0"; //default, fallback

        $supportedVersions = [
            'mysql' => [
                '0'     => $default,
                '8.0.4' => "CAST(NULLIF(REGEXP_REPLACE($sortField, '[[:alpha:]]+', ''), '') AS SIGNED INTEGER)",
            ],
            'sqlite' => [
                '0' => "CAST($sortField AS INTEGER)",
            ],
            'pgsql' => [
                '0' => "CAST(NULLIF(REGEXP_REPLACE($sortField, '\D', '', 'g'), '') AS INTEGER)",
            ],
            'sqlsrv' => [
                '0' => "CAST(SUBSTRING($sortField, PATINDEX('%[a-z]%', $sortField), LEN($sortField)-PATINDEX('%[a-z]%', $sortField)) AS INT)",
            ],
        ];

        if (!isset($supportedVersions[$driverName])) {
            return $default;
        }

        $syntax = collect($supportedVersions[$driverName])
                ->filter(function ($syntax, $version) use ($driverVersion) {
                    return version_compare($version, $driverVersion, '<=');
                })
                ->last();

        return is_null($syntax) === true ? $default : $syntax;
    }

    /**
     * @param string|null $sortFieldType
     * @return bool
     */
    public static function isValidSortFieldType(?string $sortFieldType): bool
    {
        if (is_null($sortFieldType)) {
            return false;
        }

        return in_array($sortFieldType, self::$sortStringNumberTypes);
    }

    /**
     * @param string $sortField
     * @return string
     * @throws Exception
     */
    public static function getSortFieldType(string $sortField): ?string
    {
        $data = explode('.', $sortField);
        if (!isset($data[1])) {
            return null;
        }

        if (!Schema::hasColumn($data[0], $data[1])) {
            throw new Exception("There is no column with name '$data[1]' on table '$data[0]'. Please see: https://livewire-powergrid.docsforge.com/main/include-columns/#fieldstring-field-string-datafield");
        }

        return Schema::getConnection()
            ->getDoctrineColumn($data[0], $data[1])
            ->getType()
            ->getName();
    }

    public static function getDatabaseDriverName(): string
    {
        return DB::getDriverName();
    }

    public static function getDatabaseVersion(): string
    {
        return DB::getPdo()->getAttribute(constant('PDO::ATTR_SERVER_VERSION'));
    }
}
