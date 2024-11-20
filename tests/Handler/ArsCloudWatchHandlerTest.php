<?php

namespace ArrowSphere\CloudWatchLogs\Tests\Handler;

use ArrowSphere\CloudWatchLogs\Handler\ArsCloudWatchHandler;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeaderProcessorInterface;
use ArrowSphere\CloudWatchLogs\Tests\LogRecordFaker;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Command;
use Aws\Result;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Class ArsCloudWatchHandlerTest
 *
 * @phpstan-import-type Level from Logger
 * @phpstan-import-type Record from Logger
 */
class ArsCloudWatchHandlerTest extends TestCase
{
    private const CORRELATION_ID = 'myCorrelationId';

    private const REQUEST_ID = 'myRequestId';

    /**
     * @var MockObject|CloudWatchLogsClient
     */
    private $clientMock;

    /**
     * @var MockObject|Result
     */
    private $awsResultMock;

    private string $accountAlias = 'accountAlias';

    private string $stage = 'stage';

    private string $application = 'application';

    protected function setUp(): void
    {
        $this->clientMock = $this
            ->getMockBuilder(CloudWatchLogsClient::class)
            ->addMethods(
                [
                    'describeLogGroups',
                    'CreateLogGroup',
                    'PutRetentionPolicy',
                    'DescribeLogStreams',
                    'CreateLogStream',
                    'PutLogEvents',
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getGroupName(): string
    {
        return strtolower(sprintf('/%s/%s/%s', $this->accountAlias, $this->stage, $this->application));
    }

    /**
     * @param int $batchSize
     * @param array $tags
     * @param string $streamName
     *
     * @return ArsCloudWatchHandler
     *
     * @throws Exception
     */
    private function initHandler(int $batchSize = 10000, array $tags = [], string $streamName = null): ArsCloudWatchHandler
    {
        $arsHeaderProcessor = $this->getMockBuilder(ArsHeaderProcessorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $arsHeaderProcessor->method('getCorrelationId')->willReturn(self::CORRELATION_ID);
        $arsHeaderProcessor->method('getRequestId')->willReturn(self::REQUEST_ID);
        $arsHeaderProcessor->method('__invoke')->willReturnCallback(static fn ($args) => $args);

        return new ArsCloudWatchHandler(
            [],
            $this->accountAlias,
            $this->stage,
            $this->application,
            14,
            $batchSize,
            $tags,
            $this->clientMock,
            $arsHeaderProcessor,
            $streamName
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithExistingLogGroup(): void
    {
        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogGroups');

        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogStreams');

        $this
            ->clientMock
            ->expects(self::never())
            ->method('createLogGroup');

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamName' => self::CORRELATION_ID . '/' . self::REQUEST_ID
            ]);

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeLogStream');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithTags(): void
    {
        $tags = [
            'applicationName' => 'dummyApplicationName',
            'applicationEnvironment' => 'dummyApplicationEnvironment',
        ];

        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogGroups');

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogGroup')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'tags' => $tags,
            ]);

        $handler = $this->initHandler(10000, $tags);

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeGroup');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithEmptyTags(): void
    {
        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogGroups');

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogGroup')
            ->with(['logGroupName' => $this->getGroupName()]); //The empty array of tags is not handed over

        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogStreams');

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeGroup');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithCustomStreamName(): void
    {
        $streamName = 'custom-log-stream-name';

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamName' => $streamName,
            ])
            ->willReturn([]);

        $handler = $this->initHandler(10000, [], $streamName);

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeLogStream');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithMissingGroupAndStream(): void
    {
        $command = $this->createMock(Command::class);

        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogStreams');

        $this
            ->clientMock
            ->expects(self::exactly(2))
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamName' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturnOnConsecutiveCalls(
                self::throwException(new CloudWatchLogsException('ResourceNotFoundException', $command, ['code' => 'ResourceNotFoundException'])),
                []
            );

        $this
            ->clientMock
            ->expects(self::never())
            ->method('describeLogGroups');

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogGroup')
            ->with(['logGroupName' => $this->getGroupName()]);

        $this
            ->clientMock
            ->expects(self::once())
            ->method('putRetentionPolicy')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'retentionInDays' => 14,
            ]);

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initializeLogStream');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws Exception
     */
    public function testLimitExceeded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->initHandler(10001);
    }

    /**
     * @throws Exception
     */
    public function testSendsOnClose(): void
    {
        $this->prepareMocks();

        /** @phpstan-ignore-next-line Because PutLogEvents is a magic method */
        $this
            ->clientMock
            ->expects(self::once())
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->initHandler(1);

        $handler->handle(LogRecordFaker::getRecord(Level::Debug));

        $handler->close();
    }

    /**
     * @throws Exception
     */
    public function testSendsBatches(): void
    {
        $this->prepareMocks();

        /** @phpstan-ignore-next-line Because PutLogEvents is a magic method */
        $this
            ->clientMock
            ->expects(self::exactly(2))
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->initHandler(3);

        foreach (LogRecordFaker::getMultipleRecords() as $record) {
            $handler->handle($record);
        }

        $handler->close();
    }

    /**
     * @throws Exception
     */
    public function testPutLogEventsWithMissingGroupAndStream(): void
    {
        $this->prepareMocks();

        $command = $this->createMock(Command::class);

        $this
            ->clientMock
            ->expects(self::exactly(2))
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamName' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturnOnConsecutiveCalls(
                self::throwException(new CloudWatchLogsException('ResourceNotFoundException', $command, ['code' => 'ResourceNotFoundException'])),
                []
            );

        $this
            ->clientMock
            ->expects(self::once())
            ->method('createLogGroup')
            ->with(['logGroupName' => $this->getGroupName()]);

        /** @phpstan-ignore-next-line Because PutLogEvents is a magic method */
        $this
            ->clientMock
            ->expects(self::exactly(3))
            ->method('PutLogEvents')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new CloudWatchLogsException('ResourceNotFoundException', $command, ['code' => 'ResourceNotFoundException'])),
                $this->awsResultMock,
                $this->awsResultMock,
            );

        $handler = $this->initHandler(3);

        foreach (LogRecordFaker::getMultipleRecords() as $record) {
            $handler->handle($record);
        }

        $handler->close();
    }

    private function prepareMocks(): void
    {
        $this->clientMock
            ->expects(self::never())
            ->method('describeLogGroups');

        $this->clientMock
            ->expects(self::never())
            ->method('describeLogStreams');

        $this->awsResultMock = $this
                ->getMockBuilder(Result::class)
                ->onlyMethods(['get'])
                ->disableOriginalConstructor()
                ->getMock();
    }

    /**
     * @throws Exception
     */
    public function testSortsEntriesChronologically(): void
    {
        $this->prepareMocks();

        /** @phpstan-ignore-next-line Because PutLogEvents is a magic method */
        $this->clientMock
            ->expects(self::once())
            ->method('PutLogEvents')
            ->willReturnCallback(function (array $data) {
                $this->assertStringContainsString('record1', $data['logEvents'][0]['message']);
                $this->assertStringContainsString('record2', $data['logEvents'][1]['message']);
                $this->assertStringContainsString('record3', $data['logEvents'][2]['message']);
                $this->assertStringContainsString('record4', $data['logEvents'][3]['message']);

                return $this->awsResultMock;
            });

        $handler = $this->initHandler(4);

        // created with chronological timestamps:
        $records = [];

        for ($i = 1; $i <= 4; ++$i) {
            $record = LogRecordFaker::getRecord(
                Level::Info,
                'record' . $i,
                DateTimeImmutable::createFromFormat('U', (string) (time() + $i))
            );
            $records[] = $record;
        }

        // but submitted in a different order:
        $handler->handle($records[2]);
        $handler->handle($records[0]);
        $handler->handle($records[3]);
        $handler->handle($records[1]);

        $handler->close();
    }
}
