<?php

namespace App\Filament\App\Resources\Issues;

use App\Enums\EvidenceStatus;
use App\Enums\IssueStatus;
use App\Enums\Materiality;
use App\Filament\App\Resources\Issues\Pages\CreateIssue;
use App\Filament\App\Resources\Issues\Pages\EditIssue;
use App\Filament\App\Resources\Issues\Pages\ListIssues;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Issue;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class IssueResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Issue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'Isu pengawasan';

    protected static ?string $pluralModelLabel = 'Isu Pengawasan';

    public static function canViewAny(): bool
    {
        return static::aksiCan('isu.kelola');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('space_id', static::visibleSpaceIds());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('title')->label('Isu')->required()->columnSpanFull(),
                Textarea::make('description')->label('Uraian dan alasan prioritas')->rows(4)->columnSpanFull(),
                Select::make('materiality')->label('Materialitas')->options(Materiality::class)->default(Materiality::Sedang)->required(),
                Select::make('evidence_status')->label('Status bukti')->options(EvidenceStatus::class)->default(EvidenceStatus::Indikasi)->required()
                    ->helperText('Bedakan fakta terverifikasi dari indikasi atau dugaan.'),
                Select::make('status')->label('Status')->options(IssueStatus::class)->default(IssueStatus::Dibuka)->required(),
                Select::make('space_id')->label('Ruang')->options(fn () => static::spaceOptions())->required(),
                DatePicker::make('data_period_end')->label('Periode data s.d.'),
                TextInput::make('source_note')->label('Sumber (dokumen/halaman)'),
                Select::make('owner_user_id')->label('Pemilik pemantauan')->options(fn () => static::memberOptions()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Isu')->wrap()->searchable()->description(fn (Issue $record) => $record->source_note),
                TextColumn::make('materiality')->label('Materialitas')->badge(),
                TextColumn::make('evidence_status')->label('Status bukti')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('data_period_end')->label('Periode data')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('owner.name')->label('Pemilik')->placeholder('—'),
                TextColumn::make('updated_at')->label('Diperbarui')->since(),
            ])
            ->filters([
                SelectFilter::make('materiality')->label('Materialitas')->options(Materiality::class),
                SelectFilter::make('status')->options(IssueStatus::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssues::route('/'),
            'create' => CreateIssue::route('/create'),
            'edit' => EditIssue::route('/{record}/edit'),
        ];
    }
}
