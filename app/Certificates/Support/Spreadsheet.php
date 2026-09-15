<?php

namespace App\Certificates\Support;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\TempFolderOptionTrait;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * FastExcel/OpenSpout configured to use a temp folder inside storage/.
 *
 * OpenSpout defaults to sys_get_temp_dir() (C:\Windows\Temp on Windows),
 * which web-server processes often cannot write to.
 */
final class Spreadsheet
{
    public static function tempDir(): string
    {
        $dir = storage_path('app/tmp');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** A FastExcel instance whose reader and writer options use our temp dir. */
    public static function excel(mixed $data = null): FastExcel
    {
        $excel = $data === null ? new FastExcel : new FastExcel($data);

        return $excel->configureOptionsUsing(function (object $options) {
            if (in_array(TempFolderOptionTrait::class, class_uses_recursive($options), true)) {
                $options->setTempFolder(self::tempDir());
            }
        });
    }

    public static function readerOptions(): XlsxReaderOptions
    {
        $options = new XlsxReaderOptions;
        $options->setTempFolder(self::tempDir());

        return $options;
    }

    /** Unique path for a temporary xlsx we will write ourselves. */
    public static function tempFile(string $prefix = 'sheet-'): string
    {
        return self::tempDir().DIRECTORY_SEPARATOR.$prefix.bin2hex(random_bytes(8)).'.xlsx';
    }
}
