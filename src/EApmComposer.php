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

use EApmPhp\EApmMiddleware;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Transaction\EApmTransaction;
use EApmPhp\Util\ElasticApmConfigUtil;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\EApmUtil;
use Elastic\Apm\TransactionInterface;
use Elastic\Apm\ElasticApm;

/**
 * Class EApmComposer
 * EApm-Php composer
 *
 */
class EApmComposer
{
    /**
     * Default transaction type
     * @const
     */
    public const DEFAULT_TRANSACTION_TYPE = "eapm-php-type";

    /**
     * Default transaction library
     * @const
     */
    public const DEFAULT_TRANSACTION_LIBRARY = "eapm-php";

    /**
     * official transaction library
     * @const
     */
    public const OFFICIAL_TRANSACTION_LIBRARY = "elastic-apm-php";


    /**
     * EApmMiddleware object
     *
     */
    protected $middleware;

    /**
     * EApmDistributeTrace object
     *
     */
    protected $distributeTrace;

    /**
     * EApmConfigure object
     *
     */
    protected $configure;

    /**
     * Transaction library
     * @var
     */
    protected $library;

    /**
     * Current transaction
     * @var
     */
    protected $currentTransaction = null;

    /**
     * EApmComposer constructor.
     *
     */
    public function __construct(?array $defaultMiddwareOpts, ?string $library)
    {
        $this->setLibrary($library ?? self::DEFAULT_TRANSACTION_LIBRARY);
        // distribute trace
        $this->setDistributeTrace(new EApmDistributeTrace());
        $this->getDistributeTrace()->setComposer($this);
        // middleware
        $this->setMiddleware(new EApmMiddleware($defaultMiddwareOpts ?? [], $this->getLibrary()));
        $this->getMiddleware()->setDistributeTrace($this->getDistributeTrace());
        $this->getMiddleware()->parseDefaultMiddlewareOptions();
    }

    /**
     * Set transaction library
     */
    public function setLibrary(string $library) : void
    {
        $this->library = $library;
    }

    /**
     * Get transaction library
     */
    public function getLibrary() : string
    {
        return $this->library;
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
     * Set configure object
     *
     * @var \EApmPhp\EApmConfigure
     */
    public function setConfigure(EApmConfigure $configure) : void
    {
        $this->configure = $configure;
    }

    /**
     * Get configure object
     *
     * @return \EApmPhp\EApmConfigure
     */
    public function getConfigure() : EApmConfigure
    {
        return $this->configure;
    }

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
     * Get current transaction object
     *
     */
    public function getCurrentTransaction()
    {
        return $this->currentTransaction
            ?? $this->startNewTransaction(
                $this->getDefaultTransactionName(),
                self::DEFAULT_TRANSACTION_TYPE
            );
    }

    /**
     * Get default transaction name according to service_name
     *
     * @return string
     */
    public function getDefaultTransactionName(): string
    {
        return ($this->getConfiguration("service_name")) . date("YmdH");
    }

    /**
     * project invoke
     */
    public function EApmUse(callable $call = null) {
        $this->getMiddleware()->middlewareInvoke($call);
    }

    /**
     * Start new transaction
     */
    public function startNewTransaction(string $name, string $type)
    {
        if (!is_null($this->currentTransaction) || !$this->getCurrentTransaction()->isStarted()) {
            if ($this->getLibrary() == self::DEFAULT_TRANSACTION_LIBRARY) {
                $this->createNewTransaction($name, $type);
            } else {
                $this->currentTransaction = ElasticApm::beginCurrentTransaction($name, $type);
            }
            $this->isStarted = true;
            register_shutdown_function([$this, "endCurrentTransaction"]);
        }
        $this->setTraceResponseHeader();

        return $this->currentTransaction;
    }

    /**
     * Create a new transaction
     *
     * @return EApmTransaction
     */
    public function createNewTransaction(string $name, string $type) : EApmTransaction
    {
        $transaction = new EApmTransaction();
        $transaction->setName($name);
        $transaction->setType($type);
        $transaction->setId(EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::SPANID_LENGTH / 2));
        // parent trace context
        if (!$this->getDistributeTrace()->getHasValidTrace()) {
            $this->getDistributeTrace()->setTraceId(
                EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::TRACEID_LENGTH / 2)
            );
        }
        $transaction->setComposer($this);
        $this->currentTransaction = $transaction;
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
     * Get combined tracestate header
     * The new key-value pair MUST be added to the beginning (left) of the list.
     *
     * @return string
     */
    public function getCombinedTracestateHeader() : string
    {
        $combinedHeader = "";
        $serviceName = $this->getConfiguration("service_name");
        $this->getDistributeTrace()->addValidTracestate($serviceName,
            base64_encode($this->getCurrentTransactionSpanId()));
        $tracestate = $this->getDistributeTrace()->getValidTracestate();

        while (EApmUtil::calTracestateLength($tracestate)
            > EApmDistributeTrace::TRACESTATE_COMBINED_HEADER_MAX_LENGTH) {
            array_pop($tracestate);
        }

        foreach ($tracestate as $memberName => $member) {
            $combinedHeader .= "$memberName=$member,";
        }
        $combinedHeader = substr($combinedHeader, 0, -1);

        return $combinedHeader;
    }

    /**
     * Vendors MAY choose to include a traceresponse header on any response
     * regardless of whether or not a traceparent header was included on the request.
     *
     * @return void
     */
    public function setTraceResponseHeader() : void
    {
        header("traceresponse: " . $this->getDistributeTrace()->getTraceResponseHeader());
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
            $this->getCurrentTransaction()->getTraceId(),
            $this->getCurrentTransactionSpanId(),
            EApmDistributeTrace::DEFAULT_TRACE_FLAG
        ));
    }

    /**
     * Register a function to end transaction when script exit
     *
     * @return void
     */
    public function endCurrentTransaction() : void
    {
        try {
            if ($this->getCurrentTransaction()->isStarted()) {
                $this->getCurrentTransaction()->end();
            }
        } catch (\Exception $exception) {return;}
    }

    /**
     * Get apm configuration
     *
     * @return string
     */
    public function getConfiguration(string $configName) : string
    {
        if ($this->library == self::OFFICIAL_TRANSACTION_LIBRARY) {
            return ElasticApmConfigUtil::getElasticApmConfig($configName);
        } elseif ($this->library == self::DEFAULT_TRANSACTION_LIBRARY) {
            return $this->getConfigure()->getConfiguration($configName);
        }
    }
}