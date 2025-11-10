<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Tests\Formatter;

use ArrowSphere\CloudWatchLogs\Formatter\ArsJsonFormatter;
use DateTimeImmutable;
use Generator;
use JsonException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArsJsonFormatterTest extends TestCase
{
    /**
     * @return Generator
     */
    public static function providerFormat(): Generator
    {
        $channel = 'default';
        $message = 'my message';

        yield [
            'record'   => new LogRecord(new DateTimeImmutable(), $channel, Level::Debug, $message),
            'expected' => [
                'type'    => 'DEBUG',
                'message' => $message,
                'tags'    => [],
                'entries' => [],
            ],
        ];

        yield [
            'record'   => new LogRecord(
                new DateTimeImmutable(),
                $channel,
                Level::Info,
                $message,
                [
                    'my context'   => 'test',
                    'my context 2' => [
                        'my sub-context 1' => 123,
                        'my sub-context 2' => false,
                    ],
                ],
                [
                    'ars-correlation-id' => 'correlationId',
                    'ars-request-id'     => 'requestId',
                    'ars-parent-id'      => 'parentId',
                    'extra key 1'        => 'key 1',
                    'extra key 2'        => [
                        'extra sub key 1' => 456,
                        'extra sub key 2' => true,
                    ],
                ]
            ),
            'expected' => [
                'type'    => 'INFO',
                'message' => $message,
                'tags'    => [],
                'entries' => [],
                'ars'     => [
                    'correlation' => 'correlationId',
                    'request'     => 'requestId',
                    'parent'      => 'parentId',
                ],
                'context' => [
                    'my context'   => 'test',
                    'my context 2' => [
                        'my sub-context 1' => 123,
                        'my sub-context 2' => false,
                    ],
                    'extra'        => [
                        'extra key 1' => 'key 1',
                        'extra key 2' => [
                            'extra sub key 1' => 456,
                            'extra sub key 2' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('providerFormat')]
    public function testFormat(LogRecord $record, array $expected): void
    {
        $actual = (new ArsJsonFormatter())->format($record);

        self::assertSame($expected, json_decode($actual, true, 512, JSON_THROW_ON_ERROR));
    }
}
