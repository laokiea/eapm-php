<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <laokiea@163.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace EApmPhp\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EApmServiceUtil
{
    /**
     * Speculate the service name
     * @return null|string
     */
    public static function speculateServiceName() : ?string
    {
        return isset($_SERVER["DOCUMENT_ROOT"])
            ? substr($_SERVER["DOCUMENT_ROOT"], strrpos($_SERVER["DOCUMENT_ROOT"], DIRECTORY_SEPARATOR) + 1)
            : null;
    }
}