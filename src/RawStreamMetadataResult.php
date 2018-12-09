<?php

/**
 * This file is part of `prooph/event-store-http-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Prooph\EventStoreHttpClient\Exception\InvalidArgumentException;

class RawStreamMetadataResult
{
    /** @var string */
    private $stream;
    /** @var bool */
    private $isStreamDeleted;
    /** @var int */
    private $metastreamVersion;
    /** @var string */
    private $streamMetadata;

    public function __construct(string $stream, bool $isStreamDeleted, int $metastreamVersion, string $streamMetadata)
    {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty string');
        }

        $this->stream = $stream;
        $this->isStreamDeleted = $isStreamDeleted;
        $this->metastreamVersion = $metastreamVersion;
        $this->streamMetadata = $streamMetadata;
    }

    public function stream(): string
    {
        return $this->stream;
    }

    public function isStreamDeleted(): bool
    {
        return $this->isStreamDeleted;
    }

    public function metastreamVersion(): int
    {
        return $this->metastreamVersion;
    }

    public function streamMetadata(): string
    {
        return $this->streamMetadata;
    }
}
