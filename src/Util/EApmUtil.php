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

namespace EApmPhp\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EApmUtil
{
    /**
     * This function returns all http request headers
     * @return array
     */
    public static function getAllHttpHeaders() : array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $value = trim($value);
                $headerName = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
                if ($headerName === "tracestate") {
                    $headers[$headerName][] = $value;
                } else {
                    $headers[$headerName] = $value;
                }
            }
        }
        return $headers;
    }

    /**
     * Calculate the length of tracestate header
     * Notice that parameter $tracestate is should a copy of original tracestate headers
     *
     * @param array $tracestate
     * @return int
     */
    public static function calTracestateLength(array $tracestate) : int
    {
        $tracestateLength = 0;
        if (!empty($tracestate)) {
            array_walk($tracestate, function($v, $k) use($tracestateLength) {
                $tracestateLength += strlen("$k=$v,");
            });
            --$tracestateLength;
        }

        return $tracestateLength;
    }
}