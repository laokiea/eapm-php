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
    protected $action;

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
     * Json serialize transaction event object
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     * @return array
     */
    public function jsonSerialize()
    {

    }
}