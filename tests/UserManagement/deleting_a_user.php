<?php

/**
 * This file is part of `prooph/event-store-http-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreHttpClient\UserManagement;

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreHttpClient\Exception\UserCommandFailed;
use ProophTest\EventStoreHttpClient\DefaultData;

class deleting_a_user extends TestWithNode
{
    /** @test */
    public function deleting_non_existing_user_throws(): void
    {
        $this->expectException(UserCommandFailed::class);

        try {
            $this->manager->deleteUser(Guid::generateString(), DefaultData::adminCredentials());
        } catch (UserCommandFailed $e) {
            $this->assertSame(HttpStatusCode::NOT_FOUND, $e->httpStatusCode());

            throw $e;
        }
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_created_user_deletes_it(): void
    {
        $user = Guid::generateString();

        $this->manager->createUser($user, 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());
        $this->manager->deleteUser($user, DefaultData::adminCredentials());
    }

    /** @test */
    public function deleting_empty_user_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->deleteUser('', DefaultData::adminCredentials());
    }

    /** @test */
    public function can_delete_a_user(): void
    {
        $name = Guid::generateString();

        $this->manager->createUser(
            $name,
            'ouro',
            ['foo', 'bar'],
            'ouro',
            DefaultData::adminCredentials()
        );

        $x = $this->manager->getUser($name, DefaultData::adminCredentials());

        $this->assertInstanceOf(UserDetails::class, $x);

        $this->manager->deleteUser($name, DefaultData::adminCredentials());

        $this->expectException(UserCommandFailed::class);

        $this->manager->getUser($name, DefaultData::adminCredentials());
    }
}