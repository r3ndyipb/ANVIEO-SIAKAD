<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleNavigationTest extends TestCase
{
    public function test_navigation_collapses_at_tablet_widths(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'admin-tablet-navigation',
            'legacy_id' => 'admin-tablet-navigation',
            'legacy_type' => 'admin',
            'login' => 'admin-tablet-navigation',
            'name' => 'Admin Tablet',
            'role' => 'admin',
            'email' => 'tablet@example.test',
            'password' => 'not-used',
            'remember_token' => null,
        ]));

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('name="application-name" content="siakad smartkampus.id"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false)
            ->assertSee('ASEAN University International. All Rights Reserved.')
            ->assertSee('@media (max-width: 1100px), (hover: none) and (pointer: coarse) and (max-width: 1400px)', false)
            ->assertSee("window.matchMedia('(max-width: 1100px), (hover: none) and (pointer: coarse) and (max-width: 1400px)')", false)
            ->assertSee("window.addEventListener('orientationchange', closeSidebarAfterRotation)", false)
            ->assertSee('data-open-navigation', false)
            ->assertSee('data-close-navigation', false);
    }

    public function test_every_legacy_role_has_a_menu_and_only_calendar_roles_see_its_route(): void
    {
        $roles = [
            'admin' => 'Data Master',
            'dosen' => 'Jadwal Mata Kuliah',
            'asdos' => 'Absensi Kuliah',
            'mahasiswa' => 'Kartu Rencana Studi',
            'regulang' => 'Registrasi Ulang',
            'pimuniv' => 'Monitoring KRS',
            'pimfak' => 'Rekapitulasi Fakultas',
            'timkur' => 'Dashboard',
            'userprodi' => 'Transfer Nilai MBKM',
            'usertu' => 'Operator Prodi',
            'keuangan' => 'Pembayaran Uang Kuliah',
            'wisuda' => 'Periode Wisuda',
            'validasi' => 'Validasi Nilai',
            'lppm' => 'Lokasi KKN',
        ];
        $calendarRoles = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];

        foreach ($roles as $role => $expectedMenuLabel) {
            $this->actingAs(new GenericUser([
                'id' => $role.'-navigation-test',
                'legacy_id' => $role.'-navigation-test',
                'legacy_type' => 'admin',
                'login' => $role.'-navigation-test',
                'name' => 'Pengguna '.$role,
                'role' => $role,
                'email' => 'navigation@example.test',
                'password' => 'not-used',
                'remember_token' => null,
            ]));

            $response = $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee(config('simak.role_labels.'.$role))
                ->assertSee($expectedMenuLabel)
                ->assertDontSee('E-Kurikulum OBE');

            foreach (config('simak.navigation.'.$role) as $group) {
                foreach ($group['items'] as $item) {
                    $response->assertSee($item['label']);
                    if (isset($item['route'])) {
                        $response->assertSee(route($item['route']));
                    }
                }
            }

            if (in_array($role, $calendarRoles, true)) {
                $response->assertSee(route('academic-calendars.index'));
            } else {
                $response->assertDontSee(route('academic-calendars.index'));
            }
        }
    }

    public function test_wisuda_menu_items_link_to_available_read_and_management_modules(): void
    {
        foreach (['wisuda'] as $role) {
            $this->actingAs(new GenericUser([
                'id' => $role.'-navigation-test',
                'legacy_id' => $role.'-navigation-test',
                'login' => $role.'-navigation-test',
                'name' => 'Pengguna '.$role,
                'role' => $role,
                'email' => 'navigation@example.test',
                'password' => 'not-used',
                'remember_token' => null,
            ]));

            $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee(route('graduation-periods.index'))
                ->assertSee(route('graduation-participants.index'))
                ->assertSee(route('graduation-validations.index'))
                ->assertSee(route('krs.index'))
                ->assertSee(route('khs.index'))
                ->assertSee(route('transcripts.index'));
        }
    }

    public function test_finance_role_has_only_the_finance_dashboard_menu_item(): void
    {
        $dashboardItems = collect(config('simak.navigation.keuangan'))
            ->firstWhere('label', 'Dashboard')['items'];

        $this->assertSame(['Dashboard Keuangan'], collect($dashboardItems)->pluck('label')->all());
        $this->assertSame(['finance.dashboard'], collect($dashboardItems)->pluck('route')->all());
    }

    public function test_short_semester_menu_is_shown_only_to_academic_management_roles(): void
    {
        $managementRoles = ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'];
        $lecturerRoles = ['dosen', 'asdos'];

        foreach (array_keys(config('simak.role_labels')) as $role) {
            $groups = collect(config('simak.navigation.'.$role));
            $group = $groups->firstWhere('label', 'Semester Pendek');

            if (in_array($role, $managementRoles, true)) {
                $this->assertNotNull($group, "Role {$role} harus melihat grup Semester Pendek.");
                $this->assertSame(['Kurikulum SP', 'Jadwal SP', 'Nilai SP'], collect($group['items'])->pluck('label')->all());
                $this->assertSame('short-semester-curricula.index', $group['items'][0]['route']);
                $this->assertSame('short-semester-schedules.index', $group['items'][1]['route']);
                $this->assertSame('short-semester-grades.index', $group['items'][2]['route']);
                $this->assertNotContains('KRS SP', collect($group['items'])->pluck('label')->all());
                $this->assertSame('calendar-days', config('simak.navigation_group_icons.Semester Pendek'));
            } elseif (in_array($role, $lecturerRoles, true)) {
                $this->assertNotNull($group, "Role {$role} harus melihat menu Nilai SP.");
                $this->assertSame(['Nilai SP'], collect($group['items'])->pluck('label')->all());
                $this->assertSame('short-semester-grades.index', $group['items'][0]['route']);
            } else {
                $this->assertNull($group, "Role {$role} tidak boleh melihat grup Semester Pendek.");
            }
        }
    }

    public function test_program_service_menu_is_last_and_has_no_program_summary_placeholder(): void
    {
        foreach (['userprodi', 'usertu'] as $role) {
            $groups = collect(config('simak.navigation.'.$role));

            $this->assertSame('Layanan Prodi', $groups->last()['label']);
            $this->assertNotContains(
                'Rekapitulasi Prodi',
                $groups->flatMap(static fn (array $group): array => $group['items'])->pluck('label')->all()
            );
        }
    }

    public function test_leadership_finance_dashboard_is_grouped_under_dashboard(): void
    {
        $universityNavigation = collect(config('simak.navigation.pimuniv'));
        $universityDashboard = $universityNavigation->firstWhere('label', 'Dashboard');
        $this->assertContains('finance.dashboard', collect($universityDashboard['items'])->pluck('route')->all());
        $this->assertFalse($universityNavigation->contains('label', 'Keuangan'));

        foreach (['admin', 'pimfak'] as $role) {
            $navigation = collect(config('simak.navigation.'.$role));
            $routes = $navigation->flatMap(static fn (array $group): array => $group['items'])
                ->pluck('route')->filter()->all();
            $this->assertNotContains('finance.dashboard', $routes);
        }
    }

    public function test_new_academic_modules_are_linked_only_for_legacy_dispatcher_roles(): void
    {
        $routeRoles = [
            'krs.index' => ['admin', 'dosen', 'mahasiswa', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'validasi', 'wisuda', 'regulang'],
            'grade-management.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos'],
            'short-semester-grades.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'asdos'],
            'mbkm-transfers.index' => ['admin', 'userprodi'],
            'class-transfers.index' => ['admin', 'userprodi'],
            'course-attendance.index' => ['admin', 'userprodi', 'usertu', 'dosen', 'asdos'],
            'graduation-periods.index' => ['admin', 'wisuda'],
            'graduation-participants.index' => ['usertu', 'wisuda'],
            'graduation-validations.index' => ['admin', 'validasi', 'wisuda'],
            'grade-validation.index' => ['admin', 'validasi', 'lppm'],
            'camaba-verification.index' => ['admin', 'regulang'],
            'akm-feeder.index' => ['admin', 'userprodi', 'usertu'],
            'activity-teaching.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'student-activities.index' => ['admin', 'userprodi', 'usertu'],
            'student-akm.index' => ['admin', 'userprodi', 'usertu'],
            'survey-edom.questions.index' => ['admin', 'pimuniv', 'pimfak'],
            'survey-edom.results.index' => ['admin', 'userprodi', 'usertu', 'dosen', 'asdos', 'pimuniv', 'pimfak'],
            'academic-dashboard.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'lms-dashboard.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa'],
            'finance.dashboard' => ['pimuniv', 'keuangan'],
            'finance.midtrans-transactions.index' => ['admin', 'keuangan'],
            'theses.dashboard' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'],
            'theses.titles.index' => ['mahasiswa'],
            'theses.titles.validation.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'theses.assignments.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'theses.bimbingan.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa'],
            'theses.exams.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen'],
            'theses.upload.index' => ['admin'],
            'feeder-export.index' => ['admin', 'usertu'],
            'short-semester-schedules.index' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'kkn.periods.index' => ['admin'],
            'kkn.locations.index' => ['admin', 'lppm'],
            'kkn.proposals.index' => ['admin', 'validasi', 'usertu'],
            'kkn.validations.index' => ['admin', 'validasi'],
            'kkn.participants.index' => ['admin', 'lppm'],
            'kkn.student.index' => ['mahasiswa'],
            'student-payments.index' => ['mahasiswa'],
        ];

        foreach (config('simak.navigation') as $role => $groups) {
            $routes = collect($groups)->flatMap(static fn (array $group): array => $group['items'])
                ->pluck('route')->filter()->all();
            foreach ($routeRoles as $route => $allowedRoles) {
                $this->assertSame(
                    in_array($role, $allowedRoles, true),
                    in_array($route, $routes, true),
                    "Unexpected {$route} navigation access for {$role}."
                );
            }
        }

        $adminGroups = collect(config('simak.navigation.admin'))->keyBy('label');
        $this->assertFalse($adminGroups->has('Program & Laporan'));
        $this->assertFalse($adminGroups->has('Administrasi'));
        $this->assertTrue($adminGroups->has('Administrasi & Tools'));
        $adminTools = $adminGroups['Administrasi & Tools'];
        $adminToolLabels = collect($adminTools['items'])->pluck('label')->all();
        $adminToolRoutes = collect($adminTools['items'])->pluck('route')->filter()->all();
        $this->assertNotContains('Laporan Akademik', $adminToolLabels);
        $this->assertContains('Survey EDOM', $adminToolLabels);
        $this->assertContains('Export ke Feeder', $adminToolLabels);
        $this->assertNotContains('Inisialisasi Data Feeder', $adminToolLabels);
        $this->assertContains('survey-edom.questions.index', $adminToolRoutes);
        $this->assertContains('feeder-export.index', $adminToolRoutes);
        $this->assertContains('camaba-verification.index', $adminToolRoutes);
        $this->assertContains('akm-feeder.index', collect($adminTools['items'])->pluck('route')->all());
        $graduationLinks = collect(config('simak.navigation.admin'))->firstWhere('label', 'Data Wisuda');
        $this->assertSame(
            ['graduation-periods.index', 'graduation-validations.index'],
            collect($graduationLinks['items'])->pluck('route')->all()
        );
        $this->assertNotContains('graduation-participants.index', collect($graduationLinks['items'])->pluck('route')->all());
        $wisudaLinks = collect(config('simak.navigation.wisuda'))->firstWhere('label', 'Wisuda');
        $wisudaItems = collect($wisudaLinks['items']);
        $this->assertSame('graduation-validations.index', $wisudaItems->firstWhere('label', 'Validasi Peserta Wisuda')['route']);
        $validatorLinks = collect(config('simak.navigation.validasi'))->firstWhere('label', 'Validasi');
        $this->assertContains('Peserta Wisuda', collect($validatorLinks['items'])->pluck('label')->all());
        $registrationLinks = collect(config('simak.navigation.regulang'))->firstWhere('label', 'Registrasi');
        $this->assertContains('camaba-verification.index', collect($registrationLinks['items'])->pluck('route')->all());
    }

    public function test_dashboard_uses_a_white_content_card_with_a_green_theme(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'admin-theme-test',
            'legacy_id' => 'admin-theme-test',
            'login' => 'admin-theme-test',
            'name' => 'Admin Theme',
            'role' => 'admin',
            'email' => 'theme@example.test',
            'password' => 'not-used',
            'remember_token' => null,
        ]));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="simak-content"', false)
            ->assertSee('background: #fff; box-shadow: 0 10px 28px rgba(31, 69, 44, .08);', false)
            ->assertSee('background: linear-gradient(128deg, #c8ddc9 0%, #e3eddf 45%, #b9d3c2 100%);', false)
            ->assertDontSee('linear-gradient(105deg, #f6d84f', false);
    }

    public function test_navigation_groups_are_collapsed_until_their_submenu_is_active(): void
    {
        $user = new GenericUser([
            'id' => 'admin-navigation-state-test',
            'legacy_id' => 'admin-navigation-state-test',
            'login' => 'admin-navigation-state-test',
            'name' => 'Admin Navigation',
            'role' => 'admin',
            'email' => 'navigation-state@example.test',
            'password' => 'not-used',
            'remember_token' => null,
        ]);
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('is-collapsed', false)
            ->assertSee('inert', false);

        $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_thesis_dashboard_is_under_dashboard_and_program_staff_have_a_separate_thesis_menu(): void
    {
        foreach (['userprodi', 'usertu'] as $role) {
            $this->actingAs(new GenericUser([
                'id' => $role.'-navigation-test',
                'legacy_id' => $role.'-navigation-test',
                'id_prodi' => 'program-1',
                'login' => $role.'-navigation-test',
                'name' => 'Pengguna '.$role,
                'role' => $role,
                'email' => 'navigation@example.test',
            ]));

            $groups = collect(config('simak.navigation.'.$role))->keyBy('label');
            $this->assertContains(
                'theses.dashboard',
                collect($groups['Dashboard']['items'])->pluck('route')->all()
            );
            $this->assertSame(
                [
                    'theses.titles.validation.index',
                    'theses.assignments.index',
                    'theses.bimbingan.index',
                    'theses.exams.index',
                ],
                collect($groups['E-Skripsi']['items'])->pluck('route')->filter()->values()->all()
            );

            $this->get(route('dashboard'))->assertOk()
                ->assertSee('Dashboard E-Skripsi')
                ->assertSee('E-Skripsi');
        }
    }

    public function test_graduate_thesis_dashboard_is_grouped_under_dashboard_for_all_module_roles(): void
    {
        foreach (['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu', 'dosen', 'mahasiswa'] as $role) {
            $groups = collect(config('simak.navigation.'.$role))->keyBy('label');
            $dashboardRoutes = collect($groups['Dashboard']['items'])->pluck('route')->all();
            $this->assertContains('graduate_theses.dashboard', $dashboardRoutes, $role);

            $graduateThesisMenu = collect($groups['E-Tesis & Disertasi']['items'])
                ->pluck('route')->filter()->values()->all();
            $this->assertNotContains('graduate_theses.dashboard', $graduateThesisMenu, $role);
        }
    }

    public function test_e_kurikulum_menus_are_hidden_but_routes_remain_registered(): void
    {
        foreach (config('simak.navigation') as $role => $groups) {
            $this->assertFalse(
                collect($groups)->contains('label', 'E-Kurikulum OBE'),
                "Role {$role} should not see the E-Kurikulum OBE menu."
            );
        }

        $this->assertTrue(Route::has('ekurikulum.teams.index'));
        $this->assertTrue(Route::has('ekurikulum.profile.index'));
        $this->assertTrue(Route::has('ekurikulum.curricula.index'));
    }

    public function test_session_notice_renders_as_an_accessible_flash_toast(): void
    {
        $this->actingAs(new GenericUser([
            'id' => 'admin-toast-test',
            'legacy_id' => 'admin-toast-test',
            'login' => 'admin-toast-test',
            'name' => 'Admin Toast',
            'role' => 'admin',
            'email' => 'toast@example.test',
            'password' => 'not-used',
            'remember_token' => null,
        ]));

        $this->withSession(['status' => 'Perubahan berhasil disimpan.'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-flash-toast')
            ->assertSee('role="status"', false)
            ->assertSee('Perubahan berhasil disimpan.')
            ->assertSee('Tutup notifikasi');
    }
}
