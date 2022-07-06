<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Processor;

use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManager;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManagerInterface;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsRequestIdentifierEnum;
use Monolog\Logger;

/**
 * Class ArsHeaderProcessor
 *
 * @phpstan-import-type Record from Logger
 */
final class ArsHeaderProcessor implements ArsHeaderProcessorInterface
{
    private ArsHeaderManagerInterface $arsHeaderManager;

    /**
     * @param ArsHeaderManagerInterface|null $arsHeaderManager
     */
    public function __construct(ArsHeaderManagerInterface $arsHeaderManager = null)
    {
        $this->arsHeaderManager = $arsHeaderManager ?? ArsHeaderManager::initFromGlobals();
    }

    /**
     * @param array $record
     *
     * @return array
     *
     * @phpstan-param  Record $record
     * @phpstan-return Record
     */
    public function __invoke(array $record): array
    {
        $record['extra'][ArsRequestIdentifierEnum::ARS_CORRELATION_ID] = $this->getCorrelationId();
        $record['extra'][ArsRequestIdentifierEnum::ARS_REQUEST_ID] = $this->getRequestId();
        $record['extra'][ArsRequestIdentifierEnum::ARS_PARENT_ID] = $this->getParentId();

        return $record;
    }

    /**
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->arsHeaderManager->getCorrelationId();
    }

    /**
     * @return string
     */
    public function getRequestId(): string
    {
        return $this->arsHeaderManager->getRequestId();
    }

    /**
     * @return string
     */
    public function getParentId(): string
    {
        return $this->arsHeaderManager->getParentId();
    }
}