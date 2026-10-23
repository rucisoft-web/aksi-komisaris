<?php

namespace App\Filament\App\Resources\Meetings\RelationManagers;

use App\Models\AgendaItem;
use App\Models\AgendaMaterial;
use App\Models\Document;
use App\Models\User;
use App\Services\MeetingService;
use App\Support\AccessGate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgendaItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'agendaItems';

    protected static ?string $modelLabel = 'agenda';

    protected static ?string $pluralModelLabel = 'agenda';

    protected static ?string $title = 'Agenda dan bahan';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function canManage(): bool
    {
        return auth()->user()->canIn('rapat.kelola', $this->getOwnerRecord()->company_id);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sort')->label('No.')->numeric()->default(fn () => $this->getOwnerRecord()->agendaItems()->count() + 1)->required(),
            TextInput::make('title')->label('Agenda')->required(),
            TextInput::make('presenter')->label('Penanggung jawab bahan'),
            Textarea::make('description')->label('Keterangan')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();
        $gate = app(AccessGate::class);

        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('sort')->label('No.'),
                TextColumn::make('title')->label('Agenda')->wrap()->description(fn (AgendaItem $record) => $record->presenter),
                TextColumn::make('materials_list')->label('Bahan')->html()->state(function (AgendaItem $record) use ($user, $gate) {
                    $materials = AgendaMaterial::query()->where('agenda_item_id', $record->id)->with(['document', 'version'])->get();

                    if ($materials->isEmpty()) {
                        return '<span style="color:#dc2626">Belum ada bahan</span>';
                    }

                    return $materials->map(function (AgendaMaterial $m) use ($user, $gate) {
                        if (! $m->document || ! $gate->canViewDocument($user, $m->document)) {
                            return '<span style="opacity:.6">Bahan tertutup untuk Anda</span>';
                        }

                        $versionId = $m->document_version_id ?? $m->document->current_version_id;
                        $label = e($m->document->title).($m->version ? ' (v'.$m->version->version_no.')' : '').($m->is_addendum ? ' · adendum' : '');

                        return $versionId ? '<a href="'.route('berkas.lihat', $versionId).'" target="_blank" style="text-decoration:underline">'.$label.'</a>' : $label;
                    })->implode('<br>');
                }),
                TextColumn::make('conflicts_count')->label('Benturan')->counts('conflicts')->badge()->color('warning'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah agenda')->visible(fn () => $this->canManage()),
            ])
            ->recordActions([
                Action::make('attach')
                    ->label('Lampirkan bahan')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn () => $this->canManage())
                    ->schema([
                        Select::make('document_id')->label('Dokumen')->searchable()->required()
                            ->options(fn () => $gate->scopeDocuments(Document::query(), $user, $this->getOwnerRecord()->company)->latest('updated_at')->limit(300)->pluck('title', 'id')),
                    ])
                    ->action(function (AgendaItem $record, array $data) {
                        $material = app(MeetingService::class)->attachMaterial($this->getOwnerRecord(), $record->id, (int) $data['document_id']);
                        Notification::make()->title($material->is_addendum ? 'Bahan ditambahkan sebagai adendum.' : 'Bahan ditambahkan.')->success()->send();
                    }),
                EditAction::make()->visible(fn () => $this->canManage() && ! $this->getOwnerRecord()->isFrozen()),
                DeleteAction::make()->visible(fn () => $this->canManage() && ! $this->getOwnerRecord()->isFrozen()),
            ]);
    }
}
