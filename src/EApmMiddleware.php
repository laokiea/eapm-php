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
     * EApmDistributeTrace object
     *
     */
    protected $distributeTrace;

    /**
     * Set distribute trace object
     *
     * @var \EApmPhp\Trace\EApmDistributeTrace
     */
    public function setDistributeTrace(EApmDistributeTrace $distributeTrace) : void
    {
        $this->distributeTrace = $distributeTrace;
    }

    /**
     * Get distribute trace object
     *
     * @return \EApmPhp\Trace\EApmDistributeTrace
     */
    public function getDistributeTrace() : EApmDistributeTrace
    {
        return $this->distributeTrace;
    }

    /**
     * middleware
     *
     * @var array
     */
    protected $middleware = array();

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
    }

    /**
     * get valid transparent
     *
     * @return array
     */
    public function getValidTraceparent() : array
    {
        return $this->validTraceparent;
    }

    /**
     * parse default middleware options
     *
     * @return void
     */
    public function parseDefaultMiddlewareOptions() : void
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
            throw new RuntimeException("Middleware must has at least one Closure object");
        } else {
            $nextMiddleware = $middlewareFuncArgs[0];
            if ($nextMiddleware->getType()->getName() !== "Closure") {
                throw new RuntimeException("Middleware must has at least one Closure object");
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
            $getallheaders = function() {
                $headers = [];
                foreach ($_SERVER as $name => $value) {
                    if (substr($name, 0, 5) == 'HTTP_') {
                        $value = trim($value);
                        $headerName = str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))));
                        if ($headerName === "tracestate") {
                            $headers[$headerName][] = $value;
                        } else {
                            $headers[$headerName] = $value;
                        }
                    }
                }
                return $headers;
            };
            $httpHeaders = $getallheaders();

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

                $this->setValidTraceparent(compact(["versionId", "traceId", "parentId", "traceFlag"]));
                $this->setHasValidTrace(true);
            }

            if (isset($httpHeaders["tracestate"]) && !empty($httpHeaders["tracestate"])) {
                $tracestate = array();
                foreach ($httpHeaders["tracestate"] as $tracestateList) {
                    $listMembers = explode(",", $tracestateList);
                    if (count($listMembers) == 0) {
                        continue;
                    }
                    if (count($listMembers) > EApmDistributeTrace::TRACESTATE_LIST_MEMBERS_MAX_NUM
                    ) {
                        throw new RuntimeException("Tracestate header can has up to 32 members");
                    }
                    foreach ($listMembers as $member) {
                        @list($memberKey, $memberValue) = explode("=", $member);
                        $tracestate[$memberKey] = $tracestate[$memberValue];
                    }
                }
                $this->setValidTracestate($tracestate);
            }

            $next();
        };
        $middleware = $middleware->bindTo($this->getDistributeTrace(), EApmDistributeTrace::class);
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
                "service_name",
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
        if (!is_null($call) && !($call instanceof \Closure)) {
            $func = $call;
            $call = function() use($func) {
                $func();
            };
        }

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