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

namespace EApmPhp\Events;

use EApmPhp\Base\EApmEventBase;
use RuntimeException;
use GuzzleHttp\Exception\RequestException;

/**
 * Class EApmSpan
 * @package EApmPhp\Events
 */
class EApmSpan extends EApmEventBase implements \JsonSerializable
{
    /**
     * @const
     */
    public const EVENT_TYPE = "span";

    /**
     * @const
     */
    public const DEFAULT_ERROR_HTTP_CODE = 400;

    /**
     * @var
     */
    protected $action = null;

    /**
     * @var
     */
    protected $subtype = "";

    /**
     * @var bool
     */
    protected $isSync = true;

    /**
     * Set action of the span
     * @param string $action
     */
    public function setAction(string $action) : void
    {
        $this->action = $action;
    }

    /**
     * Get action of th span
     * @return string
     */
    public function getAction() : ?string
    {
        return $this->action;
    }

    /**
     * Set subtype of the span
     * @param string $subtype
     */
    public function setSubtype(string $subtype) : void
    {
        $this->subtype = $subtype;
    }

    /**
     * Get subtype of th span
     * @return string
     */
    public function getSubtype() : string
    {
        return $this->subtype;
    }

    /**
     * Is span a sync type operation
     *
     * @return bool
     */
    public function getSpanIsSync() : bool
    {
        return $this->isSync;
    }

    /**
     * Set span operation type
     *
     * @param bool $isSync
     */
    public function setSpanIsSync(bool $isSync) : void
    {
        $this->isSync = $isSync;
    }

    /**
     * EApmSpan constructor.
     * @param string $name
     * @param string $type
     * @param string $subtype
     * @param EApmEventBase $parentEvent
     */
    public function __construct(string $name, string $type, string $subtype, EApmEventBase $parentEvent)
    {
        $this->setName($name);
        $this->setType($type);
        $this->setSubtype($subtype);

        parent::__construct($parentEvent);
    }

    /**
     * Start http request
     * options example ["timeout" => 1.0,"verify" => false,"headers"=>[],"json"=>[]]
     *
     * @param string $method
     * @param string $url
     * @param array $options
     *
     * @return array|boolean
     */
    public function startHttpTypeSpan(string $method, string $url, array $options = [])
    {
        $this->setSpanIsSync(true);
        $this->setAction($method);
        $context = [
            "http" => [
                "url" => $url,
                "method" => $method,
            ],
        ];

        if (!preg_match("/^https?:\/\/.*$/", $url)) {
            $url = "http://" . $url;
        }

        $aPmReQuEsTHeAdErS = $this->getComposer()->getNextRequestTraceHeaders();
        $options["headers"] = array_merge($options["headers"] ?? [],
            $aPmReQuEsTHeAdErS);

        if (!isset($options["verify"])) {
            $options["verify"] = false;
        }

        try {
            $response = $this->getComposer()->getEventIntake()->getEventClient()->request(
                $method,
                $url,
                $options
            );
        } catch (RequestException $exception) {
            $this->getComposer()->captureError($exception, $this);
            $context["status_code"] = self::DEFAULT_ERROR_HTTP_CODE;
            $this->setContext($context);
            $this->end();
            return false;
        }

        $this->end();
        $context["status_code"] = $response->getStatusCode();
        $this->setContext($context);

        return [
            "code" => $response->getStatusCode(),
            "body" => $response->getBody()->getContents(),
        ];
    }

    /**
     * Start a redis type span
     *
     * @param \Redis $redis
     * @param string $command
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function startRedisTypeSpan(\Redis $redis, string $command, ...$args)
    {
        $this->setSpanIsSync(true);
        $this->setAction($command);
        $this->setContext([
            "db" => [
                "instance"  => $this->getSubtype(),
                "statement" => $command . " " . implode(" ", $args),
                "type"      => "command",
            ],
        ]);

        try {
            $result = call_user_func_array([$redis, $command], $args);
        } catch (\Exception $exception) {
            $this->getComposer()->captureError($exception, $this);
            $this->end();
            return false;
        }

        $this->end();

        return $result;
    }

    /**
     * Start mysql type span
     *
     * @param $mysql
     * @param string $sql
     *
     * @return bool|null
     */
    public function startMysqlTypeSpan($mysql, string $sql)
    {
        $dbContext = [
            "instance" => $this->getSubtype(),
            "statement" => $sql,
            "type" => "sql",
            "rows_affected" => 0,
        ];
        $this->setSpanIsSync(true);

        $queryType = "";
        preg_match("/^(.*?)\s/", $sql, $match);
        $queryType = $match[1];
        $this->setAction(strtoupper($queryType));

        $result = null;
        try {
            switch (get_class($mysql)) {
                case "mysqli":
                case "mysql":
                    $result = $mysql->query($sql);
                    if (!$result) {
                        return false;
                    }
                    if (strtolower($queryType) == "select") {
                        $result = $result->fetch_assoc();
                    }
                    $dbContext["rows_affected"] = $mysql->affected_rows ?? 0;
                    break;
                case "PDO":
                    switch(strtolower($queryType)) {
                        case "select":
                            $result = $mysql->query($sql);
                            if (!$result) {
                                return false;
                            }
                            $result = $result->fetchAll(\PDO::FETCH_ASSOC);
                            $dbContext["rows_affected"] = count($result);
                            break;
                        case "update":
                        case "delete":
                            $result = $mysql->exec($sql);
                            $dbContext["rows_affected"] = (int)$result;
                            break;
                    }
                    break;
                default:
                    $this->getComposer()->captureError(new \Error("Unsupported db extension"), $this);
                    $this->setContext($dbContext);
                    $this->end();
                    return false;
            }
        } catch (\Exception $exception) {
            $this->getComposer()->captureError($exception, $this);
            $this->setContext($dbContext);
            $this->end();
            return false;
        }

        $this->setContext($dbContext);
        $this->end();

        return $result;
    }

    /**
     * Set message queue publish/consume context
     *
     * @param string $messageBody
     */
    public function setMessageQueueSpanContext(string $messageBody) : void
    {
        $this->setAction(strtolower($this->getName()));
        $this->setContext([
            "message" => [
                "queue" => [
                    "name" => $this->getSubtype(),
                ],
                "body" => $messageBody,
            ],
        ]);
        $this->end();
    }

    /**
     * Json serialize span event object
     * @link https://www.elastic.co/guide/en/apm/server/master/span-api.html
     * @return array
     */
    public function jsonSerialize() : array
    {
        return array(
            "span" => [
                "timestamp" => $this->getTimestamp(),
                "type" => $this->getType(),
                "subtype" => $this->getSubtype(),
                "id" => $this->getId(),
                "transaction_id" => $this->getEventTransactionId(),
                "trace_id" => $this->getTraceId(),
                "parent_id" => $this->getParentId(),
                "child_ids" => $this->getChildSpanIds(),
                "start" => null,
                "context" => $this->getEventContext(),
                "action" => $this->getAction() ?? "",
                "name" => $this->getName(),
                "duration" => $this->getDuration(),
                "stacktrace" => null,
                "sync" => $this->getSpanIsSync(),
                "sample_rate" => $this->getComposer()->getConfigure()->getAppConfig("sample_rate"),
            ],
        );
    }
}