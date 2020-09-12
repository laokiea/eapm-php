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

namespace EApmPhp\Events;

use EApmPhp\EApmComposer;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Base\EApmEventBase;
use EApmPhp\Util\EApmUtil;
use EApmPhp\Util\ShutdownFunctionUtil;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\ElasticApmConfigUtil;

/**
 * Class EApmTransaction
 * @package EApmPhp\Events
 */
class EApmTransaction extends EApmEventBase implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "transaction";

    /**
     * Events type
     * @var
     */
    protected $type;

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
     * EApmTransaction constructor.
     */
    public function __construct(string $name, string $type, ?EApmEventBase $parentEvent)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setIsStarted();
        $this->setTimestamp(EApmUtil::getRequestStartTimestamp());
        // transaction/span id
        $this->setId($this->getRandomAndUniqueSpanId());

        if (!is_null($parentEvent)) {
            $this->setParent($parentEvent);
        } else {
            $this->setTraceId(
                EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::TRACEID_LENGTH / 2)
            );
        }

        // parent constructor
        parent::__construct($parentEvent);
    }

    /**
     * Number of correlated spans that are recorded.
     * @return int
     */
    public function getStartedSpans() : int
    {
        $recordedSpansCount = 0;
        if (empty($this->registeredEvents)) {
            foreach ($this->registeredEvents as $event) {
                if (($event["started"]
                    ||
                    ($event["ended"] && $event["duration"] > 0)
                ) && $event["type"] === "span") {
                    $recordedSpansCount++;
                }
            }
        }
        return $recordedSpansCount;
    }

    /**
     * Json serialize transaction event object
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/transaction-api.html
     * @return array
     */
    public function jsonSerialize() : array
    {
        return [
            "transaction" => [
                "id" => $this->getId(),
                "trace_id" => $this->getTraceId(),
                "parent_id" => $this->getParentId(),
                "sample_rate" => $this->getComposer()->getConfigure()->getAppConfig("sample_rate"),
                "span_count" => [
                    "started" => $this->getStartedSpans(),
                    "dropped" => 0,
                ],
            ],
        ];
    }
}