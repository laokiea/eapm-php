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

    /**
     * @param string $string
     * @return string
     */
    public static function wordUppercaseFirst(string $string, string $delimiter = "_") : string
    {
       return implode("", array_map(function($word) {
            return ucfirst($word);
        }, explode($delimiter, $string)));
    }

    /**
     * Get start time of the incoming request
     *
     * @return int
     */
    public static function getRequestStartTimestamp() : int
    {
        if (isset($_SERVER["REQUEST_TIME_FLOAT"])) {
            return intval($_SERVER["REQUEST_TIME_FLOAT"] * 1000000);
        } elseif (isset($_SERVER["REQUEST_TIME"])) {
            return $_SERVER["REQUEST_TIME"];
        } else {
            return time();
        }
    }

    /**
     * Get event duration in MilliSeconds
     * @return int
     */
    public static function getDurationMilliseconds(float $startTime) : int
    {
        return intval((microtime(true) - $startTime) * 1000);
    }
}