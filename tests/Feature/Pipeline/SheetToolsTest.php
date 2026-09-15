<?php

use App\Certificates\Pipeline\ColumnMapper;
use App\Certificates\Pipeline\SampleSheetBuilder;
use App\Certificates\Pipeline\SheetReader;
use App\Models\Template;
use Tests\Support\MakesSheets;

uses(MakesSheets::class);

it('reads headers and rows from an xlsx', function () {
    $path = $this->makeSheet($this->participantRows());
    $reader = new SheetReader;

    expect($reader->headers($path))->toBe(['Name', 'Title', 'Email', 'Phone']);

    $rows = $reader->rows($path);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['name'])->toBe('أحمد محمود')
        ->and($rows[0]['email'])->toBe('ahmed@example.com');
});

it('refuses sheets over the row limit', function () {
    config()->set('certificates.max_rows', 2);
    $path = $this->makeSheet($this->participantRows());

    expect(fn () => (new SheetReader)->rows($path))->toThrow(RuntimeException::class, 'limit is 2');
});

it('suggests a column mapping from Arabic or English headers', function () {
    $template = Template::factory()->create([
        'fields_schema' => [
            ['key' => 'event_name', 'label' => 'اسم الفعالية', 'scope' => 'fixed'],
            ['key' => 'department', 'label' => 'الإدارة', 'scope' => 'row', 'required' => true],
        ],
    ]);

    $map = (new ColumnMapper)->suggest(['الاسم بالكامل', 'الدرجة العلمية', 'Email Address', 'رقم الواتساب', 'الإدارة'], $template);

    expect($map)->toBe([
        'name' => 'الاسم بالكامل',
        'title' => 'الدرجة العلمية',
        'email' => 'Email Address',
        'phone' => 'رقم الواتساب',
        'department' => 'الإدارة',
    ]);

    expect((new ColumnMapper)->missing(['name' => null, 'department' => null], $template))->toBe(['name', 'department']);
});

it('builds a sample sheet with the template columns', function () {
    $template = Template::factory()->create([
        'fields_schema' => [['key' => 'hours', 'label' => 'عدد الساعات', 'scope' => 'row', 'type' => 'number', 'required' => false]],
    ]);

    $path = (new SampleSheetBuilder)->build($template);

    expect((new SheetReader)->headers($path))->toBe(['الاسم', 'الصفة / اللقب', 'البريد الإلكتروني', 'رقم الهاتف', 'عدد الساعات']);
});
