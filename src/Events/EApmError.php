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

/**
 * Class EApmError
 * @package EApmPhp\Events
 */
class EApmError implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "error";

    /**
     * Json serialize transaction event object
     * @link https://www.elastic.co/guide/en/apm/server/master/error-api.html
     * @return array
     */
    public function jsonSerialize()
    {

    }
}
