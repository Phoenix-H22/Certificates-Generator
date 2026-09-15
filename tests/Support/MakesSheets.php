<?php

namespace Tests\Support;

use App\Certificates\Support\Spreadsheet;

/** Builds small xlsx files for pipeline tests. */
trait MakesSheets
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return string absolute path of a temporary xlsx
     */
    protected function makeSheet(array $rows): string
    {
        $path = Spreadsheet::tempFile('sheet-');

        Spreadsheet::excel(collect($rows))->export($path);

        return $path;
    }

    /** @return list<array<string, mixed>> */
    protected function participantRows(): array
    {
        return [
            ['Name' => 'أحمد محمود', 'Title' => 'الدكتور', 'Email' => 'ahmed@example.com', 'Phone' => '01012345678'],
            ['Name' => 'سارة علي', 'Title' => 'الأستاذة', 'Email' => 'not-an-email', 'Phone' => ''],
            ['Name' => '', 'Title' => 'مجهول', 'Email' => 'x@example.com', 'Phone' => '01098765432'],
        ];
    }
}
