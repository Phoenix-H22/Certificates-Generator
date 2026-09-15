<?php

namespace App\Filament\Pages;

use App\Models\OrganisationSettings;
use App\Models\Template;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Singleton settings: organisation names and delivery message wording. */
class OrganisationSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'بيانات الجهة';

    protected static ?string $title = 'بيانات الجهة ورسائل الإرسال';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعداد';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.organisation-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(OrganisationSettings::current()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $placeholders = '{name} {title} {organisation} {parent_organisation} {event_name} {event_date} {verify_url} {download_url} {code} {issued_at}';

        return $schema
            ->components([
                Section::make('الجهة المُصدِرة')
                    ->description('تظهر هذه الأسماء على الشهادة وفي صفحة التحقق.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ar')->label('اسم الجهة (عربي)')->required()->maxLength(255),
                        TextInput::make('name_en')->label('اسم الجهة (إنجليزي)')->maxLength(255),
                        TextInput::make('parent_name_ar')->label('الجهة الأم (مثل الهيئة العامة لتعليم الكبار)')->maxLength(255),
                        TextInput::make('website')->label('الموقع الإلكتروني')->url()->maxLength(255),
                        Select::make('default_template_id')
                            ->label('القالب الافتراضي')
                            ->options(fn () => Template::active()->pluck('name', 'id'))
                            ->searchable(),
                        Textarea::make('verification_footer')->label('نص إضافي في صفحة التحقق')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('رسائل الإرسال')
                    ->description('العناصر المتاحة: '.$placeholders)
                    ->schema([
                        TextInput::make('email_subject')->label('عنوان البريد الإلكتروني')->required()->maxLength(255),
                        Textarea::make('email_body')->label('نص البريد الإلكتروني')->required()->rows(9),
                        Textarea::make('whatsapp_message')->label('رسالة واتساب')->required()->rows(6),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        OrganisationSettings::current()->update($data);

        Notification::make()->success()->title('تم حفظ الإعدادات')->send();
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('save')->label('حفظ')->submit('save')->keyBindings(['mod+s']),
        ];
    }
}
