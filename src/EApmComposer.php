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

use EApmPhp\EApmMiddleware;

/**
 * Class EApmComposer
 * EApm-Php composer
 *
 */
class EApmComposer
{
    /**
     * EApmMiddleware object
     *
     */
    protected $middleware;

    /**
     * EApmComposer constructor.
     *
     */
    public function __construct(array $defaultMiddwareOpts = [])
    {
        $this->setMiddleware(new EApmMiddleware($defaultMiddwareOpts));
    }

    /**
     * Set middleware object
     *
     * @var \EApmPhp\EApmMiddleware
     */
    public function setMiddleware(EApmMiddleware $middleware) : void
    {
        $this->middleware = $middleware;
    }

    /**
     * Get middleware object
     *
     * @return \EApmPhp\EApmMiddleware
     */
    public function getMiddleware() : EApmMiddleware
    {
        return $this->middleware;
    }

    /**
     * project
     *
     */
    public function EApmUse(callable $call = null) {
        $this->getMiddleware()->middlewareInvoke($call);
    }
}