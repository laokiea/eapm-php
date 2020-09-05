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
final class ShutdownFunctionUtil
{
    /**
     * push shutdown function into an array instead of calling register_shutdown_function several times
     * @var array
     */
    public static $shutdownFuncs = array();

    /**
     * Add a shutdown function
     * @param \Closure $shutdownFunc
     *
     * @return void
     */
    public static function addShutdownFunction(callable $shutdownFunc) : void
    {
        array_push(self::$shutdownFuncs, $shutdownFunc);
    }

    /**
     * Register all shutdown functions
     *
     * @return void
     */
    public static function register() : void
    {
        if (empty(self::$shutdownFuncs)) {
            foreach (self::$shutdownFuncs as $func) {
                register_shutdown_function($func);
            }
        }
    }
}