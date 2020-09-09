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

namespace EApmPhp\Transaction;

use EApmPhp\EApmComposer;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Util\ShutdownFunctionUtil;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\ElasticApmConfigUtil;

/**
 * Class EApmTransaction
 *
 * Current transaction operations
 */
class EApmTransaction
{
    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

    /**
     * The Span-Id of current transaction
     * @var
     */
    protected $id;

    /**
     * Transaction name
     * @var
     */
    protected $name;

    /**
     * Transaction type
     * @var
     */
    protected $type;

    /**
     * Current transaction is started
     * @var
     */
    protected $isStarted = false;

    /**
     * Set the Span-Id of current transaction
     *
     * @param string $id
     */
    public function setId(string $id) : void
    {
        $this->id = $id;
    }

    /**
     * Get the Span-Id of current transaction
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Set the name of current transaction
     *
     * @param string $name
     */
    public function setName(string $name) : void
    {
        $this->name = $name;
    }

    /**
     * Get the name of current transaction
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Set the type of current transaction
     *
     * @param string $type
     */
    public function setType(string $type) : void
    {
        $this->type = $type;
    }

    /**
     * Get the type of current transaction
     *
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

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
     * Get distribute trace id
     *
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->getComposer()->getDistributeTrace()->getTraceId();
    }

    /**
     * End a transaction
     *
     * @return void
     */
    public function end() : void
    {

    }

    /**
     * Current transaction is started
     *
     * @return bool
     */
    public function isStarted() : bool
    {
        return $this->isStarted;
    }
}