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

use EApmPhp\Base\EApmEventBase;

/**
 * Class EApmSpan
 * @package EApmPhp\Events
 */
class EApmSpan extends EApmEventBase implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "span";

    /**
     * @var
     */
    protected $action = null;

    /**
     * @var
     */
    protected $subtype;

    /**
     * Set action of the span
     * @param string $action
     */
    public function setAction(string $action) : void
    {
        $this->action = $action;
    }

    /**
     * Get action of th span
     * @return string
     */
    public function getAction() : string
    {
        return $this->action;
    }

    /**
     * Set subtype of the span
     * @param string $subtype
     */
    public function setSubtype(string $subtype) : void
    {
        $this->subtype = $subtype;
    }

    /**
     * Get subtype of th span
     * @return string
     */
    public function getSubtype() : string
    {
        return $this->subtype;
    }

    public function __construct(string $name, string $type, string $subtype, EApmEventBase $parentEvent)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setSubtype($subtype);

        parent::__construct($parentEvent);
    }

    /**
     * Json serialize span event object
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     * @return array
     */
    public function jsonSerialize() : array
    {
        return array(
            "span" => [
                "timestamp" => $this->getTimestamp(),
                "type" => $this->getType(),
                "subtype" => $this->getSubtype(),
                "id" => $this->getId(),
                "transaction_id" => $this->getParentId(),
                "trace_id" => $this->getTraceId(),
                "parent_id" => $this->getParentId(),
                "child_ids" => $this->getChildSpanIds(),
                "start" => null,
                "context" => $this->getEventContext(),
                "action" => $this->getAction() ?? "",
                "name" => $this->getName(),
                "duration" => $this->getDuration(),
                "stacktrace" => null,
                "sync" => false,
                "sample_rate" => $this->getComposer()->getConfigure()->getAppConfig("sample_rate"),
            ],
        );
    }
}