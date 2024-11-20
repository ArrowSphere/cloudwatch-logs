<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Processor\ArsHeader;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactoryInterface;

/**
 * Class ArsHeaderManager
 */
final class ArsHeaderManager implements ArsHeaderManagerInterface
{
    private static ?ArsHeaderManager $instance = null;

    /**
     * The correlation id is the id of the chain originator.
     * If there is none, then it will be auto-generated and should be passed to further calls.
     */
    private string $correlationId;

    /**
     * The request id is the id of the current request.
     */
    private string $requestId;

    /**
     * The parent id is the id of the request that directly called this one.
     */
    private string $parentId;

    /**
     * ArsHeaderManager constructor.
     *
     * @param array<string, string> $data The data array when the ids can potentially be found.
     * @param UuidFactoryInterface|null $factory The factory to generate uuids.
     */
    public function __construct(array $data, UuidFactoryInterface $factory = null)
    {
        $factory ??= Uuid::getFactory();

        // Headers should be case-insensitive
        $data = array_change_key_case($data, CASE_LOWER);

        // Initialize properties
        $this->requestId = $factory->uuid4()->toString();
        $this->correlationId = $data[ArsRequestIdentifierEnum::ARS_CORRELATION_ID] ?? $this->requestId;
        $this->parentId = $data[ArsRequestIdentifierEnum::ARS_REQUEST_ID] ?? '';

        // (Re)load them into the superglobal $_SERVER
        $_SERVER[ArsRequestIdentifierEnum::ARS_CORRELATION_ID] = $this->correlationId;
        $_SERVER[ArsRequestIdentifierEnum::ARS_REQUEST_ID] = $this->requestId;
        $_SERVER[ArsRequestIdentifierEnum::ARS_PARENT_ID] = $this->parentId;
    }

    /**
     * Returns the correlation id.
     *
     * @return string The correlation id.
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Returns the request id.
     *
     * @return string The request id.
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * Returns the parent id.
     *
     * @return string The parent id.
     */
    public function getParentId(): string
    {
        return $this->parentId;
    }

    /**
     * Initializes the header manager using the values found in the
     * $_SERVER superglobal (typically in the headers).
     *
     * @param UuidFactoryInterface|null $factory The factory to generate uuids.
     * @param bool $forceNewInstance True to force the process to create a new instance.
     *
     * @return ArsHeaderManagerInterface The header manager, initialized and ready to be used;
     */
    public static function initFromGlobals(UuidFactoryInterface $factory = null, bool $forceNewInstance = false): ArsHeaderManagerInterface
    {
        if (self::$instance === null || $forceNewInstance) {
            self::$instance = new self($_SERVER, $factory);
        }

        return self::$instance;
    }
}
