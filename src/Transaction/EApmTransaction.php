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
    protected $currentTransaction = null;

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
    public function getCurrentTransaction(): TransactionInterface
    {
        return $this->currentTransaction
            ?? $this->startNewTransaction(
                $this->getDefaultTransactionName(),
                self::DEFAULT_TRANSACTION_TYPE
            );
    }


    /**
     * Start new transaction
     *
     * @return void
     */
    public function startNewTransaction(string $name, string $type) : TransactionInterface
    {
        if (!is_null($this->currentTransaction)) {
            // parent trace context
            if ($this->getComposer()->getDistributeTrace()->getHasValidTrace()) {

            } else {
                $this->currentTransaction = ElasticApm::beginCurrentTransaction($name, $type);
            }
            register_shutdown_function([$this, "endCurrentTransaction"]);
        }
        $this->setTraceResponseHeader();

        return $this->currentTransaction;
    }

    /**
     * Register a function to end transaction when script exit
     *
     * @return void
     */
    public function endCurrentTransaction() : void
    {
        try {
            $this->getCurrentTransaction()->end();
        } catch (\Exception $exception) {return;}
    }

    /**
     * Gets the ID of the transaction. Transaction ID is a hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @return string
     */
    public function getCurrentTransactionSpanId() : string
    {
        return $this->getCurrentTransaction()->getId();
    }

    /**
     * Get current transaction traceparent info
     *
     * @return string
     */
    public function getCurrentTraceResponseHeader() : string
    {
        return implode("-", array(
            EApmDistributeTrace::SPECIFIC_VERSION,
            $this->currentTransaction->getTraceId(),
            $this->getCurrentTransactionSpanId(),
            EApmDistributeTrace::DEFAULT_TRACE_FLAG
        ));
    }

    /**
     * Vendors MAY choose to include a traceresponse header on any response
     * regardless of whether or not a traceparent header was included on the request.
     *
     * @return void
     */
    public function setTraceResponseHeader() : void
    {
        header("traceresponse: " . $this->getComposer()->getDistributeTrace()->getTraceResponseHeader());
    }
}