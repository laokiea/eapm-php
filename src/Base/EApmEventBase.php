<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <sashengpeng@blued.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace EApmPhp\Base;

use EApmPhp\EApmComposer;
use EApmPhp\Events\EApmSpan;
use EApmPhp\Events\EApmTransaction;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Util\EApmRandomIdUtil;

/**
 * Class EApmEventBase, All transaction/span parent class
 * @package EApmPhp
 */
class EApmEventBase
{
    /**
     * Registered events collect
     * @var array
     */
    protected $registeredEvents = [];

    /**
     * Registered events stat
     * @var array
     */
    protected $eventStat = [];

    /**
     * Events/Span context
     * @var
     */
    protected $context = array(
        "user" => array(),
        "http" => array(),
        "db"   => array(),
        "tags" => array(),
    );

    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

    /**
     * Events start timestamp
     * @var
     */
    protected $timestamp;

    /**
     * The trace id of current transaction
     * @var
     */
    protected $traceId;

    /**
     * The Span-Id of current transaction
     * @var
     */
    protected $id;

    /**
     * Current event type
     * @var
     */
    protected $eventType;

    /**
     * Events name
     * @var
     */
    protected $name;

    /**
     * The Parent-Span-Id of current event
     * @var
     */
    protected $parentId = null;

    /**
     * Events duration
     * @var
     */
    protected $duration;

    /**
     * Current event is started
     * @var
     */
    protected $isStarted = false;

    /**
     * Current event is ended
     * @var
     */
    protected $isEnded = false;


    /**
     * Set the Trace-Id of current event
     *
     * @param string $traceId
     */
    public function setTraceId(string $traceId) : void
    {
        $this->traceId = $traceId;
    }

    /**
     * Get the Trace-Id of current event
     *
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * Set the Event-Type of current event
     *
     * @param string $eventType
     */
    public function setEventType(string $eventType) : void
    {
        $this->eventType = $eventType;
    }

    /**
     * Get the Event-Type of current event
     *
     * @return string
     */
    public function getEventType() : string
    {
        return $this->eventType;
    }

    /**
     * Set the Span-Id of current event
     *
     * @param string $id
     */
    public function setId(string $id) : void
    {
        $this->id = $id;
    }

    /**
     * Get the Span-Id of current event
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Set the Parent-Span-Id of current event
     *
     * @param string $parentId
     */
    public function setParentId(string $parentId) : void
    {
        $this->parentId = $parentId;
    }

    /**
     * Get the Parent-Span-Id of current transaction
     *
     * @return string
     */
    public function getParentId() : string
    {
        return $this->parentId;
    }

    /**
     * Set the name of current transaction
     *
     * @param string $name
     */
    public function setName(string $name) : void
    {
        $this->name = $name;
    }

    /**
     * Get the name of current transaction
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set transaction duration
     *
     * @param int $duration
     */
    public function setDuration(int $duration) : void
    {
        $this->duration = $duration;
    }

    /**
     * Get transaction duration
     *
     * @return int
     */
    public function getDuration() : int
    {
        return $this->duration;
    }

    /**
     * Set current transaction started
     * @return void
     */
    public function setIsStarted() : void
    {
        $this->isStarted = true;
    }

    /**
     * Set current transaction ended
     * @return void
     */
    public function setIsEnded() : void
    {
        $this->isEnded = false;
    }

    /**
     * Current event is started
     *
     * @return bool
     */
    public function isStarted() : bool
    {
        return $this->isStarted;
    }

    /**
     * Current event is started
     *
     * @return bool
     */
    public function isEnded() : bool
    {
        return $this->isEnded;
    }

    /**
     * Set global composer object
     *
     * @param EApmComposer $composer
     */
    public function setComposer(EApmComposer $composer): void
    {
        $this->composer = $composer;
    }

    /**
     * Get global composer object
     *
     * @return EApmComposer
     */
    public function getComposer(): EApmComposer
    {
        return $this->composer;
    }

    /**
     * End a transaction
     *
     * @return void
     */
    public function end() : void
    {
        $duration = time() - $this->getTimestamp();
        $this->setIsEnded();
        $this->setDuration($duration);
        $this->updateRegisteredEvent();
        $this->getComposer()->getEventIntake()->addEvent($this);
    }

    /**
     * Any other arbitrary data captured by the agent, optionally provided by the user
     * Include DB, HTTP, TAGS, USERS, see link below for more
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     * @param array $context
     */
    public function setContext(array $context = []) : void
    {
        $this->context = array_merge_recursive($this->context, $context);
    }

    /**
     * Set transaction start timestamp
     *
     * @param int $timestamp
     */
    public function setTimestamp(int $timestamp) : void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Get transaction start timestamp
     *
     * @return int
     */
    public function getTimestamp() : int
    {
        return $this->timestamp;
    }

    /**
     * Set event stat
     *
     * @param array $eventStat
     */
    public function setEventStat(array $eventStat) : void
    {
        $this->eventStat = $eventStat;
    }

    /**
     * Get event stat
     *
     * @return array
     */
    public function getEventStat() : array
    {
        return $this->eventStat;
    }

    /**
     * Parent constructor
     *
     * EApmEventBase constructor.
     */
    public function __construct(?EApmEventBase $parentEvent)
    {
        $this->setEventType(static::EVENT_TYPE);
        $this->setComposer(EApmContainer::make("GAgent"));
        $this->eventRegister($parentEvent);
        register_shutdown_function([$this, "end"]);
    }

    /**
     * Set parent Event
     * @param EApmEventBase $parentEvent
     */
    public function setParent(EApmEventBase $parentEvent) : void
    {
        $this->setTraceId($parentEvent->getTraceId());
        $this->setParentId($parentEvent->getId());
    }

    /**
     * Get a random and unique span id
     *
     * @return string
     */
    public function getRandomAndUniqueSpanId() : string
    {
        do {
            $spanId = EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::SPANID_LENGTH / 2);
        } while(in_array($spanId, array_keys($this->registeredEvents)));
        return $spanId;
    }

    /**
     * Update current event status and duration
     * @return void
     */
    public function updateRegisteredEvent() : void
    {
        if (isset($this->registeredEvents[$this->getId()])) {
            $this->registeredEvents[$this->getId()]["started"] = $this->isStarted();
            $this->registeredEvents[$this->getId()]["ended"] = $this->isEnded();
            $this->registeredEvents[$this->getId()]["duration"] = $this->getDuration();
        }
    }

    /**
     * Registered all the event
     *
     * @param EApmEventBase null|$event
     * @return void
     */
    public function eventRegister(?EApmEventBase $parentEvent) : void
    {
        if (is_null($this->getId())) {
            $this->setId($this->getRandomAndUniqueSpanId());
        }

        $eVeNtTyPe = $this->getEventType();
        $eVeNtBaSeMeTrIcS = [
            "eventType" => $eVeNtTyPe,
            "parentId"  => $this->getParentId() ?? "",
            "traceId"   => $this->getTraceId(),
            "ended"     => $this->isEnded(),
            "started"   => $this->isStarted(),
            "duration"  => $this->getDuration(),
        ];

        // extra statistic
        switch ($eVeNtTyPe) {
            case EApmTransaction::EVENT_TYPE:
                $eVeNtBaSeMeTrIcS["eventExtra"] = [
                    "name" => $this->getName(),
                    "type" => $this->getType(),
                ];
                break;
            case EApmSpan::EVENT_TYPE:
                $eVeNtBaSeMeTrIcS["eventExtra"] = [
                    "name"   => $this->getName(),
                    "action" => $this->getAction(),
                ];
                break;
            default:
                $eVeNtBaSeMeTrIcS["eventExtra"] = array();
                break;
        }

        if (!is_null($parentEvent)) {
            $parentId = $parentEvent->getId();
            $this->registeredEvents[$parentId]["childEvent"] = array();
            $this->registeredEvents[$parentId]["childEvent"][] = $this->getId();
        }

        $this->registeredEvents[$this->getId()] = $eVeNtBaSeMeTrIcS;
    }

    /**
     * Current event stat
     *
     * @return array
     */
    public function eventStat() : array
    {
        return array_merge(
            $this->getParentEventStat($this->getId()),
            $this->getChildEventStat($this->getId())
        );
    }

    /**
     * @return array
     */
    public function getChildEventStat(string $spanId) : array
    {
        $childEventStat = [];
        $eventRegistered = $this->registeredEvents[$spanId];
        if ($childSpanId = $eventRegistered["childEvent"]) {
            foreach ($childSpanId as $spanId) {
                $childEventStat[] = $this->registeredEvents[$spanId];
            }
        }
        return $childEventStat;
    }

    /**
     * @return array
     */
    public function getParentEventStat(string $spanId) : array
    {
        static $parentEventStat = [];
        $eventRegistered = $this->registeredEvents[$spanId];
        if ($eventRegistered["parentId"]) {
            $parentEventStat = $this->getParentEventStat($eventRegistered["parentId"]);
        }
        array_unshift($parentEventStat, $eventRegistered);
        return $parentEventStat;
    }
}