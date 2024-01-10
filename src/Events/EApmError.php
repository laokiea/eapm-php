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

namespace EApmPhp\Events;

use EApmPhp\Base\EApmEventBase;

/**
 * Class EApmError
 * @package EApmPhp\Events
 */
class EApmError extends EApmEventBase implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "error";

    /**
     * @var
     */
    private $error;

    /**
     * Get capture error
     * @return \Throwable
     */
    public function getError() : \Throwable
    {
        return $this->error;
    }

    /**
     * Set capture error
     * @param \Throwable $error
     */
    public function setError(\Throwable $error)
    {
        $this->error = $error;
    }

    /**
     * EApmError constructor.
     * @param \Throwable $error
     * @param EApmEventBase|null $parentEvent
     */
    public function __construct(\Throwable $error, EApmEventBase $parentEvent)
    {
        $this->setError($error);
        parent::__construct($parentEvent);
    }

    /**
     * get culprit context
     * @return string
     */
    public function getCulpritContext() : string
    {
        return sprintf("File: %s, Line: %s",
        $this->getError()->getFile(),
        $this->getError()->getLine()
        );
    }

    /**
     * Get error exception context
     * @return array
     * @throws \ReflectionException
     */
    public function getExceptionContext() : array
    {
        return array(
            "code" => $this->getError()->getCode(),
            "message" => $this->getError()->getMessage(),
            "module" => $this->getError()->getFile(),
            "stacktrace" => $this->getErrorStackTrace(),
            "type" => $this->getErrorType(),
        );
    }

    /**
     * Get error stack trace
     * @return array|null
     */
    public function getErrorStackTrace() : ?array
    {
        $errorStackTrace = [];
        foreach ($this->getError()->getTrace() as $fc => $oriTrace) {
            $errorStackTrace[] = [
                "abs_path" => $oriTrace["file"],
                "colno" => $fc,
                "filename" => basename($oriTrace["file"]),
                "classname" => $oriTrace["class"] ?? "",
                "function" => $oriTrace["function"] ?? "",
                "lineno" => $oriTrace["line"],
                "module" => $oriTrace["class"] ?? "",
            ];
        }

        return !empty($errorStackTrace) ? $errorStackTrace : null;
    }

    /**
     * Get error type
     * @return string
     * @throws \ReflectionException
     */
    public function getErrorType() : string
    {
        $error = $this->getError();
        $errorType = (new \ReflectionClass($error))->getName();
        $previousError = $error->getPrevious();
        while(!is_null($previousError)) {
            $errorType = (new \ReflectionClass($previousError))->getName() . "-$errorType";
            $previousError = $previousError->getPrevious();
        }

        return $errorType;
    }

    /**
     * Json serialize transaction event object
     * @link https://www.elastic.co/guide/en/apm/server/master/error-api.html
     * @return array
     * @throws \ReflectionException
     */
    public function jsonSerialize()
    {
        return [
            "error" => [
                "id" => $this->getId(),
                "transaction_id" => $this->getEventTransactionId(),
                "trace_id" => $this->getTraceId(),
                "parent_id" => $this->getParentId(),
                "transaction" => [
                    "sampled" => true,
                    "type" => "request",
                ],
                "context" => $this->getEventContext(),
                "culprit" => $this->getCulpritContext(),
                "exception" => $this->getExceptionContext(),
            ],
        ];
    }
}
