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

use EApmPhp\Util\EApmRequestUtil;
use EApmPhp\Util\ElasticApmConfigUtil;
use RuntimeException;

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
    private $serverUrl = "";

    /**
     * Elastic apm secret token
     * @var
     */
    private $secretToken = "";

    /**
     * Elastic apm service name
     * @var
     */
    private $serviceName = null;

    /**
     * EApm app config
     */
    private $appConfig = array(
        "loggerFile" => null,
        "sample_rate" => 1,
        "environment" => "dev",
        "service_version" => "v0.0.1",
        "uid" => null,
        "max_pending_loop_times" => 5000,
        "env_list" => ["HTTP_HOST", "HTTP_CONNECTION", "HTTP_CACHE_CONTROL", "HTTP_USER_AGENT", "REMOTE_ADDR", "REMOTE_PORT"],
    );

    /**
     * EApmConfigure constructor.
     */
    public function __construct(?string $serverUrl = null, ?string $secretToken = null, ?string $serviceName = null)
    {
        $this->setServerUrl($serverUrl
            ?? ElasticApmConfigUtil::getElasticApmConfig("server_url"));
        $this->setSecretToken($secretToken
            ?? ElasticApmConfigUtil::getElasticApmConfig("secret_token"));
        $this->setServiceName($serviceName
            ?? ElasticApmConfigUtil::getElasticApmConfig("service_name"));
    }

    /**
     * EApmConfigure callStatic function.
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
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
        if (is_null($serverUrl)) {
            throw new RuntimeException("Server url can not be null.");
        }
        $this->serverUrl = $serverUrl;
    }

    /**
     * Set user id
     * @param int $userId
     */
    public function setUserId(int $userId) : void
    {
        $this->setAppConfig("uid", $userId);
    }

    /**
     * Get user id
     * @return int
     */
    public function getUserId() : ?int
    {
        return $this->getAppConfig("uid");
    }

    /**
     * Set sample rate
     * @param float $sampleRate
     */
    public function setSampleRate(float $sampleRate) : void
    {
        if ($sampleRate < 0 || $sampleRate > 1) {
            return;
        }
        $this->setAppConfig("sample_rate", $sampleRate);
    }

    /**
     * Get configuration
     *
     * @param string $configName
     * @return string|null
     */
    public function getConfiguration(string $configName) : ?string
    {
        return call_user_func([$this, "get".EApmRequestUtil::wordUppercaseFirst($configName)]);
    }

    /**
     * Get app configuration
     * @param string $appConfigName
     */
    public function getAppConfig(string $appConfigName)
    {
        return isset($this->appConfig[$appConfigName])
            ? $this->appConfig[$appConfigName]
            : null;
    }

    /**
     * Set app configuration
     * @param string $configName
     * @param $value
     * @return void
     */
    public function setAppConfig(string $configName, $configValue) : void
    {
        $this->appConfig[$configName] = $configValue;
    }
}