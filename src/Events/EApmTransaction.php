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

use EApmPhp\Base\EApmContainer;
use EApmPhp\EApmComposer;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Base\EApmEventBase;
use EApmPhp\Util\EApmRequestUtil;
use EApmPhp\Util\ShutdownFunctionUtil;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\ElasticApmConfigUtil;

/**
 * Transaction event
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
     * transaction result
     * @var
     */
    protected $result;

    /**
     * get result
     * @return string
     */
    public function getTransactionResult() : string
    {
        return $this->result;
    }

    /**
     * set result
     * @param string $result
     * @return void
     */
    public function setTransactionResult(string $result)
    {
        $this->checkContextFieldLength($result, "");
        $this->result = $result;
    }

    /**
     * EApmTransaction constructor.
     * @param string $name
     * @param string $type
     * @param EApmEventBase|null $parentEvent
     * @throws \Exception
     */
    public function __construct(string $name, string $type, ?EApmEventBase $parentEvent)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setStarted();

        $this->setComposer(EApmContainer::make("GAgent"));
        if (!is_null($parentEvent)) {
            $this->setParent($parentEvent);
        } else {
            if ($this->getComposer()->getDistributeTrace()->getHasValidTrace()) {
                $this->setTraceId(
                    $this->getComposer()->getDistributeTrace()->getTraceId()
                );
                $this->setParentId(
                    $this->getComposer()->getDistributeTrace()->getParentSpanId()
                );
            } else {
                $this->setTraceId(
                    EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::TRACEID_LENGTH / 2)
                );
            }
        }
        // prepare result
        $this->setTransactionResult("200");

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
        if (empty(self::$registeredEvents)) {
            foreach (self::$registeredEvents as $event) {
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
                "name" => $this->getName(),
                "type" => $this->getType(),
                "id" => $this->getId(),
                "timestamp" => $this->getTimestamp(),
                "trace_id" => $this->getTraceId(),
                "parent_id" => $this->getParentId(),
                "sample_rate" => $this->getComposer()->getConfigure()->getAppConfig("sample_rate"),
                "span_count" => [
                    "started" => $this->getStartedSpans(),
                    "dropped" => 0,
                ],
                "context" => $this->getEventContext(),
                "duration" => $this->getDuration(),
                "result" => $this->getTransactionResult(),
                "marks" => null,
            ],
        ];
    }
}