<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Handler;

use ArrowSphere\CloudWatchLogs\Formatter\ArsJsonFormatter;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeaderProcessor;
use ArrowSphere\CloudWatchLogs\Processor\ArsHeaderProcessorInterface;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CloudWatchLogs\Exception\CloudWatchLogsException;
use DateTime;
use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Class ArsCloudWatchHandler
 *
 * @phpstan-type Record array{message: string, timestamp: float|int}
 */
final class ArsCloudWatchHandler extends AbstractProcessingHandler
{
    /**
     * Requests per second limit (https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/cloudwatch_limits_cwl.html)
     */
    private const RPS_LIMIT = 5;

    /**
     * The max number of entries that can be sent to AWS at once.
     */
    private const MAX_BATCH_SIZE = 10000;

    /**
     * Event size limit (https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/cloudwatch_limits_cwl.html)
     */
    private const EVENT_SIZE_LIMIT = 262118; // 262144 - reserved 26

    /**
     * Data amount limit (http://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutLogEvents.html)
     */
    private const DATA_AMOUNT_LIMIT = 1048576;

    private CloudWatchLogsClient $client;

    private string $group;

    private string $stream;

    private int $retention;

    private int $batchSize;

    /** @var list<Record> */
    private array $buffer = [];

    /** @var list<string> */
    private array $tags;

    private int $currentDataAmount = 0;

    private int $remainingRequests = self::RPS_LIMIT;

    private DateTime $savedTime;

    /**
     * ArsCloudWatchHandler constructor.
     *
     * @param array<string, string|mixed> $sdkParams The parameters to provide to AWS.
     * @param string $accountAlias Account alias, to create the log group name.
     * @param string $stage Environment stage, to create the log group name.
     * @param string $application Application name, to create the log group name.
     * @param int $retentionDays Days to keep logs, 90 by default.
     * @param int $batchSize How many log entries to store in memory before sending them to AWS, 10000 by default.
     * @param list<string> $tags The tags that should be added to the log group
     * @param CloudWatchLogsClient|null $client
     * @param ArsHeaderProcessorInterface|null $arsHeaderProcessor
     * @param string|null $streamName
     */
    public function __construct(
        array $sdkParams,
        string $accountAlias,
        string $stage,
        string $application,
        int $retentionDays = 90,
        int $batchSize = self::MAX_BATCH_SIZE,
        array $tags = [],
        CloudWatchLogsClient $client = null,
        ArsHeaderProcessorInterface $arsHeaderProcessor = null,
        string $streamName = null
    ) {
        if ($batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(sprintf('Batch size can not be greater than %s', self::MAX_BATCH_SIZE));
        }

        // Log group name, will be created if none
        $groupName = strtolower(sprintf('/%s/%s/%s', $accountAlias, $stage, $application));

        $arsHeaderProcessor ??= new ArsHeaderProcessor();

        $this->client = $client ?? new CloudWatchLogsClient($sdkParams);
        $this->group = $groupName;
        $this->stream = $streamName ?? sprintf(
            '%s/%s',
            $arsHeaderProcessor->getCorrelationId(),
            $arsHeaderProcessor->getRequestId()
        );

        $this->retention = $retentionDays;
        $this->batchSize = $batchSize;
        $this->tags = $tags;

        parent::__construct();

        $this->savedTime = new DateTime();
        $this->pushProcessor($arsHeaderProcessor);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(LogRecord $record): void
    {
        $records = $this->formatRecords($record);

        foreach ($records as $currentRecord) {
            if ($this->currentDataAmount + $this->getMessageSize($currentRecord) >= self::DATA_AMOUNT_LIMIT) {
                $this->flushBuffer();
            }

            $this->addToBuffer($currentRecord);

            if (count($this->buffer) >= $this->batchSize) {
                $this->flushBuffer();
            }
        }
    }

    /**
     * @param Record $record
     */
    private function addToBuffer(array $record): void
    {
        $this->currentDataAmount += $this->getMessageSize($record);

        $this->buffer[] = $record;
    }

    private function flushBuffer(): void
    {
        if (! empty($this->buffer)) {
            $this->send($this->buffer);

            // clear buffer
            $this->buffer = [];

            // clear data amount
            $this->currentDataAmount = 0;
        }
    }

    private function checkThrottle(): void
    {
        $current = new DateTime();
        $diff = $current->diff($this->savedTime)->s;
        $sameSecond = $diff === 0;

        if ($sameSecond && $this->remainingRequests > 0) {
            $this->remainingRequests--;
        } elseif ($sameSecond && $this->remainingRequests === 0) {
            sleep(1);
            $this->remainingRequests = self::RPS_LIMIT;
        } elseif (! $sameSecond) {
            $this->remainingRequests = self::RPS_LIMIT;
        }

        $this->savedTime = new DateTime();
    }

    /**
     * http://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutLogEvents.html
     *
     * @param Record $record
     *
     * @return int
     */
    private function getMessageSize(array $record): int
    {
        return strlen($record['message']) + 26;
    }

    /**
     * Event size in the batch can not be bigger than 256 KB
     * https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/cloudwatch_limits_cwl.html
     *
     * @param LogRecord $entry
     *
     * @return list<Record>
     */
    private function formatRecords(LogRecord $entry): array
    {
        $formatted = $entry->formatted;

        if (is_array($formatted)) {
            $formatted = json_encode($formatted);
        }

        if (is_string($formatted)) {
            $entries = str_split($formatted, self::EVENT_SIZE_LIMIT);

            $timestamp = $entry->datetime->format('U.u') * 1000;
            $records = [];

            foreach ($entries as $currentEntry) {
                $records[] = [
                    'message' => $currentEntry,
                    'timestamp' => $timestamp,
                ];
            }

            return $records;
        }

        throw new InvalidArgumentException(sprintf('Unhandled message format : %s', gettype($formatted)));
    }

    /**
     * The batch of events must satisfy the following constraints:
     *  - The maximum batch size is 1,048,576 bytes, and this size is calculated as the sum of all event messages in
     * UTF-8, plus 26 bytes for each log event.
     *  - None of the log events in the batch can be more than 2 hours in the future.
     *  - None of the log events in the batch can be older than 14 days or the retention period of the log group.
     *  - The log events in the batch must be in chronological ordered by their timestamp (the time the event occurred,
     * expressed as the number of milliseconds since Jan 1, 1970 00:00:00 UTC).
     *  - The maximum number of log events in a batch is 10,000.
     *  - A batch of log events in a single request cannot span more than 24 hours. Otherwise, the operation fails.
     *
     * @param list<Record> $entries
     *
     * @throws CloudWatchLogsException Thrown by putLogEvents for example in case of an invalid sequence token
     */
    private function send(array $entries): void
    {
        // AWS expects to receive entries in chronological order...
        usort($entries, static fn (array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

        $data = [
            'logGroupName' => $this->group,
            'logStreamName' => $this->stream,
            'logEvents' => $entries,
        ];

        $this->checkThrottle();

        for ($attempts = 1; $attempts <= 2; ++$attempts) {
            try {
                $this->client->putLogEvents($data);

                return;
            } catch (CloudWatchLogsException $e) {
                if ($e->getAwsErrorCode() !== 'ResourceNotFoundException' || $attempts === 2) {
                    throw $e;
                }

                $this->initializeLogStream();
            }
        }
    }

    private function initializeGroup(): void
    {
        // create group and set retention policy if not created yet
        $createLogGroupArguments = ['logGroupName' => $this->group];

        if (! empty($this->tags)) {
            $createLogGroupArguments['tags'] = $this->tags;
        }

        try {
            $this->client->createLogGroup($createLogGroupArguments);
        } catch (CloudWatchLogsException $e) {
            if ($e->getAwsErrorCode() === 'ResourceAlreadyExistsException') {
                return;
            }

            throw $e;
        }

        $this->client->putRetentionPolicy(
            [
                'logGroupName' => $this->group,
                'retentionInDays' => $this->retention,
            ]
        );
    }

    private function initializeLogStream(): void
    {
        for ($attempts = 1; $attempts <= 2; ++$attempts) {
            try {
                $this
                    ->client
                    ->createLogStream(
                        [
                            'logGroupName' => $this->group,
                            'logStreamName' => $this->stream,
                        ]
                    );

                return;
            } catch (CloudWatchLogsException $e) {
                if ($e->getAwsErrorCode() === 'ResourceAlreadyExistsException') {
                    return;
                }

                if ($e->getAwsErrorCode() !== 'ResourceNotFoundException' || $attempts === 2) {
                    throw $e;
                }

                $this->initializeGroup();
            }
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        $arsJsonFormatter = new ArsJsonFormatter();

        // Default depth is 9 which is not enough for our needs
        $arsJsonFormatter->setMaxNormalizeDepth(30);

        return $arsJsonFormatter;
    }

    public function close(): void
    {
        $this->flushBuffer();
    }

    /**
     * @param string $stream
     */
    public function setStream(string $stream): void
    {
        $this->close();

        $this->stream = $stream;
    }
}
