<?php

declare(strict_types=1);

namespace TaskQueue\Support;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function createStyledLogger(string $name, string $stream = 'php://stdout', Level $level = Level::Debug): LoggerInterface
    {
        $logger = new Logger($name);

        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor(true));
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $colors = [
                Level::Debug->value => "\033[36m",
                Level::Info->value => "\033[32m",
                Level::Notice->value => "\033[34m",
                Level::Warning->value => "\033[33m",
                Level::Error->value => "\033[31m",
                Level::Critical->value => "\033[35m",
                Level::Alert->value => "\033[95m",
                Level::Emergency->value => "\033[41;97m",
            ];
            $levelValue = $record->level->value;
            $record->extra['level_color_start'] = $colors[$levelValue] ?? "\033[0m";
            $record->extra['level_color_end'] = "\033[0m";
            return $record;
        });

        $handler = new StreamHandler($stream, $level);
        $format = "%datetime% %extra.level_color_start%[%level_name%]%extra.level_color_end% %message% %context% %extra%\n";
        $formatter = new LineFormatter($format, 'Y-m-d H:i:s', true, true);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        return $logger;
    }
}
