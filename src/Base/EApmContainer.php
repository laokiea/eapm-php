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

namespace EApmPhp\Base;

use RuntimeException;

/**
 * Class EApmContainer
 * @package EApmPhp\Base
 */
final class EApmContainer
{
    /**
     * @var array
     */
    public static $binds = [];

    /**
     * Container, in this class, use setter instead of using reflect
     * Bind a closure that main layer use to make resource
     * @param string $class
     * @param $concret
     */
    public static function bind(string $class, $concret)
    {
        if(!$concret instanceof \Closure) {
            if (!class_exists($concret)) {
                throw new RuntimeException("Parameter concret must be a Closure or abstract class name.");
            } else {
                $concret = function () use ($concret) {
                    return new $concret;
                };
            }
        }

        self::$binds[$class] = $concret;
    }

    /**
     * Main layer use to overture IoC
     * Return the specified resource the caller depended
     */
    public static function make(string $class)
    {
        if (isset(self::$binds[$class])) {
            $concret = self::$binds[$class];
            return $concret();
        } else {
            throw new RuntimeException("No target bind closure");
        }
    }

    public static function prepareBinds() : void
    {
        self::bind("distributeTrace", "\EApmPhp\Trace\EApmDistributeTrace");
        self::bind("eventIntake", "\EApmPhp\EApmEventIntake");
        self::bind("logger", "\EApmPhp\Component\EApmLogger");
    }

    public static function prepareBindMiddleware(array $opt, string $library) : void
    {
        self::bind("middleware", function() use($opt,$library) {
            return new \EApmPhp\EApmMiddleware($opt, $library);
        });
    }
}