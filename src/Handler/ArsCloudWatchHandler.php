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

/**
 * Class ArsCloudWatchHandler
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

    private bool $initialized = false;

    private ?string $sequenceToken = null;

    private int $batchSize;

    private array $buffer = [];

    private array $tags;

    private int $currentDataAmount = 0;

    private int $remainingRequests = self::RPS_LIMIT;

    private DateTime $savedTime;

    /**
     * ArsCloudWatchHandler constructor.
     *
     * @param array $sdkParams The parameters to provide to AWS.
     * @param string $accountAlias Account alias, to create the log group name.
     * @param string $stage Environment stage, to create the log group name.
     * @param string $application Application name, to create the log group name.
     * @param int $retentionDays Days to keep logs, 90 by default.
     * @param int $batchSize How many log entries to store in memory before sending them to AWS, 10000 by default.
     * @param array $tags The tags that should be added to the log group
     * @param CloudWatchLogsClient|null $client
     * @param ArsHeaderProcessorInterface|null $arsHeaderProcessor
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
        ArsHeaderProcessorInterface $arsHeaderProcessor = null
    ) {
        if ($batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException(sprintf('Batch size can not be greater than %s', self::MAX_BATCH_SIZE));
        }

        // Log group name, will be created if none
        $groupName = strtolower(sprintf('/%s/%s/%s', $accountAlias, $stage, $application));

        $arsHeaderProcessor ??= new ArsHeaderProcessor();

        $this->client = $client ?? new CloudWatchLogsClient($sdkParams);
        $this->group = $groupName;
        $this->stream = $arsHeaderProcessor->getRequestId();
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
    protected function write(array $record): void
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
     * @param array $record
     */
    private function addToBuffer(array $record): void
    {
        $this->currentDataAmount += $this->getMessageSize($record);

        $this->buffer[] = $record;
    }

    private function flushBuffer(): void
    {
        if (! empty($this->buffer)) {
            if (! $this->initialized) {
                $this->initialize();
            }

            // send items, retry once with a fresh sequence token
            try {
                $this->send($this->buffer);
            } catch (CloudWatchLogsException $e) {
                $this->refreshSequenceToken();
                $this->send($this->buffer);
            }

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
     * @param array $record
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
     * @param array $entry
     * @return array
     */
    private function formatRecords(array $entry): array
    {
        $entries = str_split($entry['formatted'], self::EVENT_SIZE_LIMIT);
        $timestamp = $entry['datetime']->format('U.u') * 1000;
        $records = [];

        foreach ($entries as $currentEntry) {
            $records[] = [
                'message' => $currentEntry,
                'timestamp' => $timestamp,
            ];
        }

        return $records;
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
     * @param array $entries
     *
     * @throws CloudWatchLogsException Thrown by putLogEvents for example in case of an invalid sequence token
     */
    private function send(array $entries): void
    {
        // AWS expects to receive entries in chronological order...
        usort($entries, static fn(array $a, array $b) => $a['timestamp'] <=> $b['timestamp']);

        $data = [
            'logGroupName' => $this->group,
            'logStreamName' => $this->stream,
            'logEvents' => $entries,
        ];

        if (! empty($this->sequenceToken)) {
            $data['sequenceToken'] = $this->sequenceToken;
        }

        $this->checkThrottle();

        $response = $this->client->putLogEvents($data);

        /** @var string|null $nextSequenceToken */
        $nextSequenceToken = $response->get('nextSequenceToken');
        $this->sequenceToken = $nextSequenceToken;
    }

    private function initializeGroup(): void
    {
        // fetch existing groups
        /** @var array $existingGroups */
        $existingGroups = $this->client
            ->describeLogGroups(['logGroupNamePrefix' => $this->group])
            ->get('logGroups');

        // extract existing groups names
        $existingGroupsNames = array_column($existingGroups, 'logGroupName');

        // create group and set retention policy if not created yet
        if (! in_array($this->group, $existingGroupsNames, true)) {
            $createLogGroupArguments = ['logGroupName' => $this->group];

            if (! empty($this->tags)) {
                $createLogGroupArguments['tags'] = $this->tags;
            }

            $this->client->createLogGroup($createLogGroupArguments);

            $this->client->putRetentionPolicy(
                [
                    'logGroupName' => $this->group,
                    'retentionInDays' => $this->retention,
                ]
            );
        }
    }

    private function initialize(): void
    {
        $this->initializeGroup();
        $this->refreshSequenceToken();
    }

    private function refreshSequenceToken(): void
    {
        // fetch existing streams
        /** @var array $existingStreams */
        $existingStreams =
            $this
                ->client
                ->describeLogStreams(
                    [
                        'logGroupName' => $this->group,
                        'logStreamNamePrefix' => $this->stream,
                    ]
                )->get('logStreams');

        // extract existing streams names
        $existingStreamsNames = array_map(
            function (array $stream) {
                // set sequence token
                if ($stream['logStreamName'] === $this->stream && isset($stream['uploadSequenceToken'])) {
                    $this->sequenceToken = $stream['uploadSequenceToken'];
                }

                return $stream['logStreamName'];
            },
            $existingStreams
        );

        // create stream if not created
        if (! in_array($this->stream, $existingStreamsNames, true)) {
            $this
                ->client
                ->createLogStream(
                    [
                        'logGroupName' => $this->group,
                        'logStreamName' => $this->stream,
                    ]
                );
        }

        $this->initialized = true;
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        // return new LineFormatter("%channel%: %level_name%: %message% %context% %extra%", null, false, true);
        $arsJsonFormatter = new ArsJsonFormatter();

        // Default depth is 9 which is not enough for our needs
        $arsJsonFormatter->setMaxNormalizeDepth(30);

        return $arsJsonFormatter;
    }

    public function close(): void
    {
        $this->flushBuffer();
    }
}
