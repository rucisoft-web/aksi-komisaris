<?php

namespace App\Filament\App\Resources\Advices;

use App\Enums\AdviceKind;
use App\Enums\AdviceStatus;
use App\Filament\App\Resources\Advices\Pages\CreateAdvice;
use App\Filament\App\Resources\Advices\Pages\EditAdvice;
use App\Filament\App\Resources\Advices\Pages\ListAdvices;
use App\Filament\App\Resources\Advices\Pages\ViewAdvice;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\Advice;
use App\Models\Meeting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AdviceResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = Advice::class;

    protected static ?string $slug = 'advices';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Nasihat';

    protected static ?string $pluralModelLabel = 'Nasihat & Rekomendasi';

    public static function canViewAny(): bool
    {
        return static::aksiCan('nasihat.buat') || static::aksiCan('tindaklanjut.kelola');
    }

    public static function canCreate(): bool
    {
        return static::aksiCan('nasihat.buat');
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === AdviceStatus::Draf && static::aksiCan('nasihat.buat');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('space_id', static::visibleSpaceIds());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('kind')->label('Jenis keluaran')->options(AdviceKind::class)->default(AdviceKind::Nasihat)->required()
                    ->helperText('Pilih sesuai kewenangan dewan. Tidak semua aksi korporasi memerlukan persetujuan komisaris.'),
                Select::make('space_id')->label('Ruang')->options(fn () => static::spaceOptions())->required(),
                TextInput::make('title')->label('Judul')->required()->columnSpanFull(),
                RichEditor::make('body')->label('Isi')->required()->columnSpanFull(),
                Textarea::make('basis')->label('Dasar (dokumen, angka, pembahasan)')->columnSpanFull(),
                Select::make('meeting_id')->label('Rapat terkait')->options(fn () => Meeting::query()->whereIn('space_id', static::visibleSpaceIds())->latest('scheduled_at')->pluck('title', 'id')),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('title')->label('Judul')->columnSpanFull()->weight('bold'),
                TextEntry::make('kind')->label('Jenis')->badge(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('approved_at')->label('Disahkan')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('delivered_at')->label('Disampaikan')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextEntry::make('body')->label('Isi')->html()->columnSpanFull(),
                TextEntry::make('basis')->label('Dasar')->columnSpanFull()->placeholder('—'),
                TextEntry::make('management_response')->label('Tanggapan manajemen')->columnSpanFull()->placeholder('Belum ada tanggapan.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->wrap()->searchable(),
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('follow_ups_count')->label('Tindak lanjut')->counts('followUps'),
                TextColumn::make('updated_at')->label('Diperbarui')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(AdviceStatus::class), SelectFilter::make('kind')->label('Jenis')->options(AdviceKind::class)])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdvices::route('/'),
            'create' => CreateAdvice::route('/create'),
            'view' => ViewAdvice::route('/{record}'),
            'edit' => EditAdvice::route('/{record}/edit'),
        ];
    }
}
