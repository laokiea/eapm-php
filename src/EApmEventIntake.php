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

use EApmPhp\Base\EApmEventBase;
use EApmPhp\Events\EApmMetadata;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

/**
 * Class EApmEventIntake
 * @package EApmPhp
 */
class EApmEventIntake
{
    /**
     * Event push v2 endpoint
     * @const string
     */
    private const EVEN_INTAKE_V2_ENDPOINT = "/intake/v2/events";

    /**
     * Event push content-type header
     * @const string
     */
    private const EVENT_INTAKE_CONTENT_TYPE = "application/x-ndjson";

    /**
     * Ndjson delimiter
     * @const string
     */
    private const EVENT_INTAKE_NEWLINE_DELIMITED_JSON_DELIMITER = "\n";

    /**
     * @const float
     */
    private const EVENT_PUSH_REQUEST_TIMEOUT = 1.0;

    /**
     * @const int
     */
    private const EVENT_PUSH_SUCCESS_ACCEPTED_STATUS_CODE = 202;

    /**
     * Guzzle client
     * @var
     */
    private $client = null;

    /**
     * Guzzle async handler basement
     * @var
     */
    private $asyncHandlerBase = null;

    /**
     * Events
     * @var array
     */
    private $events = array();

    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

    /**
     * @var bool
     */
    protected $metadataSet = false;

    /**
     * EApmEventIntake constructor.
     * @param EApmComposer|null $composer
     */
    public function __construct(?EApmComposer $composer = null)
    {
        $this->client = $this->getEventClient();
        if (!is_null($composer)) {
            $this->setComposer($composer);
        }
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
     * @return bool
     */
    public function isSetMetadata() : bool
    {
        return $this->metadataSet;
    }

    /**
     * @return void
     */
    public function metadataSated() : void
    {
        $this->metadataSet = true;
    }

    /**
     * Add an event(transaction/span/error/metadata)
     * @param EApmEventBase $event
     * @param bool $preinsert
     */
    public function addEvent(EApmEventBase $event, $preinsert = false) : void
    {
        if (!$event instanceof \JsonSerializable) {
            throw new RuntimeException("Event must implements class JsonSerializable.");
        }

        if (!$preinsert) {
            $this->events[] = json_encode($event);
        } else {
            array_unshift($this->events, json_encode($event));
        }
    }

    /**
     * Reset events array after pushing
     * @return void
     */
    public function eventsReset() : void
    {
        /*
        if (count($this->events) > 1) {
            $this->>events = array_slice($this->events, 0, 1);
        }
        */
        $this->events = array();
    }

    /**
     * Return a guzzle client
     * @return Client
     */
    public function getEventClient() : Client
    {
        if (is_null($this->client)) {
            $this->asyncHandlerBase = new CurlMultiHandler();
            $newRequestHandler = HandlerStack::create($this->asyncHandlerBase);

            $this->client = new Client([
                "timeout" => self::EVENT_PUSH_REQUEST_TIMEOUT,
                "handler" => $newRequestHandler,
            ]);
        }

        return $this->client;
    }

    /**
     * Get event push url
     *
     * @return string
     */
    public function getEventPushServerUrl() : string
    {
        $serverUrl = $this->getComposer()->getConfiguration("server_url");
        if (is_null($serverUrl)) {
            throw new RuntimeException("Server Url cannot be null");
        }

        if (preg_match("/^.*\/$/", $serverUrl)) {
            $serverUrl = substr($serverUrl, 0, -1);
        }

        return $serverUrl . self::EVEN_INTAKE_V2_ENDPOINT;
    }

    /**
     * Get intake request body
     *
     * @link https://www.elastic.co/guide/en/apm/server/master/example-intake-events.html
     * @return string
     */
    public function getIntakeRequestBody() : string
    {
        $ApMSeRvErReQuEsTbOdY = "";
        foreach ($this->events as $eventNdjson) {
            $ApMSeRvErReQuEsTbOdY .= $eventNdjson . self::EVENT_INTAKE_NEWLINE_DELIMITED_JSON_DELIMITER;
        }
        return $ApMSeRvErReQuEsTbOdY;
    }

    /**
     * Get intake request headers
     *
     * @link https://github.com/elastic/apm-agent-php/blob/master/src/ext/util_for_PHP.c#L260
     * @return array
     */
    public function getIntakeRequestHeaders() : array
    {
        $ApMSeRvErReQuEsThEaDeRs = [
            "Content-Type" => self::EVENT_INTAKE_CONTENT_TYPE,
            "User-Agent"   => sprintf("eapm-php/%s", EApmComposer::AGENT_VERSION),
        ];

        if (!is_null($this->getComposer()->getConfiguration("secret_token"))) {
            $ApMSeRvErReQuEsThEaDeRs["Authorization"] =
                sprintf("Bearer %s", $this->getComposer()->getConfiguration("secret_token"));
        }

        return $ApMSeRvErReQuEsThEaDeRs;
    }

    /**
     * Send all events to the APM server
     *
     * @return bool
     */
    public function eventPush() : bool
    {
        if (!$this->isSetMetadata()) {
            $this->addEvent(new EApmMetadata(null), true);
            $this->metadataSated();
        }

        try {
            // do nothing and drop response
            $promise = $this->getEventClient()->requestAsync("POST", $this->getEventPushServerUrl(), [
                "headers" => $this->getIntakeRequestHeaders(),
                "body" => $this->getIntakeRequestBody(),
            ])->then();

            // event loop
            $pendingLoopTimes = 0;
            while ($promise->getState() === "pending"
                && $pendingLoopTimes < $this->getComposer()->getConfigure()->getAppConfig("max_pending_loop_times")) {
                $this->asyncHandlerBase->tick();
                ++$pendingLoopTimes;
            }
        } catch (RequestException $exception) {
            if ($this->getComposer()->getConfigure()->getAppConfig("debug")) {
                $this->getComposer()->getLogger()->logError("Request Apm Failed: ".$exception->getMessage());
                echo \GuzzleHttp\Psr7\str($exception->getResponse());
            }
            return false;
        } finally {
            $this->eventsReset();
        }

        /*
        if ($response->getStatusCode() == self::EVENT_PUSH_SUCCESS_ACCEPTED_STATUS_CODE) {
            return true;
        } else {
            $this->getComposer()->getLogger()->logWarn("Request Apm Failed: ".$response->getBody()->getContents());
            return false;
        }
        */
        return true;
    }

    /**
     * Send a request to APM server
     * @return ResponseInterface
     */
    public function pingApmServer() : ResponseInterface
    {
        return $this->getEventClient()->get(
            $this->getComposer()->getConfiguration("server_url"),
            [
                "headers" => $this->getIntakeRequestHeaders(),
            ]
        );
    }
}