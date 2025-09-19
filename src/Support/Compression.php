<?php

declare(strict_types=1);

namespace TaskQueue\Support;

class Compression
{
    public static function compress(string $data): string
    {
        if (strlen($data) < 1024) {
            return $data; // Don't compress small data
        }

        $compressed = gzcompress($data, 6);
        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress data');
        }

        return base64_encode($compressed);
    }

    public static function decompress(string $compressedData): string
    {
        if (!self::isCompressed($compressedData)) {
            return $compressedData;
        }

        $data = base64_decode($compressedData, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid compressed data');
        }

        $decompressed = gzuncompress($data);
        if ($decompressed === false) {
            throw new \RuntimeException('Failed to decompress data');
        }

        return $decompressed;
    }

    public static function isCompressed(string $data): bool
    {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        // Check for gzip magic number
        return substr($decoded, 0, 2) === "\x1f\x8b";
    }

    public static function getCompressionRatio(string $original, string $compressed): float
    {
        if (strlen($original) === 0) {
            return 0.0;
        }

        return (strlen($original) - strlen($compressed)) / strlen($original) * 100;
    }
}
