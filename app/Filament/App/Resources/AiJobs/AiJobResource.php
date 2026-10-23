<?php

namespace App\Filament\App\Resources\AiJobs;

use App\Filament\App\Resources\AiJobs\Pages\CreateAiJob;
use App\Filament\App\Resources\AiJobs\Pages\ListAiJobs;
use App\Filament\App\Resources\AiJobs\Pages\ViewAiJob;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\AiJob;
use App\Models\Document;
use App\Models\InformationRequest;
use App\Models\Meeting;
use App\Services\Ai\AiModes;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AiJobResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = AiJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Asisten AI';

    protected static ?string $navigationLabel = 'Asisten AI';

    protected static ?string $modelLabel = 'Permintaan asisten';

    protected static ?string $pluralModelLabel = 'Asisten AI';

    public static function canViewAny(): bool
    {
        return static::aksiCan('ai.gunakan');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('ai.gunakan');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Riwayat asisten bersifat pribadi: hanya pemiliknya yang melihat. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Apa yang ingin Anda telaah?')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('mode')->label('Mode')->options(AiModes::options())->required()->live()
                        ->helperText(fn (Get $get) => AiModes::all()[$get('mode')]['description'] ?? null),
                    Select::make('meeting_id')->label('Rapat')
                        ->options(fn () => Meeting::query()->whereIn('space_id', static::visibleSpaceIds())->latest('scheduled_at')->limit(50)
                            ->get()->mapWithKeys(fn (Meeting $m) => [$m->id => $m->title.' · '.$m->scheduled_at->format('d/m/Y')]))
                        ->visible(fn (Get $get) => $get('mode') === 'siapkan_rapat')
                        ->required(fn (Get $get) => $get('mode') === 'siapkan_rapat'),
                    Select::make('information_request_id')->label('Permintaan informasi')
                        ->options(fn () => InformationRequest::query()->whereIn('space_id', static::visibleSpaceIds())->has('responses')->latest()->limit(50)
                            ->get()->mapWithKeys(fn ($r) => [$r->id => '#'.$r->id.' '.str($r->question)->limit(70)]))
                        ->visible(fn (Get $get) => $get('mode') === 'periksa_jawaban')
                        ->required(fn (Get $get) => $get('mode') === 'periksa_jawaban'),
                    Select::make('document_ids')->label('Dokumen sumber')->multiple()->searchable()
                        ->options(fn () => static::gate()->scopeDocuments(Document::query(), static::aksiUser(), static::aksiCompany())
                            ->latest('updated_at')->limit(300)->get()
                            ->mapWithKeys(fn (Document $d) => [$d->id => $d->title.($d->period_end ? ' ('.$d->period_end->format('m/Y').')' : '')]))
                        ->helperText('Kosongkan untuk mencari di semua dokumen yang boleh Anda akses.')
                        ->visible(fn (Get $get) => ! in_array($get('mode'), ['siapkan_rapat', 'periksa_jawaban'], true))
                        ->columnSpanFull(),
                    Textarea::make('prompt')->label('Permintaan Anda')->rows(4)->required()->columnSpanFull()
                        ->placeholder('Contoh: Tampilkan lima isu terpenting dan sumbernya. Pendapatan naik tetapi arus kas operasi turun, apa penjelasannya?'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('mode')->label('Mode')->formatStateUsing(fn ($state) => AiModes::options()[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('prompt')->label('Permintaan')->limit(80)->wrap(),
                TextColumn::make('status')->label('Status')->badge()->color(fn ($state) => match ($state) {
                    'selesai' => 'success', 'gagal' => 'danger', 'berjalan' => 'warning', default => 'gray',
                }),
                TextColumn::make('validation.unverified_items')->label('Butir ditandai')->placeholder('—'),
                TextColumn::make('created_at')->label('Dibuat')->since(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiJobs::route('/'),
            'create' => CreateAiJob::route('/create'),
            'view' => ViewAiJob::route('/{record}'),
        ];
    }
}
