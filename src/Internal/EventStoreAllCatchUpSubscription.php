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

namespace Prooph\EventStoreHttpClient\Internal;

use Closure;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventStoreAllCatchUpSubscription as EventStoreAllCatchUpSubscriptionInterface;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface;
use Throwable;

class EventStoreAllCatchUpSubscription extends EventStoreCatchUpSubscription implements EventStoreAllCatchUpSubscriptionInterface
{
    private Position $nextReadPosition;
    private Position $lastProcessedPosition;

    /**
     * @internal
     *
     * @param Closure(EventStoreCatchUpSubscription, ResolvedEvent): void $eventAppeared
     * @param null|Closure(EventStoreCatchUpSubscription): void $liveProcessingStarted
     * @param null|Closure(EventStoreCatchUpSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        EventStoreConnection $connection,
        LoggerInterface $logger,
        ?Position $fromPositionExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted,
        ?Closure $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        parent::__construct(
            $connection,
            $logger,
            '',
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $this->lastProcessedPosition = $fromPositionExclusive ?? Position::end();
        $this->nextReadPosition = $fromPositionExclusive ?? Position::start();
    }

    public function lastProcessedPosition(): Position
    {
        return $this->lastProcessedPosition;
    }

    protected function readEventsTill(
        EventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): void {
        $this->readEventsInternal($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition);
    }

    private function readEventsInternal(
        EventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition
    ): void {
        do {
            $slice = $connection->readAllEventsForward(
                $this->nextReadPosition,
                $this->readBatchSize,
                $resolveLinkTos,
                $userCredentials
            );

            $shouldStopOrDone = $this->readEventsCallback($slice, $lastCommitPosition);
        } while (! $shouldStopOrDone);
    }

    private function readEventsCallback(AllEventsSlice $slice, ?int $lastCommitPosition): bool
    {
        $shouldStopOrDone = $this->shouldStop || $this->processEvents($lastCommitPosition, $slice);

        if ($shouldStopOrDone && $this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: finished reading events, nextReadPosition = %s',
                $this->subscriptionName(),
                $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                (string) $this->nextReadPosition
            ));
        }

        return $shouldStopOrDone;
    }

    private function processEvents(?int $lastCommitPosition, AllEventsSlice $slice): bool
    {
        foreach ($slice->events() as $e) {
            if (null === $e->originalPosition()) {
                throw new RuntimeException(\sprintf(
                    'Subscription %s event came up with no OriginalPosition',
                    $this->subscriptionName()
                ));
            }

            $this->tryProcess($e);
        }

        $this->nextReadPosition = $slice->nextPosition();

        $done = (null === $lastCommitPosition)
            ? $slice->isEndOfStream()
            : $slice->nextPosition()->greaterOrEquals(new Position($lastCommitPosition, $lastCommitPosition));

        if (! $done && $slice->isEndOfStream()) {
            // we are waiting for server to flush its data
            \sleep(1);
        }

        return $done;
    }

    protected function tryProcess(ResolvedEvent $e): void
    {
        $processed = false;

        if ($e->originalPosition()->greater($this->lastProcessedPosition)) {
            try {
                ($this->eventAppeared)($this, $e);
            } catch (Throwable $ex) {
                $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);
            }

            $this->lastProcessedPosition = $e->originalPosition();
            $processed = true;
        }

        if ($this->verbose) {
            /** @psalm-suppress PossiblyNullReference */
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: %s event (%s, %d, %s @ %s)',
                $this->subscriptionName(),
                $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                $processed ? 'processed' : 'skipping',
                $e->originalEvent()->eventStreamId(),
                $e->originalEvent()->eventNumber(),
                $e->originalEvent()->eventType(),
                $e->originalPosition() ? $e->originalPosition()->__toString() : '<null>'
            ));
        }
    }
}
