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
final class EApmRandomIdUtil
{
    /**
     * Generate a random id according to specific bytes size
     *
     * @param int $size
     * @return string
     */
    public static function RandomIdGenerate(int $size, bool $secure = true) : string
    {
        if ($secure) {
            return bin2hex(random_bytes($size));
        } else {
            $RandomID = "";
            for ($i = 0;$i < $size;++$i) {
                $RandomID .= sprintf("%02x", mt_rand(0, 255));
            }
            return $RandomID;
        }
    }
}