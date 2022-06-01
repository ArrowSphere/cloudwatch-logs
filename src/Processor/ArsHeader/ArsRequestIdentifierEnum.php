<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Processor\ArsHeader;

/**
 * Class ArsRequestIdentifierEnum
 *
 * This class indicates the names of the ARS identifiers.
 */
final class ArsRequestIdentifierEnum
{
    /**
     * The correlation id is the id of the chain originator.
     * If there is none, then it will be auto-generated and should be passed to further calls.
     */
    public const ARS_CORRELATION_ID = 'ars-correlation-id';

    /**
     * The request id is the id of the current request.
     */
    public const ARS_REQUEST_ID = 'ars-request-id';

    /**
     * The parent id is the id of the request that directly called this one.
     */
    public const ARS_PARENT_ID = 'ars-parent-id';
}
