<?php

namespace App\Filament\App\Resources\AuditLogs;

use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AuditLogResource extends Resource
{
    use InteractsWithAksi;

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $modelLabel = 'Jejak audit';

    protected static ?string $pluralModelLabel = 'Jejak Audit';

    public static function canViewAny(): bool
    {
        return static::aksiCan('audit.lihat');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d/m/Y H:i:s'),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Sistem')->searchable(),
                TextColumn::make('action')->label('Tindakan')->badge()->color('gray')->searchable(),
                TextColumn::make('subject')->label('Objek')->state(fn (AuditLog $record) => $record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : '—'),
                TextColumn::make('meta')->label('Rincian')->state(fn (AuditLog $record) => $record->meta ? json_encode($record->meta, JSON_UNESCAPED_UNICODE) : '')->limit(80),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Jenis objek')->options(fn () => AuditLog::query()->distinct()->pluck('subject_type')->filter()->mapWithKeys(fn ($t) => [$t => class_basename($t)])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
