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

namespace EApmPhp\Component;

use EApmPhp\EApmComposer;

/**
 * Class EApmLogger
 * @package EApmPhp\Component
 */
final class EApmLogger
{
    public const DEFAULT_LOGGER_PATH = "/data/logs/EAPM-PHP/";

    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

    /**
     * Set global composer object
     *
     * @param EApmComposer $composer
     */
    public function setComposer(EApmComposer $composer): void
    {
        $this->composer = $composer;
    }

    /**
     * Get global composer object
     *
     * @return EApmComposer
     */
    public function getComposer(): EApmComposer
    {
        return $this->composer;
    }

    /**
     * Logger
     *
     * @param string $level
     * @param string $msg
     *
     * @return void
     */
    public function appLogger(string $level, string $msg) : void
    {
        try {
            if (!file_exists(self::DEFAULT_LOGGER_PATH)) {
                mkdir(self::DEFAULT_LOGGER_PATH, 0777, true);
            }
            $loggerFile = $this->getComposer()->getConfigure()->getAppConfig("loggerFile");
            if (is_null($loggerFile)) {
                $loggerFile = self::DEFAULT_LOGGER_PATH . "eapm-php." . "$level." . date("Ymd") . ".log";
            }
            $msg = "[" . date("Y-m-d H:i:s") . "]" . $msg;
            file_put_contents($loggerFile, $msg.PHP_EOL, FILE_APPEND);
        } catch (\Exception $exception) {
            $this->getComposer()->captureError($exception,
                $this->getComposer()->getCurrentTransaction()
            );
        }
    }

    /**
     * Log warning message
     * @param string $msg
     * @return void
     */
    public function logWarn(string $msg) : void
    {
        $this->appLogger("warn", $msg);
    }

    /**
     * Log error message
     * @param string $msg
     * @return void
     */
    public function logError(string $msg) : void
    {
        $this->appLogger("error", $msg);
    }

    /**
     * Log info message
     * @param string $msg
     * @return void
     */
    public function logInfo(string $msg) : void
    {
        $this->appLogger("info", $msg);
    }
}