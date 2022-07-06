<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Processor\ArsHeader;

/**
 * Class ArsHeaderManager
 */
interface ArsHeaderManagerInterface
{
    /**
     * Returns the correlation id.
     *
     * @return string The correlation id.
     */
    public function getCorrelationId(): string;

    /**
     * Returns the request id.
     *
     * @return string The request id.
     */
    public function getRequestId(): string;

    /**
     * Returns the parent id.
     *
     * @return string The parent id.
     */
    public function getParentId(): string;
}
