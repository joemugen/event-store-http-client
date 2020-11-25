<?php

/**
 * This file is part of `prooph/event-store-http-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\UserCredentials;
use Psr\Log\NullLogger;

class ConnectionString
{
    private static $allowedValues = [
        'endpoint' => EndPoint::class,
        'schema' => 'string',
        'defaultusercredentials' => UserCredentials::class,
        'requiremaster' => 'bool',
        'verboselogging' => 'bool',
    ];

    public static function getConnectionSettings(
        string $connectionString,
        ?ConnectionSettings $settings = null
    ): ConnectionSettings {
        $settings = [
            'verboselogging' => false,
            'endpoint' => new EndPoint('localhost', 2113),
            'schema' => 'http',
            'defaultusercredentials' => null,
            'requiremaster' => true,
        ];

        foreach (self::getParts($connectionString) as $value) {
            [$key, $value] = \explode('=', $value);
            $key = \strtolower($key);

            if (! \array_key_exists($key, self::$allowedValues)) {
                throw new InvalidArgumentException(\sprintf(
                    'Key %s is not an allowed key in %s',
                    $key,
                    __CLASS__
                ));
            }

            $type = self::$allowedValues[$key];

            switch ($type) {
                case 'bool':
                    $filteredValue = \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'int':
                    $filteredValue = \filter_var($value, \FILTER_VALIDATE_INT);

                    if (false === $filteredValue) {
                        throw new InvalidArgumentException(\sprintf(
                            'Expected type for key %s is %s, but %s given',
                            $key,
                            $type,
                            $value
                        ));
                    }
                    break;
                case 'string':
                    $filteredValue = $value;
                    break;
                case EndPoint::class:
                    $exploded = \explode(':', $value);

                    if (\count($exploded) !== 2) {
                        throw new InvalidArgumentException(\sprintf(
                            'Expected user credentials in format user:pass, %s given',
                            $value
                        ));
                    }

                    $filteredValue = new EndPoint($exploded[0], (int) $exploded[1]);
                    break;
                case UserCredentials::class:
                    $exploded = \explode(':', $value);

                    if (\count($exploded) !== 2) {
                        throw new InvalidArgumentException(\sprintf(
                            'Expected user credentials in format user:pass, %s given',
                            $value
                        ));
                    }

                    $filteredValue = new UserCredentials($exploded[0], $exploded[1]);
                    break;
            }

            $settings[$key] = $filteredValue;
        }

        return new ConnectionSettings(
            new NullLogger(),
            $settings['verboselogging'],
            $settings['endpoint'],
            $settings['schema'],
            $settings['defaultusercredentials'],
            $settings['requiremaster']
        );
    }

    /**
     * @param string $connectionString
     *
     * @return string[]
     */
    private static function getParts(string $connectionString): array
    {
        return \explode(';', \str_replace(' ', '', $connectionString));
    }
}
