<?php

namespace App\Certificates\Pipeline;

use App\Certificates\Data\FieldSchema;
use App\Models\Template;
use Rap2hpoutre\FastExcel\FastExcel;

/** Builds the downloadable "fill this in" xlsx for a template. */
final class SampleSheetBuilder
{
    /** @return string absolute path of the generated xlsx (temp file) */
    public function build(Template $template): string
    {
        $headers = [];

        foreach (FieldSchema::CORE_FIELDS as $key => $meta) {
            $headers[$key] = $meta['label'];
        }

        foreach ($template->rowFields() as $field) {
            $headers[$field->key] = $field->label;
        }

        $examples = [
            ['name' => 'أحمد محمود عبد الرحمن', 'title' => 'الدكتور', 'email' => 'ahmed@example.com', 'phone' => '01012345678'],
            ['name' => 'سارة محمد علي', 'title' => 'الأستاذة', 'email' => 'sara@example.com', 'phone' => '01198765432'],
        ];

        $rows = collect($examples)->map(function (array $example) use ($headers) {
            $row = [];

            foreach ($headers as $key => $label) {
                $row[$label] = $example[$key] ?? '';
            }

            return $row;
        });

        $path = tempnam(sys_get_temp_dir(), 'sample-sheet-').'.xlsx';

        (new FastExcel($rows))->export($path);

        return $path;
    }

    public function fileName(Template $template): string
    {
        return 'participants-'.$template->slug.'.xlsx';
    }
}
