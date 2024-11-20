<?php

namespace ArrowSphere\CloudWatchLogs\Tests;

use Monolog\Level;
use Monolog\LogRecord;
use DateTimeImmutable;

class LogRecordFaker
{
    /**
     * @param Level             $level
     * @param string            $message
     * @param DateTimeImmutable|bool $datetime
     *
     * @return LogRecord
     */
    public static function getRecord(
        Level $level = Level::Warning,
        string $message = 'test',
        DateTimeImmutable|bool $datetime = null
    ): LogRecord {
        if ($datetime === null) {
            /** @var DateTimeImmutable $datetime */
            $datetime = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        }

        $channel = 'test';
        $context = [];
        $extra = [];

        return new LogRecord(
            $datetime,
            $channel,
            $level,
            $message,
            $context,
            $extra
        );
    }

    /**
     * @return array
     */
    public static function getMultipleRecords(): array
    {
        return [
            static::getRecord(Level::Debug, 'debug message 1'),
            static::getRecord(Level::Debug, 'debug message 2'),
            static::getRecord(Level::Info, 'information'),
            static::getRecord(Level::Warning, 'warning'),
            static::getRecord(Level::Error, 'error'),
        ];
    }
}
