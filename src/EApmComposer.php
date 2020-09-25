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

use EApmPhp\Base\EApmContainer;
use EApmPhp\Base\EApmEventBase;
use EApmPhp\Component\EApmLogger;
use EApmPhp\Events\EApmError;
use EApmPhp\Events\EApmSpan;
use EApmPhp\Trace\EApmDistributeTrace;
use EApmPhp\Events\EApmTransaction;
use EApmPhp\Util\ElasticApmConfigUtil;
use EApmPhp\Util\EApmRandomIdUtil;
use EApmPhp\Util\EApmRequestUtil;
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
    public const OFFICIAL_TRANSACTION_LIBRARY = "elastic-apm-php-agent";

    /**
     * Agent version
     * @const
     */
    public const AGENT_VERSION = "0.0.1";

    /**
     * Agent name
     * @const
     */
    public const AGENT_NAME = "eapm-php";

    /**
     * Guzzle client to send event
     * @var
     */
    public static $eventClient = null;

    /**
     * EApmMiddleware object
     *
     */
    protected $middleware = null;

    /**
     * EApmDistributeTrace object
     *
     */
    protected $distributeTrace = null;

    /**
     * EApmConfigure object
     *
     */
    protected $configure = null;

    /**
     * Events library
     * @var
     */
    protected $library;

    /**
     * Current transaction
     * @var
     */
    protected $currentTransaction = null;

    /**
     * Logger
     * @var
     */
    protected $logger = null;

    /**
     * EApmEventIntake object
     * @var
     */
    protected $eventIntake;

    /**
     * @var bool
     */
    protected $eventPushed = false;

    /**
     * EApmComposer constructor.
     * @param string|null $library
     * @param array|null $defaultMiddwareOpts
     */
    public function __construct(?array $defaultMiddlewareOpts = null, ?string $library = null)
    {
        $this->setLibrary($library ?? self::DEFAULT_TRANSACTION_LIBRARY);
        $this->prepareBinds($defaultMiddwareOpts ?? []);

        $this->setDistributeTrace(EApmContainer::make("distributeTrace"));
        $this->getDistributeTrace()->setComposer($this);

        $this->setMiddleware(EApmContainer::make("middleware"));
        $this->getMiddleware()->setDistributeTrace($this->getDistributeTrace());
        $this->getMiddleware()->parseDefaultMiddlewareOptions();

        $this->setEventIntake(EApmContainer::make("eventIntake"));
        $this->getEventIntake()->setComposer($this);

        $this->setLogger(EApmContainer::make("logger"));
        $this->getLogger()->setComposer($this);

        EApmContainer::bind("GAgent", function() {
            return $this;
        });
    }

    /**
     * @return void
     */
    public function prepareBinds(array $opt) : void
    {
        EApmContainer::prepareBinds();
        EApmContainer::prepareBindMiddleware($opt, $this->getLibrary());
    }

    /**
     * @param EApmEventIntake $eventIntake
     */
    public function setEventIntake(EApmEventIntake $eventIntake) : void
    {
        $this->eventIntake = $eventIntake;
    }

    /**
     * @return EApmEventIntake
     */
    public function getEventIntake() : EApmEventIntake
    {
        return $this->eventIntake;
    }

    /**
     * @param EApmLogger $logger
     */
    public function setLogger(EApmLogger $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * @return EApmLogger
     */
    public function getLogger() : EApmLogger
    {
        return $this->logger;
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
     * Add a new middleware
     * @param \Closure $middleware
     */
    public function addMiddleware(\Closure $middleware) : void
    {
        $this->getMiddleware()->addMiddleWares($middleware);
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
     * Set user id
     *
     * @param int $userId
     *
     * @return void
     */
    public function setUserId(int $userId) : void
    {
        $this->getConfigure()->setUserId($userId);
    }

    /**
     * Set sample rate
     *
     * @param float $sampleRate
     *
     * @return void
     */
    public function setSampleRate(float $sampleRate) : void
    {
        $this->getConfigure()->setSampleRate($sampleRate);
    }

    /**
     * Get current transaction object
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
    public function getDefaultTransactionName() : string
    {
        return ($this->getConfiguration("service_name")) . date("YmdH");
    }

    /**
     * project invoke
     *
     * @param callable $call|null
     *
     * @return void
     */
    public function EApmUse(?callable $call = null) : void
    {
        $this->getMiddleware()->middlewareInvoke($call);
    }

    /**
     * Set app configuration
     *
     * @param string $configName
     * @param $configValue
     *
     * @return void
     */
    public function setAppConfig(string $configName, $configValue) : void
    {
        $this->getConfigure()->setAppConfig($configName, $configValue);
    }

    /**
     * Start a new transaction.
     * This new transaction offer a whole-life Trace-Id
     *
     * @param string $name
     * @param string $type
     *
     * @return EApmTransaction
     */
    public function startNewTransaction(string $name, string $type)
    {
        if (is_null($this->currentTransaction)) {
            if ($this->getLibrary() == self::DEFAULT_TRANSACTION_LIBRARY) {
                $this->currentTransaction = $this->createNewTransaction($name, $type);
            } else {
                $this->currentTransaction = ElasticApm::beginCurrentTransaction($name, $type);
            }
        }
        $this->setTraceResponseHeader();
        $this->currentTransaction->setIsRootEvent();
        $this->currentTransaction->updateRegisteredEvent();

        return $this->currentTransaction;
    }

    /**
     * Create a new transaction
     *
     * @param string $name
     * @param string $type
     * @param EApmEventBase|null $parentEvent
     *
     * @return EApmTransaction
     */
    public function createNewTransaction(string $name, string $type, ?EApmEventBase $parentEvent = null) : EApmTransaction
    {
        $transaction = new EApmTransaction($name, $type, $parentEvent);

        // parent trace context
        if (is_null($parentEvent) && $this->getDistributeTrace()->getHasValidTrace()) {
            $transaction->setTraceId($this->getDistributeTrace()->getTraceId());
            $transaction->setParentId($this->getDistributeTrace()->getParentSpanId());
        } else {
            $transaction->setTraceId(
                EApmRandomIdUtil::RandomIdGenerate(EApmDistributeTrace::TRACEID_LENGTH / 2)
            );
        }

        return $transaction;
    }

    /**
     * Start a new apm span
     *
     * @param string $name
     * @param string $type
     * @param string $subType
     * @param EApmEventBase $parentEvent
     *
     * @return EApmSpan
     */
    public function startNewSpan(string $name, string $type, string $subType, EApmEventBase $parentEvent) : EApmSpan
    {
        return new EApmSpan($name, $type, $subType, $parentEvent);
    }

    /**
     * Capture a new Throwable error|exception
     *
     * @param \Throwable $error
     * @param EApmEventBase $parentEvent
     *
     * @return void
     */
    public function captureError(\Throwable $error, EApmEventBase $parentEvent) : void
    {
        if ($this->getConfigure()->getAppConfig("debug")) {
            $this->getLogger()->logError($error->getMessage());
        }
        $this->addEvent(new EApmError($error, $parentEvent));
    }

    /**
     * Gets the ID of the transaction. Events ID is a hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
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

        while (EApmRequestUtil::calTracestateLength($tracestate)
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
        @header("traceresponse: " . $this->getDistributeTrace()->getTraceResponseHeader());
    }

    /**
     * Get whole current transaction traceparent info
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
     * Get the specific APM configuration.
     *
     * @param string $configName
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

    /**
     * Add a new APM event object.
     * These events will be sent to APM server.

     * @param EApmEventBase $event
     *
     * @return void
     */
    public function addEvent(EApmEventBase $event) : void
    {
        $this->getEventIntake()->addEvent($event);
    }

    /**
     * Push all the events to the APM server.
     *
     * @return void
     */
    public function eventsPush() : void
    {
        if (is_null($this->getConfigure())) {
            $this->setConfigure(new EApmConfigure("", "", "eapm-php-project"));
        }

        if (!$this->eventPushed) {
            if (extension_loaded("pcntl")) {
                $pid = @pcntl_fork();
                if ($pid == 0) {
                    $this->getEventIntake()->eventPush();
                    exit();
                }
            } else {
                $this->getEventIntake()->eventPush();
            }
            $this->eventPushed = true;
        }
    }

    /**
     * Send all the events to APM server
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function pingApmServer() : \Psr\Http\Message\ResponseInterface
    {
        return $this->getEventIntake()->pingApmServer();
    }

    /**
     * Get next request trace headers
     *
     * @return array
     */
    public function getNextRequestTraceHeaders() : array
    {
        return $this->getDistributeTrace()->getNextRequestTraceHeaders();
    }

    /**
     * __destructor
     */
    public function __destruct()
    {
        if (!$this->eventPushed) {
            $this->eventsPush();
            $this->eventPushed = true;
        }
    }
}