<?php

namespace ArrowSphere\CloudWatchLogs\Tests\Handler;

use ArrowSphere\CloudWatchLogs\Handler\ArsCloudWatchHandler;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeaderProcessorInterface;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use Aws\Result;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
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

    /** @var MockObject|CloudWatchLogsClient */
    private $clientMock;

    /** @var MockObject|Result */
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
     * @return ArsCloudWatchHandler
     * @throws Exception
     */
    private function initHandler(int $batchSize = 10000, array $tags = []): ArsCloudWatchHandler
    {
        $arsHeaderProcessor = $this->getMockBuilder(ArsHeaderProcessorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $arsHeaderProcessor->method('getCorrelationId')->willReturn(self::CORRELATION_ID);
        $arsHeaderProcessor->method('getRequestId')->willReturn(self::REQUEST_ID);
        $arsHeaderProcessor->method('__invoke')->willReturnCallback(fn($args) => $args);

        return new ArsCloudWatchHandler(
            [],
            $this->accountAlias,
            $this->stage,
            $this->application,
            14,
            $batchSize,
            $tags,
            $this->clientMock,
            $arsHeaderProcessor
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithExistingLogGroup(): void
    {
        $logGroupsResult = new Result(['logGroups' => [['logGroupName' => $this->getGroupName()]]]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogGroups')
            ->with(['logGroupNamePrefix' => $this->getGroupName()])
            ->willReturn($logGroupsResult);

        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => self::REQUEST_ID,
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198322',
                ],
            ],
        ]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogStreams')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamNamePrefix' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturn($logStreamResult);

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initialize');
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

        $logGroupsResult = new Result(['logGroups' => [['logGroupName' => $this->getGroupName() . 'foo']]]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogGroups')
            ->with(['logGroupNamePrefix' => $this->getGroupName()])
            ->willReturn($logGroupsResult);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogGroup')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'tags' => $tags,
            ]);

        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => self::REQUEST_ID,
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198322',
                ],
            ],
        ]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogStreams')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamNamePrefix' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturn($logStreamResult);

        $handler = $this->initHandler(10000, $tags);

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initialize');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithEmptyTags(): void
    {
        $logGroupsResult = new Result(['logGroups' => [['logGroupName' => $this->getGroupName() . 'foo']]]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogGroups')
            ->with(['logGroupNamePrefix' => $this->getGroupName()])
            ->willReturn($logGroupsResult);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogGroup')
            ->with(['logGroupName' => $this->getGroupName()]); //The empty array of tags is not handed over

        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => self::REQUEST_ID,
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198322',
                ],
            ],
        ]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogStreams')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamNamePrefix' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturn($logStreamResult);

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initialize');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($handler);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testInitializeWithMissingGroupAndStream(): void
    {
        $logGroupsResult = new Result(['logGroups' => [['logGroupName' => $this->getGroupName() . 'foo']]]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogGroups')
            ->with(['logGroupNamePrefix' => $this->getGroupName()])
            ->willReturn($logGroupsResult);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogGroup')
            ->with(['logGroupName' => $this->getGroupName()]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('putRetentionPolicy')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'retentionInDays' => 14,
            ]);

        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => self::CORRELATION_ID . '/' . self::REQUEST_ID . 'bar',
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198324',
                ],
            ],
        ]);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('describeLogStreams')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamNamePrefix' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturn($logStreamResult);

        $this
            ->clientMock
            ->expects($this->once())
            ->method('createLogStream')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamName' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ]);

        $handler = $this->initHandler();

        $reflection = new ReflectionClass($handler);
        $reflectionMethod = $reflection->getMethod('initialize');
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
            ->expects($this->once())
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->initHandler(1);

        $handler->handle($this->getRecord(Logger::DEBUG));

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
            ->expects($this->exactly(2))
            ->method('PutLogEvents')
            ->willReturn($this->awsResultMock);

        $handler = $this->initHandler(3);

        foreach ($this->getMultipleRecords() as $record) {
            $handler->handle($record);
        }

        $handler->close();
    }

    /**
     * @throws Exception
     */
    public function testExceptionFromDescribeLogGroups(): void
    {
        // e.g. 'User is not authorized to perform logs:DescribeLogGroups'
        $awsException = $this->getMockBuilder(CloudWatchLogsException::class)
            ->disableOriginalConstructor()
            ->getMock();

        // if this fails ...
        $this->clientMock
            ->expects($this->atLeastOnce())
            ->method('describeLogGroups')
            ->will($this->throwException($awsException));

        // ... this should not be called:
        $this->clientMock
            ->expects($this->never())
            ->method('describeLogStreams');

        $this->expectException(CloudWatchLogsException::class);

        $handler = $this->initHandler(0);
        $handler->handle($this->getRecord(Logger::INFO));
    }

    private function prepareMocks(): void
    {
        $logGroupsResult = new Result(['logGroups' => [['logGroupName' => $this->getGroupName()]]]);

        $this->clientMock
            ->expects($this->once())
            ->method('describeLogGroups')
            ->with(['logGroupNamePrefix' => $this->getGroupName()])
            ->willReturn($logGroupsResult);

        $logStreamResult = new Result([
            'logStreams' => [
                [
                    'logStreamName' => self::REQUEST_ID,
                    'uploadSequenceToken' => '49559307804604887372466686181995921714853186581450198322',
                ],
            ],
        ]);

        $this->clientMock
            ->expects($this->once())
            ->method('describeLogStreams')
            ->with([
                'logGroupName' => $this->getGroupName(),
                'logStreamNamePrefix' => self::CORRELATION_ID . '/' . self::REQUEST_ID,
            ])
            ->willReturn($logStreamResult);

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
            ->expects($this->once())
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
            $record = $this->getRecord(Logger::INFO, 'record' . $i);
            /** @var DateTimeImmutable $datetime */
            $datetime = DateTimeImmutable::createFromFormat('U', (string)(time() + $i));
            $record['datetime'] = $datetime;
            $records[] = $record;
        }

        // but submitted in a different order:
        $handler->handle($records[2]);
        $handler->handle($records[0]);
        $handler->handle($records[3]);
        $handler->handle($records[1]);

        $handler->close();
    }

    /**
     * @param int $level
     * @param string $message
     * @return array
     *
     * @phpstan-param  Level $level
     * @phpstan-return Record
     */
    private function getRecord(int $level = Logger::WARNING, string $message = 'test'): array
    {
        /** @var DateTimeImmutable $datetime */
        $datetime = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));

        return [
            'message' => $message,
            'context' => [],
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => $datetime,
            'extra' => [],
        ];
    }

    /**
     * @return array
     */
    private function getMultipleRecords(): array
    {
        return [
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
            $this->getRecord(Logger::WARNING, 'warning'),
            $this->getRecord(Logger::ERROR, 'error'),
        ];
    }
}
