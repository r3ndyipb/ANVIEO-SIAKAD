<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MbkmTransferTest extends TestCase
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
        $this->seedData();
    }

    public function test_admin_can_add_both_mbkm_conversion_types_and_audit_them(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));

        $this->get(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Pertukaran Pelajar')
            ->assertSee('Non-Pertukaran Pelajar');

        $this->post(route('mbkm-transfers.store', 'student-1'), [
            'mode' => 'exchange',
            'id_perguruan_tinggi_asal' => 'origin-pt',
            'kode_mk_asal' => 'ASAL-01',
            'nama_mk_asal' => 'Mata Kuliah Asal',
            'sks_asal' => '3',
            'nilai_huruf_asal' => 'B+',
            'id_matkul' => 'course-1',
            'nilai_angka' => '82',
        ])->assertRedirect(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertSessionHas('status');

        $this->post(route('mbkm-transfers.store', 'student-1'), [
            'mode' => 'non_exchange',
            'id_kegiatan' => 'activity-1',
            'id_matkul' => 'course-2',
            'nilai_angka' => '75',
        ])->assertRedirect(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertSessionHas('status');

        $exchange = DB::connection('simak_main')->table('s1m4k_krs')->where('id_matkul', 'course-1')->first();
        $nonExchange = DB::connection('simak_main')->table('s1m4k_krs')->where('id_matkul', 'course-2')->first();
        $this->assertSame('origin-pt', $exchange->id_pt_asal);
        $this->assertSame('ASAL-01', $exchange->kode_mk_asal);
        $this->assertSame('A', $exchange->nilai_huruf);
        $this->assertSame('4.00', $exchange->nilai_bobot);
        $this->assertSame('activity-1', $nonExchange->id_kegiatan);
        $this->assertSame('B', $nonExchange->nilai_huruf);
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Transfer Nilai MBKM')->count());
    }

    public function test_userprodi_can_register_mbkm_status_from_the_transfer_page(): void
    {
        DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->update(['status_mahasiswa' => 'A']);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));
        $this->get(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Belum ditetapkan')
            ->assertSee('Daftarkan Status MBKM');

        $this->post(route('mbkm-transfers.register', 'student-1'))
            ->assertRedirect(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertSessionHas('status');

        $akm = DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->where('id_kalender', 'calendar-current')->first();
        $this->assertSame('M', $akm->status_mahasiswa);
        $this->assertSame('Kampus Merdeka', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('nama_status_mahasiswa'));
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'Transfer Nilai MBKM')->count());
    }

    public function test_mbkm_status_can_be_removed_only_before_any_mbkm_course_is_entered(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $this->get(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertOk()->assertSee('Hapus Status MBKM');

        $this->delete(route('mbkm-transfers.unregister', 'student-1'))
            ->assertRedirect(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertSessionHas('status');
        $this->assertSame('A', DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->value('status_mahasiswa'));
        $this->assertSame('Aktif', DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->value('nama_status_mahasiswa'));

        DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->update(['status_mahasiswa' => 'M']);
        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->update([
                'id_status_mahasiswa' => 'M', 'nama_status_mahasiswa' => 'Kampus Merdeka',
            ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'mbkm-krs', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'student-1', 'id_matkul' => 'course-1', 'krs_mbkm' => 'Y',
        ]);

        $this->get(route('mbkm-transfers.index', ['search' => '2025001']))
            ->assertOk()->assertDontSee('Hapus Status MBKM');
        $this->delete(route('mbkm-transfers.unregister', 'student-1'))->assertStatus(422);
        $this->assertSame('M', DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', 'student-1')->value('status_mahasiswa'));
    }

    public function test_mbkm_transfer_requires_registration_and_active_mbkm_status_and_limits_total_credits(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'regular-krs',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'student-1',
            'id_matkul' => 'regular-course',
            'krs_mbkm' => 'N',
            'krs_reguler' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            'id_matkul' => 'regular-course',
            'id_prodi' => 'program-1',
            'status' => 'A',
            'nama_mata_kuliah' => 'Reguler 22 SKS',
            'kode_mata_kuliah' => 'REG18',
            'sks_mata_kuliah' => '22',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', ['id_prodi' => 'program-1']));

        $this->post(route('mbkm-transfers.store', 'student-1'), [
            'mode' => 'exchange',
            'id_perguruan_tinggi_asal' => 'origin-pt',
            'kode_mk_asal' => 'ASAL-01',
            'nama_mk_asal' => 'Mata Kuliah Asal',
            'sks_asal' => '3',
            'nilai_huruf_asal' => 'B',
            'id_matkul' => 'course-1',
            'nilai_angka' => '80',
        ])->assertSessionHasErrors('id_matkul');

        DB::connection('simak_main')->table('s1m4k_mastermhs')
            ->where('id_mahasiswa', 'student-1')->update(['id_prodi' => 'program-2']);
        $this->post(route('mbkm-transfers.store', 'student-1'), [
            'mode' => 'exchange',
            'id_perguruan_tinggi_asal' => 'origin-pt',
            'kode_mk_asal' => 'ASAL-01',
            'nama_mk_asal' => 'Mata Kuliah Asal',
            'sks_asal' => '3',
            'nilai_huruf_asal' => 'B',
            'id_matkul' => 'course-1',
            'nilai_angka' => '80',
        ])->assertNotFound();
    }

    public function test_mbkm_transfer_rejects_duplicate_courses_and_invalid_grade_ranges(): void
    {
        $this->actingAs($this->user('admin', 'admin-1'));
        $payload = [
            'mode' => 'exchange',
            'id_perguruan_tinggi_asal' => 'origin-pt',
            'kode_mk_asal' => 'ASAL-01',
            'nama_mk_asal' => 'Mata Kuliah Asal',
            'sks_asal' => '3',
            'nilai_huruf_asal' => 'B',
            'id_matkul' => 'course-1',
            'nilai_angka' => '101',
        ];
        $this->post(route('mbkm-transfers.store', 'student-1'), $payload)
            ->assertSessionHasErrors('nilai_angka');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')->count());

        $payload['nilai_angka'] = '80';
        $this->post(route('mbkm-transfers.store', 'student-1'), $payload)->assertRedirect();
        $this->post(route('mbkm-transfers.store', 'student-1'), $payload)
            ->assertSessionHasErrors('id_matkul');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_krs')->count());
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('id_prodi');
            $table->string('id_fakultas')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_registrasi_mahasiswa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun');
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_prodi')->nullable();
            $table->string('id_kalender');
            $table->string('tahun')->nullable();
            $table->string('status_mahasiswa');
            $table->string('ips')->nullable();
            $table->string('ipk')->nullable();
            $table->string('sks_semester')->nullable();
            $table->string('sks_program')->nullable();
            $table->string('sks_lulus')->nullable();
            $table->integer('update_akm')->default(0);
            $table->integer('simpan')->nullable();
            $table->integer('flag_feeder')->default(0);
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('id_prodi');
            $table->string('status');
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('semester')->nullable();
            $table->string('sks_mata_kuliah');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_mahasiswa');
            $table->string('id_matkul');
            $table->string('tgl_update')->nullable();
            $table->string('krs_reguler')->nullable();
            $table->string('krs_mbkm')->nullable();
            $table->string('id_pt_asal')->nullable();
            $table->string('kode_mk_asal')->nullable();
            $table->string('nama_mk_asal')->nullable();
            $table->string('sks_asal')->nullable();
            $table->string('nilai_huruf_asal')->nullable();
            $table->string('nilai_angka')->nullable();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_bobot')->nullable();
            $table->string('tahun')->nullable();
            $table->string('user')->nullable();
            $table->string('id_kegiatan')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterbobotnilai', function (Blueprint $table): void {
            $table->string('id_bobot_nilai')->primary();
            $table->string('nilai_huruf');
            $table->string('nilai_indeks');
            $table->string('bobot_minimum');
            $table->string('bobot_maksimum');
        });
        Schema::connection('simak_main')->create('s1m4k_masterallpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('kode_perguruan_tinggi')->nullable();
            $table->string('nama_perguruan_tinggi');
        });
        Schema::connection('simak_main')->create('s1m4k_kegiatanmhs', function (Blueprint $table): void {
            $table->string('id_kegiatan')->primary();
            $table->string('id_kalender');
            $table->string('id_mahasiswa')->nullable();
            $table->string('judul');
        });
        Schema::connection('simak_main')->create('s1m4k_peran_kegiatan', function (Blueprint $table): void {
            $table->string('id_peran')->primary();
            $table->string('id_aktivitas');
            $table->string('id_registrasi_mahasiswa');
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log', 36)->primary();
            $table->string('id_user', 100)->nullable();
            $table->string('level_user', 50)->nullable();
            $table->string('nama_user', 255);
            $table->string('modul', 100);
            $table->longText('aktifitas');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'nim' => '2025001', 'nama_mahasiswa' => 'Mahasiswa Satu', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'id_perguruan_tinggi' => 'pt-1', 'id_registrasi_mahasiswa' => 'registration-mahasiswa-1'],
            ['id_mahasiswa' => 'student-2', 'nim' => '2025002', 'nama_mahasiswa' => 'Mahasiswa Dua', 'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'id_perguruan_tinggi' => 'pt-1', 'id_registrasi_mahasiswa' => 'registration-mahasiswa-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-current', 'id_prodi' => 'program-1', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
            ['id_kalender' => 'calendar-other', 'id_prodi' => 'program-2', 'tahun' => '20261', 'namatahun' => '2026 Ganjil', 'status' => 'Y'],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            ['id_registrasi' => 'registration-1', 'id_mahasiswa' => 'student-1', 'id_kalender' => 'calendar-current'],
            ['id_registrasi' => 'registration-2', 'id_mahasiswa' => 'student-2', 'id_kalender' => 'calendar-other'],
        ]);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            ['id_ipmhs' => 'akm-1', 'id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-current', 'tahun' => '20261', 'status_mahasiswa' => 'M', 'ips' => '3.00', 'ipk' => '3.20'],
            ['id_ipmhs' => 'akm-2', 'id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-other', 'tahun' => '20261', 'status_mahasiswa' => 'M', 'ips' => '3.00', 'ipk' => '3.20'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
            ['id_matkul' => 'course-1', 'id_prodi' => 'program-1', 'status' => 'A', 'kode_mata_kuliah' => 'MK001', 'nama_mata_kuliah' => 'Mata Kuliah Satu', 'semester' => '1', 'sks_mata_kuliah' => '3'],
            ['id_matkul' => 'course-2', 'id_prodi' => 'program-1', 'status' => 'A', 'kode_mata_kuliah' => 'MK002', 'nama_mata_kuliah' => 'Mata Kuliah Dua', 'semester' => '2', 'sks_mata_kuliah' => '3'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterbobotnilai')->insert([
            ['id_bobot_nilai' => 'grade-e', 'nilai_huruf' => 'E', 'nilai_indeks' => '0.00', 'bobot_minimum' => '0', 'bobot_maksimum' => '39'],
            ['id_bobot_nilai' => 'grade-b', 'nilai_huruf' => 'B', 'nilai_indeks' => '3.00', 'bobot_minimum' => '60', 'bobot_maksimum' => '79'],
            ['id_bobot_nilai' => 'grade-a', 'nilai_huruf' => 'A', 'nilai_indeks' => '4.00', 'bobot_minimum' => '80', 'bobot_maksimum' => '100'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterallpt')->insert([
            ['id_perguruan_tinggi' => 'origin-pt', 'kode_perguruan_tinggi' => 'PT001', 'nama_perguruan_tinggi' => 'Perguruan Tinggi Asal'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kegiatanmhs')->insert([
            ['id_kegiatan' => 'activity-1', 'id_kalender' => 'calendar-current', 'id_mahasiswa' => 'student-1', 'judul' => 'Kegiatan MBKM'],
        ]);
        DB::connection('simak_main')->table('s1m4k_peran_kegiatan')->insert([
            ['id_peran' => 'role-1', 'id_aktivitas' => 'activity-1', 'id_registrasi_mahasiswa' => 'registration-mahasiswa-1'],
        ]);
    }

    private function user(string $role, string $id, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id,
            'legacy_id' => $id,
            'role' => $role,
            'login' => 'NIDN-1',
            'name' => 'Pengguna Uji',
            ...$extra,
        ]);
    }
}
