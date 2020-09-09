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

namespace EApmPhp;

use EApmPhp\Util\EApmUtil;

/**
 * Class EApmConfigure
 * @package EApmPhp
 */
class EApmConfigure
{
    /**
     * Elastic apm server url
     * @var
     */
    private $serverUrl;

    /**
     * Elastic apm secret token
     * @var
     */
    private $secretToken;

    /**
     * Elastic apm service name
     * @var
     */
    private $serviceName;

    /**
     * EApmConfigure constructor.
     */
    public function __construct(?string $serverUrl, ?string $secretToken, ?string $serviceName)
    {
        $this->setServerUrl($serverUrl);
        $this->setSecretToken($secretToken);
        $this->setServiceName($serviceName);
    }

    /**
     * EApmConfigure callStatic function.
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        // TODO: Implement __callStatic() method.
        return null;
    }

    /**
     * @return null|string
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * @param null|string $serviceName
     */
    public function setServiceName(?string $serviceName): void
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return null|string
     */
    public function getSecretToken(): ?string
    {
        return $this->secretToken;
    }

    /**
     * @param null|string $secretToken
     */
    public function setSecretToken(?string $secretToken): void
    {
        $this->secretToken = $secretToken;
    }

    /**
     * @return null|string
     */
    public function getServerUrl(): ?string
    {
        return $this->serverUrl;
    }

    /**
     * @param null|string $serverUrl
     */
    public function setServerUrl(?string $serverUrl): void
    {
        $this->serverUrl = $serverUrl;
    }

    /**
     * Get configuration
     *
     * @param string $configName
     * @return string|null
     */
    public function getConfiguration(string $configName) : ?string
    {
        return call_user_func([$this, "get".EApmUtil::wordUppercaseFirst($configName)]);
    }
}