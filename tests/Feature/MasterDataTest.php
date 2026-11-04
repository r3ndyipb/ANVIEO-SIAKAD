<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.simak_main' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        $this->createSchema();
    }

    public function test_master_routes_obey_dispatcher_roles_and_organization_scope(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-1', 'nidn' => '10001', 'nama_dosen' => 'Dosen Satu', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1'],
            ['id_dosen' => 'dosen-2', 'nidn' => '10002', 'nama_dosen' => 'Dosen Dua', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'asdos-1', 'kdasisten' => 'A001', 'nama' => 'Asisten Satu', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1'],
            ['id_asdos' => 'asdos-2', 'kdasisten' => 'A002', 'nama' => 'Asisten Dua', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2'],
        ]);

        $this->signIn('pimuniv');
        $this->get(route('master-data.program-studi.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.values.nama_program_studi', 'Prodi Satu')
            ->assertJsonPath('data.1.values.nama_program_studi', 'Prodi Dua');

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $this->get(route('master-data.program-studi.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_program_studi', 'Prodi Satu');
        $this->get(route('master-data.dosen.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_dosen', 'Dosen Satu');

        $this->signIn('pimfak', ['id_fakultas' => null]);
        $this->get(route('master-data.program-studi.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 0)
            ->assertJsonCount(0, 'data');

        $this->signIn('userprodi', ['id_prodi' => 'prodi-2']);
        $this->get(route('master-data.dosen.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_dosen', 'Dosen Dua');
        $this->get(route('master-data.asisten-dosen.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama', 'Asisten Dua');
        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);
        $this->get(route('master-data.asisten-dosen.data'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama', 'Asisten Satu');
        $this->get(route('master-data.perguruan-tinggi.index'))->assertForbidden();

        $this->signIn('regulang');
        $this->get(route('master-data.mahasiswa.index'))->assertOk();
        $this->get(route('master-data.mahasiswa.data'))->assertOk();
        $this->get(route('master-data.mata-kuliah.index'))->assertOk();
        $this->get(route('master-data.ruang.index'))->assertForbidden();
    }

    public function test_executive_master_dashboard_scopes_metrics_by_role(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'dash-mhs-1', 'nim' => 'D001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'dash-mhs-2', 'nim' => 'D002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dash-dosen-1', 'nidn' => 'N001', 'nama_dosen' => 'Dosen Satu', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'dash-dosen-2', 'nidn' => 'N002', 'nama_dosen' => 'Dosen Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'dash-asdos-1', 'kdasisten' => 'A001', 'nama' => 'Asisten Satu', 'id_prodi' => 'prodi-1'],
            ['id_asdos' => 'dash-asdos-2', 'kdasisten' => 'A002', 'nama' => 'Asisten Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'dash-mk-1', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'MK Satu', 'id_prodi' => 'prodi-1'],
            ['id_matkul' => 'dash-mk-2', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'MK Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            ['id_ruang' => 'dash-ruang-1', 'nama_ruang' => 'Ruang Satu', 'id_prodi' => 'prodi-1'],
            ['id_ruang' => 'dash-ruang-2', 'nama_ruang' => 'Ruang Dua', 'id_prodi' => 'prodi-2'],
        ]);

        $this->signIn('admin');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Seluruh universitas')
            ->assertSee('Prodi Satu')
            ->assertSee('Prodi Dua')
            ->assertSee('Aktif')
            ->assertSee('Cuti');

        $this->signIn('pimuniv');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Seluruh universitas')
            ->assertSee('Prodi Satu')
            ->assertSee('Prodi Dua');

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Fakultas Fakultas Satu')
            ->assertSee('Prodi Satu')
            ->assertDontSee('Prodi Dua')
            ->assertSee('Aktif')
            ->assertDontSee('Cuti');

        foreach (['userprodi', 'usertu'] as $role) {
            $this->signIn($role, ['id_prodi' => 'prodi-2']);
            $this->get(route('dashboard'))->assertOk()
                ->assertSee('Program studi Prodi Dua')
                ->assertSee('Prodi Dua')
                ->assertDontSee('Prodi Satu')
                ->assertSee('Cuti')
                ->assertDontSee('Aktif');
        }

        $this->signIn('dosen', ['id_prodi' => 'prodi-2']);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Selamat datang, Pengguna Uji.')
            ->assertDontSee('dashboard-visualization-data', false)
            ->assertDontSee('Ringkasan data master');

        foreach (['dosen', 'asdos', 'mahasiswa', 'regulang', 'wisuda', 'validasi', 'lppm', 'timkur'] as $role) {
            $navigation = config('simak.navigation.'.$role);
            $this->assertSame('Dashboard', $navigation[0]['items'][0]['label']);
        }
        $this->assertSame('Dashboard Keuangan', config('simak.navigation.keuangan.0.items.0.label'));
        foreach (['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'] as $role) {
            $navigation = config('simak.navigation.'.$role);
            $this->assertSame('Dashboard Master', $navigation[0]['items'][0]['label']);
            $this->assertSame('Dashboard Akademik', $navigation[0]['items'][1]['label']);
        }
    }

    public function test_master_dashboard_excludes_programs_without_active_status(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'prodi-2')
            ->update(['status' => 'N']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'active-program-student', 'nim' => 'ACTIVE-1', 'nama_mahasiswa' => 'Mahasiswa Aktif', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'inactive-program-student', 'nim' => 'INACTIVE-1', 'nama_mahasiswa' => 'Mahasiswa Prodi Nonaktif', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'active-program-lecturer', 'nidn' => 'ACTIVE-D', 'nama_dosen' => 'Dosen Aktif', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'inactive-program-lecturer', 'nidn' => 'INACTIVE-D', 'nama_dosen' => 'Dosen Prodi Nonaktif', 'id_prodi' => 'prodi-2'],
        ]);

        $this->signIn('admin');
        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('Prodi Satu')
            ->assertDontSee('Prodi Dua')
            ->assertDontSee('Mahasiswa Prodi Nonaktif');

        $this->assertSame(1, collect($response->viewData('cards'))->firstWhere('label', 'Program studi')['value']);
        $this->assertSame(1, collect($response->viewData('cards'))->firstWhere('label', 'Mahasiswa')['value']);
        $this->assertCount(1, $response->viewData('programSummary'));
    }

    public function test_master_dashboard_counts_unique_dtps_across_all_semesters(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'prodi-2')
            ->update(['status' => 'N']);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'dtps-term-old', 'id_prodi' => 'prodi-1', 'tahun' => '20251', 'namatahun' => '2025 Ganjil'],
            ['id_kalender' => 'dtps-term-latest', 'id_prodi' => 'prodi-1', 'tahun' => '20262', 'namatahun' => '2026 Genap'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'dtps-course-old', 'nama_mata_kuliah' => 'Mata Kuliah Lama', 'id_prodi' => 'prodi-1'],
            ['id_matkul' => 'dtps-course-latest', 'nama_mata_kuliah' => 'Mata Kuliah Terbaru', 'id_prodi' => 'prodi-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dtps-home', 'nidn' => 'NIDN-HOME', 'nama_dosen' => 'Dosen Homebase', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'dtps-visitor', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'dtps-visitor-copy', 'nidn' => 'NIDN-VISITOR', 'nama_dosen' => 'Dosen Tamu Duplikat', 'id_prodi' => 'prodi-2'],
            ['id_dosen' => 'dtps-slot-14', 'nidn' => 'NIDN-SLOT14', 'nama_dosen' => null, 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'dtps-assistant', 'kdasisten' => 'DTPS-ASDOS', 'nama' => 'Asisten Terdaftar', 'id_prodi' => 'prodi-2'],
        ]);
        $schedules = DB::connection('simak_main')->table('s1m4k_jadwal');
        $schedules->insert(
            [
                'id_jadwal' => 'dtps-class-1', 'id_prodi' => 'prodi-1', 'id_kalender' => 'dtps-term-old', 'id_matkul' => 'dtps-course-old',
                'dosen1' => 'dtps-home', 'dosen2' => 'dtps-visitor', 'dosen3' => 'dtps-visitor',
                'dosen4' => 'dtps-visitor-copy', 'dosen12' => 'missing-master-person',
                'dosen13' => 'dtps-assistant', 'dosen14' => 'dtps-slot-14',
            ],
        );
        $schedules->insert(
            [
                'id_jadwal' => 'dtps-class-2', 'id_prodi' => 'prodi-1', 'id_kalender' => 'dtps-term-latest', 'id_matkul' => 'dtps-course-latest',
                'dosen1' => 'dtps-home', 'dosen3' => 'dtps-visitor-copy',
            ],
        );

        $this->signIn('admin');
        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('DTPS homebase')
            ->assertSee('DTPS non-homebase')
            ->assertSee('.dtps-details-dialog { width: min(900px, calc(100% - 32px)); max-height: min(82vh, 760px); margin: auto;', false);

        $program = $response->viewData('programSummary')->sole();
        $this->assertSame(3, $program['dtps']);
        $this->assertSame(1, $program['dtpsHomebase']);
        $this->assertSame(2, $program['dtpsNonHomebase']);

        $details = $this->getJson(route('dashboard.dtps-details', [
            'program' => 'prodi-1',
            'category' => 'all',
        ]))->assertOk();
        $this->assertSame(3, $details->json('total'));
        $visitor = collect($details->json('lecturers'))->firstWhere('id', 'dtps-visitor-copy');
        $this->assertSame('Dosen Tamu Duplikat', $visitor['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'dtps-assistant'));
        $unnamedLecturer = collect($details->json('lecturers'))->firstWhere('id', 'dtps-slot-14');
        $this->assertNotNull($unnamedLecturer);
        $this->assertNull($unnamedLecturer['name']);
        $this->assertNull(collect($details->json('lecturers'))->firstWhere('id', 'missing-master-person'));
        $this->assertSame('20262', $visitor['latest_academic_year']);
        $this->assertCount(1, $visitor['courses']);
        $this->assertSame('2026 Genap', collect($visitor['courses'])->firstWhere('name', 'Mata Kuliah Terbaru')['academic_year']);
        $this->assertSame(2, $this->getJson(route('dashboard.dtps-details', [
            'program' => 'prodi-1',
            'category' => 'non_homebase',
        ]))->assertOk()->json('total'));

        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'prodi-2')
            ->update(['status' => 'A']);
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->getJson(route('dashboard.dtps-details', [
            'program' => 'prodi-2',
            'category' => 'all',
        ]))->assertNotFound();
    }

    public function test_master_dashboard_counts_only_a_c_n_and_m_students(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'status-a', 'nim' => 'S001', 'nama_mahasiswa' => 'Status A', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'status-c', 'nim' => 'S002', 'nama_mahasiswa' => 'Status C', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti'],
            ['id_mahasiswa' => 'status-n', 'nim' => 'S003', 'nama_mahasiswa' => 'Status N', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Nonaktif sementara'],
            ['id_mahasiswa' => 'status-m', 'nim' => 'S004', 'nama_mahasiswa' => 'Status M', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka'],
            ['id_mahasiswa' => 'status-g', 'nim' => 'S005', 'nama_mahasiswa' => 'Status G', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'G', 'nama_status_mahasiswa' => 'Lainnya'],
            ['id_mahasiswa' => 'status-l', 'nim' => 'S006', 'nama_mahasiswa' => 'Status L', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'L', 'nama_status_mahasiswa' => 'Lulus'],
            ['id_mahasiswa' => 'status-no-nim', 'nim' => null, 'nama_mahasiswa' => 'Tanpa NIM', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'status-blank-nim', 'nim' => '  ', 'nama_mahasiswa' => 'NIM Kosong', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'N', 'nama_status_mahasiswa' => 'Nonaktif'],
            ['id_mahasiswa' => 'status-no-name', 'nim' => 'S007', 'nama_mahasiswa' => null, 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti'],
            ['id_mahasiswa' => 'status-blank-name', 'nim' => 'S008', 'nama_mahasiswa' => '  ', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka'],
        ]);
        $this->signIn('admin');

        $response = $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-tooltip=', false);

        $this->assertSame(4, $response->viewData('studentTotal'));
        $this->assertSame(4, $response->viewData('cards')[1]['value']);
        $this->assertSame('Status A, N, C, dan M', $response->viewData('cards')[1]['detail']);
        $this->assertSame(4, $response->viewData('programSummary')->sum('students'));
        $this->assertCount(4, $response->viewData('studentStatuses'));
        $this->assertSame(0, $response->viewData('distributionTotal'));
        $this->assertSame(4, $response->viewData('studentTotal') - $response->viewData('distributionTotal'));
        $this->assertStringContainsString(
            'Total mahasiswa status A, N, C, dan M: 4. Peta menampilkan 0 mahasiswa dengan data provinsi; 4 belum terpetakan.',
            $response->getContent()
        );
        $this->assertSame(
            ['Aktif', 'Cuti', 'Kampus Merdeka', 'Nonaktif sementara'],
            $response->viewData('studentStatuses')->pluck('label')->sort()->values()->all()
        );
    }

    public function test_dashboard_intake_trends_and_map_data_use_legacy_fields_and_scope(): void
    {
        $this->seedOrganization();
        $years = range(now()->year - 5, now()->year);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            [
                'keterangan' => 'GetWilayah',
                'kode_referensi' => 'WIL-1',
                'ket_referensi' => 'Kota Uji',
                'nama_provinsi' => 'Provinsi Uji',
                'lat_provinsi' => 1.25,
                'lng_provinsi' => 127.5,
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'trend-1', 'nim' => 'T001', 'nama_mahasiswa' => 'Angkatan Prodi Satu A', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_periode_masuk' => $years[0].'1', 'id_wilayah' => 'WIL-1'],
            ['id_mahasiswa' => 'trend-2', 'nim' => 'T002', 'nama_mahasiswa' => 'Angkatan Prodi Satu B', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti', 'id_periode_masuk' => $years[0].'2', 'id_wilayah' => 'WIL-1'],
            ['id_mahasiswa' => 'trend-3', 'nim' => 'T003', 'nama_mahasiswa' => 'Angkatan Prodi Dua', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_periode_masuk' => $years[3].'1', 'id_wilayah' => 'WIL-1'],
            ['id_mahasiswa' => 'trend-old', 'nim' => 'T004', 'nama_mahasiswa' => 'Angkatan Lama', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_periode_masuk' => ($years[0] - 1).'1', 'id_wilayah' => 'WIL-1'],
            ['id_mahasiswa' => 'trend-inactive', 'nim' => 'T005', 'nama_mahasiswa' => 'Tidak Aktif', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'L', 'nama_status_mahasiswa' => 'Lulus', 'id_periode_masuk' => $years[1].'1', 'id_wilayah' => 'WIL-1'],
            ['id_mahasiswa' => 'trend-no-region', 'nim' => 'T006', 'nama_mahasiswa' => 'Tanpa Wilayah', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'id_periode_masuk' => $years[1].'1', 'id_wilayah' => ''],
        ]);

        $this->signIn('admin');
        $dashboard = $this->get(route('dashboard'))->assertOk()
            ->assertSee('Tren Mahasiswa Baru per Program Studi')
            ->assertSee('Sebaran Mahasiswa')
            ->assertSee('Grafik jumlah mahasiswa, dosen, DTPS homebase dan non-homebase, asisten, mata kuliah, dan ruang per program studi')
            ->assertSee('data-notification-center', false)
            ->assertSee('notification-count', false)
            ->assertSee('Provinsi Uji')
            ->assertSee('data-series=', false)
            ->assertSee('data-distribution=', false);
        $series = collect($dashboard->viewData('intakeSeries'))->keyBy('id');
        $this->assertSame([2, 2, 0, 0, 0, 0], $series['prodi-1']['values']);
        $this->assertSame([0, 0, 0, 1, 0, 0], $series['prodi-2']['values']);
        $this->assertSame(4, $dashboard->viewData('distributionTotal'));
        $this->assertSame('Provinsi Uji', $dashboard->viewData('studentDistribution')[0]['province']);
        $this->assertSame(4, $dashboard->viewData('studentDistribution')[0]['total']);
        $this->assertSame(3, $dashboard->viewData('studentDistribution')[0]['faculties']['Fakultas Satu']);

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $facultyDashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertCount(1, $facultyDashboard->viewData('intakeSeries'));
        $this->assertSame([2, 2, 0, 0, 0, 0], $facultyDashboard->viewData('intakeSeries')[0]['values']);
        $this->assertSame(3, $facultyDashboard->viewData('distributionTotal'));

        $this->signIn('userprodi', ['id_prodi' => 'prodi-2']);
        $programDashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertCount(1, $programDashboard->viewData('intakeSeries'));
        $this->assertSame([0, 0, 0, 1, 0, 0], $programDashboard->viewData('intakeSeries')[0]['values']);
        $this->assertSame(1, $programDashboard->viewData('distributionTotal'));
    }

    public function test_academic_dashboard_defaults_to_active_calendar_and_loads_trends_separately(): void
    {
        $this->seedOrganization();
        $calendars = [];
        foreach (['prodi-1', 'prodi-2'] as $programId) {
            foreach (range(2022, 2025) as $year) {
                $calendarId = $programId.'-'.$year;
                $calendars[$programId][$year] = $calendarId;
                DB::connection('simak_main')->table('s1m4k_kalender')->insert([
                    'id_kalender' => $calendarId,
                    'id_prodi' => $programId,
                    'tahun' => $year.'1',
                    'namatahun' => $year.' Ganjil',
                    'status' => $year === 2025 ? 'Y' : 'N',
                ]);
            }
        }
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'acad-mhs-1', 'nim' => 'A001', 'nama_mahasiswa' => 'Mahasiswa A', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif'],
            ['id_mahasiswa' => 'acad-mhs-2', 'nim' => 'A002', 'nama_mahasiswa' => 'Mahasiswa B', 'id_prodi' => 'prodi-1', 'id_status_mahasiswa' => 'C', 'nama_status_mahasiswa' => 'Cuti'],
            ['id_mahasiswa' => 'acad-mhs-3', 'nim' => 'B001', 'nama_mahasiswa' => 'Mahasiswa C', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka'],
            ['id_mahasiswa' => 'acad-mhs-4', 'nim' => 'B002', 'nama_mahasiswa' => 'Mahasiswa Lulus', 'id_prodi' => 'prodi-2', 'id_status_mahasiswa' => 'L', 'nama_status_mahasiswa' => 'Lulus'],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            ['id_registrasi' => 'reg-1', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'acad-mhs-1', 'id_kalender' => $calendars['prodi-1'][2025]],
            ['id_registrasi' => 'reg-2', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'acad-mhs-2', 'id_kalender' => $calendars['prodi-1'][2025]],
            ['id_registrasi' => 'reg-3', 'id_prodi' => 'prodi-2', 'id_mahasiswa' => 'acad-mhs-3', 'id_kalender' => $calendars['prodi-2'][2025]],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'acad-krs-1', 'id_mahasiswa' => 'acad-mhs-1', 'id_kalender' => $calendars['prodi-1'][2025], 'input_nilai' => 'Y'],
            ['id_krs' => 'acad-krs-2', 'id_mahasiswa' => 'acad-mhs-2', 'id_kalender' => $calendars['prodi-1'][2025], 'input_nilai' => 'N'],
            ['id_krs' => 'acad-krs-3', 'id_mahasiswa' => 'acad-mhs-3', 'id_kalender' => $calendars['prodi-2'][2025], 'input_nilai' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahan' => 'approval-1', 'id_mahasiswa' => 'acad-mhs-1', 'id_kalender' => $calendars['prodi-1'][2025], 'nama_kpro' => 'Ketua Prodi'],
        ]);
        $akm = [];
        foreach ([2022 => [2.75, 2.75], 2023 => [3.0, 3.0], 2024 => [3.25, 3.5], 2025 => [3.5, 3.6]] as $year => [$ips, $ipk]) {
            $akm[] = [
                'id_ipmhs' => 'akm-one-'.$year,
                'id_prodi' => 'prodi-1',
                'id_mahasiswa' => 'acad-mhs-1',
                'id_kalender' => $calendars['prodi-1'][$year],
                'ips' => (string) $ips,
                'ipk' => (string) $ipk,
            ];
        }
        $akm[] = [
            'id_ipmhs' => 'akm-two-2025', 'id_prodi' => 'prodi-1', 'id_mahasiswa' => 'acad-mhs-2',
            'id_kalender' => $calendars['prodi-1'][2025], 'ips' => '2.50', 'ipk' => '3.00',
        ];
        $akm[] = [
            'id_ipmhs' => 'akm-three-2025', 'id_prodi' => 'prodi-2', 'id_mahasiswa' => 'acad-mhs-3',
            'id_kalender' => $calendars['prodi-2'][2025], 'ips' => '2.25', 'ipk' => '2.75',
        ];
        DB::connection('simak_main')->table('s1m4k_akm')->insert($akm);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'acad-dosen-1', 'nidn' => 'AD001', 'nama_dosen' => 'Dosen Akademik Satu', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'acad-dosen-2', 'nidn' => 'AD002', 'nama_dosen' => 'Dosen Akademik Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'acad-jadwal-1', 'id_prodi' => 'prodi-1', 'id_kalender' => $calendars['prodi-1'][2025], 'id_matkul' => 'acad-mk-1', 'nama_kelas' => 'A', 'dosen1' => 'acad-dosen-1'],
            ['id_jadwal' => 'acad-jadwal-2', 'id_prodi' => 'prodi-2', 'id_kalender' => $calendars['prodi-2'][2025], 'id_matkul' => 'acad-mk-2', 'nama_kelas' => 'B', 'dosen1' => 'acad-dosen-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_edom')->insert([
            ['id_edom' => 'acad-edom-1', 'id_krs' => 'acad-krs-1', 'id_jadwal' => 'acad-jadwal-1', 'id_kalender' => $calendars['prodi-1'][2025], 'id_survey' => '1', 'nilai' => 5],
            ['id_edom' => 'acad-edom-2', 'id_krs' => 'acad-krs-1', 'id_jadwal' => 'acad-jadwal-1', 'id_kalender' => $calendars['prodi-1'][2025], 'id_survey' => '2', 'nilai' => 4],
            ['id_edom' => 'acad-edom-3', 'id_krs' => 'acad-krs-3', 'id_jadwal' => 'acad-jadwal-2', 'id_kalender' => $calendars['prodi-2'][2025], 'id_survey' => '1', 'nilai' => 3],
        ]);

        $this->signIn('admin');
        $this->get(route('academic-dashboard.index'))->assertOk()
            ->assertSee('data-academic-dashboard', false)
            ->assertSee('data-academic-calendar-options', false)
            ->assertSee('data-selected-year="20251"', false)
            ->assertSee('data-insights-url=', false);
        $this->get(route('academic-dashboard.index', ['calendar' => '20251']))
            ->assertOk()->assertSee('Dashboard Akademik')->assertSee('data-selected-year="20251"', false);
        $admin = $this->getJson(route('academic-dashboard.data', ['calendar' => '20251']))->assertOk();
        $summary = collect($admin->json('programSummary'))->keyBy('id');
        $this->assertSame(2, $summary['prodi-1']['active_students']);
        $this->assertSame(2, $summary['prodi-1']['registered_students']);
        $this->assertSame(2, $summary['prodi-1']['krs_students']);
        $this->assertSame(1, $summary['prodi-1']['approved_students']);
        $this->assertSame(1, $summary['prodi-1']['grades_entered']);
        $this->assertEquals(3.0, $summary['prodi-1']['average_ips']);
        $this->assertEquals(3.3, $summary['prodi-1']['average_ipk']);
        $this->assertEquals(90.0, $summary['prodi-1']['average_edom_score']);
        $this->assertEquals(60.0, $summary['prodi-2']['average_edom_score']);
        $this->assertSame([2022, 2023, 2024, 2025], $admin->json('trendYears'));
        $this->assertSame([], $admin->json('trendRows'));
        $this->assertSame([], $admin->json('topEdomLecturers'));
        $insights = $this->getJson(route('academic-dashboard.insights', ['calendar' => '20251']))->assertOk();
        $this->assertSame([2022, 2023, 2024, 2025], $insights->json('trendYears'));
        $trends = collect($insights->json('trendRows'))->keyBy('id');
        $this->assertSame(2.75, $trends['prodi-1']['values']['2022']['ips']);
        $this->assertSame('Dosen Akademik Satu', $insights->json('topEdomLecturers.0.name'));
        $this->assertEquals(90.0, $insights->json('topEdomLecturers.0.average_score'));
        $this->get(route('academic-dashboard.index', ['calendar' => '99991']))->assertNotFound();
        $this->getJson(route('academic-dashboard.data', ['calendar' => '99991']))->assertNotFound();
        $this->getJson(route('academic-dashboard.insights', ['calendar' => '99991']))->assertNotFound();

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $faculty = $this->getJson(route('academic-dashboard.data', ['calendar' => '20251']))->assertOk();
        $this->assertCount(1, $faculty->json('programSummary'));
        $this->assertSame('prodi-1', $faculty->json('programSummary.0.id'));

        foreach (['userprodi', 'usertu'] as $role) {
            $this->signIn($role, ['id_prodi' => 'prodi-2']);
            $program = $this->getJson(route('academic-dashboard.data', ['calendar' => '20251']))->assertOk();
            $this->assertCount(1, $program->json('programSummary'));
            $this->assertSame('prodi-2', $program->json('programSummary.0.id'));
        }

        $this->signIn('dosen');
        $this->get(route('academic-dashboard.index'))->assertForbidden();
        $this->getJson(route('academic-dashboard.data', ['calendar' => '20251']))->assertForbidden();
        $this->getJson(route('academic-dashboard.insights', ['calendar' => '20251']))->assertForbidden();
    }

    public function test_dashboard_uses_empty_welcome_for_roles_outside_master_dashboard(): void
    {
        $this->signIn('mahasiswa');
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Selamat datang, Pengguna Uji.')
            ->assertSee('class="welcome-band"', false)
            ->assertSee('grid-template-columns: minmax(0, 1.4fr) minmax(190px, .6fr)', false)
            ->assertSee('Role aktif')
            ->assertDontSee('Dashboard Master')
            ->assertDontSee('Tren Mahasiswa Baru')
            ->assertDontSee('Sebaran Mahasiswa');
    }

    public function test_university_profile_sync_updates_local_profile_from_feeder(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', 'pt-1')
            ->update([
                'alamat_feeder' => 'https://feeder.example.test',
                'user_feeder' => 'dummy-user',
                'password_feeder' => 'YQ==',
            ]);
        Http::fake([
            'https://feeder.example.test' => Http::sequence()
                ->push(['data' => ['token' => 'token-profile']])
                ->push(['data' => [[
                    'id_perguruan_tinggi' => 'pt-1',
                    'kode_perguruan_tinggi' => '001001',
                    'nama_perguruan_tinggi' => 'Universitas Sinkron',
                    'email' => 'pt@example.test',
                    'telepon' => '0921-12345',
                    'website' => 'https://unkhair.example.test',
                    'tanggal_sk_pendirian' => '2004-04-15 00:00:00',
                ]]]),
        ]);

        $this->signIn('admin');
        $this->get(route('master-data.perguruan-tinggi.index'))->assertOk()
            ->assertSee('data-can-sync="true"', false)
            ->assertSee('Sinkronkan dari Feeder');
        $this->postJson(route('master-data.perguruan-tinggi.sync'))->assertOk()
            ->assertJsonPath('synced', 1);

        Http::assertSent(fn (ClientRequest $request): bool => $request['act'] === 'GetToken');
        Http::assertSent(fn (ClientRequest $request): bool => $request['act'] === 'GetProfilPT'
            && $request['limit'] === 1
            && $request['offset'] === 0);
        $this->assertDatabaseHas('s1m4k_masterpt', [
            'id_perguruan_tinggi' => 'pt-1',
            'nama_perguruan_tinggi' => 'Universitas Sinkron',
            'tanggal_sk_pendirian' => '2004-04-15',
        ], 'simak_main');

        $this->signIn('mahasiswa');
        $this->get(route('master-data.mahasiswa.index'))->assertForbidden();
        $this->get(route('master-data.mahasiswa.data'))->assertForbidden();
    }

    public function test_admin_can_create_local_faculty_assistant_and_room_records(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'dosen-dekan',
            'nidn' => '10001',
            'nama_dosen' => 'Dosen Dekan Uji',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        $this->signIn('admin');

        $this->postJson(route('master-data.fakultas.store'), [
            'kode_fakultas' => '03',
            'nama_fakultas' => 'Fakultas Baru',
            'dekan' => 'dosen-dekan',
            'pd1' => 'dosen-dekan',
            'status' => 'Tidak Aktif',
        ])->assertCreated()
            ->assertJsonPath('message', 'Fakultas berhasil ditambahkan.')
            ->assertJsonPath('record.values.nama_fakultas', 'Fakultas Baru')
            ->assertJsonPath('record.values.dekan', 'dosen-dekan');
        $this->assertDatabaseHas('s1m4k_masterfakultas', [
            'kode_fakultas' => '03',
            'dekan' => 'dosen-dekan',
            'pd1' => 'dosen-dekan',
            'pd2' => null,
            'pd3' => null,
            'status' => 'Tidak Aktif',
        ], 'simak_main');

        $facultyId = DB::connection('simak_main')->table('s1m4k_masterfakultas')
            ->where('kode_fakultas', '03')->value('id_fakultas');
        $this->putJson(route('master-data.fakultas.update', $facultyId), [
            'nama_fakultas' => 'Fakultas Uji Diperbarui',
            'dekan' => null,
            'pd1' => null,
            'pd2' => 'dosen-dekan',
            'pd3' => null,
            'status' => 'Tidak Aktif',
        ])->assertOk()
            ->assertJsonPath('record.values.nama_fakultas', 'Fakultas Uji Diperbarui');
        $this->assertDatabaseHas('s1m4k_masterfakultas', [
            'id_fakultas' => $facultyId,
            'nama_fakultas' => 'Fakultas Uji Diperbarui',
            'dekan' => null,
            'pd2' => 'dosen-dekan',
            'status' => 'Tidak Aktif',
        ], 'simak_main');

        $this->putJson(route('master-data.fakultas.update', $facultyId), [
            'nama_fakultas' => 'Fakultas Uji Diperbarui',
            'status' => 'Status Buatan',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->postJson(route('master-data.fakultas.store'), [
            'kode_fakultas' => '04',
            'nama_fakultas' => 'Fakultas Dosen Tidak Valid',
            'dekan' => 'missing-lecturer',
            'status' => 'Aktif',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['dekan']);

        $this->postJson(route('master-data.asisten-dosen.store'), [
            'kdasisten' => 'ASD001',
            'nama' => 'Asisten Baru',
            'email' => 'asisten@example.test',
            'id_prodi' => 'prodi-1',
            'password' => 'password-dummy',
            'status' => 'Tidak Aktif',
        ])->assertCreated();

        $assistant = DB::connection('simak_main')->table('s1m4k_masterasdos')
            ->where('kdasisten', 'ASD001')->first();
        $this->assertSame('pt-1', $assistant->id_perguruan_tinggi);
        $this->assertSame('fak-1', $assistant->id_fakultas);
        $this->assertSame('Tidak Aktif', $assistant->status);
        $this->assertTrue(Hash::check('password-dummy', $assistant->password));

        $this->postJson(route('master-data.ruang.store'), [
            'nama_ruang' => 'Ruang Uji',
            'kapasitas' => '30',
            'id_prodi' => 'prodi-2',
            'status' => 'Aktif',
        ])->assertCreated();

        $room = DB::connection('simak_main')->table('s1m4k_masterruang')
            ->where('nama_ruang', 'Ruang Uji')->first();
        $this->assertSame('pt-1', $room->id_perguruan_tinggi);
        $this->assertSame('fak-2', $room->id_fakultas);
        $this->assertSame('prodi-2', $room->id_prodi);
    }

    public function test_faculty_lecturer_search_only_returns_named_matching_lecturers(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-match', 'nidn' => '998877', 'nama_dosen' => 'Nama Dosen Pilihan'],
            ['id_dosen' => 'dosen-empty', 'nidn' => '998878', 'nama_dosen' => '   '],
            ['id_dosen' => 'dosen-other', 'nidn' => '112233', 'nama_dosen' => 'Orang Lain'],
        ]);
        $this->signIn('admin');

        $this->getJson(route('master-data.fakultas.lecturers', ['search' => 'Pilihan']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', 'dosen-match')
            ->assertJsonPath('data.0.text', 'Nama Dosen Pilihan (998877)');
        $this->getJson(route('master-data.fakultas.lecturers', ['search' => 'Pi']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);

        $this->signIn('pimuniv');
        $this->getJson(route('master-data.fakultas.lecturers', ['search' => 'Pilihan']))
            ->assertForbidden();
    }

    public function test_lecturer_master_list_displays_both_academic_titles(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'titled-lecturer',
            'nidn' => '998877',
            'nama_dosen' => 'Nama Dosen',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom.',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        $this->signIn('admin');

        $this->get(route('master-data.dosen.data'))
            ->assertOk()
            ->assertJsonPath('data.0.values.nama_dosen', 'Dr. Nama Dosen, M.Kom.');
    }

    public function test_program_roles_can_edit_lecturers_only_within_their_program(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            [
                'id_dosen' => 'lecturer-own-program',
                'nidn' => '10001',
                'nama_dosen' => 'Dosen Prodi Sendiri',
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'fak-1',
            ],
            [
                'id_dosen' => 'lecturer-other-program',
                'nidn' => '10002',
                'nama_dosen' => 'Dosen Prodi Lain',
                'id_prodi' => 'prodi-2',
                'id_fakultas' => 'fak-1',
            ],
        ]);

        foreach (['userprodi', 'usertu'] as $role) {
            $this->signIn($role, ['id_prodi' => 'prodi-1']);

            $this->getJson(route('master-data.dosen.edit-data', 'lecturer-own-program'))
                ->assertOk();
            $this->getJson(route('master-data.dosen.edit-data', 'lecturer-other-program'))
                ->assertNotFound();
            $this->putJson(route('master-data.dosen.update', 'lecturer-own-program'), [
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Kom.',
                'email' => 'dosen@example.test',
                'nip' => '19800101',
                'nama_dosen' => 'Nama Tidak Boleh Diubah',
                'nidn' => '99999',
            ])->assertOk();
            $this->putJson(route('master-data.dosen.update', 'lecturer-other-program'), [
                'gelar_depan' => 'Prof.',
            ])->assertNotFound();
        }

        $this->assertSame(
            'Dosen Prodi Sendiri',
            DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', 'lecturer-own-program')->value('nama_dosen'),
        );
        $this->assertSame(
            '10002',
            DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', 'lecturer-other-program')->value('nidn'),
        );
    }

    public function test_admin_can_update_only_supported_local_fields(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterprodi')->where('id_prodi', 'prodi-1')
            ->update(['ketua_prodi' => 'Ketua Lama']);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'dosen-1',
            'nidn' => '10001',
            'nama_dosen' => 'Dosen Satu',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        $this->signIn('admin');
        foreach ([
            'perguruan-tinggi', 'fakultas', 'program-studi', 'dosen', 'asisten-dosen',
            'mahasiswa', 'mata-kuliah', 'ruang', 'bobot-nilai',
        ] as $module) {
            $response = $this->get(route('master-data.'.$module.'.index'))
                ->assertOk()
                ->assertDontSee('Data ditampilkan sesuai kewenangan dan cakupan akun SIMAK Anda.');
            if ($module === 'fakultas') {
                $response->assertSee('data-master-create', false)
                    ->assertSee('id="master-data-actions-root"', false)
                    ->assertSee('data-datatable="master-data"', false)
                    ->assertSee('data-lecturer-search-url=', false);
            }
        }

        $this->putJson(route('master-data.program-studi.update', 'prodi-1'), [
            'id_fakultas' => 'fak-2',
            'ketua_prodi' => 'dosen-1',
        ])->assertOk();
        $this->putJson(route('master-data.program-studi.update', 'prodi-1'), [
            'id_fakultas' => 'fak-2',
            'ketua_prodi' => 'bukan-id-dosen',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ketua_prodi']);
        $this->assertDatabaseHas('s1m4k_masterprodi', [
            'id_prodi' => 'prodi-1',
            'kode_program_studi' => 'P01',
            'id_fakultas' => 'fak-2',
            'ketua_prodi' => 'dosen-1',
        ], 'simak_main');

        $this->putJson(route('master-data.dosen.update', 'dosen-1'), [
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom.',
            'email' => 'dosen@example.test',
            'nip' => '19800101',
        ])->assertOk();
        $this->assertDatabaseHas('s1m4k_masterdosen', [
            'id_dosen' => 'dosen-1',
            'nidn' => '10001',
            'gelar_depan' => 'Dr.',
            'nip' => '19800101',
        ], 'simak_main');
    }

    public function test_master_tables_join_related_names_exclude_legacy_empty_rows_and_keep_column_counts_aligned(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            'id_dosen' => 'dosen-1',
            'nidn' => '10001',
            'nama_dosen' => 'Dekan Uji',
            'gelar_depan' => 'Dr.',
            'gelar_belakang' => 'M.Kom.',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            'id_asdos' => 'asdos-1',
            'kdasisten' => 'AS001',
            'nama' => 'Asisten Uji',
            'id_prodi' => 'prodi-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            'id_ruang' => 'ruang-1',
            'nama_ruang' => 'Ruang Uji',
            'id_prodi' => 'prodi-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')
            ->where('id_fakultas', 'fak-1')->update(['dekan' => 'dosen-1']);
        DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', 'prodi-1')->update(['ketua_prodi' => 'dosen-1']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-valid', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Valid', 'id_prodi' => 'prodi-1'],
            ['id_mahasiswa' => 'mhs-empty-name', 'nim' => '2025002', 'nama_mahasiswa' => '   ', 'id_prodi' => 'prodi-1'],
            ['id_mahasiswa' => 'mhs-empty-nim', 'nim' => '', 'nama_mahasiswa' => 'Mahasiswa Tanpa NIM', 'id_prodi' => 'prodi-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'mk-valid', 'kode_mata_kuliah' => 'MK01', 'nama_mata_kuliah' => 'Mata Kuliah Valid', 'id_prodi' => 'prodi-1'],
            ['id_matkul' => 'mk-empty-name', 'kode_mata_kuliah' => 'MK02', 'nama_mata_kuliah' => '', 'id_prodi' => 'prodi-1'],
        ]);

        $this->signIn('admin');
        $faculty = $this->get(route('master-data.fakultas.index'))->assertOk();
        $facultyData = $this->get(route('master-data.fakultas.data'))->assertOk();
        $facultyData->assertJsonPath('data.0.values.nama_dekan', 'Dr. Dekan Uji, M.Kom.');
        $this->assertMasterTableColumnCounts($faculty->getContent());

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $facultyViewOnly = $this->get(route('master-data.fakultas.index'))->assertOk();
        $this->assertMasterTableColumnCounts($facultyViewOnly->getContent());

        $this->signIn('admin');
        $programs = $this->get(route('master-data.program-studi.index'))->assertOk();
        $programs->assertSee('data-lecturer-search-url=', false);
        $programData = $this->get(route('master-data.program-studi.data'))->assertOk();
        $programData->assertJsonPath('data.0.values.nama_fakultas', 'Fakultas Satu')
            ->assertJsonPath('data.0.values.nama_ketua_prodi', 'Dr. Dekan Uji, M.Kom.');
        $this->getJson(route('master-data.program-studi.edit-data', 'prodi-1'))
            ->assertOk()
            ->assertJsonPath('record.values.ketua_prodi_label', 'Dr. Dekan Uji, M.Kom.');
        $this->assertMasterTableColumnCounts($programs->getContent());

        $lecturers = $this->get(route('master-data.dosen.index'))->assertOk()
            ->assertDontSee('Sinkron Data Dosen')
            ->assertDontSee('data-master-sync', false);
        $this->get(route('master-data.dosen.data'))->assertOk()
            ->assertJsonPath('data.0.values.nama_dosen', 'Dr. Dekan Uji, M.Kom.');
        $this->assertMasterTableColumnCounts($lecturers->getContent());

        $assistants = $this->get(route('master-data.asisten-dosen.index'))->assertOk();
        $this->get(route('master-data.asisten-dosen.data'))->assertOk()
            ->assertJsonPath('data.0.values.nama', 'Asisten Uji');
        $this->assertMasterTableColumnCounts($assistants->getContent());

        $rooms = $this->get(route('master-data.ruang.index'))->assertOk();
        $this->get(route('master-data.ruang.data'))->assertOk()
            ->assertJsonPath('data.0.values.nama_ruang', 'Ruang Uji');
        $this->assertMasterTableColumnCounts($rooms->getContent());

        $this->get(route('master-data.mahasiswa.index'))->assertOk()
            ->assertSee('Sinkron Feeder by NPM')
            ->assertSee('data-can-sync="true"', false);
        $students = $this->get(route('master-data.mahasiswa.index', ['prodi' => 'prodi-1']))->assertOk()
            ->assertSee('Sinkron Feeder by NPM');
        $students->assertDontSee('data-sync-program-id=', false)
            ->assertSee('"name":"id_prodi","label":"Program studi","type":"select"', false)
            ->assertSee('"name":"npm","label":"NPM","required":true,"max":24', false);
        $this->get(route('master-data.mahasiswa.data'))->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_mahasiswa', 'Mahasiswa Valid')
            ->assertJsonMissingPath('data.0.values.nik')
            ->assertJsonMissingPath('data.0.values.nama_ayah');
        $this->assertMasterTableColumnCounts($students->getContent());

        $courses = $this->get(route('master-data.mata-kuliah.index'))->assertOk();
        $this->get(route('master-data.mata-kuliah.data'))->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_mata_kuliah', 'Mata Kuliah Valid');
        $this->assertMasterTableColumnCounts($courses->getContent());
        $courses->assertDontSee('class="pagination"', false);

        $studentIndex = $this->get(route('master-data.mahasiswa.index'))->assertOk();
        $this->assertSame(
            3,
            substr_count($studentIndex->getContent(), '"options":{"5":"SMA \\/ Sederajat"}')
        );
    }

    public function test_feeder_dependent_entities_are_read_only_except_scoped_course_actions(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-1',
            'nim' => '202501',
            'nama_mahasiswa' => 'Mahasiswa Uji',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'mk-1',
            'kode_mata_kuliah' => 'MK001',
            'nama_mata_kuliah' => 'Mata Kuliah Uji',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);

        $this->signIn('admin');
        $university = $this->get(route('master-data.perguruan-tinggi.index'))->assertOk()
            ->assertSee('master-identity-card', false)
            ->assertSee('Universitas Uji')
            ->assertDontSee('master-data-table', false);
        $this->assertSame(1, substr_count($university->getContent(), '<section class="master-identity-card"'));
        $this->get(route('master-data.mahasiswa.index'))->assertOk();
        $this->get(route('master-data.mahasiswa.data'))->assertJsonPath('data.0.values.nama_mahasiswa', 'Mahasiswa Uji');
        $this->get(route('master-data.mata-kuliah.index'))->assertOk();
        $this->get(route('master-data.mata-kuliah.data'))->assertJsonPath('data.0.values.nama_mata_kuliah', 'Mata Kuliah Uji');
        $this->postJson(route('master-data.mahasiswa.store'), [])->assertForbidden();
        $this->postJson('/master-data/dosen/sync')->assertStatus(405);
        $this->postJson(route('master-data.mahasiswa.sync'), [])->assertJsonValidationErrors(['npm', 'id_prodi']);
        $this->putJson(route('master-data.mata-kuliah.update', 'mk-1'), [])->assertUnprocessable();
        $this->putJson(route('master-data.perguruan-tinggi.update', 'pt-1'), [])->assertForbidden();

        $this->signIn('regulang');
        $this->getJson(route('master-data.mata-kuliah.evaluations', 'mk-1'))->assertForbidden();
        $this->postJson(route('master-data.mahasiswa.sync'), [])->assertJsonValidationErrors(['npm', 'id_prodi']);
        $this->postJson('/master-data/dosen/sync')->assertStatus(405);

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('master-data.asisten-dosen.index'))->assertOk();
        $this->postJson(route('master-data.asisten-dosen.store'), [
            'kdasisten' => 'ASD-PRODI',
            'nama' => 'Asisten Prodi',
            'id_prodi' => 'prodi-2',
            'password' => 'password-dummy',
            'status' => 'Aktif',
        ])->assertCreated();
        $this->assertDatabaseHas('s1m4k_masterasdos', [
            'kdasisten' => 'ASD-PRODI',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ], 'simak_main');
        $this->postJson(route('master-data.mahasiswa.sync'), ['npm' => '202501', 'id_prodi' => 'prodi-2'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Konfigurasi koneksi Feeder belum lengkap pada data perguruan tinggi.');
    }

    public function test_program_managers_can_sync_students_by_npm_only_for_their_own_program(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')->where('id_perguruan_tinggi', 'pt-1')->update([
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => 'YQ==',
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request->data()['act'] ?? '') {
                'GetToken' => Http::response(['data' => ['token' => 'dummy-token']]),
                'GetDataLengkapMahasiswaProdi' => Http::response(['data' => [[
                    'id_mahasiswa' => 'mhs-tuprodi',
                    'id_registrasi_mahasiswa' => 'reg-tuprodi',
                    'id_prodi' => 'prodi-1',
                    'nim' => 'TUP-001',
                    'nama_mahasiswa' => 'Mahasiswa Sinkron TU Prodi',
                    'nama_status_mahasiswa' => 'Aktif',
                    'jenis_kelamin' => 'L',
                    'tanggal_lahir' => '2000-01-02',
                    'nama_ibu_kandung' => 'Ibu Uji',
                    'nomor_kps' => 'KPS-001',
                    'kewarganegaraan' => 'Indonesia',
                    'sks_diakui' => '12',
                ]]]),
                'GetListRiwayatPendidikanMahasiswa' => Http::response(['data' => [[
                    'id_perguruan_tinggi' => 'pt-1',
                    'id_jenis_daftar' => '1',
                    'id_jalur_daftar' => '1',
                    'id_pembiayaan' => '1',
                    'tanggal_daftar' => '2024-08-01',
                    'biaya_masuk' => '1250000',
                ]]]),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Tindakan tidak dikenal'], 400),
            };
        });

        $this->signIn('admin');
        $this->get(route('master-data.mahasiswa.index'))->assertOk()
            ->assertSee('Sinkron Feeder by NPM')
            ->assertSee('data-can-sync="true"', false)
            ->assertSee('data-can-sync-by-identity="true"', false)
            ->assertSee('"name":"id_prodi","label":"Program studi","type":"select"', false)
            ->assertSee('Unduh Excel mahasiswa');
        $this->postJson(route('master-data.mahasiswa.sync'), [
            'npm' => 'TUP-001',
            'id_prodi' => 'prodi-1',
        ])->assertOk()
            ->assertJsonPath('synced', 1)
            ->assertJsonPath('message', 'Data mahasiswa berhasil disinkronkan dari Feeder.');
        $this->postJson(route('master-data.mahasiswa.sync'), [
            'npm' => 'TUP-001',
            'id_prodi' => 'prodi-tidak-terdaftar',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_prodi']);

        foreach (['userprodi', 'usertu'] as $role) {
            $this->signIn($role, ['id_prodi' => 'prodi-1']);
            $this->get(route('master-data.mahasiswa.index'))->assertOk()
                ->assertSee('Sinkron Feeder by NPM')
                ->assertSee('data-can-sync="true"', false)
                ->assertSee('data-can-sync-by-identity="true"', false)
                ->assertSee('data-sync-program-id="prodi-1"', false)
                ->assertSee('data-sync-fields=\'[{"name":"npm","label":"NPM","required":true,"max":24}]\'', false);
            $this->postJson(route('master-data.mahasiswa.sync'), [
                'npm' => 'TUP-001',
                'id_prodi' => 'prodi-2',
            ])->assertOk()
                ->assertJsonPath('synced', 1)
                ->assertJsonPath('message', 'Data mahasiswa berhasil disinkronkan dari Feeder.');
        }

        Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['act'] ?? '') === 'GetDataLengkapMahasiswaProdi'
            && ($request->data()['filter'] ?? null) === "nim='TUP-001' AND id_prodi='prodi-1'");
        Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['act'] ?? '') === 'GetListRiwayatPendidikanMahasiswa'
            && ($request->data()['filter'] ?? null) === "id_mahasiswa='mhs-tuprodi' AND id_prodi='prodi-1'");
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-tuprodi',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'nim' => 'TUP-001',
            'nama_mahasiswa' => 'Mahasiswa Sinkron TU Prodi',
            'id_perguruan_tinggi' => 'pt-1',
            'id_jenis_daftar' => '1',
            'biaya_masuk_kuliah' => '1250000',
            'nama_ibu' => 'Ibu Uji',
            'nomor_kps' => 'KPS-001',
            'id_negara' => 'ID',
            'sks_diakui' => '12',
            'flag_feeder' => 1,
        ], 'simak_main');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'mhs-tuprodi')->count());
    }

    public function test_admin_and_program_user_can_export_complete_student_data_without_passwords(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            [
                'id_dosen' => 'lecturer-export-1', 'nama_dosen' => 'Dosen Pembimbing Satu',
                'gelar_depan' => 'Dr.', 'gelar_belakang' => 'M.Kom.',
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['keterangan' => 'GetNegara', 'kode_referensi' => 'ID', 'ket_referensi' => 'Indonesia', 'ket_referensi2' => null, 'nama_provinsi' => null],
            ['keterangan' => 'GetWilayah', 'kode_referensi' => 'WIL-1', 'ket_referensi' => null, 'ket_referensi2' => 'Kota Ternate', 'nama_provinsi' => 'Maluku Utara'],
            ['keterangan' => 'GetWilayah', 'kode_referensi' => 'WIL-2', 'ket_referensi' => null, 'ket_referensi2' => 'Kota Tidore', 'nama_provinsi' => 'Maluku Utara'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            [
                'id_mahasiswa' => 'mhs-export-1', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-1', 'dosen_pa' => 'lecturer-export-1',
                'nim' => '=2+2', 'nama_mahasiswa' => 'Mahasiswa Ekspor Satu',
                'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif', 'email' => 'satu@example.test', 'nama_ayah' => 'Ayah Satu',
                'id_negara' => 'ID', 'id_wilayah' => 'WIL-1',
                'jalan' => 'Jalan Lengkap 1', 'password' => 'secret-one', 'password_baru' => 'pending-secret-one',
            ],
            [
                'id_mahasiswa' => 'mhs-export-2', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-2', 'dosen_pa' => null,
                'nim' => '202502', 'nama_mahasiswa' => 'Mahasiswa Ekspor Dua',
                'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'id_status_mahasiswa' => 'C',
                'nama_status_mahasiswa' => 'Cuti', 'email' => 'dua@example.test', 'nama_ayah' => null,
                'id_negara' => 'ID', 'id_wilayah' => 'WIL-2',
                'jalan' => null,
                'password' => 'secret-two', 'password_baru' => 'pending-secret-two',
            ],
            [
                'id_mahasiswa' => 'mhs-export-3', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-3', 'dosen_pa' => null,
                'nim' => '202503', 'nama_mahasiswa' => 'Mahasiswa Ekspor Tiga',
                'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'id_status_mahasiswa' => 'N',
                'nama_status_mahasiswa' => 'Nonaktif sementara', 'email' => null, 'nama_ayah' => null,
                'id_negara' => null, 'id_wilayah' => null, 'jalan' => null, 'password' => null, 'password_baru' => null,
            ],
            [
                'id_mahasiswa' => 'mhs-export-4', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-4', 'dosen_pa' => null,
                'nim' => '202504', 'nama_mahasiswa' => 'Mahasiswa Ekspor Empat',
                'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'id_status_mahasiswa' => 'M',
                'nama_status_mahasiswa' => 'Kampus Merdeka', 'email' => null, 'nama_ayah' => null,
                'id_negara' => null, 'id_wilayah' => null, 'jalan' => null, 'password' => null, 'password_baru' => null,
            ],
            [
                'id_mahasiswa' => 'mhs-export-5', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-5', 'dosen_pa' => null,
                'nim' => '202505', 'nama_mahasiswa' => 'Mahasiswa Status G',
                'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'id_status_mahasiswa' => 'G',
                'nama_status_mahasiswa' => 'Lainnya', 'email' => null, 'nama_ayah' => null,
                'id_negara' => null, 'id_wilayah' => null, 'jalan' => null, 'password' => null, 'password_baru' => null,
            ],
            [
                'id_mahasiswa' => 'mhs-export-6', 'id_perguruan_tinggi' => 'pt-1',
                'id_registrasi_mahasiswa' => 'reg-export-6', 'dosen_pa' => null,
                'nim' => '202506', 'nama_mahasiswa' => 'Mahasiswa Lulus',
                'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'id_status_mahasiswa' => 'L',
                'nama_status_mahasiswa' => 'Lulus', 'email' => null, 'nama_ayah' => null,
                'id_negara' => null, 'id_wilayah' => null, 'jalan' => null, 'password' => null, 'password_baru' => null,
            ],
        ]);

        foreach ([
            ['admin', [], 4],
            ['userprodi', ['id_prodi' => 'prodi-1'], 3],
        ] as [$role, $attributes, $expectedRows]) {
            $this->signIn($role, $attributes);
            $this->get(route('master-data.mahasiswa.index'))->assertOk()
                ->assertSee('Unduh Excel mahasiswa');
            $response = $this->get(route('master-data.mahasiswa.export'))->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('content-disposition', 'attachment; filename=data-mahasiswa.xlsx');

            $path = tempnam(sys_get_temp_dir(), 'simak-students-');
            file_put_contents($path, $response->streamedContent());
            try {
                $sheet = IOFactory::load($path)->getActiveSheet();
                $headers = array_map('strval', $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0]);
                $this->assertSame($expectedRows, $sheet->getHighestRow() - 1);
                $this->assertContains('NPM / NIM', $headers);
                $this->assertContains('Nama ayah', $headers);
                $this->assertContains('Jalan', $headers);
                $this->assertNotContains('Password', $headers);
                $this->assertNotContains('Password Baru', $headers);
                $this->assertNotContains('password', $headers);
                $this->assertNotContains('Flag Feeder', $headers);
                $this->assertNotContains('Kategori Ukt', $headers);
                $this->assertNotContains('Nominal Ukt', $headers);
                $this->assertNotContains('Update Ukt', $headers);
                $this->assertNotContains('id_mahasiswa', $headers);
                $this->assertNotContains('id_registrasi_mahasiswa', $headers);
                $this->assertNotContains('id_status_mahasiswa', $headers);
                $this->assertContains('Perguruan tinggi', $headers);
                $this->assertContains('Fakultas', $headers);
                $this->assertContains('Program studi', $headers);
                $this->assertContains('Dosen pembimbing akademik', $headers);
                $this->assertContains('Wilayah', $headers);
                $this->assertContains('Negara', $headers);
                $this->assertContains('Status mahasiswa', $headers);
                $this->assertNotContains('ID', $headers);
                $npmColumn = Coordinate::stringFromColumnIndex(array_search('NPM / NIM', $headers, true) + 1);
                $npmRow = null;
                for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                    if ($sheet->getCell($npmColumn.$row)->getValue() === '=2+2') {
                        $npmRow = $row;
                        break;
                    }
                }
                $this->assertNotNull($npmRow);
                $this->assertSame('s', $sheet->getCell($npmColumn.$npmRow)->getDataType());
                $row = array_combine($headers, $sheet->rangeToArray('A'.$npmRow.':'.$sheet->getHighestColumn().$npmRow)[0]);
                $this->assertSame('Universitas Uji', $row['Perguruan tinggi']);
                $this->assertSame('Fakultas Satu', $row['Fakultas']);
                $this->assertSame('Prodi Satu', $row['Program studi']);
                $this->assertSame('Dr. Dosen Pembimbing Satu, M.Kom.', $row['Dosen pembimbing akademik']);
                $this->assertSame('Indonesia', $row['Negara']);
                $this->assertSame('Kota Ternate - Maluku Utara', $row['Wilayah']);
                $this->assertSame('Aktif', $row['Status mahasiswa']);
                $this->assertNotContains('secret-one', array_merge(...$sheet->toArray()));
                $exportedNpmValues = $sheet->rangeToArray(
                    $npmColumn.'2:'.$npmColumn.$sheet->getHighestRow(),
                    null,
                    true,
                    false,
                    false
                );
                $this->assertEqualsCanonicalizing(
                    $role === 'admin'
                        ? ['=2+2', '202502', '202503', '202504']
                        : ['=2+2', '202503', '202504'],
                    array_column($exportedNpmValues, 0)
                );
                if ($role === 'admin') {
                    $programColumn = Coordinate::stringFromColumnIndex(array_search('Program studi', $headers, true) + 1);
                    $this->assertEqualsCanonicalizing(
                        ['Prodi Satu', 'Prodi Satu', 'Prodi Satu', 'Prodi Dua'],
                        array_column($sheet->rangeToArray(
                            $programColumn.'2:'.$programColumn.$sheet->getHighestRow(),
                            null,
                            true,
                            false,
                            false
                        ), 0)
                    );
                } else {
                    $this->assertNotNull($npmRow);
                    $this->assertSame(3, $sheet->getHighestRow() - 1);
                }
            } finally {
                unlink($path);
            }
        }

        $this->signIn('pimuniv');
        $this->get(route('master-data.mahasiswa.export'))->assertForbidden();
    }

    public function test_lecturer_manual_nidn_sync_is_removed_from_master_data(): void
    {
        $this->signIn('admin');
        $this->get(route('master-data.dosen.index'))->assertOk()
            ->assertDontSee('Sinkron Data Dosen')
            ->assertDontSee('data-master-sync', false)
            ->assertDontSee('NIDN dosen');
        $this->postJson('/master-data/dosen/sync')->assertStatus(405);
    }

    public function test_program_users_can_manage_assistants_and_rooms_only_in_their_program(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'asdos-own', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'kdasisten' => 'AS-OWN', 'nama' => 'Asisten Lama', 'status' => 'Aktif'],
            ['id_asdos' => 'asdos-other', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'kdasisten' => 'AS-OTHER', 'nama' => 'Asisten Lain', 'status' => 'Aktif'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            ['id_ruang' => 'ruang-own', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'nama_ruang' => 'Ruang Lama', 'status' => 'Aktif'],
            ['id_ruang' => 'ruang-other', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'nama_ruang' => 'Ruang Lain', 'status' => 'Aktif'],
        ]);
        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);

        $this->getJson(route('master-data.asisten-dosen.edit-data', 'asdos-own'))->assertOk();
        $this->getJson(route('master-data.asisten-dosen.edit-data', 'asdos-other'))->assertNotFound();
        $this->getJson(route('master-data.mahasiswa.edit-data', 'mhs-own'))->assertNotFound();
        $this->putJson(route('master-data.asisten-dosen.update', 'asdos-own'), [
            'nama' => 'Asisten Diperbarui',
            'status' => 'Tidak Aktif',
        ])->assertOk();
        $this->putJson(route('master-data.asisten-dosen.update', 'asdos-other'), [
            'nama' => 'Tidak Boleh Diubah',
            'status' => 'Tidak Aktif',
        ])->assertNotFound();
        $this->putJson(route('master-data.ruang.update', 'ruang-own'), [
            'nama_ruang' => 'Ruang Diperbarui',
            'kapasitas' => '25',
            'status' => 'Tidak Aktif',
        ])->assertOk();
        $this->putJson(route('master-data.ruang.update', 'ruang-other'), [
            'nama_ruang' => 'Tidak Boleh Diubah',
            'status' => 'Tidak Aktif',
        ])->assertNotFound();
        $this->postJson(route('master-data.ruang.store'), [
            'nama_ruang' => 'Ruang Baru Prodi',
            'kapasitas' => '40',
            'id_prodi' => 'prodi-2',
            'status' => 'Tidak Aktif',
        ])->assertCreated();

        $this->assertDatabaseHas('s1m4k_masterasdos', [
            'id_asdos' => 'asdos-own',
            'id_prodi' => 'prodi-1',
            'nama' => 'Asisten Diperbarui',
            'status' => 'Tidak Aktif',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_masterruang', [
            'id_ruang' => 'ruang-own',
            'id_prodi' => 'prodi-1',
            'nama_ruang' => 'Ruang Diperbarui',
            'status' => 'Tidak Aktif',
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_masterruang', [
            'nama_ruang' => 'Ruang Baru Prodi',
            'id_prodi' => 'prodi-1',
            'status' => 'Tidak Aktif',
        ], 'simak_main');
    }

    public function test_program_users_can_edit_only_scoped_student_fields_with_country_filtered_regions(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['keterangan' => 'GetNegara', 'kode_referensi' => 'XX', 'ket_referensi' => 'Negara Lain', 'ket_referensi2' => null, 'nama_provinsi' => null],
            ['keterangan' => 'GetStatusMahasiswa', 'kode_referensi' => 'C', 'ket_referensi' => 'Cuti', 'ket_referensi2' => null, 'nama_provinsi' => null],
            ['keterangan' => 'GetWilayah', 'kode_referensi' => 'WIL-OLD', 'ket_referensi' => 'ID', 'ket_referensi2' => 'Kota Lama', 'nama_provinsi' => 'Provinsi Satu'],
            ['keterangan' => 'GetWilayah', 'kode_referensi' => 'WIL-NEW', 'ket_referensi' => 'ID', 'ket_referensi2' => 'Kota Baru', 'nama_provinsi' => 'Provinsi Satu'],
            ['keterangan' => 'GetWilayah', 'kode_referensi' => 'WIL-OTHER', 'ket_referensi' => 'XX', 'ket_referensi2' => 'Kota Baru', 'nama_provinsi' => 'Provinsi Lain'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-own', 'nidn' => '10001', 'nama_dosen' => 'Dosen Prodi Satu', 'id_prodi' => 'prodi-1'],
            ['id_dosen' => 'dosen-other', 'nidn' => '10002', 'nama_dosen' => 'Dosen Prodi Dua', 'id_prodi' => 'prodi-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-own', 'id_perguruan_tinggi' => 'pt-1', 'nim' => '202501', 'nama_mahasiswa' => 'Nama Awal', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'fak-1', 'id_negara' => 'ID', 'id_wilayah' => 'WIL-OLD', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'dosen_pa' => 'dosen-own'],
            ['id_mahasiswa' => 'mhs-other', 'id_perguruan_tinggi' => 'pt-1', 'nim' => '202502', 'nama_mahasiswa' => 'Mahasiswa Lain', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'fak-2', 'id_negara' => 'ID', 'id_wilayah' => 'WIL-OLD', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'dosen_pa' => null],
        ]);
        Http::fake();

        foreach (['userprodi', 'usertu'] as $role) {
            DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'mhs-own')->update([
                'id_wilayah' => 'WIL-OLD',
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
                'dosen_pa' => 'dosen-own',
            ]);
            $this->signIn($role, ['id_prodi' => 'prodi-1']);
            $this->get(route('master-data.mahasiswa.index'))
                ->assertOk()
                ->assertSee('"name":"id_negara"', false)
                ->assertSee('"name":"id_wilayah","label":"Wilayah"', false)
                ->assertSee('"name":"id_status_mahasiswa"', false)
                ->assertSee('"name":"dosen_pa"', false)
                ->assertDontSee('"name":"nama_mahasiswa"', false)
                ->assertDontSee('"name":"nim"', false);
            $this->getJson(route('master-data.mahasiswa.regions', ['id_negara' => 'ID', 'search' => 'Kota Baru']))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.value', 'WIL-NEW')
                ->assertJsonPath('data.0.text', 'Kota Baru - Provinsi Satu');
            $this->getJson(route('master-data.fakultas.lecturers', ['search' => 'Dosen Prodi']))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.value', 'dosen-own');
            $this->getJson(route('master-data.mahasiswa.edit-data', 'mhs-own'))
                ->assertOk()
                ->assertJsonPath('record.values.id_wilayah', 'WIL-OLD')
                ->assertJsonPath('record.values.id_wilayah_label', 'Kota Lama - Provinsi Satu');

            $this->putJson(route('master-data.mahasiswa.update', 'mhs-own'), [
                'id_negara' => 'ID',
                'id_wilayah' => 'WIL-NEW',
                'id_status_mahasiswa' => 'C',
                'dosen_pa' => 'dosen-own',
                'nama_mahasiswa' => 'Nama Tidak Boleh Berubah',
                'id_prodi' => 'prodi-2',
            ])->assertOk();
            $this->assertDatabaseHas('s1m4k_mastermhs', [
                'id_mahasiswa' => 'mhs-own',
                'nama_mahasiswa' => 'Nama Awal',
                'id_prodi' => 'prodi-1',
                'id_negara' => 'ID',
                'id_wilayah' => 'WIL-NEW',
                'id_status_mahasiswa' => 'C',
                'nama_status_mahasiswa' => 'Cuti',
                'dosen_pa' => 'dosen-own',
            ], 'simak_main');
            $this->getJson(route('master-data.mahasiswa.edit-data', 'mhs-other'))->assertNotFound();
            $this->putJson(route('master-data.mahasiswa.update', 'mhs-other'), [
                'id_negara' => 'ID',
                'id_wilayah' => 'WIL-NEW',
                'id_status_mahasiswa' => 'C',
                'dosen_pa' => 'dosen-own',
            ])->assertNotFound();
            $this->putJson(route('master-data.mahasiswa.update', 'mhs-own'), [
                'id_negara' => 'ID',
                'id_wilayah' => 'WIL-NEW',
                'id_status_mahasiswa' => 'C',
                'dosen_pa' => 'dosen-other',
            ])->assertUnprocessable()->assertJsonValidationErrors('dosen_pa');
        }

        Http::assertNothingSent();
    }

    public function test_admin_student_edit_updates_feeder_before_local_record(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', 'pt-1')
            ->update([
                'alamat_feeder' => 'https://feeder.example.test',
                'user_feeder' => 'dummy-user',
                'password_feeder' => 'YQ==',
            ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            'keterangan' => 'GetWilayah',
            'kode_referensi' => 'WIL-ID',
            'ket_referensi' => 'ID',
            'ket_referensi2' => 'Kota Uji',
            'nama_provinsi' => 'Provinsi Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-edit',
            'nim' => '202502',
            'nama_mahasiswa' => 'Nama Lama',
            'email' => 'lama@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'id_registrasi_mahasiswa' => 'registrasi-lama',
            'id_kebutuhan_khusus_ayah' => '2',
            'id_kebutuhan_khusus_ibu' => '0',
            'id_kebutuhan_khusus_mahasiswa' => '1,2',
        ]);
        Http::fake([
            'https://feeder.example.test' => Http::sequence()
                ->push(['data' => ['token' => 'token-sukses']])
                ->push(['data' => []])
                ->push(['data' => ['token' => 'token-riwayat']])
                ->push(['data' => []])
                ->push(['data' => ['token' => 'token-gagal']])
                ->push(['error_code' => '1', 'error_desc' => 'Gagal uji']),
        ]);
        $this->signIn('admin');
        $payload = [
            'nim' => '202502',
            'nama_mahasiswa' => 'Nama Baru',
            'id_prodi' => 'prodi-1',
            'id_status_mahasiswa' => 'A',
            'email' => 'baru@example.test',
            'id_jenis_daftar' => '1',
            'id_jalur_daftar' => '1',
            'id_periode_masuk' => '20241',
            'tanggal_daftar' => '2024-08-01',
            'sks_diakui' => '0',
            'id_pembiayaan' => '1',
            'biaya_masuk_kuliah' => '1000000',
            'nik' => '1234567890123456',
            'handphone' => '0812345678',
            'telepon' => '',
            'jalan' => 'Jalan Baru',
            'dusun' => '',
            'rt' => '01',
            'rw' => '02',
            'kelurahan' => '',
            'kode_pos' => '97711',
            'id_negara' => 'ID',
            'id_wilayah' => 'WIL-ID',
            'id_jenis_tinggal' => '',
            'id_alat_transportasi' => '',
        ];

        $this->getJson(route('master-data.mahasiswa.edit-data', 'mhs-edit'))
            ->assertOk()
            ->assertJsonPath('record.values.id_kebutuhan_khusus_ayah', ['2'])
            ->assertJsonPath('record.values.id_kebutuhan_khusus_ibu', [])
            ->assertJsonPath('record.values.id_kebutuhan_khusus_mahasiswa', ['1', '2']);
        $this->get(route('master-data.mahasiswa.index'))
            ->assertOk()
            ->assertSee('Kebutuhan khusus A')
            ->assertDontSee('Tidak ada');
        $payload['id_kebutuhan_khusus_ayah'] = ['1', '2'];
        $payload['id_kebutuhan_khusus_ibu'] = [];
        $payload['id_kebutuhan_khusus_mahasiswa'] = ['2'];

        $this->putJson(route('master-data.mahasiswa.update', 'mhs-edit'), $payload)
            ->assertOk()
            ->assertJsonPath('record.values.nama_mahasiswa', 'Nama Baru');
        Http::assertSent(fn (ClientRequest $request): bool => $request['act'] === 'UpdateBiodataMahasiswa'
            && $request['record']['nama_mahasiswa'] === 'Nama Baru'
            && $request['record']['kewarganegaraan'] === 'ID'
            && $request['record']['id_kebutuhan_khusus_ayah'] === '1,2'
            && $request['record']['id_kebutuhan_khusus_ibu'] === '0'
            && $request['record']['id_kebutuhan_khusus_mahasiswa'] === '2');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-edit',
            'nama_mahasiswa' => 'Nama Baru',
            'email' => 'baru@example.test',
            'id_kebutuhan_khusus_ayah' => '1,2',
            'id_kebutuhan_khusus_ibu' => '0',
            'id_kebutuhan_khusus_mahasiswa' => '2',
        ], 'simak_main');

        $invalidPayload = $payload;
        $invalidPayload['id_kebutuhan_khusus_mahasiswa'] = ['kode-tidak-valid'];
        $this->putJson(route('master-data.mahasiswa.update', 'mhs-edit'), $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_kebutuhan_khusus_mahasiswa.0');

        $payload['nama_mahasiswa'] = 'Tidak Disimpan';
        $this->putJson(route('master-data.mahasiswa.update', 'mhs-edit'), $payload)
            ->assertStatus(502);
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-edit',
            'nama_mahasiswa' => 'Nama Baru',
        ], 'simak_main');
    }

    public function test_admin_cannot_change_student_program_from_edit_form(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-pindah',
            'nim' => '202503',
            'nama_mahasiswa' => 'Mahasiswa Pindah',
            'email' => 'mahasiswa@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
        ]);
        $this->signIn('admin');
        $this->get(route('master-data.mahasiswa.index'))
            ->assertOk()
            ->assertSee('"name":"id_prodi"', false)
            ->assertSee('"disabled":true', false);
        $this->putJson(route('master-data.mahasiswa.update', 'mhs-pindah'), [
            'id_prodi' => 'prodi-2',
        ])->assertUnprocessable()->assertJsonValidationErrors('id_prodi');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-pindah',
            'id_prodi' => 'prodi-1',
        ], 'simak_main');
        Http::assertNothingSent();
    }

    public function test_regulang_can_sync_and_edit_student_records_like_admin(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', 'pt-1')
            ->update([
                'alamat_feeder' => 'https://feeder.example.test',
                'user_feeder' => 'dummy-user',
                'password_feeder' => 'YQ==',
            ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            [
                'keterangan' => 'GetWilayah',
                'kode_referensi' => 'WIL-ID',
                'ket_referensi' => 'ID',
                'ket_referensi2' => 'Kota Uji',
                'nama_provinsi' => 'Provinsi Uji',
            ],
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request->data()['act'] ?? '') {
                'GetToken' => Http::response(['data' => ['token' => 'token-uji']]),
                'GetDataLengkapMahasiswaProdi' => Http::response(['data' => [[
                    'id_mahasiswa' => 'mhs-regulang',
                    'nim' => 'NPM-REG',
                    'id_prodi' => 'prodi-2',
                    'id_registrasi_mahasiswa' => 'reg-regulang',
                    'nama_mahasiswa' => 'Nama Sinkron',
                    'nama_status_mahasiswa' => 'Aktif',
                    'email' => 'lama@example.test',
                ]]]),
                'GetListRiwayatPendidikanMahasiswa' => Http::response(['data' => [[
                    'id_registrasi_mahasiswa' => 'reg-regulang',
                    'id_jenis_daftar' => '1',
                    'id_jalur_daftar' => '1',
                    'id_pembiayaan' => '1',
                    'tanggal_daftar' => '2024-08-01',
                ]]]),
                'UpdateBiodataMahasiswa', 'UpdateRiwayatPendidikanMahasiswa' => Http::response(['data' => []]),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Tindakan tidak dikenal'], 400),
            };
        });
        $this->signIn('regulang');

        $this->get(route('master-data.mahasiswa.index', ['prodi' => 'prodi-2']))
            ->assertOk()
            ->assertSee('data-can-edit="true"', false)
            ->assertSee('data-can-sync="true"', false)
            ->assertSee('data-sync-program-id="prodi-2"', false)
            ->assertSee('data-sync-fields=\'[{"name":"npm","label":"NPM","required":true,"max":24}]\'', false)
            ->assertSee('"name":"nama_mahasiswa"', false);
        $this->postJson(route('master-data.mahasiswa.sync'), [
            'npm' => 'NPM-REG',
            'id_prodi' => 'prodi-2',
        ])->assertOk()->assertJsonPath('synced', 1);
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-regulang',
            'id_prodi' => 'prodi-2',
            'nama_mahasiswa' => 'Nama Sinkron',
        ], 'simak_main');

        $this->putJson(route('master-data.mahasiswa.update', 'mhs-regulang'), [
            'nim' => 'NPM-REG',
            'nama_mahasiswa' => 'Nama Diperbarui',
            'id_prodi' => 'prodi-2',
            'id_status_mahasiswa' => 'A',
            'email' => 'baru@example.test',
            'id_negara' => 'ID',
            'id_wilayah' => 'WIL-ID',
            'id_jenis_daftar' => '1',
            'id_jalur_daftar' => '1',
            'id_periode_masuk' => '20241',
            'tanggal_daftar' => '2024-08-01',
            'id_pembiayaan' => '1',
            'biaya_masuk_kuliah' => '1000000',
        ])->assertOk()->assertJsonPath('record.values.nama_mahasiswa', 'Nama Diperbarui');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-regulang',
            'nama_mahasiswa' => 'Nama Diperbarui',
            'email' => 'baru@example.test',
        ], 'simak_main');
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Data Master Mahasiswa')->count());
    }

    public function test_program_user_can_edit_courses_and_manage_evaluations_through_feeder(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')->where('id_perguruan_tinggi', 'pt-1')->update([
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => 'YQ==',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'mk-1',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'kode_mata_kuliah' => 'MK001',
            'nama_mata_kuliah' => 'Mata Kuliah Awal',
            'semester' => '1',
            'status' => 'A',
            'id_kelompok_mata_kuliah' => 'A',
            'id_jenis_mata_kuliah' => 'A',
            'sks_mata_kuliah' => '3.00',
            'sks_tatap_muka' => '3.00',
            'sks_praktek' => '0.00',
            'sks_praktek_lapangan' => '0.00',
            'sks_simulasi' => '0.00',
            'ada_sap' => '0',
            'ada_silabus' => '0',
            'ada_bahan_ajar' => '0',
            'ada_acara_praktek' => '0',
            'ada_diktat' => '0',
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request->data()['act'] ?? '') {
                'GetToken' => Http::response(['data' => ['token' => 'dummy-token']]),
                'UpdateMataKuliah', 'GetListRencanaEvaluasi', 'DeleteRencanaEvaluasi' => Http::response([
                    'data' => ($request->data()['act'] ?? '') === 'GetListRencanaEvaluasi'
                        ? [[
                            'id_matkul' => 'mk-1',
                            'id_rencana_evaluasi' => 'eval-1',
                            'nama_evaluasi' => 'UTS',
                            'bobot_evaluasi' => 30,
                        ]]
                        : [],
                ]),
                'GetDetailMataKuliah' => Http::response(['data' => [[
                    'id_matkul' => 'mk-synced',
                    'id_prodi' => 'prodi-1',
                    'kode_mata_kuliah' => 'MK-SYNC',
                    'nama_mata_kuliah' => 'Mata Kuliah Sinkron',
                    'id_jenis_mata_kuliah' => 'A',
                ]]]),
                default => Http::response(['error_code' => '1', 'error_desc' => 'Tindakan tidak dikenal'], 400),
            };
        });
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $this->postJson(route('master-data.mata-kuliah.sync'), [
            'kode_mata_kuliah' => 'MK-SYNC',
            'id_prodi' => 'prodi-2',
        ])->assertOk()
            ->assertJsonPath('synced', 1);
        Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['act'] ?? '') === 'GetDetailMataKuliah'
            && str_contains($request->data()['filter'] ?? '', "id_prodi='prodi-1'")
            && str_contains($request->data()['filter'] ?? '', "kode_mata_kuliah='MK-SYNC'"));
        $this->assertDatabaseHas('s1m4k_mastermk', [
            'id_matkul' => 'mk-synced',
            'id_prodi' => 'prodi-1',
            'kode_mata_kuliah' => 'MK-SYNC',
        ], 'simak_main');

        $invalid = [
            'kode_mata_kuliah' => 'MK002',
            'nama_mata_kuliah' => 'Mata Kuliah Baru',
            'semester' => '2',
            'id_kelompok_mata_kuliah' => 'A',
            'id_jenis_mata_kuliah' => 'A',
            'sks_mata_kuliah' => '2',
            'sks_tatap_muka' => '2',
            'sks_praktek' => '1',
            'sks_praktek_lapangan' => '0',
            'sks_simulasi' => '0',
            'ada_sap' => '0',
            'ada_silabus' => '0',
            'ada_bahan_ajar' => '0',
            'ada_acara_praktek' => '0',
            'ada_diktat' => '0',
            'metode_kuliah' => 'Tatap muka',
            'status' => 'A',
        ];
        $this->putJson(route('master-data.mata-kuliah.update', 'mk-1'), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sks_mata_kuliah']);
        Http::assertNotSent(fn (ClientRequest $request): bool => ($request->data()['act'] ?? '') === 'UpdateMataKuliah');

        $valid = [...$invalid, 'sks_mata_kuliah' => '3'];
        $this->putJson(route('master-data.mata-kuliah.update', 'mk-1'), $valid)
            ->assertOk()
            ->assertJsonPath('record.values.nama_mata_kuliah', 'Mata Kuliah Baru');
        $this->assertDatabaseHas('s1m4k_mastermk', [
            'id_matkul' => 'mk-1',
            'nama_mata_kuliah' => 'Mata Kuliah Baru',
            'semester' => '2',
            'sks_mata_kuliah' => '3',
        ], 'simak_main');
        Http::assertSent(fn (ClientRequest $request): bool => ($request->data()['act'] ?? '') === 'UpdateMataKuliah'
            && ($request->data()['key'] ?? null) === ['id_matkul' => 'mk-1']
            && ($request->data()['record']['id_prodi'] ?? null) === 'prodi-1'
            && ($request->data()['record']['id_kelompok_mata_kuliah'] ?? null) === 'A'
            && ($request->data()['record']['sks_mata_kuliah'] ?? null) === '3.00'
            && ! array_key_exists('tanggal_akhir_efektif', $request->data()['record'] ?? [])
            && ! array_key_exists('tanggal_selesai_efektif', $request->data()['record'] ?? []));

        $this->getJson(route('master-data.mata-kuliah.evaluations', 'mk-1'))
            ->assertOk()
            ->assertJsonPath('data.0.nama_evaluasi', 'UTS');
        $this->deleteJson(route('master-data.mata-kuliah.evaluations.delete', ['record' => 'mk-1', 'evaluation' => 'eval-1']))
            ->assertOk()
            ->assertJsonPath('message', 'Komponen evaluasi berhasil dihapus dari Feeder.');
        Http::assertSent(fn (ClientRequest $request) => ($request->data()['act'] ?? '') === 'DeleteRencanaEvaluasi');

        $this->signIn('pimfak', ['id_fakultas' => 'fak-1']);
        $this->getJson(route('master-data.mata-kuliah.evaluations', 'mk-1'))
            ->assertOk()
            ->assertJsonPath('data.0.nama_evaluasi', 'UTS');
        $this->deleteJson(route('master-data.mata-kuliah.evaluations.delete', ['record' => 'mk-1', 'evaluation' => 'eval-1']))
            ->assertForbidden();
        $this->signIn('pimfak', ['id_fakultas' => 'fak-2']);
        $this->getJson(route('master-data.mata-kuliah.evaluations', 'mk-1'))->assertNotFound();

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->putJson(route('master-data.mata-kuliah.update', 'mk-other'), $valid)->assertNotFound();
        $this->getJson(route('master-data.mata-kuliah.evaluations', 'mk-other'))->assertNotFound();
    }

    public function test_course_sync_and_update_return_feeder_errors_to_the_user(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterpt')->where('id_perguruan_tinggi', 'pt-1')->update([
            'alamat_feeder' => 'https://feeder.example.test',
            'user_feeder' => 'dummy-user',
            'password_feeder' => 'YQ==',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'mk-error',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'kode_mata_kuliah' => 'MK-ERROR',
            'nama_mata_kuliah' => 'Mata Kuliah Error',
            'semester' => '1',
            'status' => 'A',
            'id_kelompok_mata_kuliah' => 'A',
            'id_jenis_mata_kuliah' => 'A',
            'sks_mata_kuliah' => '3.00',
            'sks_tatap_muka' => '3.00',
            'sks_praktek' => '0.00',
            'sks_praktek_lapangan' => '0.00',
            'sks_simulasi' => '0.00',
            'ada_sap' => '0',
            'ada_silabus' => '0',
            'ada_bahan_ajar' => '0',
            'ada_acara_praktek' => '0',
            'ada_diktat' => '0',
        ]);
        Http::fake(function (ClientRequest $request) {
            return match ($request->data()['act'] ?? '') {
                'GetToken' => Http::response(['error_code' => '0', 'data' => ['token' => 'course-token']]),
                'GetDetailMataKuliah', 'UpdateMataKuliah' => Http::response([
                    'error_code' => '77',
                    'error_desc' => 'Field Feeder tidak valid',
                ]),
                default => Http::response(['error_code' => '0', 'data' => []]),
            };
        });
        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);

        $this->postJson(route('master-data.mata-kuliah.sync'), [
            'kode_mata_kuliah' => 'MK-ERROR',
        ])->assertStatus(502)
            ->assertJsonPath('message', 'Feeder gagal (77): Field Feeder tidak valid');

        $this->putJson(route('master-data.mata-kuliah.update', 'mk-error'), [
            'kode_mata_kuliah' => 'MK-ERROR',
            'nama_mata_kuliah' => 'Mata Kuliah Error',
            'semester' => '1',
            'id_kelompok_mata_kuliah' => 'A',
            'id_jenis_mata_kuliah' => 'A',
            'sks_mata_kuliah' => '3',
            'sks_tatap_muka' => '3',
            'sks_praktek' => '0',
            'sks_praktek_lapangan' => '0',
            'sks_simulasi' => '0',
            'ada_sap' => '0',
            'ada_silabus' => '0',
            'ada_bahan_ajar' => '0',
            'ada_acara_praktek' => '0',
            'ada_diktat' => '0',
            'metode_kuliah' => 'Tatap muka',
            'status' => 'A',
        ])->assertStatus(502)
            ->assertJsonPath('message', 'Feeder gagal (77): Field Feeder tidak valid');

        Http::assertSentCount(4);
    }

    public function test_admin_and_program_chair_can_reset_passwords_only_within_program_scope(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-1', 'nidn' => 'NIDN001', 'nama_dosen' => 'Dosen Satu', 'id_prodi' => 'prodi-1', 'password' => Hash::make('old-password')],
            ['id_dosen' => 'dosen-2', 'nidn' => 'NIDN002', 'nama_dosen' => 'Dosen Dua', 'id_prodi' => 'prodi-2', 'password' => Hash::make('old-password')],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterasdos')->insert([
            ['id_asdos' => 'asdos-1', 'kdasisten' => 'AS001', 'nama' => 'Asisten Satu', 'id_prodi' => 'prodi-1', 'password' => Hash::make('old-password')],
            ['id_asdos' => 'asdos-2', 'kdasisten' => 'AS002', 'nama' => 'Asisten Dua', 'id_prodi' => 'prodi-2', 'password' => Hash::make('old-password')],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'mhs-1', 'nim' => '202501', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'prodi-1', 'password' => Hash::make('old-password')],
            ['id_mahasiswa' => 'mhs-2', 'nim' => '202502', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'prodi-2', 'password' => Hash::make('old-password')],
        ]);

        $this->signIn('admin');
        $this->get(route('master-data.dosen.index'))->assertOk()
            ->assertSee('data-can-reset-password="true"', false)
            ->assertSee('data-reset-url-template=', false);
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-2'))
            ->assertOk()
            ->assertJsonPath('message', 'Password berhasil dikembalikan ke nidn yang terdaftar.');
        $this->assertTrue(Hash::check('NIDN002', DB::connection('simak_main')->table('s1m4k_masterdosen')->where('id_dosen', 'dosen-2')->value('password')));
        $this->postJson(route('master-data.asisten-dosen.reset-password', 'asdos-2'))->assertOk();
        $this->assertTrue(Hash::check('AS002', DB::connection('simak_main')->table('s1m4k_masterasdos')->where('id_asdos', 'asdos-2')->value('password')));
        $this->get(route('master-data.mahasiswa.index'))->assertOk()
            ->assertSee('data-can-reset-password="true"', false);
        $this->postJson(route('master-data.mahasiswa.reset-password', 'mhs-2'))->assertOk();
        $this->assertTrue(Hash::check('202502', DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'mhs-2')->value('password')));

        $this->signIn('userprodi', ['id_prodi' => 'prodi-1']);
        $this->get(route('master-data.dosen.index'))->assertOk()
            ->assertSee('data-can-reset-password="true"', false);
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-1'))->assertOk();
        $this->assertTrue(Hash::check('NIDN001', DB::connection('simak_main')->table('s1m4k_masterdosen')->where('id_dosen', 'dosen-1')->value('password')));
        $this->postJson(route('master-data.asisten-dosen.reset-password', 'asdos-1'))->assertOk();
        $this->assertTrue(Hash::check('AS001', DB::connection('simak_main')->table('s1m4k_masterasdos')->where('id_asdos', 'asdos-1')->value('password')));
        $this->postJson(route('master-data.mahasiswa.reset-password', 'mhs-1'))->assertOk();
        $this->assertTrue(Hash::check('202501', DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'mhs-1')->value('password')));
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-2'))->assertNotFound();
        $this->postJson(route('master-data.asisten-dosen.reset-password', 'asdos-2'))->assertNotFound();
        $this->postJson(route('master-data.mahasiswa.reset-password', 'mhs-2'))->assertNotFound();

        $this->signIn('regulang');
        $this->get(route('master-data.mahasiswa.index'))->assertOk()
            ->assertSee('data-can-edit="true"', false)
            ->assertSee('data-reset-url-template=', false);
        $this->getJson(route('master-data.fakultas.lecturers', [
            'search' => 'Dosen',
            'module' => 'mahasiswa',
        ]))->assertOk();
        $this->getJson(route('master-data.mahasiswa.regions', [
            'id_negara' => 'ID',
            'search' => 'Maluku',
        ]))->assertOk();
        $this->getJson(route('master-data.mahasiswa.origins', [
            'type' => 'university',
            'search' => 'Universitas',
        ]))->assertOk();
        $this->postJson(route('master-data.mahasiswa.reset-password', 'mhs-2'))->assertOk();
        $this->assertTrue(Hash::check('202502', DB::connection('simak_main')
            ->table('s1m4k_mastermhs')->where('id_mahasiswa', 'mhs-2')->value('password')));
        $resetActivity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('id_user', 'regulang-master-test')
            ->where('modul', 'Data Master Mahasiswa')
            ->first();
        $this->assertNotNull($resetActivity);
        $this->assertSame('regulang', $resetActivity->level_user);
        $this->assertTrue(
            json_decode($resetActivity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['perubahan']['password_diubah']
        );
        $this->assertSame(
            'Mahasiswa Dua',
            json_decode($resetActivity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['identitas_data']['nama']
        );
        $this->assertStringNotContainsString('202502', $resetActivity->aktifitas);

        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);
        $this->get(route('master-data.dosen.index'))->assertOk()
            ->assertDontSee('data-master-sync', false)
            ->assertDontSee('data-master-reset', false);
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-1'))->assertForbidden();
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-2'))->assertForbidden();

        $this->signIn('pimuniv');
        $this->postJson(route('master-data.dosen.reset-password', 'dosen-1'))->assertForbidden();
        $this->postJson(route('master-data.mahasiswa.reset-password', 'mhs-1'))->assertForbidden();
    }

    public function test_student_program_and_status_filters_intersect(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            [
                'id_mahasiswa' => 'mhs-1',
                'nim' => '202501',
                'nama_mahasiswa' => 'Mahasiswa Aktif Prodi Satu',
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'fak-1',
            ],
            [
                'id_mahasiswa' => 'mhs-2',
                'nim' => '202502',
                'nama_mahasiswa' => 'Mahasiswa Cuti Prodi Dua',
                'id_status_mahasiswa' => 'C',
                'nama_status_mahasiswa' => 'Cuti',
                'id_prodi' => 'prodi-2',
                'id_fakultas' => 'fak-2',
            ],
        ]);
        $this->signIn('regulang');

        $this->get(route('master-data.mahasiswa.data', ['prodi' => 'prodi-1', 'status' => 'A']))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.values.nama_mahasiswa', 'Mahasiswa Aktif Prodi Satu');
    }

    public function test_master_table_endpoint_applies_server_side_pagination_and_search(): void
    {
        $this->seedOrganization();
        $faculties = [];
        for ($index = 1; $index <= 30; $index++) {
            $faculties[] = [
                'id_fakultas' => sprintf('faculty-%02d', $index),
                'kode_fakultas' => sprintf('%02d', $index),
                'nama_fakultas' => $index === 23 ? 'Target Fakultas' : 'Fakultas '.$index,
            ];
        }
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert($faculties);
        $this->signIn('admin');

        $this->getJson(route('master-data.fakultas.data', [
            'draw' => 1,
            'start' => 10,
            'length' => 10,
        ]))->assertOk()
            ->assertJsonPath('draw', 1)
            ->assertJsonPath('recordsTotal', 32)
            ->assertJsonPath('recordsFiltered', 32)
            ->assertJsonCount(10, 'data');

        $this->getJson(route('master-data.fakultas.data', [
            'draw' => 2,
            'search' => ['value' => 'Target Fakultas'],
        ]))->assertOk()
            ->assertJsonPath('draw', 2)
            ->assertJsonPath('recordsTotal', 32)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.values.nama_fakultas', 'Target Fakultas');

        DB::connection('simak_main')->table('s1m4k_masterbobotnilai')->insert([
            ['id_bobot_nilai' => 'bobot-b', 'nilai_huruf' => 'B'],
            ['id_bobot_nilai' => 'bobot-a', 'nilai_huruf' => 'A'],
        ]);
        $this->getJson(route('master-data.bobot-nilai.data'))
            ->assertOk()
            ->assertJsonPath('data.0.values.nilai_huruf', 'A')
            ->assertJsonPath('data.1.values.nilai_huruf', 'B');
    }

    public function test_non_admin_can_update_own_profile_and_student_password_is_not_sent_to_feeder(): void
    {
        $this->seedOrganization();
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 'userprodi-master-test',
            'username' => 'akun-prodi',
            'nama_lengkap' => 'Nama Awal',
            'email' => 'awal@example.test',
            'no_telp' => '123',
            'password' => Hash::make('password-lama'),
        ]);
        $this->signIn('userprodi');
        $this->get(route('user-profile.edit'))
            ->assertOk()
            ->assertSee('data-account-menu', false)
            ->assertSee('data-account-edit', false)
            ->assertSee('data-profile-modal', false)
            ->assertSee('Edit Profil')
            ->assertSee('Keluar dari SIMAK')
            ->assertSee('akun-prodi')
            ->assertSee('Nama Awal');
        $this->put(route('user-profile.update'), [
            'nama_lengkap' => 'Nama Baru',
            'email' => 'baru@example.test',
            'no_telp' => '456',
            'password' => 'password-baru',
        ])->assertRedirect();
        $this->assertDatabaseHas('s1m4k_admin', [
            'id_admin' => 'userprodi-master-test',
            'nama_lengkap' => 'Nama Baru',
            'email' => 'baru@example.test',
            'no_telp' => '456',
        ], 'simak_main');
        $this->assertTrue(Hash::check(
            'password-baru',
            DB::connection('simak_main')->table('s1m4k_admin')
                ->where('id_admin', 'userprodi-master-test')->value('password')
        ));
        $profileActivity = DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Profil Pengguna')->first();
        $profileChanges = json_decode($profileActivity->aktifitas, true, flags: JSON_THROW_ON_ERROR)['perubahan'];
        $this->assertSame('Nama Baru', $profileChanges['nama_lengkap']['sesudah']);
        $this->assertTrue($profileChanges['password']);
        $this->putJson(route('user-profile.update'), [
            'nama_lengkap' => 'Nama Modal',
            'email' => 'modal@example.test',
            'no_telp' => '789',
        ])->assertOk()->assertJsonPath('message', 'Profil akun berhasil diperbarui.');
        $this->assertDatabaseHas('s1m4k_admin', [
            'id_admin' => 'userprodi-master-test',
            'nama_lengkap' => 'Nama Modal',
            'email' => 'modal@example.test',
            'no_telp' => '789',
        ], 'simak_main');

        DB::connection('simak_main')->table('s1m4k_masterpt')
            ->where('id_perguruan_tinggi', 'pt-1')
            ->update([
                'alamat_feeder' => 'https://feeder.example.test',
                'user_feeder' => 'dummy-user',
                'password_feeder' => 'YQ==',
            ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'mhs-profile',
            'nim' => '202501',
            'nama_mahasiswa' => 'Mahasiswa Profil',
            'email' => 'mahasiswa@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'password' => Hash::make('password-lama'),
        ]);
        Http::fake([
            'https://feeder.example.test' => Http::sequence()
                ->push(['data' => ['token' => 'token-dummy']])
                ->push(['data' => []]),
        ]);
        $this->signIn('mahasiswa', [
            'legacy_type' => 'mahasiswa',
            'legacy_id' => 'mhs-profile',
            'id_prodi' => 'prodi-1',
        ]);
        $this->put(route('user-profile.update'), [
            'email' => 'mahasiswa-baru@example.test',
            'nik' => '1234567890123456',
            'id_negara' => 'ID',
            'jalan' => 'Jalan Baru',
            'rt' => '01',
            'rw' => '02',
            'dusun' => '',
            'kelurahan' => '',
            'kode_pos' => '97711',
            'id_wilayah' => '',
            'id_jenis_tinggal' => '',
            'id_alat_transportasi' => '',
            'handphone' => '',
            'telepon' => '',
            'password' => 'password-mahasiswa-baru',
        ])->assertRedirect();
        Http::assertSent(fn (ClientRequest $request): bool => $request['act'] === 'UpdateBiodataMahasiswa'
            && ! isset($request['record']['password'])
            && $request['record']['kewarganegaraan'] === 'ID');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'mhs-profile',
            'email' => 'mahasiswa-baru@example.test',
        ], 'simak_main');
        $this->assertTrue(Hash::check(
            'password-mahasiswa-baru',
            DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->where('id_mahasiswa', 'mhs-profile')->value('password')
        ));

        $this->signIn('admin');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-account-menu', false);
        $this->get(route('user-profile.edit'))->assertForbidden();
    }

    private function signIn(string $role, array $attributes = []): void
    {
        $this->actingAs(new GenericUser(array_merge([
            'id' => $role.'-master-test',
            'legacy_type' => 'admin',
            'legacy_id' => $role.'-master-test',
            'login' => $role.'-master-test',
            'role' => $role,
            'name' => 'Pengguna Uji',
            'email' => 'master@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'fak-1',
            'password' => 'not-used',
            'remember_token' => null,
        ], $attributes)));
    }

    private function seedOrganization(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'pt-1',
            'nama_perguruan_tinggi' => 'Universitas Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'fak-1', 'id_perguruan_tinggi' => 'pt-1', 'kode_fakultas' => '01', 'nama_fakultas' => 'Fakultas Satu', 'status' => 'Aktif'],
            ['id_fakultas' => 'fak-2', 'id_perguruan_tinggi' => 'pt-1', 'kode_fakultas' => '02', 'nama_fakultas' => 'Fakultas Dua', 'status' => 'Aktif'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-1', 'kode_program_studi' => 'P01', 'nama_program_studi' => 'Prodi Satu', 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'fak-1', 'status' => 'A'],
            ['id_prodi' => 'prodi-2', 'kode_program_studi' => 'P02', 'nama_program_studi' => 'Prodi Dua', 'id_perguruan_tinggi' => 'pt-1', 'id_fakultas' => 'fak-2', 'status' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterallpt')->insert([
            ['id_perguruan_tinggi' => 'pt-1', 'nama_perguruan_tinggi' => 'Universitas Uji'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterallprodi')->insert([
            ['id_prodi' => 'prodi-1', 'id_perguruan_tinggi' => 'pt-1', 'nama_program_studi' => 'Prodi Satu'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kodereferensi')->insert([
            ['keterangan' => 'GetStatusMahasiswa', 'kode_referensi' => 'A', 'ket_referensi' => 'Aktif'],
            ['keterangan' => 'GetJenisPendaftaran', 'kode_referensi' => '1', 'ket_referensi' => 'Peserta didik baru'],
            ['keterangan' => 'GetJalurMasuk', 'kode_referensi' => '1', 'ket_referensi' => 'Reguler'],
            ['keterangan' => 'GetPembiayaan', 'kode_referensi' => '1', 'ket_referensi' => 'Mandiri'],
            ['keterangan' => 'GetNegara', 'kode_referensi' => 'ID', 'ket_referensi' => 'Indonesia'],
            ['keterangan' => 'GetJenjangPendidikan', 'kode_referensi' => '5', 'ket_referensi' => 'SMA / Sederajat'],
            ['keterangan' => 'GetKebutuhanKhusus', 'kode_referensi' => '0', 'ket_referensi' => 'Tidak ada'],
            ['keterangan' => 'GetKebutuhanKhusus', 'kode_referensi' => '1', 'ket_referensi' => 'Kebutuhan khusus A'],
            ['keterangan' => 'GetKebutuhanKhusus', 'kode_referensi' => '2', 'ket_referensi' => 'Kebutuhan khusus B'],
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table) {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('nama_perguruan_tinggi')->nullable();
            $table->string('faximile')->nullable();
            $table->string('email')->nullable();
            $table->string('telepon')->nullable();
            $table->string('website')->nullable();
            $table->string('jalan')->nullable();
            $table->string('dusun')->nullable();
            $table->string('rt_rw')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('id_wilayah')->nullable();
            $table->string('nama_wilayah')->nullable();
            $table->string('lintang_bujur')->nullable();
            $table->string('bank')->nullable();
            $table->string('unit_cabang')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('mbs')->nullable();
            $table->string('luas_tanah_milik')->nullable();
            $table->string('luas_tanah_bukan_milik')->nullable();
            $table->string('sk_pendirian')->nullable();
            $table->string('tanggal_sk_pendirian')->nullable();
            $table->string('id_status_milik')->nullable();
            $table->string('nama_status_milik')->nullable();
            $table->string('status_perguruan_tinggi')->nullable();
            $table->string('sk_izin_operasional')->nullable();
            $table->string('tanggal_izin_operasional')->nullable();
            $table->string('alamat_feeder')->nullable();
            $table->string('alamat_wsdl')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterallpt', function (Blueprint $table) {
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nama_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterallprodi', function (Blueprint $table) {
            $table->string('id_prodi')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nama_program_studi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table) {
            $table->string('id_admin')->primary();
            $table->string('username')->nullable();
            $table->string('nama_lengkap')->nullable();
            $table->string('email')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('password')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table) {
            $table->string('id_fakultas')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('kode_fakultas')->nullable();
            $table->string('nama_fakultas')->nullable();
            $table->string('dekan')->nullable();
            $table->string('pd1')->nullable();
            $table->string('pd2')->nullable();
            $table->string('pd3')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table) {
            $table->string('id_prodi')->primary();
            $table->string('kode_program_studi')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('ketua_prodi')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table) {
            $table->string('id_dosen')->primary();
            $table->string('id_registrasi_dosen')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nidn')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('email')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_tahun_ajaran')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('id_status_aktif')->nullable();
            $table->string('nama_status_aktif')->nullable();
            $table->string('nomor_surat_tugas')->nullable();
            $table->string('tanggal_surat_tugas')->nullable();
            $table->string('mulai_surat_tugas')->nullable();
            $table->integer('flag_feeder')->nullable();
            $table->string('password')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table) {
            $table->string('id_jadwal')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('nama_kelas')->nullable();
            for ($slot = 1; $slot <= 14; $slot++) {
                $table->string('dosen'.$slot)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_edom', function (Blueprint $table) {
            $table->string('id_edom')->primary();
            $table->string('id_krs')->nullable();
            $table->string('id_jadwal')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_survey')->nullable();
            $table->integer('nilai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterasdos', function (Blueprint $table) {
            $table->string('id_asdos')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('kdasisten')->nullable();
            $table->string('nama')->nullable();
            $table->string('jkel')->nullable();
            $table->string('password')->nullable();
            $table->string('passwordb')->nullable();
            $table->string('notlp')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table) {
            $table->string('keterangan')->nullable();
            $table->string('kode_referensi')->nullable();
            $table->string('ket_referensi')->nullable();
            $table->string('ket_referensi2')->nullable();
            $table->string('nama_provinsi')->nullable();
            $table->decimal('lat_provinsi', 10, 7)->nullable();
            $table->decimal('lng_provinsi', 10, 7)->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table) {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('dosen_pa')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('nisn')->nullable();
            $table->string('npwp')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('password_baru')->nullable();
            $table->string('nik')->nullable();
            $table->string('id_negara')->nullable();
            $table->string('jalan')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('dusun')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kode_pos')->nullable();
            $table->string('id_wilayah')->nullable();
            $table->string('id_jenis_tinggal')->nullable();
            $table->string('id_alat_transportasi')->nullable();
            $table->string('handphone')->nullable();
            $table->string('telepon')->nullable();
            $table->string('penerima_kps')->nullable();
            $table->string('nomor_kps')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->date('tanggal_lahir_ayah')->nullable();
            $table->string('nik_ayah')->nullable();
            $table->string('id_pendidikan_ayah')->nullable();
            $table->string('id_pekerjaan_ayah')->nullable();
            $table->string('id_penghasilan_ayah')->nullable();
            $table->string('id_kebutuhan_khusus_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->date('tanggal_lahir_ibu')->nullable();
            $table->string('nik_ibu')->nullable();
            $table->string('id_pendidikan_ibu')->nullable();
            $table->string('id_pekerjaan_ibu')->nullable();
            $table->string('id_penghasilan_ibu')->nullable();
            $table->string('id_kebutuhan_khusus_ibu')->nullable();
            $table->string('nama_wali')->nullable();
            $table->date('tanggal_lahir_wali')->nullable();
            $table->string('id_pendidikan_wali')->nullable();
            $table->string('id_pekerjaan_wali')->nullable();
            $table->string('id_penghasilan_wali')->nullable();
            $table->string('id_kebutuhan_khusus_mahasiswa')->nullable();
            $table->string('id_jenis_daftar')->nullable();
            $table->string('id_jalur_daftar')->nullable();
            $table->string('id_periode_masuk')->nullable();
            $table->date('tanggal_daftar')->nullable();
            $table->string('sks_diakui')->nullable();
            $table->string('id_perguruan_tinggi_asal')->nullable();
            $table->string('id_prodi_asal')->nullable();
            $table->string('id_pembiayaan')->nullable();
            $table->string('biaya_masuk_kuliah')->nullable();
            $table->string('flag_feeder')->nullable();
            $table->string('kategori_ukt')->nullable();
            $table->string('nominal_ukt')->nullable();
            $table->string('update_ukt')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table) {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_mahasiswa_old')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_jadwal')->nullable();
            $table->string('input_nilai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table) {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('tahun')->nullable();
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table) {
            $table->string('id_registrasi')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table) {
            $table->string('id_pengesahan')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('nama_kpro')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table) {
            $table->string('id_ipmhs')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table) {
            $table->string('id_matkul')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('semester')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('status')->nullable();
            $table->string('id_jenis_mata_kuliah')->nullable();
            $table->string('id_kelompok_mata_kuliah')->nullable();
            $table->string('sks_tatap_muka')->nullable();
            $table->string('sks_praktek')->nullable();
            $table->string('sks_praktek_lapangan')->nullable();
            $table->string('sks_simulasi')->nullable();
            $table->string('ada_sap')->nullable();
            $table->string('ada_silabus')->nullable();
            $table->string('ada_bahan_ajar')->nullable();
            $table->string('ada_acara_praktek')->nullable();
            $table->string('ada_diktat')->nullable();
            $table->string('metode_kuliah')->nullable();
            $table->string('flag_feeder')->nullable();
            $table->date('tanggal_mulai_efektif')->nullable();
            $table->date('tanggal_selesai_efektif')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table) {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('kapasitas')->nullable();
            $table->string('status')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterbobotnilai', function (Blueprint $table) {
            $table->string('id_bobot_nilai')->primary();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_indeks')->nullable();
            $table->string('bobot_minimum')->nullable();
            $table->string('bobot_maksimum')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_mulai_efektif')->nullable();
            $table->date('tanggal_akhir_efektif')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->text('aktifitas');
            $table->timestamp('created_at')->nullable();
        });
    }

    private function assertMasterTableColumnCounts(string $html): void
    {
        $document = new \DOMDocument;
        $previousErrorMode = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);

        $table = $document->getElementById('master-data-table');
        $this->assertNotNull($table);
        $headers = $table->getElementsByTagName('thead')->item(0)->getElementsByTagName('th')->length;
        $this->assertNotSame('', $table->getAttribute('data-master-columns'));
        $this->assertSame('', $table->getAttribute('data-columns'));
        foreach ($table->getElementsByTagName('tbody')->item(0)->getElementsByTagName('tr') as $row) {
            $this->assertSame($headers, $row->getElementsByTagName('td')->length);
        }
    }
}
