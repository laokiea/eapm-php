<?php

/*
 * This file is part of the laokiea/eapm-php.
 *
 * (c) laokiea <sashengpeng@blued.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EApmPhp\Trace;

/**
 * Class EApmDistributeTrace
 *
 * Distribute Trace
 */
class EApmDistributeTrace
{
    /**
     * Hex digit char array
     */
    public const HEX_DIGIT = array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f');

    /**
     * Specific version according to specification
     */
    public const SPECIFIC_VERSION = "00";

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
    public const PARENT_SPANID_LENGTH = 16;

    /**
     * Invalid traceid format
     */
    public const PARENT_SPANID_INVALID_FORMAT = "0000000000000000";

    /**
     * has to record request
     */
    private const MASK_RECORD_REQUEST = 0x01;

    /**
     * valid traceparent header
     */
    protected $hasValidTrace;

    /**
     * Distribute trace id
     * @var
     */
    protected $tarceId = null;

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
     * Check distribute header info is right hex char
     *
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
     *
     */
    public function setTraceId(string $traceId)
    {
        $this->tarceId = trim($traceId);
    }

    /**
     * get distribute trace id
     *
     */
    public function getTraceId() : string
    {
        return $this->tarceId ?? "";
    }

    /**
     * set distribute trace id
     *
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
     *
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
     */
    public function setTraceFlag(string $traceFlag)
    {
        $this->traceFlag = trim($traceFlag);
    }

    /**
     * get distribute trace flag
     *
     */
    public function getTraceFlag() : string
    {
        return $this->traceFlag ?? "";
    }

    /**
     * record request according to trace flag
     *
     * @return bool
     */
    public function isRecordRequest() : bool
    {
        return boolval(hexdec($this->getTraceFlag()) & self::MASK_RECORD_REQUEST);
    }
}