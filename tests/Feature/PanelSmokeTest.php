<?php

namespace Tests\Feature;

use App\Models\Advice;
use App\Models\AiJob;
use App\Models\Company;
use App\Models\Document;
use App\Models\FollowUp;
use App\Models\InformationRequest;
use App\Models\Issue;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\PersonalNote;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Setiap halaman panel bisa dibuka setiap peran demo tanpa error server. */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_pages_render_for_every_demo_role(): void
    {
        $this->seed(DemoSeeder::class);
        $company = Company::where('code', 'ENR')->firstOrFail();
        $failures = [];
        $statuses = [];

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods()) && str_starts_with((string) $route->getName(), 'filament.app.'))
            ->reject(fn ($route) => str_contains($route->uri(), 'login') || str_contains($route->uri(), 'logout') || str_contains($route->uri(), 'multi-factor'));

        foreach (User::where('email', 'like', '%@demo.aksi.id')->get() as $user) {
            // Filament menyimpan hash kata sandi di sesi; kosongkan saat berganti pengguna.
            session()->flush();
            $this->actingAs($user);

            foreach ($routes as $route) {
                $uris = [$route->uri()];

                if (str_contains($route->uri(), '{record}')) {
                    $model = $this->modelFor($route->getName());
                    $id = $model ? $model::withoutGlobalScopes()->where('company_id', $company->id)->value('id') : null;
                    $uris = $id ? [str_replace('{record}', (string) $id, $route->uri())] : [];
                }

                foreach ($uris as $uri) {
                    $uri = '/'.str_replace(['{tenant:slug}', '{tenant}'], $company->slug, $uri);
                    $response = $this->get($uri);

                    $statuses[$response->status()] = ($statuses[$response->status()] ?? 0) + 1;

                    if ($response->status() >= 500) {
                        $failures[] = $user->email.' '.$uri.' => '.$response->status().' '.str($response->exception?->getMessage())->limit(300);
                    }
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
        $this->assertGreaterThan(100, $statuses[200] ?? 0, json_encode($statuses));
        fwrite(STDERR, 'Status halaman: '.json_encode($statuses).PHP_EOL);
    }

    public function test_platform_panel_renders_for_platform_admin(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::create(['name' => 'Admin', 'email' => 'root@example.test', 'password' => 'secret-password', 'is_platform_admin' => true]);

        foreach (['/platform', '/platform/companies', '/platform/users'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }

        $this->actingAs(User::where('email', 'utama@demo.aksi.id')->first())->get('/platform/companies')->assertForbidden();
    }

    public function test_mfa_setup_is_enforced_when_required(): void
    {
        // Middleware MFA dipasang saat rute didaftarkan, jadi aplikasi dimuat ulang dengan MFA wajib.
        foreach (['putenv', '_ENV', '_SERVER'] as $target) {
            $target === 'putenv' ? putenv('AKSI_MFA_REQUIRED=true') : $GLOBALS[$target]['AKSI_MFA_REQUIRED'] = 'true';
        }

        try {
            $this->refreshApplication();
            $this->artisan('migrate');
            $this->seed(DemoSeeder::class);
            $user = User::where('email', 'utama@demo.aksi.id')->first();

            $this->actingAs($user)->get('/app/enr')->assertRedirectContains('multi-factor-authentication/set-up');
            $this->actingAs($user)->get('/app/multi-factor-authentication/set-up')->assertOk();
        } finally {
            putenv('AKSI_MFA_REQUIRED=false');
            $_ENV['AKSI_MFA_REQUIRED'] = $_SERVER['AKSI_MFA_REQUIRED'] = 'false';
        }
    }

    protected function modelFor(string $routeName): ?string
    {
        $resource = str($routeName)->after('filament.app.resources.')->before('.')->toString();
        $map = [
            'documents' => Document::class,
            'meetings' => Meeting::class,
            'follow-ups' => FollowUp::class,
            'information-requests' => InformationRequest::class,
            'advices' => Advice::class,
            'issues' => Issue::class,
            'personal-notes' => PersonalNote::class,
            'memberships' => Membership::class,
            'spaces' => Space::class,
            'ai-jobs' => AiJob::class,
        ];

        return $map[$resource] ?? null;
    }
}
