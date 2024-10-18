<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Processor;

use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManager;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManagerInterface;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsRequestIdentifierEnum;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Class ArsHeaderProcessor
 *
 * @phpstan-import-type Record from Logger
 */
final class ArsHeaderProcessor implements ArsHeaderProcessorInterface
{
    private ?ArsHeaderManagerInterface $arsHeaderManager;

    /**
     * @param ArsHeaderManagerInterface|null $arsHeaderManager
     */
    public function __construct(ArsHeaderManagerInterface $arsHeaderManager = null)
    {
        $this->arsHeaderManager = $arsHeaderManager;
    }

    /**
     * @param LogRecord $record
     *
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra = array_merge(
            $record->extra,
            [
                ArsRequestIdentifierEnum::ARS_CORRELATION_ID => $this->getCorrelationId(),
                ArsRequestIdentifierEnum::ARS_REQUEST_ID     => $this->getRequestId(),
                ArsRequestIdentifierEnum::ARS_PARENT_ID      => $this->getParentId(),
            ]
        );

        return $record;
    }

    /**
     * @return ArsHeaderManagerInterface
     */
    protected function getArsHeaderManager(): ArsHeaderManagerInterface
    {
        return $this->arsHeaderManager ?? ArsHeaderManager::initFromGlobals();
    }

    /**
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->getArsHeaderManager()->getCorrelationId();
    }

    /**
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->getArsHeaderManager()->getRequestId();
    }

    /**
     * @return string
     */
    public function getParentId(): string
    {
        return $this->getArsHeaderManager()->getParentId();
    }
}
