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
use EApmPhp\EApmComposer;
use EApmPhp\Util\EApmServiceUtil;

/**
 * Class EApmMetadata
 * @package EApmPhp\Events
 */
class EApmMetadata extends EApmEventBase implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "metadata";

    /**
     * Json serialize transaction event object
     * @link https://www.elastic.co/guide/en/apm/server/master/metadata-api.html
     * @return array
     */
    public function jsonSerialize()
    {
        $argv = isset($argv) ? $argv : null;
        return [
            "metadata" => [
                "service" => [
                    "agent" => [
                        "name" => EApmComposer::AGENT_NAME,
                        "version" => EApmComposer::AGENT_VERSION,
                        "ephemeral_id" => null,
                    ],
                    "framework" => [
                        "name" => $this->getComposer()->getConfigure()->getAppConfig("framework") ?? "",
                        "version" => $this->getComposer()->getConfigure()->getAppConfig("framework_version") ?? "",
                    ],
                    "language" => [
                        "name" => "php",
                        "version" => phpversion(),
                    ],
                    "name" => $this->getComposer()->getConfigure()->getServiceName() ?? EApmServiceUtil::speculateServiceName(),
                    "environment" => $this->getComposer()->getConfigure()->getAppConfig("environment"),
                    "version" => $this->getComposer()->getConfigure()->getAppConfig("service_version"),
                    "runtime" => null,
                ],
                "process" => [
                    "pid" => getmypid(),
                    "title" => PHP_SAPI == "cli" ? cli_get_process_title() : "",
                    "argv" => $argv,
                ],
                "system" => [
                    "hostname" => gethostname(),
                    "platform" => PHP_OS,
                ],
                "user" => [
                    "id" => $this->getComposer()->getConfigure()->getUserId(),
                ],
            ],
        ];
    }
}