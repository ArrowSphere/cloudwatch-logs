<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Tests\Processor;

use Monolog\Level;
use DateTimeImmutable;
use ArrowSphere\CloudWatchLogs\Tests\LogRecordFaker;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManagerInterface;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeaderProcessor;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ArsHeaderProcessorTest
 *
 * @phpstan-import-type Record from Logger
 */
class ArsHeaderProcessorTest extends TestCase
{
    public function testInvoke(): void
    {
        /** @var MockObject $arsHeaderManager */
        $arsHeaderManager = $this->getMockBuilder(ArsHeaderManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $arsHeaderManager->expects(self::once())->method('getCorrelationId')->willReturn('correlationId');
        $arsHeaderManager->expects(self::once())->method('getRequestId')->willReturn('requestId');
        $arsHeaderManager->expects(self::once())->method('getParentId')->willReturn('parentId');

        /** @var ArsHeaderManagerInterface $arsHeaderManager */
        $arsHeaderProcessor = new ArsHeaderProcessor($arsHeaderManager);

        $datetime = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $record = LogRecordFaker::getRecord(Level::Info, 'hello', $datetime);

        $expected = [
            'message' => 'hello',
            'extra' => [
                'ars-correlation-id' => 'correlationId',
                'ars-request-id' => 'requestId',
                'ars-parent-id' => 'parentId',
            ],
        ];

        $actual = $arsHeaderProcessor($record);

        self::assertSame($expected['message'], $actual->message);
        self::assertSame($expected['extra'], $actual->extra);
    }
}
