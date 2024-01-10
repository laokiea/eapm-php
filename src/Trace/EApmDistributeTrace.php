<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <laokiea@163.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace EApmPhp\Trace;

use EApmPhp\EApmComposer;

/**
 * Class EApmDistributeTrace
 *
 * Distribute Trace
 */
class EApmDistributeTrace
{
    /**
     * Traceparent header name
     */
    public const ELASTIC_APM_TRACEPARENT_HEADER_NAME = "elastic-apm-traceparent";

    /**
     * Hex digit char array
     */
    public const HEX_DIGIT = array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f');

    /**
     * Specific version according to specification
     */
    public const SPECIFIC_VERSION = "00";

    /**
     * Default trace flag
     */
    public const DEFAULT_TRACE_FLAG = "00";


    /**
     * Trace id length
     */
    public const TRACEID_LENGTH = 32;

    /**
     * Invalid traceid format
     */
    public const TRACEID_INVALID_FORMAT = "00000000000000000000000000000000";

    /**
     * Span id length
     */
    public const SPANID_LENGTH = 16;

    /**
     * Invalid traceid format
     */
    public const PARENT_SPANID_INVALID_FORMAT = "0000000000000000";

    /**
     * Tracestate list members max num
     */
    public const TRACESTATE_LIST_MEMBERS_MAX_NUM = 32;

    /**
     * Combined tracestate header max length
     */
    public const TRACESTATE_COMBINED_HEADER_MAX_LENGTH = 512;

    /**
     * Combined tracestate member key max length
     */
    public const TRACESTATE_MEMBER_KEY_VALUE_MAX_LENGTH = 256;

    /**
     * Not privacy tracestate keys collects
     */
    public const NOT_PRIVACY_KEYS_COLLECTS = array("ip", "uid", "token", "auth");

    /**
     * has to record request
     */
    private const MASK_RECORD_REQUEST = 0x01;

    /**
     * valid traceparent header
     */
    protected $hasValidTrace = null;

    /**
     * valid transparent header
     *
     * @var array
     */
    protected $validTraceparent = array();

    /**
     * valid tracestate header
     *
     * @var array
     */
    protected $validTracestate = array();

    /**
     * Distribute trace id
     * @var
     */
    protected $traceId = null;

    /**
     * Distribute version id
     * @var
     */
    protected $versionId = null;

    /**
     * Distribute span(parent-span) id
     * @var
     */
    protected $parentSpanId = null;

    /**
     * Distribute trace flag
     * @var
     */
    protected $traceFlag = null;

    /**
     * EApmComposer class object
     * @var
     */
    protected $composer;

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
     * set valid traceparent
     * @param array $traceparent
     */
    public function setValidTraceparent(array $traceparent)
    {
        $this->validTraceparent = $traceparent;
        extract($this->validTraceparent, EXTR_OVERWRITE);
        $this->setVersionId($versionId);
        $this->setTraceId($traceId);
        $this->setParentSpanId($parentId);
        $this->setTraceFlag($traceFlag);
    }

    /**
     * get valid traceparent
     *
     */
    public function getValidTraceparent() : array
    {
        return $this->validTraceparent;
    }

    /**
     * set valid tracestate
     * @param array $tracestate
     */
    private function setValidTracestate(array $tracestate)
    {
        $this->validTracestate = $tracestate;
    }

    /**
     * get valid tracestate
     *
     */
    public function getValidTracestate() : array
    {
        return $this->validTracestate;
    }

    /**
     * Validate tracestate identity
     * @param string $identity
     * @return bool
     */
    public function validateTracestateKey(string $identity) : bool
    {
        $pattern = sprintf("/^[0-9a-z\_\-\*\/\@]{1,%d}$/", self::TRACESTATE_MEMBER_KEY_VALUE_MAX_LENGTH);
        return preg_match($pattern, $identity) && $this->checkKeyPrivacy($identity);
    }

    /**
     * Tracestate $identity MUST NOT include any user identifiable information like ip,uid,token etc.
     *
     * @param string $identity
     * @return bool
     */
    public function checkKeyPrivacy(string $identity) : bool
    {
        foreach (self::NOT_PRIVACY_KEYS_COLLECTS as $key) {
            if (preg_match("/^.*$key.*$/", $identity)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate tracestate value
     * @param string $value
     * @return bool
     */
    public function validateTracestateValue(string $value) : bool
    {
       return mb_strlen($value) <= EApmDistributeTrace::TRACESTATE_MEMBER_KEY_VALUE_MAX_LENGTH;
    }

    /**
     * Add user-specify tracestate
     * Vendors MUST NOT include any personally identifiable information in the tracestate header.
     *
     * @param string $identity
     * @param string $member
     */
    public function addValidTracestate(string $identity, string $member) : void
    {
        if ($this->validateTracestateKey($identity) && $this->validateTracestateValue($member)) {
            $newValidTracestate = array(
                $identity => $member,
            );
            if (empty($this->validTracestate)) {
                foreach ($this->validTracestate as $key => $value) {
                    $newValidTracestate[$key] = $value;
                }
            }
            $this->validTracestate = $newValidTracestate;
        }
    }

    /**
     * set has valid trace
     * @param bool $has
     */
    private function setHasValidTrace(bool $has)
    {
        $this->hasValidTrace = $has;
    }

    /**
     * get has valid trace
     *
     */
    public function getHasValidTrace() : bool
    {
        return $this->hasValidTrace ?? false;
    }

    /**
     * Check distribute header info is right hex char
     *
     * @param string $chars
     * @return bool
     */
    public static function checkHexChar(string $chars) : bool
    {
        for ($i = 0;$i < strlen($chars);$i++) {
            if (!in_array($chars[$i], self::HEX_DIGIT)) {
                return false;
            }
        }
        return true;
    }

    /**
     * set distribute trace id
     * @param string $traceId
     */
    public function setTraceId(string $traceId)
    {
        $this->traceId = trim($traceId);
    }

    /**
     * get distribute trace id
     *
     */
    public function getTraceId() : string
    {
        return $this->traceId ?? "";
    }

    /**
     * set distribute trace id
     * @param string $parentSpanId
     */
    public function setParentSpanId(string $parentSpanId)
    {
        $this->parentSpanId = trim($parentSpanId);
    }

    /**
     * get distribute trace id
     *
     */
    public function getParentSpanId() : string
    {
        return $this->parentSpanId ?? "";
    }

    /**
     * set version id
     * @param string $versionId
     */
    public function setVersionId(string $versionId)
    {
        $this->versionId = trim($versionId);
    }

    /**
     * get distribute trace id
     *
     */
    public function getVersionId() : string
    {
        return $this->versionId ?? "";
    }

    /**
     * set trace flag
     *
     * @param string $traceFlag
     * @return void
     */
    public function setTraceFlag(string $traceFlag) : void
    {
        $this->traceFlag = trim($traceFlag);
    }

    /**
     * get distribute trace flag bit field
     *
     * @return string
     */
    public function getTraceFlag() : string
    {
        return $this->traceFlag ?? "";
    }

    /**
     * Get traceresponse header string
     * If the request header contains a valid traceparent with a trace-id/parent-id
     * the callee SHOULD omit the trace-id/proposed-parent-id field from the traceresponse.
     *
     * @return string
     */
    public function getTraceResponseHeader() : string
    {
        return $this->getHasValidTrace() ?
            self::SPECIFIC_VERSION."---".$this->getTraceFlag() :
            $this->getComposer()->getCurrentTraceResponseHeader();
    }

    /**
     * Get traceparent header for outgoing request.
     * The value of the parent-id field can be set to the new value representing the ID of the current operation.
     * This is the most typical mutation and should be considered a default.
     *
     * @return string
     */
    public function getNextRequestTraceparentHeader() : string
    {
        return $this->getHasValidTrace() ?
            $this->getVersionId()."-"
            .$this->getTraceId()."-"
            .$this->getComposer()->getCurrentTransactionSpanId()
            ."-".self::DEFAULT_TRACE_FLAG
                :
            $this->getComposer()->getCurrentTraceResponseHeader();
    }

    /**
     * Get next request trace headers
     *
     * @return array
     */
    public function getNextRequestTraceHeaders() : array
    {
        return array(
            EApmDistributeTrace::ELASTIC_APM_TRACEPARENT_HEADER_NAME => $this->getNextRequestTraceparentHeader(),
            "tracestate" => $this->getComposer()->getCombinedTracestateHeader(),
        );
    }

    /**
     * record request according to trace flag bit field
     *
     * @return bool
     */
    public function isParentRecordRequest() : bool
    {
        return boolval(hexdec($this->getTraceFlag()) & self::MASK_RECORD_REQUEST);
    }
}