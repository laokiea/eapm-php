<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <sashengpeng@blued.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EApmPhp;

use RuntimeException;
use InvalidArgumentException;
use EApmPhp\Trace\EApmDistributeTrace;

/**
 * Class EApmMiddleware
 * EApm-Php middleware package
 *
 */
class EApmMiddleware {
    /**
     * default middleware options
     *
     * @var array
     */
    protected $defaultMiddlewareOptions = array(
        "parseDistributeHeaders" => true,
        "EApmExtensionCheck" => true,
    );

    /**
     * default middleware options name
     *
     * @var array
     */
    protected $defaultMiddlewareOptionsName = array(
        "parseDistributeHeaders",
        "EApmExtensionCheck",
    );
    /**
     * middleware
     *
     * @var array
     */
    protected $middleware = array();

    /**
     * valid transparent header
     *
     * @var array
     */
    protected $validTransparent = array();

    /**
     * EApmMiddleware constructor.
     *
     */
    public function __construct(array $defaultMiddwareOpts = [])
    {
        if (!empty($defaultMiddwareOpts)) {
            foreach ($defaultMiddwareOpts as $middlewareName => $opt) {
                if (!in_array($middlewareName, $this->defaultMiddlewareOptionsName)) {
                    throw new InvalidArgumentException("Undefined middleware name");
                } else {
                    $this->defaultMiddlewareOptions[$middlewareName] = (bool)$opt;
                }
            }
        }

        $this->parseDefaultMiddlewareOptions();
    }

    /**
     * get valid transparent
     *
     * @return array
     */
    public function getValidTransparent() : array
    {
        return $this->validTransparent;
    }

    /**
     * parse default middleware options
     *
     * @return void
     */
    private function parseDefaultMiddlewareOptions() : void
    {
        if (!empty($this->defaultMiddlewareOptions)) {
            foreach ($this->defaultMiddlewareOptions as $middlewareName => $opt) {
                if ($opt === true) {
                    call_user_func([$this, "add".ucfirst($middlewareName)."Middleware"]);
                }
            }
        }
    }

    /**
     * add a middleware
     *
     * @param \Closure $middleware
     * @return void
     */
    public function addMiddleWares(\Closure $middleware) : void
    {
        $middlewareFunc = new \ReflectionFunction($middleware);
        $middlewareFuncArgs = $middlewareFunc->getParameters();
        if (empty($middlewareFuncArgs)) {
            throw new RuntimeException("Middleware must has at least one callable object");
        } else {
            $nextMiddleware = $middlewareFuncArgs[0];
            if (!$nextMiddleware->isCallable()) {
                throw new RuntimeException("Middleware must has at least one callable object");
            }
        }

        array_push($this->middleware, $middleware);
    }

    /**
     * add a middleware for parsing HTTP headers
     *
     * @return void
     */
    public function addParseDistributeHeadersMiddleware() : void
    {
        $middleware = function(\Closure $next) {
            $httpHeaders = get_headers();
            foreach ($httpHeaders as $headerName => $header) {
                $lowerHeaderName = strtolower($headerName);
                $httpHeaders[$lowerHeaderName] = $header;
                if ($headerName !== $lowerHeaderName) {
                    unset($httpHeaders[$headerName]);
                }
            }

            if (isset($httpHeaders["traceparent"])) {
                //00-0af7651916cd43dd8448eb211c80319c-b9c7c989f97918e1-01
                @list(
                    $versionId,
                    $traceId,
                    $parentId,
                    $traceFlag,
                    ) = explode("-", trim($httpHeaders["traceparent"]));
                if ($versionId !== EApmDistributeTrace::SPECIFIC_VERSION) {
                    throw new RuntimeException("Distribute traceparent version is invalid");
                }

                if ($traceId === EApmDistributeTrace::TRACEID_INVALID_FORMAT ||
                    strlen($traceId) !== EApmDistributeTrace::TRACEID_LENGTH ||
                    !EApmDistributeTrace::checkHexChar($traceId)
                ) {
                    throw new RuntimeException("Distribute traceparent traceid is invalid");
                }

                if ($parentId === EApmDistributeTrace::PARENT_SPANID_INVALID_FORMAT ||
                    strlen($parentId) !== EApmDistributeTrace::PARENT_SPANID_LENGTH ||
                    !EApmDistributeTrace::checkHexChar($parentId)
                ) {
                    throw new RuntimeException("Distribute traceparent spanid is invalid");
                }

                if (!EApmDistributeTrace::checkHexChar($traceFlag)) {
                    throw new RuntimeException("Distribute traceparent trace-flag is invalid");
                }

                $this->validTransparent = compact(["versionId", "traceId", "parentId", "traceFlag"]);
            }
            $next();
        };
        $this->addMiddleWares($middleware);
    }

    /**
     * add a middleware for checking elastic-apm extension
     *
     * @return void
     */
    public function addEApmExtensionCheckMiddleware() : void
    {
        $middleware = function(\Closure $next) {
            $EApmExtensionName = "elastic_apm";
            if (!extension_loaded($EApmExtensionName)) {
                throw new RuntimeException("Missing elastic-apm extension");
            }

            // apm config
            $EApmConfigs = ini_get_all($EApmExtensionName, true);
            foreach ([
                "server_url",
                "secret_token",
                "server_name",
                     ] as $configName) {
                if (is_null($EApmConfigs["$EApmExtensionName.$configName"])) {
                    if (!getenv("ELASTIC_APM_" . strtoupper($configName))) {
                        throw new RuntimeException("$EApmExtensionName.$configName can not be null");
                    }
                }
            }

            $next();
        };
        $this->addMiddleWares($middleware);
    }

    /**
     * middleware call
     *
     * @return void
     */
    public function middlewareInvoke(callable $call = null) : void
    {
        if (!empty($this->middleware)) {
            $handle = array_reduce(array_reverse($this->middleware), function($next, $middleware) {
                return function() use($next, $middleware) {
                    $middleware($next);
                };
            }, $call ?? function(){});

            //invoke
            $handle();
        }
    }
}