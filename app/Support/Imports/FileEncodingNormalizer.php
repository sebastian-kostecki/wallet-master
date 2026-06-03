<?php

declare(strict_types=1);

namespace App\Support\Imports;

use RuntimeException;

final class FileEncodingNormalizer
{
    /**
     * Returns a UTF-8 readable path (original or temporary file).
     */
    public function resolveReadablePath(string $absolutePath): string
    {
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read import file.');
        }

        $hadBom = str_starts_with($contents, "\xEF\xBB\xBF");

        if ($hadBom) {
            $contents = substr($contents, 3);
        }

        $utf8Contents = $this->convertToUtf8($contents);

        if (! $hadBom && $utf8Contents === $contents && mb_check_encoding($contents, 'UTF-8')) {
            return $absolutePath;
        }

        return $this->writeTempFile($absolutePath, $utf8Contents);
    }

    public function cleanup(string $resolvedPath, string $originalPath): void
    {
        if ($resolvedPath !== $originalPath && is_file($resolvedPath)) {
            @unlink($resolvedPath);
        }
    }

    private function convertToUtf8(string $contents): string
    {
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        if (in_array('ISO-8859-2', mb_list_encodings(), true)) {
            $converted = @mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-2');

            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        foreach (['WINDOWS-1250', 'CP1250'] as $fromEncoding) {
            $converted = @iconv($fromEncoding, 'UTF-8//IGNORE', $contents);

            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $contents;
    }

    private function writeTempFile(string $originalPath, string $contents): string
    {
        $tempPath = dirname($originalPath).'/'.pathinfo($originalPath, PATHINFO_FILENAME).'.utf8.tmp';

        if (file_put_contents($tempPath, $contents) === false) {
            throw new RuntimeException('Unable to write normalized import file.');
        }

        return $tempPath;
    }
}
