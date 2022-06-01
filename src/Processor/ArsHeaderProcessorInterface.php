<?php

namespace ArrowSphere\CloudWatchLogs\Processor;

use Monolog\Processor\ProcessorInterface;

interface ArsHeaderProcessorInterface extends ProcessorInterface
{
    /**
     * Returns the correlation identifier of the request, which is passed from service to service.
     *
     * @return string
     */
    public function getCorrelationId(): string;

    /**
     * Returns the current request identifier.
     *
     * @return string
     */
    public function getRequestId(): string;

    /**
     * Returns the identifier of the request that directly called this one, or an empty string if there's none.
     *
     * @return string
     */
    public function getParentId(): string;
}
