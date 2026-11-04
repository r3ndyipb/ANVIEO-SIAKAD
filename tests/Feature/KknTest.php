<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class KknTest extends TestCase
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
        $this->seedRecords();
    }

    public function test_kkn_pages_are_available_only_to_legacy_dispatcher_roles(): void
    {
        $adminGroups = collect(config('simak.navigation.admin'))->keyBy('label');
        $this->assertSame(
            ['kkn.periods.index', 'kkn.proposals.index', 'kkn.validations.index', 'kkn.locations.index', 'kkn.participants.index'],
            collect($adminGroups['Kuliah Kerja Nyata (KKN)']['items'])->pluck('route')->all()
        );

        foreach (['admin' => ['kkn.periods.index', 'kkn.locations.index', 'kkn.proposals.index', 'kkn.validations.index', 'kkn.participants.index'],
            'lppm' => ['kkn.locations.index', 'kkn.participants.index'],
            'validasi' => ['kkn.proposals.index', 'kkn.validations.index'],
            'usertu' => ['kkn.proposals.index'],
            'mahasiswa' => ['kkn.student.index']] as $role => $routes) {
            $this->signIn($role);
            foreach ($routes as $route) {
                $this->get(route($route))->assertOk();
            }
        }

        foreach ([
            'lppm' => ['kkn.periods.index', 'kkn.validations.index', 'kkn.proposals.index'],
            'validasi' => ['kkn.locations.index', 'kkn.participants.index'],
            'usertu' => ['kkn.periods.index', 'kkn.locations.index', 'kkn.validations.index', 'kkn.participants.index'],
            'mahasiswa' => ['kkn.periods.index', 'kkn.locations.index', 'kkn.proposals.index', 'kkn.validations.index', 'kkn.participants.index'],
        ] as $role => $routes) {
            $this->signIn($role);
            foreach ($routes as $route) {
                $this->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_admin_manages_periods_and_location_staff_manage_capacity(): void
    {
        $this->signIn('admin');
        $this->post(route('kkn.periods.store'), [
            'tahun' => '20261',
            'tanggal_mulai' => today()->toDateString(),
            'tanggal_akhir' => today()->addDays(30)->toDateString(),
        ])->assertRedirect(route('kkn.periods.index'));
        $createdPeriod = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('jenis', 'kubermas')->where('aktif', 'N')->first();
        $this->assertNotNull($createdPeriod);
        $this->get(route('kkn.periods.index'))->assertOk()
            ->assertSee('data-dialog-open="kkn-period-create-dialog"', false)
            ->assertSee('data-kkn-period-edit', false)
            ->assertDontSee('class="kkn-period-create"', false);

        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-old', 'nama_periode' => 'Tahun lama', 'tahun' => '20252',
            'tanggal_mulai' => '2025-01-01', 'tanggal_akhir' => '2025-03-01', 'jenis' => 'kubermas', 'aktif' => 'Y',
        ]);
        $this->post(route('kkn.periods.activate', $createdPeriod->id_periode))
            ->assertRedirect(route('kkn.periods.index'));
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => 'period-old', 'aktif' => 'N'], 'simak_main');
        $this->assertDatabaseHas('s1m4k_periode', ['id_periode' => $createdPeriod->id_periode, 'aktif' => 'Y'], 'simak_main');

        $this->post(route('kkn.locations.store'), ['nama_lokasi' => 'Desa Uji', 'kuota' => 2])
            ->assertRedirect(route('kkn.locations.index'));
        $location = DB::connection('simak_main')->table('s1m4k_lokasi_kubermas')->where('nama_lokasi', 'Desa Uji')->first();
        $this->assertNotNull($location);
        $this->assertSame('Y', $location->aktif);
        $this->get(route('kkn.locations.index'))->assertOk()
            ->assertSee('data-dialog-open="kkn-location-create-dialog"', false)
            ->assertSee('data-kkn-location-edit', false)
            ->assertDontSee('class="kkn-inline-form"', false);
        $this->put(route('kkn.locations.update', $location->id_lokasi), [
            'nama_lokasi' => 'Desa Uji Baru', 'kuota' => 3, 'aktif' => 'N',
        ])->assertRedirect(route('kkn.locations.index'));
        $this->assertDatabaseHas('s1m4k_lokasi_kubermas', [
            'id_lokasi' => $location->id_lokasi, 'nama_lokasi' => 'Desa Uji Baru', 'kuota' => 3, 'aktif' => 'N',
        ], 'simak_main');
    }

    public function test_proposals_are_period_limited_scoped_and_idempotent(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-empty-name', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'faculty-1',
            'nim' => 'NPM-EMPTY', 'nama_mahasiswa' => '', 'id_status_mahasiswa' => 'A',
        ]);
        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);
        $this->get(route('kkn.proposals.create', ['search' => 'Satu']))
            ->assertRedirect(route('kkn.proposals.index', ['tahun' => '20261', 'create' => 1, 'search' => 'Satu']));
        $this->get(route('kkn.proposals.index', ['tahun' => '20261', 'create' => 1, 'search' => 'Satu']))
            ->assertOk()->assertSee('Mahasiswa Satu')->assertDontSee('Mahasiswa Dua')
            ->assertDontSee('NPM-EMPTY')
            ->assertSee('data-kkn-proposal-form', false)
            ->assertSee('data-candidate-live-search', false)
            ->assertSee('id="kkn-proposal-create-dialog"', false)
            ->assertSee('data-kkn-candidate-table', false)
            ->assertSee('data-searchable-select', false)
            ->assertSee('data-kkn-year="20261"', false)
            ->assertSee('kkn-student-checkbox', false);
        $this->post(route('kkn.proposals.store'), ['students' => ['student-1']])
            ->assertRedirect(route('kkn.proposals.index', ['tahun' => '20261']))
            ->assertSessionHas('status', '1 mahasiswa berhasil diusulkan sebagai peserta KKN.');
        $this->assertDatabaseHas('s1m4k_kubermas', [
            'id_mahasiswa' => 'student-1', 'id_prodi' => 'prodi-1', 'tahun' => 20261,
            'status' => 0, 'petugas_usul' => 'Pengguna Uji',
        ], 'simak_main');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_kubermas')->count());

        $this->post(route('kkn.proposals.store'), ['students' => ['student-1']])
            ->assertRedirect(route('kkn.proposals.index', ['tahun' => '20261']));
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_kubermas')->count());

        $this->signIn('usertu', ['id_prodi' => 'prodi-2']);
        $this->post(route('kkn.proposals.store'), ['students' => ['student-1']])->assertNotFound();
    }

    public function test_candidate_pagination_preserves_modal_state_for_kkn_and_graduation(): void
    {
        for ($index = 5; $index <= 26; $index++) {
            DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
                'id_mahasiswa' => 'student-'.$index,
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'faculty-1',
                'nim' => 'NPM-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'nama_mahasiswa' => 'Mahasiswa '.$index,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ]);
        }
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-wisuda-active',
            'nama_periode' => 'Wisuda Oktober 2026',
            'tahun' => '20262',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'jenis' => 'wisuda',
            'aktif' => 'Y',
        ]);
        $this->signIn('admin');

        $this->get(route('kkn.proposals.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('data-open-on-load="true"', false)
            ->assertSee('data-kkn-candidate-table', false)
            ->assertSee('data-candidate-live-search', false)
            ->assertSee('create=1&amp;students_page=2', false);
        $this->get(route('graduation-participants.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('data-open-on-load="true"', false)
            ->assertSee('data-kkn-candidate-table', false)
            ->assertSee('data-candidate-live-search', false)
            ->assertSee('create=1&amp;students_page=2', false)
            ->assertSee('data-simple-search-table', false)
            ->assertSee('data-search-placeholder="Cari NPM, nama, prodi, atau status"', false)
            ->assertSee('Belum ada usulan pada periode ini.');
    }

    public function test_proposal_and_student_registration_are_rejected_outside_the_open_window(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->where('id_periode', 'period-active')
            ->update(['tanggal_mulai' => today()->addDay()->toDateString()]);
        $this->signIn('admin');
        $this->get(route('kkn.proposals.create'))
            ->assertRedirect(route('kkn.proposals.index', ['tahun' => '20261', 'create' => 1]));
        $this->get(route('kkn.proposals.index', ['tahun' => '20261']))
            ->assertOk()->assertSee('Masa pengusulan ditutup');
        $this->post(route('kkn.proposals.store'), ['students' => ['student-1']])
            ->assertRedirect(route('kkn.proposals.index', ['tahun' => '20261']))
            ->assertSessionHas('error', 'Masa pengusulan KKN ditutup. Pengusulan hanya dapat dilakukan saat periode KKN aktif dan masih dalam tanggal pendaftaran.');
        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);
        $this->from(route('kkn.student.index'))->post(route('kkn.student.register'))
            ->assertRedirect(route('kkn.student.index'))
            ->assertSessionHas('error', 'Pendaftaran KKN sedang ditutup. Periode KKN belum aktif atau sudah melewati batas tanggal pendaftaran.');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_kubermas')->count());
    }

    public function test_graduation_proposals_require_an_active_period_and_write_to_wisuda_table(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-wisuda-active',
            'nama_periode' => 'Wisuda Oktober 2026',
            'tahun' => '20262',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'jenis' => 'wisuda',
            'aktif' => 'Y',
            'kuota_wisuda' => 100,
        ]);
        $this->signIn('admin');
        $this->get(route('graduation-participants.index', ['tahun' => '20262']))
            ->assertOk();
        $this->post(route('graduation-participants.store'), ['students' => ['student-1']])
            ->assertRedirect(route('graduation-participants.index', ['periode' => 'period-wisuda-active']))
            ->assertSessionHas('status', '1 mahasiswa berhasil diusulkan sebagai peserta wisuda.');
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_mahasiswa' => 'student-1',
            'id_periode' => 'period-wisuda-active',
            'status' => 0,
            'petugas' => 'Pengguna Uji',
        ], 'simak_main');

        DB::connection('simak_main')->table('s1m4k_periode')->where('id_periode', 'period-wisuda-active')
            ->update(['tanggal_akhir' => today()->subDay()->toDateString()]);
        $this->post(route('graduation-participants.store'), ['students' => ['student-1']])
            ->assertRedirect(route('graduation-participants.index', ['periode' => 'period-wisuda-active']))
            ->assertSessionHas('error', 'Periode wisuda aktif belum dibuka atau sudah berakhir. Pengusulan peserta wisuda ditolak.');
    }

    public function test_graduation_participants_can_be_viewed_for_active_period_without_a_year(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'wisuda-tanpa-tahun',
            'nama_periode' => 'Wisuda Periode Baru',
            'tahun' => null,
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'jenis' => 'wisuda',
            'aktif' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-peserta-uji',
            'id_periode' => 'wisuda-tanpa-tahun',
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'student-1',
            'status' => 0,
            'tgl_daftar' => today()->toDateString(),
            'petugas' => 'Pengguna Uji',
        ]);
        $this->signIn('admin');

        $this->get(route('graduation-participants.index', ['periode' => 'wisuda-tanpa-tahun']))
            ->assertOk()
            ->assertSee('value="wisuda-tanpa-tahun" selected', false)
            ->assertSee('Wisuda Periode Baru')
            ->assertSee('Mahasiswa Satu');
    }

    public function test_graduation_candidates_include_required_statuses_and_disable_same_period_registrations(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-wisuda-active',
            'nama_periode' => 'Wisuda Oktober 2026',
            'tahun' => '20262',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'jenis' => 'wisuda',
            'aktif' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-student-1',
            'id_periode' => 'period-wisuda-active',
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => 'student-1',
        ]);
        foreach ([5 => 'C', 6 => 'N', 7 => 'M', 8 => 'G'] as $index => $status) {
            DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
                'id_mahasiswa' => 'student-'.$index,
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'faculty-1',
                'nim' => 'NPM-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'nama_mahasiswa' => 'Mahasiswa '.$index,
                'id_status_mahasiswa' => $status,
                'nama_status_mahasiswa' => $status,
            ]);
        }

        $this->signIn('admin');
        $this->get(route('graduation-participants.index', ['tahun' => '20262', 'create' => 1]))
            ->assertOk()
            ->assertSee('value="student-1" disabled', false)
            ->assertSee('Sudah terdaftar pada periode ini')
            ->assertSee('Mahasiswa 5')
            ->assertSee('Mahasiswa 6')
            ->assertSee('Mahasiswa 7')
            ->assertSee('Mahasiswa Dua')
            ->assertDontSee('Mahasiswa 8');

        $this->signIn('usertu', ['id_prodi' => 'prodi-1']);
        $this->get(route('graduation-participants.index', ['tahun' => '20262', 'create' => 1]))
            ->assertOk()
            ->assertSee('Mahasiswa 5')
            ->assertDontSee('Mahasiswa Dua');
    }

    public function test_wisuda_can_search_and_propose_candidates_across_programs(): void
    {
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-wisuda-active',
            'nama_periode' => 'Wisuda Oktober 2026',
            'tahun' => '20262',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'jenis' => 'wisuda',
            'aktif' => 'Y',
        ]);
        $this->signIn('wisuda');

        $this->get(route('graduation-participants.index', [
            'tahun' => '20262',
            'create' => 1,
            'search' => 'Mahasiswa Dua',
        ]))
            ->assertOk()
            ->assertSee('Mahasiswa Dua')
            ->assertSee('data-candidate-live-search', false);
        $this->post(route('graduation-participants.store'), ['students' => ['student-2']])
            ->assertRedirect(route('graduation-participants.index', ['periode' => 'period-wisuda-active']))
            ->assertSessionHas('status', '1 mahasiswa berhasil diusulkan sebagai peserta wisuda.');
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_mahasiswa' => 'student-2',
            'id_prodi' => 'prodi-2',
            'id_periode' => 'period-wisuda-active',
        ], 'simak_main');
    }

    public function test_student_wisuda_requires_an_existing_tuprodi_proposal(): void
    {
        $this->createGraduationPeriod();
        $this->signIn('admin');
        $this->get(route('graduation-student.index'))->assertForbidden();

        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);
        $this->get(route('graduation-student.index'))
            ->assertOk()
            ->assertSee('Jika Anda sudah yudisium dan belum bisa melengkapi dokumen wisuda')
            ->assertSee('hubungi operator prodi (Tuprodi)');

        $this->post(route('graduation-student.documents.upload', 'foto'), [
            'document_file' => UploadedFile::fake()->image('foto.png'),
        ])->assertRedirect(route('graduation-student.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('s1m4k_wisuda', 0, 'simak_main');
    }

    public function test_student_can_upload_private_wisuda_documents_until_verified_without_registering(): void
    {
        Storage::fake('local');
        $periodId = $this->createGraduationPeriod();
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-student-1',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-1',
            'status' => 0,
        ]);
        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);

        $this->get(route('graduation-student.index'))
            ->assertOk()
            ->assertSee('Pas foto berlatar merah')
            ->assertSee('Surat bebas perpustakaan')
            ->assertSee('Surat bebas biaya kuliah')
            ->assertSee('Bukti penyerahan jurnal dan skripsi')
            ->assertSee('data-dialog-open="graduation-document-upload-dialog"', false)
            ->assertSee('<dialog', false)
            ->assertDontSee('Kirim pendaftaran wisuda');

        $this->post(route('graduation-student.documents.upload', 'foto'), [
            'document_file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('document_file');
        $this->post(route('graduation-student.documents.upload', 'bebas_perpus'), [
            'document_file' => UploadedFile::fake()->create('payload.pdf', 10, 'text/plain'),
        ])->assertSessionHasErrors('document_file');

        $this->post(route('graduation-student.documents.upload', 'foto'), [
            'document_file' => UploadedFile::fake()->image('pas-foto.png', 600, 800),
        ])->assertRedirect(route('graduation-student.index'))
            ->assertSessionHas('status');
        $oldPhotoPath = DB::connection('simak_main')->table('s1m4k_wisuda')
            ->where('id_peserta', 'wisuda-student-1')->value('foto');
        $this->assertStringStartsWith('wisuda/'.$periodId.'/', $oldPhotoPath);
        $this->assertTrue(Storage::disk('local')->exists($oldPhotoPath));
        $this->get(route('graduation-student.documents.download', 'foto'))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->post(route('graduation-student.documents.upload', 'foto'), [
            'document_file' => UploadedFile::fake()->image('pas-foto-baru.jpg', 600, 800),
        ])->assertRedirect(route('graduation-student.index'));
        $photoPath = DB::connection('simak_main')->table('s1m4k_wisuda')
            ->where('id_peserta', 'wisuda-student-1')->value('foto');
        $this->assertNotSame($oldPhotoPath, $photoPath);
        $this->assertTrue(Storage::disk('local')->exists($photoPath));
        $this->assertFalse(Storage::disk('local')->exists($oldPhotoPath));

        $this->signIn('mahasiswa', ['legacy_id' => 'student-2']);
        $this->get(route('graduation-student.documents.download', 'foto'))->assertNotFound();

        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);
        foreach (['bebas_perpus', 'bebas_spp', 'skripsi'] as $document) {
            $payload = [
                'document_file' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
            ];
            if ($document === 'skripsi') {
                $payload['judul_skripsi'] = 'Judul skripsi uji';
                $payload['handphone'] = '081234567890';
            }
            $this->post(route('graduation-student.documents.upload', $document), $payload)
                ->assertRedirect(route('graduation-student.index'))
                ->assertSessionHas('status');
        }
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_peserta' => 'wisuda-student-1',
            'status' => 0,
            'pernyataan' => null,
            'judul_skripsi' => 'Judul skripsi uji',
            'tgl_daftar' => null,
        ], 'simak_main');
        $this->assertDatabaseHas('s1m4k_mastermhs', [
            'id_mahasiswa' => 'student-1', 'handphone' => '081234567890',
        ], 'simak_main');

        DB::connection('simak_main')->table('s1m4k_wisuda')
            ->where('id_peserta', 'wisuda-student-1')->update(['lolos' => '0']);
        $this->get(route('graduation-student.index'))
            ->assertOk()
            ->assertSee('Berkas wisuda sudah diverifikasi')
            ->assertDontSee('data-dialog-open="graduation-document-upload-dialog"', false);
        $this->post(route('graduation-student.documents.upload', 'foto'), [
            'document_file' => UploadedFile::fake()->image('pas-foto-final.png', 600, 800),
        ])->assertRedirect(route('graduation-student.index'))
            ->assertSessionHas('error', 'Berkas wisuda sudah diverifikasi. Upload atau penggantian dokumen tidak dapat dilakukan.');
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_peserta' => 'wisuda-student-1', 'foto' => $photoPath, 'lolos' => '0',
        ], 'simak_main');
    }

    public function test_admin_and_validasi_can_review_proposed_wisuda_participants(): void
    {
        $periodId = $this->createGraduationPeriod();
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-review-student-1',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-1',
            'status' => 0,
        ]);
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-approved-student-1',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-2',
            'status' => 2,
            'lolos' => '1',
        ]);

        foreach (['admin', 'validasi'] as $role) {
            $this->signIn($role);
            $this->get(route('graduation-validations.index', ['periode' => $periodId]))
                ->assertOk()
                ->assertSee('NPM-001')
                ->assertSee('0/4 dokumen');
            $this->get(route('graduation-validations.index', ['periode' => $periodId]))
                ->assertSee('NPM-002')
                ->assertSee('Lolos Verifikasi');
            $this->get(route('graduation-validations.show', [$periodId, 'wisuda-review-student-1']))
                ->assertOk()
                ->assertSee('Mahasiswa Satu')
                ->assertSee('Bukti penyerahan jurnal dan skripsi');
        }

        foreach (['usertu', 'mahasiswa'] as $role) {
            $this->signIn($role, ['legacy_id' => 'student-1']);
            $this->get(route('graduation-validations.index', ['periode' => $periodId]))->assertForbidden();
            $this->get(route('graduation-validations.show', [$periodId, 'wisuda-review-student-1']))->assertForbidden();
        }

        $this->signIn('wisuda');
        $this->get(route('graduation-validations.index', ['periode' => $periodId]))
            ->assertOk()
            ->assertSee('NPM-001')
            ->assertSee('NPM-002')
            ->assertSee('Lolos Verifikasi')
            ->assertSee('Lihat Detail')
            ->assertSee('Belum Verifikasi');
        $this->get(route('graduation-validations.show', [$periodId, 'wisuda-review-student-1']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu');
        $this->get(route('graduation-validations.show', [$periodId, 'wisuda-approved-student-1']))
            ->assertOk()
            ->assertSee('Mahasiswa Dua');
        $this->put(route('graduation-validations.update', [$periodId, 'wisuda-approved-student-1']), [
            'lolos' => '0',
        ])->assertForbidden();
    }

    public function test_graduation_validation_updates_decision_comment_and_petugas(): void
    {
        Storage::fake('local');
        $periodId = $this->createGraduationPeriod();
        $participantId = 'wisuda-review-student-1';
        $participantDirectory = 'wisuda/'.$periodId.'/'.hash('sha256', $participantId);
        $documents = [
            'foto' => $participantDirectory.'/foto/'.Str::uuid().'.jpg',
            'bebas_perpus' => $participantDirectory.'/bebas_perpus/'.Str::uuid().'.pdf',
            'bebas_spp' => $participantDirectory.'/bebas_spp/'.Str::uuid().'.pdf',
            'skripsi' => $participantDirectory.'/skripsi/'.Str::uuid().'.pdf',
        ];
        foreach ($documents as $path) {
            Storage::disk('local')->put($path, 'test-document');
        }
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => $participantId,
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-1',
            ...$documents,
            'judul_skripsi' => 'Judul skripsi uji',
            'pernyataan' => null,
            'status' => 0,
        ]);
        $this->signIn('validasi');

        $this->get(route('graduation-validations.show', [$periodId, $participantId]))
            ->assertOk()
            ->assertSee('target="graduation-document-preview-frame"', false)
            ->assertSee('data-dialog-open="graduation-document-preview-dialog"', false)
            ->assertSee('name="graduation-document-preview-frame"', false);
        $this->get(route('graduation-validations.documents.download', [$periodId, $participantId, 'foto']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="foto-NPM-001.jpg"');
        $this->get(route('graduation-validations.documents.download', [$periodId, $participantId, 'bebas_perpus']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="bebas_perpus-NPM-001.pdf"');

        $this->put(route('graduation-validations.update', [$periodId, $participantId]), [
            'lolos' => '2', 'komentar' => 'Keputusan tidak dikenal',
        ])->assertSessionHasErrors('lolos');
        $this->put(route('graduation-validations.update', [$periodId, $participantId]), [
            'lolos' => '0', 'komentar' => '',
        ])->assertSessionHasErrors('komentar');
        $this->put(route('graduation-validations.update', [$periodId, $participantId]), [
            'lolos' => '0', 'komentar' => 'Foto tidak sesuai ketentuan.',
        ])->assertRedirect(route('graduation-validations.show', [$periodId, $participantId]))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_peserta' => $participantId,
            'lolos' => '0',
            'komentar' => 'Foto tidak sesuai ketentuan.',
            'petugas' => 'Pengguna Uji',
            'pernyataan' => null,
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'Validasi Wisuda', 'level_user' => 'validasi',
        ], 'simak_main');

        $this->put(route('graduation-validations.update', [$periodId, $participantId]), [
            'lolos' => '1', 'komentar' => 'Berkas lengkap.',
        ])->assertRedirect(route('graduation-validations.show', [$periodId, $participantId]));
        $this->assertDatabaseHas('s1m4k_wisuda', [
            'id_peserta' => $participantId,
            'lolos' => '1',
            'komentar' => 'Berkas lengkap.',
        ], 'simak_main');
    }

    public function test_graduation_validation_exports_excel_pdf_and_ppt_for_passed_students(): void
    {
        Storage::fake('local');
        $periodId = $this->createGraduationPeriod();
        $photoDirectory = 'wisuda/'.$periodId.'/'.hash('sha256', 'wisuda-export-passed').'/foto';
        $photoPath = UploadedFile::fake()->image('wisudawan.jpg', 300, 400)
            ->storeAs($photoDirectory, (string) Str::uuid().'.jpg', 'local');
        $this->assertNotNull($photoPath);
        $this->assertTrue(Storage::disk('local')->exists($photoPath));
        $this->assertIsArray(@getimagesize(Storage::disk('local')->path($photoPath)));
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-export-passed',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-1',
            'foto' => $photoPath,
            'lolos' => '1',
            'pernyataan' => null,
        ]);
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-export-rejected',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-2',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-2',
            'lolos' => '0',
        ]);
        $this->signIn('validasi');

        $excel = $this->get(route('graduation-validations.export.excel', $periodId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith("PK\x03\x04", $excel->getContent());
        $excelPath = tempnam(sys_get_temp_dir(), 'wisuda-export-');
        file_put_contents($excelPath, $excel->getContent());
        $excelSheet = IOFactory::load($excelPath)->getActiveSheet();
        $this->assertSame(2, $excelSheet->getHighestDataRow());
        $this->assertSame('NPM-001', $excelSheet->getCell('A2')->getValue());
        $this->assertNotSame('NPM-002', $excelSheet->getCell('A2')->getValue());
        unlink($excelPath);

        $pdf = $this->get(route('graduation-validations.export.pdf', $periodId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $presentation = $this->get(route('graduation-validations.export.ppt', $periodId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation');
        $this->assertStringStartsWith("PK\x03\x04", $presentation->getContent());
        $presentationPath = tempnam(sys_get_temp_dir(), 'wisuda-ppt-');
        file_put_contents($presentationPath, $presentation->getContent());
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($presentationPath));
        $allSlidesXml = '';
        $embeddedPhoto = false;
        $mediaEntries = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = (string) $archive->getNameIndex($index);
            if (preg_match('#^ppt/slides/slide\d+\.xml$#', $entry) === 1) {
                $allSlidesXml .= (string) $archive->getFromName($entry);
            }
            if (str_starts_with($entry, 'ppt/media/')) {
                $mediaEntries[] = $entry;
            }
            if (preg_match('#^ppt/media/[^/]+\.(?:jpg|jpeg|png)$#i', $entry) === 1) {
                $embeddedPhoto = true;
            }
        }
        $archive->close();
        unlink($presentationPath);
        $this->assertStringContainsString('NPM: NPM-001', $allSlidesXml);
        $this->assertStringNotContainsString('NPM-002', $allSlidesXml);
        $this->assertTrue($embeddedPhoto, 'The PPTX should contain the participant photo as embedded media. Entries: '.implode(', ', $mediaEntries));
        $this->assertStringContainsString('<p:pic>', $allSlidesXml, 'The participant photo should be placed on a slide.');

        $this->signIn('wisuda');
        $this->get(route('graduation-validations.index', ['periode' => $periodId]))
            ->assertOk()
            ->assertSee('Laporan Wisuda')
            ->assertSee('NPM-001')
            ->assertSee('NPM-002')
            ->assertSee('Download Excel')
            ->assertSee('Download PDF')
            ->assertSee('Download PPT');
        $this->get(route('graduation-validations.show', [$periodId, 'wisuda-export-passed']))
            ->assertOk()
            ->assertSee('Detail Peserta Wisuda')
            ->assertDontSee('Simpan Verifikasi');
        $this->get(route('graduation-validations.show', [$periodId, 'wisuda-export-rejected']))
            ->assertOk()
            ->assertSee('Detail Peserta Wisuda')
            ->assertDontSee('Simpan Verifikasi');
        $this->put(route('graduation-validations.update', [$periodId, 'wisuda-export-passed']), [
            'lolos' => '0', 'komentar' => 'Tidak boleh diubah',
        ])->assertForbidden();
        $this->get(route('graduation-validations.export.excel', $periodId))->assertOk();
        $this->get(route('graduation-validations.export.pdf', $periodId))->assertOk();
        $this->get(route('graduation-validations.export.ppt', $periodId))->assertOk();

        $this->signIn('usertu');
        $this->get(route('graduation-validations.export.excel', $periodId))->assertForbidden();
    }

    public function test_graduation_exports_use_default_photo_when_participant_has_no_photo(): void
    {
        $periodId = $this->createGraduationPeriod();
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            'id_peserta' => 'wisuda-export-default-photo',
            'id_periode' => $periodId,
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'id_mahasiswa' => 'student-1',
            'foto' => null,
            'lolos' => '1',
            'pernyataan' => null,
        ]);
        $this->signIn('validasi');

        $excel = $this->get(route('graduation-validations.export.excel', $periodId))->assertOk();
        $excelPath = tempnam(sys_get_temp_dir(), 'wisuda-default-xlsx-');
        file_put_contents($excelPath, $excel->getContent());
        $excelArchive = new \ZipArchive;
        $this->assertTrue($excelArchive->open($excelPath));
        $hasExcelPhoto = false;
        for ($index = 0; $index < $excelArchive->numFiles; $index++) {
            $entry = (string) $excelArchive->getNameIndex($index);
            if (preg_match('#^xl/media/[^/]+\.(?:jpg|jpeg|png)$#i', $entry) === 1) {
                $hasExcelPhoto = true;
                break;
            }
        }
        $excelArchive->close();
        unlink($excelPath);
        $this->assertTrue($hasExcelPhoto, 'The XLSX should embed the default participant photo.');

        $pdf = $this->get(route('graduation-validations.export.pdf', $periodId))->assertOk();
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());

        $presentation = $this->get(route('graduation-validations.export.ppt', $periodId))->assertOk();
        $presentationPath = tempnam(sys_get_temp_dir(), 'wisuda-default-ppt-');
        file_put_contents($presentationPath, $presentation->getContent());
        $presentationArchive = new \ZipArchive;
        $this->assertTrue($presentationArchive->open($presentationPath));
        $hasPresentationPhoto = false;
        for ($index = 0; $index < $presentationArchive->numFiles; $index++) {
            $entry = (string) $presentationArchive->getNameIndex($index);
            if (preg_match('#^ppt/media/[^/]+\.(?:jpg|jpeg|png)$#i', $entry) === 1) {
                $hasPresentationPhoto = true;
                break;
            }
        }
        $presentationArchive->close();
        unlink($presentationPath);
        $this->assertTrue($hasPresentationPhoto, 'The PPTX should embed the default participant photo.');
    }

    public function test_graduation_validation_page_uses_loader_skip_for_downloads_and_ppt_groups_by_program(): void
    {
        $periodId = $this->createGraduationPeriod();
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-uniq-1', 'nama_program_studi' => 'Informatika', 'id_fakultas' => 'faculty-1'],
            ['id_prodi' => 'prodi-uniq-2', 'nama_program_studi' => 'Sistem Informasi', 'id_fakultas' => 'faculty-1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_wisuda')->insert([
            [
                'id_peserta' => 'wisuda-prodi-1',
                'id_periode' => $periodId,
                'id_prodi' => 'prodi-uniq-1',
                'id_fakultas' => 'faculty-1',
                'id_mahasiswa' => 'student-1',
                'lolos' => '1',
                'pernyataan' => null,
                'judul_skripsi' => 'Pengembangan Sistem Informasi Akademik',
            ],
            [
                'id_peserta' => 'wisuda-prodi-2',
                'id_periode' => $periodId,
                'id_prodi' => 'prodi-uniq-2',
                'id_fakultas' => 'faculty-1',
                'id_mahasiswa' => 'student-2',
                'lolos' => '1',
                'pernyataan' => null,
                'judul_skripsi' => 'Analisis Data Wisuda',
            ],
        ]);
        $this->signIn('validasi');

        $page = $this->get(route('graduation-validations.index', ['periode' => $periodId]))
            ->assertOk();
        $page->assertSee('data-page-loader-skip', false);
        $page->assertSee('Download Excel', false);
        $page->assertSee('Download PDF', false);
        $page->assertSee('Download PPT', false);

        $presentation = $this->get(route('graduation-validations.export.ppt', $periodId))->assertOk();
        $presentationPath = tempnam(sys_get_temp_dir(), 'wisuda-prodi-ppt-');
        file_put_contents($presentationPath, $presentation->getContent());
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($presentationPath));
        $firstProgramSlide = (string) $archive->getFromName('ppt/slides/slide2.xml');
        $firstParticipantSlide = (string) $archive->getFromName('ppt/slides/slide3.xml');
        $allSlidesXml = '';
        for ($i = 1; $i <= $archive->count(); $i++) {
            $path = 'ppt/slides/slide'.$i.'.xml';
            if ($archive->locateName($path) !== false) {
                $allSlidesXml .= (string) $archive->getFromName($path);
            }
        }
        $archive->close();
        unlink($presentationPath);
        $this->assertStringContainsString('Program Studi: Informatika', $allSlidesXml);
        $this->assertStringContainsString('Program Studi: Sistem Informasi', $allSlidesXml);
        $this->assertStringContainsString('Informatika', $firstProgramSlide);
        $this->assertStringContainsString('Mahasiswa Satu', $firstParticipantSlide);
    }

    public function test_grade_validation_lists_legacy_student_statuses_with_nonempty_names_for_allowed_roles(): void
    {
        foreach ([5 => 'C', 6 => 'G', 7 => 'N', 8 => 'Z', 9 => 'M'] as $index => $status) {
            DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
                'id_mahasiswa' => 'student-'.$index,
                'id_prodi' => 'prodi-1',
                'id_fakultas' => 'faculty-1',
                'nim' => 'NPM-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'nama_mahasiswa' => 'Mahasiswa '.$index,
                'id_status_mahasiswa' => $status,
                'nama_status_mahasiswa' => $status,
            ]);
        }
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-empty-grade-name',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
            'nim' => 'NPM-EMPTY-GRADE',
            'nama_mahasiswa' => '  ',
            'id_status_mahasiswa' => 'A',
        ]);

        foreach (['admin', 'validasi', 'lppm'] as $role) {
            $this->signIn($role);
            $this->get(route('grade-validation.index'))
                ->assertOk()
                ->assertSee('Mahasiswa Satu')
                ->assertSee('Mahasiswa 5')
                ->assertSee('Mahasiswa 6')
                ->assertSee('Mahasiswa 7')
                ->assertSee('Mahasiswa 8')
                ->assertSee('Mahasiswa 9')
                ->assertDontSee('NPM-EMPTY-GRADE')
                ->assertSee(route('grade-validation.khs', 'student-1'))
                ->assertSee(route('grade-validation.transcript', 'student-1'));
        }

        foreach (['usertu', 'mahasiswa'] as $role) {
            $this->signIn($role);
            $this->get(route('grade-validation.index'))->assertForbidden();
        }
    }

    public function test_grade_validation_khs_and_transcript_follow_legacy_calculation_rules(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-grade-1', 'kode_mata_kuliah' => 'MK-001', 'nama_mata_kuliah' => 'Mata Kuliah Uji', 'semester' => '1', 'sks_mata_kuliah' => '3'],
            ['id_matkul' => 'course-grade-2', 'kode_mata_kuliah' => 'MK-002', 'nama_mata_kuliah' => 'Mata Kuliah Kedua', 'semester' => '2', 'sks_mata_kuliah' => '2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'grade-krs-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-grade-1', 'tahun' => '20261', 'nilai_huruf' => 'AB', 'nilai_bobot' => '3.50'],
            ['id_krs' => 'grade-krs-2', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-grade-2', 'tahun' => '20261', 'nilai_huruf' => 'B', 'nilai_bobot' => '3.00'],
        ]);
        $this->signIn('lppm');

        $this->get(route('grade-validation.khs', ['student' => 'student-1', 'calendar' => 'calendar-active']))
            ->assertOk()
            ->assertSee('Mata Kuliah Uji')
            ->assertSee('3.50')
            ->assertSee('3.30')
            ->assertSee('24');
        $this->get(route('grade-validation.transcript', 'student-1'))
            ->assertOk()
            ->assertSee('Mata Kuliah Uji')
            ->assertSee('AB')
            ->assertSee('3.30')
            ->assertSee('5 SKS');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_akm')->count());
    }

    public function test_validator_can_set_only_supported_decisions_with_a_required_comment(): void
    {
        $participant = $this->participant('student-1');
        $this->signIn('validasi');
        $this->get(route('kkn.validations.index', ['tahun' => '20261']))
            ->assertOk()->assertSee('data-lucide="clipboard-check"', false);
        $this->actingAs(new GenericUser([
            'id' => 'validator-without-login',
            'role' => 'validasi',
            'name' => 'Pemeriksa KKN',
        ]))->get(route('kkn.validations.index', ['tahun' => '20261']))
            ->assertOk()->assertSee('Pemeriksa KKN');
        $this->signIn('validasi');
        $this->get(route('kkn.validations.show', $participant))
            ->assertOk()->assertSee('Fakultas Uji')->assertSee('Mahasiswa Satu');
        $this->put(route('kkn.validations.update', $participant), [
            'status' => 3, 'keterangan' => 'Keputusan tidak dikenal',
        ])->assertSessionHasErrors('status');
        $this->put(route('kkn.validations.update', $participant), [
            'status' => 2, 'keterangan' => 'Dokumen lengkap',
        ])->assertRedirect(route('kkn.validations.show', $participant));
        $this->assertDatabaseHas('s1m4k_kubermas', [
            'id_peserta' => $participant, 'status' => 2, 'keterangan' => 'Dokumen lengkap', 'petugas' => 'Pengguna Uji',
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', ['modul' => 'KKN'], 'simak_main');
    }

    public function test_manual_plotting_enforces_active_location_capacity(): void
    {
        $location = $this->location('location-one', 'Desa Satu', 1);
        $first = $this->participant('student-1', 2);
        $second = $this->participant('student-2', 2);
        $this->signIn('lppm');
        $this->put(route('kkn.participants.location', $first), [
            'tahun' => '20261', 'id_lokasi' => $location,
        ])->assertRedirect();
        $this->put(route('kkn.participants.location', $second), [
            'tahun' => '20261', 'id_lokasi' => $location,
        ])->assertSessionHasErrors('id_lokasi');
        $this->assertDatabaseHas('s1m4k_kubermas', ['id_peserta' => $second, 'id_lokasi' => null], 'simak_main');
    }

    public function test_manual_location_plotting_returns_success_notification_for_ajax(): void
    {
        $location = $this->location('location-one', 'Desa Satu', 1);
        $participant = $this->participant('student-1', 2);
        $this->signIn('lppm');

        $this->putJson(route('kkn.participants.location', $participant), [
            'tahun' => '20261',
            'id_lokasi' => $location,
        ])->assertOk()->assertJsonPath('message', 'Lokasi peserta berhasil diperbarui.');

        $this->assertDatabaseHas('s1m4k_kubermas', [
            'id_peserta' => $participant,
            'id_lokasi' => $location,
        ], 'simak_main');
    }

    public function test_random_plotting_interleaves_gender_and_respects_all_location_quotas(): void
    {
        $this->location('location-one', 'Desa Satu', 2);
        $this->location('location-two', 'Desa Dua', 2);
        $ids = [
            $this->participant('student-1', 2),
            $this->participant('student-2', 2),
            $this->participant('student-3', 2),
            $this->participant('student-4', 2),
        ];
        $this->signIn('lppm');
        $this->post(route('kkn.participants.random-plot'), ['tahun' => '20261'])
            ->assertRedirect(route('kkn.participants.index', ['tahun' => '20261']));
        $assignments = DB::connection('simak_main')->table('s1m4k_kubermas')
            ->whereIn('id_peserta', $ids)->get(['id_peserta', 'id_lokasi']);
        $this->assertSame(4, $assignments->whereNotNull('id_lokasi')->count());
        foreach (['location-one', 'location-two'] as $locationId) {
            $assignedIds = $assignments->where('id_lokasi', $locationId)->pluck('id_peserta');
            $genders = DB::connection('simak_main')->table('s1m4k_mastermhs')
                ->whereIn('id_mahasiswa', $assignedIds)->pluck('jenis_kelamin');
            $this->assertLessThanOrEqual(1, abs($genders->filter(fn (string $gender): bool => $gender === 'L')->count()
                - $genders->filter(fn (string $gender): bool => $gender === 'P')->count()));
        }
    }

    public function test_student_registration_rechecks_the_legacy_credit_requirement_server_side(): void
    {
        $this->prepareEligibleStudent('student-1');
        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);
        $this->get(route('kkn.student.index'))->assertOk()
            ->assertSee('Telah lulus minimal 70% dari kebulatan studi (100 SKS)');
        $this->post(route('kkn.student.register'))->assertRedirect(route('kkn.student.index'));
        $this->assertDatabaseHas('s1m4k_kubermas', [
            'id_mahasiswa' => 'student-1', 'tahun' => 20261, 'status' => 0,
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', ['modul' => 'KKN', 'level_user' => 'mahasiswa'], 'simak_main');

        $this->post(route('kkn.student.register'))->assertSessionHasErrors('kkn');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_kubermas')->where('id_mahasiswa', 'student-1')->count());
    }

    public function test_student_registration_fails_below_the_legacy_credit_threshold(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            'id_ipmhs' => 'akm-low', 'id_mahasiswa' => 'student-1', 'sks_lulus' => '99',
        ]);
        $this->signIn('mahasiswa', ['legacy_id' => 'student-1']);
        $this->post(route('kkn.student.register'))->assertSessionHasErrors('kkn');
        $this->assertDatabaseCount('s1m4k_kubermas', 0, 'simak_main');
    }

    public function test_accepted_participants_can_be_downloaded_as_pdf_and_excel(): void
    {
        $this->participant('student-1', 2);
        $this->signIn('admin');
        $pdf = $this->get(route('kkn.participants.pdf', ['tahun' => '20261']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $excel = $this->get(route('kkn.participants.excel', ['tahun' => '20261']))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.ms-excel');
        $this->assertStringStartsWith("\xD0\xCF\x11\xE0", $excel->getContent());
    }

    public function test_kkn_validators_can_search_only_students_in_the_selected_cohort(): void
    {
        $this->participant('student-1', 0);
        $this->signIn('validasi');
        $this->get(route('khs.index', ['search' => 'Mahasiswa', 'kkn_year' => '20261']))
            ->assertOk()->assertSee('Mahasiswa Satu')->assertDontSee('Mahasiswa Dua');
        $this->get(route('khs.index', ['search' => 'Mahasiswa']))->assertSessionHasErrors('kkn_year');
        $this->get(route('khs.index', ['search' => 'NPM-002', 'student' => 'student-2', 'kkn_year' => '20261']))
            ->assertNotFound();
        $this->get(route('khs.print', ['student' => 'student-2', 'calendar' => 'calendar-active', 'kkn_year' => '20261']))
            ->assertNotFound();
        $this->get(route('transcripts.index', ['search' => 'Mahasiswa', 'kkn_year' => '20261']))
            ->assertOk()->assertSee('Mahasiswa Satu')->assertDontSee('Mahasiswa Dua');
        $this->get(route('transcripts.index', ['search' => 'Mahasiswa']))->assertSessionHasErrors('kkn_year');
        $this->get(route('transcripts.index', ['search' => 'NPM-002', 'student' => 'student-2', 'kkn_year' => '20261']))
            ->assertNotFound();
        $this->get(route('transcripts.print', ['student' => 'student-2', 'kkn_year' => '20261']))
            ->assertNotFound();
    }

    private function signIn(string $role, array $attributes = []): void
    {
        $this->actingAs(new GenericUser(array_merge([
            'id' => $role.'-kkn-test',
            'legacy_type' => $role === 'mahasiswa' ? 'mahasiswa' : 'admin',
            'legacy_id' => $role === 'mahasiswa' ? 'student-1' : 'operator-1',
            'login' => $role.'-kkn-test',
            'role' => $role,
            'name' => 'Pengguna Uji',
            'email' => 'kkn@example.test',
            'id_prodi' => 'prodi-1',
            'id_fakultas' => 'faculty-1',
        ], $attributes)));
    }

    private function createGraduationPeriod(): string
    {
        $id = 'period-wisuda-active';
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => $id,
            'nama_periode' => 'Wisuda Oktober 2026',
            'tahun' => '20262',
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(10)->toDateString(),
            'kuota_wisuda' => 100,
            'jenis' => 'wisuda',
            'aktif' => 'Y',
        ]);

        return $id;
    }

    private function participant(string $studentId, int $status = 0): string
    {
        $id = 'participant-'.$studentId;
        DB::connection('simak_main')->table('s1m4k_kubermas')->insert([
            'id_peserta' => $id,
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'prodi-1',
            'id_mahasiswa' => $studentId,
            'tahun' => 20261,
            'status' => $status,
            'tgl_daftar' => today()->toDateString(),
            'id_lokasi' => null,
            'keterangan' => null,
            'petugas' => null,
            'petugas_usul' => 'Pengusul Uji',
        ]);

        return $id;
    }

    private function location(string $id, string $name, int $quota): string
    {
        DB::connection('simak_main')->table('s1m4k_lokasi_kubermas')->insert([
            'id_lokasi' => $id, 'nama_lokasi' => $name, 'kuota' => $quota, 'aktif' => 'Y',
        ]);

        return $id;
    }

    private function prepareEligibleStudent(string $studentId): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            'id_ipmhs' => 'akm-'.$studentId, 'id_mahasiswa' => $studentId, 'id_prodi' => 'prodi-1',
            'id_kalender' => 'calendar-active', 'sks_lulus' => '100',
        ]);
    }

    private function seedRecords(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            'id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Uji',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'prodi-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Prodi Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'prodi-2', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Prodi Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'faculty-1', 'nim' => 'NPM-001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'jenis_kelamin' => 'L', 'email' => 'one@example.test'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'prodi-2', 'id_fakultas' => 'faculty-1', 'nim' => 'NPM-002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'jenis_kelamin' => 'P', 'email' => 'two@example.test'],
            ['id_mahasiswa' => 'student-3', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'faculty-1', 'nim' => 'NPM-003', 'nama_mahasiswa' => 'Mahasiswa Tiga', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'jenis_kelamin' => 'L', 'email' => null],
            ['id_mahasiswa' => 'student-4', 'id_prodi' => 'prodi-1', 'id_fakultas' => 'faculty-1', 'nim' => 'NPM-004', 'nama_mahasiswa' => 'Mahasiswa Empat', 'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'Aktif', 'jenis_kelamin' => 'P', 'email' => null],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-active', 'id_prodi' => 'prodi-1', 'tahun' => '20261',
            'namatahun' => 'Tahun Akademik 2026/2027 Ganjil', 'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_periode')->insert([
            'id_periode' => 'period-active', 'nama_periode' => 'Tahun Akademik 2026/2027 Ganjil',
            'tahun' => '20261', 'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_akhir' => today()->addDays(30)->toDateString(), 'jenis' => 'kubermas', 'aktif' => 'Y',
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
            $table->string('dekan')->nullable();
            $table->string('pd1')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('ketua_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nim')->nullable();
            $table->string('nama_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('email')->nullable();
            $table->string('handphone')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('jalan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('id_periode_masuk')->nullable();
            $table->string('id_agama')->nullable();
            $table->string('id_wilayah')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->string('dosen_pa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_periode', function (Blueprint $table): void {
            $table->string('id_periode')->primary();
            $table->string('nama_periode');
            $table->string('tahun', 5)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->integer('kuota_wisuda')->nullable();
            $table->string('jenis');
            $table->string('aktif', 1)->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi')->nullable();
            $table->string('tahun', 5)->nullable();
            $table->string('namatahun')->nullable();
            $table->string('status', 1)->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_lokasi_kubermas', function (Blueprint $table): void {
            $table->string('id_lokasi')->primary();
            $table->string('nama_lokasi', 200);
            $table->integer('kuota');
            $table->string('aktif', 1);
        });
        Schema::connection('simak_main')->create('s1m4k_kubermas', function (Blueprint $table): void {
            $table->string('id_peserta')->primary();
            $table->string('id_prodi');
            $table->string('id_fakultas');
            $table->string('id_mahasiswa');
            $table->string('id_lokasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->integer('tahun');
            $table->integer('status')->default(0);
            $table->date('tgl_daftar');
            $table->string('petugas')->nullable();
            $table->string('petugas_usul')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_wisuda', function (Blueprint $table): void {
            $table->string('id_peserta')->primary();
            $table->string('id_periode')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_mahasiswa')->nullable();
            $table->string('foto')->nullable();
            $table->string('bebas_spp')->nullable();
            $table->string('bebas_perpus')->nullable();
            $table->text('judul_skripsi')->nullable();
            $table->string('skripsi')->nullable();
            $table->integer('status')->default(0);
            $table->date('tgl_daftar')->nullable();
            $table->string('petugas')->nullable();
            $table->string('lolos', 1)->nullable();
            $table->text('komentar')->nullable();
            $table->integer('pernyataan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('ipk')->nullable();
            $table->string('sks_lulus')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->date('tgl_registasi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah')->nullable();
            $table->string('nama_mata_kuliah')->nullable();
            $table->string('semester')->nullable();
            $table->string('sks_mata_kuliah')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_mahasiswa')->nullable();
            $table->string('id_kalender')->nullable();
            $table->string('id_matkul')->nullable();
            $table->string('id_jadwal')->nullable();
            $table->string('tahun')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_bobot')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kodereferensi', function (Blueprint $table): void {
            $table->string('id_referensi')->primary();
            $table->string('kode_referensi')->nullable();
            $table->text('ket_referensi')->nullable();
            $table->string('ket_referensi2')->nullable();
            $table->string('keterangan')->nullable();
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
}
