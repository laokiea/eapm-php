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
use EApmPhp\Events\EApmMetadata;
use EApmPhp\Events\EApmSpan;
use EApmPhp\Events\EApmTransaction;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\EApmRequestUtil;

/**
 * Class EApmEventBase, All transaction/span parent class
 * @package EApmPhp
 */
class EApmEventBase
{
    /**
     * @const
     */
    private const CONTEXT_FIELD_MAX_LENGTH = 1024;

    /**
     * Registered events collect
     * @var array
     */
    protected static $registeredEvents = [];

    /**
     * Registered events stat
     * @var array
     */
    protected $eventStat = [];

    /**
     * Events/Span context
     * Transaction: request, tags
     * Span: http, db, tags
     * Others information like service,user will sent from metadata object
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
    protected $traceId = null;

    /**
     * The Span-Id of current transaction
     * @var
     */
    protected $id = null;

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
     * Events type
     * @var
     */
    protected $type;

    /**
     * @var
     */
    protected $isRoot = false;

    /**
     * Parent constructor
     *
     * EApmEventBase constructor.
     */
    public function __construct(?EApmEventBase $parentEvent = null)
    {
        $this->setEventType(static::EVENT_TYPE);
        $this->setComposer(EApmContainer::make("GAgent"));

        if (!is_null($parentEvent)) {
            $this->setParent($parentEvent);
        }

        if ($this->getEventType() !== EApmMetadata::EVENT_TYPE) {
            $this->setTimestamp(round(microtime(true) * 1e6));
            $this->setId($this->getRandomAndUniqueSpanId());
            register_shutdown_function([$this, "end"]);
        }

        $this->eventRegister($parentEvent);
    }


    /**
     * Set the type of current transaction
     *
     * @param string $type
     */
    public function setType(string $type) : void
    {
        $this->type = $type;
    }

    /**
     * Get the type of current transaction
     *
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }


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
     * @return string|null
     */
    public function getTraceId() : ?string
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
     * @return string|null
     */
    public function getId() : ?string
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
     * @return string|null
     */
    public function getParentId() : ?string
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
     * @param float $duration
     */
    public function setDuration(float $duration) : void
    {
        $this->duration = $duration;
    }

    /**
     * Get transaction duration
     *
     * @return float|null
     */
    public function getDuration() : ?float
    {
        return $this->duration;
    }

    /**
     * Set current transaction started
     * @return void
     */
    public function setStarted() : void
    {
        $this->isStarted = true;
    }

    /**
     * Set current transaction ended
     * @return void
     */
    public function setEnded() : void
    {
        $this->isEnded = true;
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
     * Current event is ended
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
        if (!$this->isEnded()) {
            $this->setEnded();
            $this->setDuration(EApmRequestUtil::getDurationMilliseconds($this->getTimestamp()));
            $this->updateRegisteredEvent();
            $this->getComposer()->getEventIntake()->addEvent($this);
        }
    }

    /**
     * Any other arbitrary data captured by the agent, optionally provided by the user
     * Include DB, HTTP, TAGS, USERS, see link below for more
     *
     * @link https://github.com/elastic/apm-server/blob/master/docs/spec/context.json
     * @param array $context
     */
    public function setContext(array $context = []) : void
    {
        $this->context = array_merge_recursive($this->context, $context);
    }

    /**
     * Get event context
     *
     * @return array
     */
    public function getEventContext() : ?array
    {
        $context = $this->context;

        foreach ($context as $contextField => $contextObject) {
            if (empty($contextObject)) {
                unset($context[$contextField]);
            }
        }

        if ($this->getEventType() === EApmTransaction::EVENT_TYPE) {
            if (!isset($context["request"]) || empty($context["request"])) {
                $context["request"] = $this->getRequestContext();
            }
        }

        return !empty($context) ? $context : null;
    }

    /**
     * Request context
     *
     * @link https://github.com/elastic/apm-server/blob/master/docs/spec/request.json
     * @return array|null
     */
    public function getRequestContext() : ?array
    {
        if (!isset($_SERVER) || !isset($_SERVER["REQUEST_METHOD"])) {
            return null;
        }

        $rEqUeStCoNtExT = array();
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $rEqUeStCoNtExT["body"] = empty($_POST)
                ? json_encode($_POST)
                : file_get_contents("php://input");
        }
        $rEqUeStCoNtExT["env"] = $_SERVER;
        $rEqUeStCoNtExT["headers"] = EApmRequestUtil::getAllHttpHeaders();
        $rEqUeStCoNtExT["http_version"] = $_SERVER["SERVER_PROTOCOL"] ?? "";
        $rEqUeStCoNtExT["method"] = $_SERVER["REQUEST_METHOD"];
        $rEqUeStCoNtExT["cookies"] = $_COOKIE ?? [];
        $rEqUeStCoNtExT["socket"] = array(
            "encrypted" => $_SERVER["HTTPS"] ?? false,
            "remote_address" => EApmRequestUtil::getRemoteAddr(),
        );
        $rEqUeStCoNtExT["url"] = array(
            "raw" => EApmRequestUtil::getHttpRawUrl(),
            "protocol" => $_SERVER["HTTPS"] ? "https" : "http",
            "full" => EApmRequestUtil::getHttpRawUrl(),
            "hostname" => $_SERVER["SERVER_NAME"],
            "port" => $_SERVER["SERVER_PORT"],
            "pathname" => pathinfo($_SERVER["REQUEST_URI"] ?? "")["dirname"],
            "search" => $_SERVER["QUERY_STRING"] ?? "",
            "hash" => hash("sha256", EApmRequestUtil::getHttpRawUrl())
        );
        array_walk($rEqUeStCoNtExT["url"], [$this, "checkContextFieldLength"]);

        return $rEqUeStCoNtExT;
    }

    /**
     * @param $value
     * @param $key
     * @return void
     */
    public function checkContextFieldLength(&$value, $key) : void
    {
        if (mb_strlen($value) > self::CONTEXT_FIELD_MAX_LENGTH) {
            $value = mb_substr($value, 0, self::CONTEXT_FIELD_MAX_LENGTH - 1) . "…";
        }
    }

    /**
     * Set transaction start timestamp
     *
     * @param int $timestamp
     */
    public function setTimestamp(float $timestamp) : void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Get transaction start timestamp
     *
     * @return int
     */
    public function getTimestamp() : float
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
     * Set event as a root event
     *
     * @return void
     */
    public function setIsRootEvent() : void
    {
        foreach (self::$registeredEvents as $event) {
            if ($event["is_root"]) {
                return;
            }
        }
        $this->isRoot = true;
    }

    /**
     * @return bool
     */
    public function getIsRootEvent() : bool
    {
        return $this->isRoot;
    }

    /**
     * Set parent Event
     *
     * @param EApmEventBase $parentEvent
     * @return void
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
        } while(in_array($spanId, array_keys(self::$registeredEvents)));
        return $spanId;
    }

    /**
     * Update current event status and duration
     * @return void
     */
    public function updateRegisteredEvent() : void
    {
        if (isset(self::$registeredEvents[$this->getId()])) {
            self::$registeredEvents[$this->getId()]["started"] = $this->isStarted();
            self::$registeredEvents[$this->getId()]["ended"] = $this->isEnded();
            self::$registeredEvents[$this->getId()]["duration"] = $this->getDuration();
            self::$registeredEvents[$this->getId()]["is_root"] = $this->getIsRootEvent();
        }
    }

    /**
     * Get child events span ids
     * @return array
     */
    public function getChildSpanIds() : array
    {
        return self::$registeredEvents[$this->getId()]["childEvent"];
    }

    /**
     * Get Root transaction id
     * @return string
     */
    public function getEventTransactionId() : string
    {
        $recursiveEventId = $this->getId();
        while(!self::$registeredEvents[$recursiveEventId]["is_root"]
            && !is_null(self::$registeredEvents[$recursiveEventId]["parentId"])) {
            $recursiveEventId = self::$registeredEvents[$recursiveEventId]["parentId"];
        }

        return $recursiveEventId;
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
            "is_root"    => $this->getIsRootEvent(),
            "id"         => $this->getId() ?? "",
            "eventType"  => $eVeNtTyPe,
            "parentId"   => $this->getParentId() ?? "",
            "traceId"    => $this->getTraceId() ?? "",
            "ended"      => $this->isEnded(),
            "started"    => $this->isStarted(),
            "duration"   => $this->getDuration(),
            "childEvent" => array(),
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
                    "type" => $this->getType(),
                    "subtype" => $this->getSubtype(),
                    "action" => $this->getAction(),
                ];
                break;
            default:
                $eVeNtBaSeMeTrIcS["eventExtra"] = array();
                break;
        }

        if (!is_null($parentEvent)) {
            $parentId = $parentEvent->getId();
            if (!isset(self::$registeredEvents[$parentId]["childEvent"])) {
                self::$registeredEvents[$parentId]["childEvent"] = array();
            }
            self::$registeredEvents[$parentId]["childEvent"][] = $this->getId();
        }

        self::$registeredEvents[$this->getId()] = $eVeNtBaSeMeTrIcS;
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
        $eventRegistered = self::$registeredEvents[$spanId];

        if ($childSpanId = $eventRegistered["childEvent"]) {
            foreach ($childSpanId as $spanId) {
                $childEventStat[] = self::$registeredEvents[$spanId];
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
        $eventRegistered = self::$registeredEvents[$spanId];

        if ($eventRegistered["parentId"]) {
            $parentEventStat = $this->getParentEventStat($eventRegistered["parentId"]);
        }
        array_unshift($parentEventStat, $eventRegistered);

        return $parentEventStat;
    }
}