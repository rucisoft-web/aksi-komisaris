<?php

namespace App\Filament\App\Pages;

use App\Enums\FollowUpStatus;
use App\Enums\IssueStatus;
use App\Enums\RequestStatus;
use App\Filament\Concerns\InteractsWithAksi;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\Meeting;
use App\Support\AccessGate;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Tampilan lintas perusahaan bagi komisaris yang menjabat di beberapa perusahaan.
 * Hanya hitungan dan tenggat; isi dokumen tidak pernah digabung antarperusahaan.
 */
class Portofolio extends Page
{
    use InteractsWithAksi;

    protected static ?int $navigationSort = -9;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Portofolio Saya';

    protected static ?string $title = 'Portofolio Saya';

    protected string $view = 'filament.pages.portofolio';

    public static function canAccess(): bool
    {
        return static::aksiUser()->getTenants(filament()->getCurrentPanel())->count() > 1;
    }

    public function getViewData(): array
    {
        $user = static::aksiUser();
        $gate = app(AccessGate::class);

        $rows = $user->getTenants(filament()->getCurrentPanel())->map(function ($company) use ($user, $gate) {
            $spaces = $gate->visibleSpaceIds($user, $company);
            $seeFollowUps = $user->canIn('tindaklanjut.lihat', $company);

            return [
                'company' => $company,
                'role' => $user->roleIn($company)?->getLabel(),
                'high_issues' => Issue::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('space_id', $spaces)
                    ->where('materiality', 'tinggi')->where('status', '!=', IssueStatus::Selesai->value)->count(),
                'overdue' => $seeFollowUps ? FollowUp::withoutGlobalScopes()->where('company_id', $company->id)
                    ->where('status', '!=', FollowUpStatus::Ditutup->value)->whereDate('target_date', '<', now())->count() : null,
                'waiting' => InformationRequest::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('space_id', $spaces)
                    ->where('status', RequestStatus::Dikirim->value)->count(),
                'next_meeting' => Meeting::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('space_id', $spaces)
                    ->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->first(),
            ];
        });

        return ['rows' => $rows];
    }
}
