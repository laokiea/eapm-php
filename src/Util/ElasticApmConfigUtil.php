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
final class ElasticApmConfigUtil
{
    /**
     * Get elastic apm config value specifically
     *
     * @param string $configName
     * @return string|null
     */
    public static function getElasticApmConfig(string $configName) : ?string
    {
        if (!($config = ini_get("elastic_apm.$configName"))) {
            if (!($config = getenv("ELASTIC_APM_".strtoupper($configName)))) {
                $config = null;
            }
        }

        return $config;
    }
}