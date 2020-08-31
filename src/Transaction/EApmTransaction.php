<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <sashengpeng@blued.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EApmPhp\Transaction;

use EApmPhp\EApmComposer;
use Elastic\Apm\TransactionInterface;
use Elastic\Apm\ElasticApm;

/**
 * Class EApmTransaction
 *
 * Current transaction operations
 */
class EApmTransaction
{
    /**
     * Default transaction type
     * @const
     */
    public const DEFAULT_TRANSACTION_TYPE = "eapm-php-type";

    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

    /**
     * Current transaction
     * @var
     */
    protected $transaction = null;

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
     * Get default transaction name according to service_name
     *
     * @return string
     */
    public function getDefaultTransactionName(): string
    {
        return ($this->getComposer()->getEApmConfig("service_name")) . date("YmdH");
    }

    /**
     * Get current transaction object
     *
     * @return TransactionInterface
     */
    public function getTransaction(): TransactionInterface
    {
        return $this->transaction
            ?? ElasticApm::beginCurrentTransaction(
                $this->getDefaultTransactionName(),
                self::DEFAULT_TRANSACTION_TYPE
            );
    }


    /**
     * Start new transaction
     *
     * @return void
     */
    public function startNewTransaction(string $name, string $type) : void
    {
        if (!is_null($this->transaction)) {
            $this->transaction = ElasticApm::beginCurrentTransaction($name, $type);
            register_shutdown_function([$this, "transactionShutdownExit"]);
        }
    }

    /**
     * Register a function to end transaction when script exit
     *
     * @return void
     */
    public function transactionShutdownExit() : void
    {
        $this->getTransaction()->end();
    }

    /**
     * Gets the ID of the transaction. Transaction ID is a hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @return string
     */
    public function getCurrentTransactionSpanId() : string
    {
        return $this->getTransaction()->getId();
    }
}