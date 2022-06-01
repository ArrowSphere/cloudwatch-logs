<?php

declare(strict_types=1);

namespace ArrowSphere\CloudWatchLogs\Tests\Formatter;

use ArrowSphere\CloudWatchLogs\Formatter\ArsJsonFormatter;
use JsonException;
use PHPUnit\Framework\TestCase;

class ArsJsonFormatterTest extends TestCase
{
    public function providerFormat(): array
    {
        return [
            'simple record with only message' => [
                'record' => [
                    'message' => 'my message',
                ],
                'expected' => [
                    'type' => 'UNKNOWN',
                    'message' => 'my message',
                    'tags' => [],
                    'entries' => [],
                ],
            ],
            'record with all fields' => [
                'record' => [
                    'message' => 'my message',
                    'level_name' => 'INFO',
                    'context' => [
                        'my context' => 'test',
                        'my context 2' => [
                            'my sub-context 1' => 123,
                            'my sub-context 2' => false,
                        ],
                    ],
                    'extra' => [
                        'ars-correlation-id' => 'correlationId',
                        'ars-request-id' => 'requestId',
                        'ars-parent-id' => 'parentId',
                        'extra key 1' => 'key 1',
                        'extra key 2' => [
                            'extra sub key 1' => 456,
                            'extra sub key 2' => true,
                        ],
                    ],
                ],
                'expected' => [
                    'type' => 'INFO',
                    'message' => 'my message',
                    'tags' => [],
                    'entries' => [],
                    'ars' => [
                        'correlation' => 'correlationId',
                        'request' => 'requestId',
                        'parent' => 'parentId',
                    ],
                    'context' => [
                        'my context' => 'test',
                        'my context 2' => [
                            'my sub-context 1' => 123,
                            'my sub-context 2' => false,
                        ],
                        'extra' => [
                            'extra key 1' => 'key 1',
                            'extra key 2' => [
                                'extra sub key 1' => 456,
                                'extra sub key 2' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider providerFormat
     *
     * @param array $record
     * @param array $expected
     *
     * @throws JsonException
     */
    public function testFormat(array $record, array $expected): void
    {
        $actual = (new ArsJsonFormatter())->format($record);

        self::assertSame($expected, json_decode($actual, true, 512, JSON_THROW_ON_ERROR));
    }
}
