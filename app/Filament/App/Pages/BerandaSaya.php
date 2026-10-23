<?php

namespace App\Filament\App\Pages;

use App\Enums\FollowUpStatus;
use App\Enums\IssueStatus;
use App\Enums\RequestStatus;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\AgendaMaterial;
use App\Models\AiJob;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\PersonalNote;
use App\Models\RequiredDocumentReceipt;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

/**
 * Beranda pribadi komisaris. Tidak ada skor tunggal; setiap masalah material
 * tampil sendiri dengan periode data, tanggal pembaruan, sumber, pemilik, dan status bukti.
 */
class BerandaSaya extends Page
{
    use InteractsWithAksi;

    protected static string $routePath = '/';

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    protected static ?int $navigationSort = -10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Beranda Saya';

    protected static ?string $title = 'Beranda Saya';

    protected string $view = 'filament.pages.beranda-saya';

    public function getViewData(): array
    {
        $user = static::aksiUser();
        $company = static::aksiCompany();
        $spaces = static::visibleSpaceIds();
        $role = $user->roleIn($company);
        $isLiaison = $role?->value === 'penghubung';

        $followUps = FollowUp::query()
            ->with('pic')
            ->where('status', '!=', FollowUpStatus::Ditutup->value)
            ->whereDate('target_date', '<=', now()->addDays(14))
            ->when($isLiaison || ! $user->canIn('tindaklanjut.lihat', $company), fn ($q) => $q->where('pic_user_id', $user->id))
            ->orderBy('target_date')
            ->limit(10)
            ->get();

        if ($isLiaison) {
            return [
                'isLiaison' => true,
                'role' => $role,
                'requests' => InformationRequest::query()->where('addressee_user_id', $user->id)->where('status', RequestStatus::Dikirim->value)->orderBy('due_date')->get(),
                'followUps' => $followUps,
            ];
        }

        $nextMeetings = Meeting::query()->whereIn('space_id', $spaces)->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->limit(3)->with('agendaItems')->get()
            ->map(function (Meeting $meeting) {
                $agendaIds = $meeting->agendaItems->pluck('id');
                $withMaterials = AgendaMaterial::query()->whereIn('agenda_item_id', $agendaIds)->distinct()->pluck('agenda_item_id');

                return [
                    'meeting' => $meeting,
                    'agenda_count' => $agendaIds->count(),
                    'materials_count' => AgendaMaterial::query()->whereIn('agenda_item_id', $agendaIds)->count(),
                    'missing' => $meeting->agendaItems->whereNotIn('id', $withMaterials)->pluck('title'),
                ];
            });

        return [
            'isLiaison' => false,
            'role' => $role,
            'issues' => Issue::query()->with('owner')->whereIn('space_id', $spaces)->where('status', '!=', IssueStatus::Selesai->value)
                ->orderByRaw("case materiality when 'tinggi' then 0 when 'sedang' then 1 else 2 end")->latest('updated_at')->limit(6)->get(),
            'nextMeetings' => $nextMeetings,
            'changes' => static::gate()->scopeDocuments(Document::query(), $user, $company)->with('currentVersion')
                ->where('documents.updated_at', '>=', now()->subDays(14))->latest('documents.updated_at')->limit(8)->get(),
            'followUps' => $followUps,
            'missingDocs' => RequiredDocumentReceipt::query()->with('requiredDocument')->whereNull('document_id')
                ->whereIn('status', ['belum_diterima', 'terlambat'])->orderBy('due_date')->limit(8)->get(),
            'pendingRequests' => InformationRequest::query()->whereIn('space_id', $spaces)->where('status', RequestStatus::Dikirim->value)->orderBy('due_date')->limit(8)->get(),
            'notes' => PersonalNote::query()->where('user_id', $user->id)->latest('updated_at')->limit(5)->get(),
            'brief' => AiJob::query()->where('user_id', $user->id)->where('status', 'selesai')->latest()->first(),
        ];
    }
}
