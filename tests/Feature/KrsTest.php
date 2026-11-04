<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KrsTest extends TestCase
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
        $this->seedStudentData();
    }

    public function test_student_can_program_krs_within_registration_window_and_audit_identity(): void
    {
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 'operator-1',
            'level' => 'userprodi',
            'id_prodi' => 'program-1',
            'blokir' => 'N',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('24 SKS')
            ->assertSee('Mata Kuliah Satu');

        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2'],
        ])->assertRedirect(route('krs.index'))
            ->assertSessionHas('status');

        $this->assertSame(2, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_kalender', 'calendar-current')->count());
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->count());
        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $details = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Mahasiswa Satu', $details['identitas_data']['nama']);
        $this->assertSame('2025001', $details['identitas_data']['nomor_identitas']);
        $this->assertCount(2, $details['perubahan']['data_baru']);
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'krs-submitted',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'userprodi',
            'recipient_id' => 'operator-1',
            'category' => 'krs-submitted',
        ], 'simak_main');
    }

    public function test_student_cannot_exceed_credit_limit_or_program_without_reenrollment(): void
    {
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));
        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2', 'schedule-3', 'schedule-4', 'schedule-5'],
        ])->assertSessionHasErrors('schedule_ids');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')->count());

        DB::connection('simak_main')->table('s1m4k_registrasiulang')->delete();
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-1']])
            ->assertSessionHasErrors('registration');
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-1']])
            ->assertSessionHasErrors('registration');
        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-1']])
            ->assertSessionHasErrors('registration');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')->count());
    }

    public function test_prodi_uses_policy_credit_limit_instead_of_student_ips(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-low-ips',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-previous',
            'tahun' => '20251',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'nilai_bobot' => '1.00',
            'krs_reguler' => 'Y',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('24 SKS')
            ->assertSee('IPS Terakhir');
        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2', 'schedule-3', 'schedule-4'],
        ])->assertRedirect()->assertSessionHas('status');

        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2', 'schedule-3', 'schedule-4', 'schedule-5'],
        ])->assertSessionHasErrors('schedule_ids');
        $this->assertSame(4, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')
            ->where('id_kalender', 'calendar-current')
            ->count());
    }

    public function test_program_roles_without_program_assignment_cannot_search_or_open_student_krs(): void
    {
        foreach (['userprodi', 'usertu'] as $role) {
            $this->actingAs($this->user($role, $role.'-unscoped', $role.'-login', ['id_prodi' => '']));
            $this->get(route('krs.index', ['search' => 'Mahasiswa']))->assertForbidden();
            $this->get(route('krs.edit', 'student-1'))->assertForbidden();
            $this->get(route('krs.print', 'student-1'))->assertForbidden();
        }
    }

    public function test_manager_npm_search_opens_krs_and_flashes_when_the_student_is_missing_or_out_of_scope(): void
    {
        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));
        $this->get(route('krs.index', ['search' => '2025001']))
            ->assertRedirect(route('krs.edit', 'student-1'));
        $this->get(route('krs.index', ['search' => '9999999']))
            ->assertRedirect(route('krs.index'))
            ->assertSessionHasErrors('search');

        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->get(route('krs.index', ['search' => '2025999']))
            ->assertRedirect(route('krs.index'))
            ->assertSessionHasErrors('search');
    }

    public function test_professional_program_staff_sees_fifty_credit_limit(): void
    {
        config(['simak.professional_program_id' => 'program-1']);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('50 SKS');
    }

    public function test_student_can_program_a_legacy_course_package_and_audit_it(): void
    {
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));
        $this->get(route('krs.index'))
            ->assertOk()
            ->assertSee('Program KRS Paket');

        $this->post(route('krs.package', 'student-1'), ['kelas' => 'PAKET-A'])
            ->assertRedirect(route('krs.index'))
            ->assertSessionHas('status');

        $this->assertSame(2, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')
            ->where('id_kalender', 'calendar-current')
            ->count());
        $activity = DB::connection('simak_main')->table('log_aktivitas')->first();
        $details = json_decode($activity->aktifitas, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Program KRS Paket', $details['aksi']);
        $this->assertSame('PAKET-A', $details['perubahan']['kelas_paket']);
        $this->assertSame('Mahasiswa Satu', $details['identitas_data']['nama']);
    }

    public function test_student_credit_limit_uses_calculated_regular_course_ips(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-regular-genap',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-break',
            'tahun' => '20252',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'status_krs' => 'B',
            'nilai_bobot' => '2.50',
            'krs_reguler' => 'Y',
            'krs_mbkm' => 'N',
            'isi_edom' => 'N',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-sp',
            'id_prodi' => 'program-1',
            'tahun' => '20253',
            'namatahun' => 'SP 2025 / 2026',
            'status' => 'N',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-sp-high-ips',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-sp',
            'tahun' => '20253',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'status_krs' => 'B',
            'nilai_bobot' => '3.00',
            'krs_reguler' => 'Y',
            'krs_mbkm' => 'N',
            'isi_edom' => 'N',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2', 'schedule-3', 'schedule-4'],
        ])->assertSessionHasErrors('schedule_ids');

        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('IPS Terakhir')
            ->assertSee('2.50')
            ->assertSee('2025 Genap');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_krs', 'krs-regular-genap')->count());
    }

    public function test_student_without_any_krs_history_gets_24_credits_but_last_active_term_ips_is_used_after_a_break(): void
    {
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));
        $this->get(route('krs.index'))->assertOk()->assertSee('24 SKS');

        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-last-active-term',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-previous',
            'tahun' => '20251',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'nilai_bobot' => '1.50',
            'krs_reguler' => 'Y',
        ]);

        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-2', 'schedule-3'],
        ])->assertSessionHasErrors('schedule_ids');
    }

    public function test_student_can_view_and_print_an_approved_krs_from_a_previous_calendar(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-previous-term',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-previous',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'tahun' => '20251',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-previous-term',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-previous',
            'yang_mengesahkan' => 'Dosen PA',
            'tgl_pengesahan' => '2025-01-10',
            'nama_kpro' => 'Ketua Prodi',
            'tgl_pengesahan_kpro' => '2025-01-11',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('krs.index', ['calendar' => 'calendar-previous']))
            ->assertOk()
            ->assertSee('Pilih tahun akademik')
            ->assertSee('value="calendar-previous" selected', false)
            ->assertSee('Mata Kuliah Satu')
            ->assertSee(route('krs.print', ['student' => 'student-1', 'calendar' => 'calendar-previous']), false);
        $this->get(route('krs.print', ['student' => 'student-1', 'calendar' => 'calendar-previous']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('krs.index', ['calendar' => 'calendar-break']))->assertNotFound();

        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->get(route('krs.edit', ['student' => 'student-1', 'calendar' => 'calendar-previous']))
            ->assertOk()
            ->assertSee('value="calendar-previous" selected', false)
            ->assertDontSee('id="krs-program-form"', false)
            ->assertDontSee('Sahkan Dosen PA')
            ->assertSee(route('krs.print', ['student' => 'student-1', 'calendar' => 'calendar-previous']), false);
    }

    public function test_student_cannot_select_two_schedules_for_the_same_course(): void
    {
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            'id_jadwal' => 'schedule-1-other-class',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_matkul' => 'course-1',
            'nama_kelas' => 'KELAS-LAIN',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('krs.index'))->assertOk()->assertSee('data-course-id="course-1"', false);
        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1', 'schedule-1-other-class'],
        ])->assertSessionHasErrors('schedule_ids');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')
            ->where('id_kalender', 'calendar-current')
            ->count());
    }

    public function test_full_class_is_disabled_and_cannot_be_programmed(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterruang')
            ->where('id_ruang', 'room-1')
            ->update(['kapasitas' => '1']);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-occupying-full-class',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'other-student',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'tahun' => '20261',
        ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertSee('Terisi Penuh (1/1)')
            ->assertSee('Kelas PAKET-A (terisi penuh)')
            ->assertSee('disabled', false);
        $this->post(route('krs.save', 'student-1'), [
            'schedule_ids' => ['schedule-1'],
        ])->assertSessionHasErrors('schedule_ids');
        $this->post(route('krs.package', 'student-1'), ['kelas' => 'PAKET-A'])
            ->assertSessionHasErrors('kelas');
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_jadwal', 'schedule-1')->count());
    }

    public function test_prodi_can_take_over_pa_approval_from_student_krs_form(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-pending-pa',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'tahun' => '20261',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('Untuk ambil alih pengesahan PA, klik tombol Sahkan PA.')
            ->assertSee('Sahkan PA');
        $this->post(route('krs.approve-pa', 'student-1'))->assertRedirect();
        $this->assertSame('Operator Prodi', DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->value('yang_mengesahkan'));
    }

    public function test_program_staff_can_approve_pa_then_program_and_each_action_is_audited(): void
    {
        $this->insertKrsAndPaApproval();
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('Mahasiswa Satu')
            ->assertSee('Cari mahasiswa');
        $this->get(route('krs.index', ['search' => '2025001']))
            ->assertRedirect(route('krs.edit', 'student-1'));
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('Sahkan Prodi')
            ->assertSee('Mahasiswa Satu')
            ->assertDontSee('Penasehat Akademik')
            ->assertSee('Sahkan Semua KRS Prodi')
            ->assertSee('Mohon Digunaakn Secara Bijak')
            ->assertSeeInOrder(['Sahkan Prodi', 'Mata Kuliah Terprogram']);
        $this->get(route('krs.index', ['search' => '2025001']))
            ->assertRedirect(route('krs.edit', 'student-1'));
        $this->post(route('krs.approve-program', 'student-1'))->assertRedirect();
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'krs-approved-program',
        ], 'simak_main');
        $approval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->first();
        $this->assertSame('Operator Prodi', $approval->nama_kpro);
        $this->assertSame(1, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'KRS Mahasiswa')->count());
        $this->assertSame('Mahasiswa Satu', json_decode(
            DB::connection('simak_main')->table('log_aktivitas')->value('aktifitas'),
            true,
            flags: JSON_THROW_ON_ERROR
        )['identitas_data']['nama']);

        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertDontSee('Reset Pengesahan Dosen PA')
            ->assertSee('Cetak KRS')
            ->assertSee('href="'.route('krs.print', ['student' => 'student-1', 'calendar' => 'calendar-current']).'"', false);
        $this->post(route('krs.reset-approval', 'student-1'))->assertRedirect();
        $resetApproval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->first();
        $this->assertNull($resetApproval->yang_mengesahkan);
        $this->assertNull($resetApproval->tgl_pengesahan);
        $this->assertSame('Operator Prodi', $resetApproval->nama_kpro);
        $this->assertSame(1, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('id_kalender', 'calendar-current')->count());
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'KRS Mahasiswa')->count());
    }

    public function test_program_tu_is_read_only_and_cannot_approve_or_edit_krs(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update([
                'nama_kpro' => 'Ketua Prodi',
                'tgl_pengesahan_kpro' => now()->toDateString(),
            ]);
        $this->actingAs($this->user('usertu', 'operator-tu', 'tu-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('Mahasiswa Satu');
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('Cetak KRS')
            ->assertDontSee('id="krs-program-form"', false)
            ->assertDontSee('Sahkan PA')
            ->assertDontSee('Sahkan Prodi')
            ->assertDontSee('Reset Pengesahan Dosen PA')
            ->assertDontSee('Sahkan Semua KRS Prodi');

        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])->assertForbidden();
        $this->post(route('krs.approve-pa', 'student-1'))->assertForbidden();
        $this->post(route('krs.approve-program', 'student-1'))->assertForbidden();
        $this->post(route('krs.reset-approval', 'student-1'))->assertForbidden();
        $this->post(route('krs.approve-program-all'), ['id_kalender' => 'calendar-current'])->assertForbidden();
        $this->get(route('krs.print', 'student-1'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_dosen_cannot_edit_after_program_approval_and_student_cannot_edit_after_pa_approval(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update([
                'nama_kpro' => 'Ketua Prodi',
                'tgl_pengesahan_kpro' => now()->toDateString(),
            ]);

        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertDontSee('id="krs-program-form"', false);
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertForbidden();

        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));
        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('id="krs-program-form"', false);
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertForbidden();
        $this->assertSame('schedule-1', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_jadwal'));
    }

    public function test_krs_edit_locks_are_enforced_by_the_correct_approval_stage(): void
    {
        $this->insertKrsAndPaApproval();
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertForbidden();

        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('id="krs-program-form"', false);

        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update(['nama_kpro' => 'Ketua Prodi']);
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertDontSee('id="krs-program-form"', false);
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertForbidden();
    }

    public function test_admin_can_only_view_and_print_krs_without_edit_or_approval_actions(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => '', 'nim' => '', 'nama_mahasiswa' => '', 'id_prodi' => 'program-1'],
            ['id_mahasiswa' => '   ', 'nim' => '', 'nama_mahasiswa' => 'Data tanpa ID', 'id_prodi' => 'program-1'],
        ]);
        $this->insertKrsAndPaApproval();
        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update(['nama_kpro' => 'Ketua Prodi']);
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertDontSee('id="krs-program-form"', false)
            ->assertDontSee('Sahkan PA')
            ->assertDontSee('Sahkan Prodi')
            ->assertDontSee('Reset Pengesahan Dosen PA')
            ->assertSee('Cetak KRS');
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertForbidden();
        $this->post(route('krs.approve-pa', 'student-1'))->assertForbidden();
        $this->get(route('krs.print', 'student-1'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_university_and_faculty_leaders_can_access_krs_read_only_with_faculty_scope(): void
    {
        $this->actingAs($this->user('pimuniv', 'leader-university', 'leader-university-login'));
        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('Mahasiswa Satu')
            ->assertSee('KRS Mahasiswa')
            ->assertSee(route('krs.index'))
            ->assertSee('Monitoring KRS')
            ->assertSee(route('monitoring-krs.index'));
        $this->get(route('krs.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Lihat KRS Mahasiswa Satu');
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertDontSee('id="krs-program-form"', false)
            ->assertDontSee('Sahkan PA')
            ->assertDontSee('Sahkan Prodi');
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-1']])
            ->assertForbidden();
        $this->post(route('krs.approve-pa', 'student-1'))->assertForbidden();

        $this->actingAs($this->user('pimfak', 'leader-faculty', 'leader-faculty-login', ['id_fakultas' => 'faculty-1']));
        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('Mahasiswa Satu')
            ->assertSee('KRS Mahasiswa')
            ->assertSee(route('krs.index'))
            ->assertSee('Monitoring KRS')
            ->assertSee(route('monitoring-krs.index'));
        $this->get(route('krs.index', ['search' => '2025001']))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Lihat KRS Mahasiswa Satu');
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertDontSee('id="krs-program-form"', false);
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-1']])
            ->assertForbidden();

        $this->actingAs($this->user('pimfak', 'leader-other-faculty', 'leader-other-faculty-login', ['id_fakultas' => 'faculty-2']));
        $this->get(route('krs.edit', 'student-1'))->assertNotFound();
    }

    public function test_validation_graduation_and_reenrollment_roles_can_only_view_krs(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update([
                'nama_kpro' => 'Ketua Prodi',
                'tgl_pengesahan_kpro' => now()->toDateString(),
            ]);

        foreach (['validasi', 'wisuda', 'regulang'] as $role) {
            $this->actingAs($this->user($role, $role.'-user', $role.'-login'));

            $this->get(route('krs.index'))
                ->assertOk()
                ->assertDontSee('Mahasiswa Satu')
                ->assertSee('Cari mahasiswa');
            $this->get(route('krs.index', ['search' => '2025001']))
                ->assertRedirect(route('krs.edit', 'student-1'));
            $this->get(route('krs.edit', 'student-1'))
                ->assertOk()
                ->assertSee('Mahasiswa Satu')
                ->assertSee('Akses ini hanya dapat melihat dan mencetak KRS')
                ->assertSee('Mata Kuliah Satu')
                ->assertSee('Cetak KRS')
                ->assertDontSee('id="krs-program-form"', false)
                ->assertDontSee('Sahkan PA')
                ->assertDontSee('Sahkan Prodi')
                ->assertDontSee('Reset Pengesahan Dosen PA');
            $this->get(route('krs.print', 'student-1'))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])->assertForbidden();
            $this->post(route('krs.package', 'student-1'), ['kelas' => 'PAKET-A'])->assertForbidden();
            $this->post(route('krs.approve-pa', 'student-1'))->assertForbidden();
            $this->post(route('krs.approve-program', 'student-1'))->assertForbidden();
            $this->post(route('krs.reset-approval', 'student-1'))->assertForbidden();
            $this->post(route('krs.approve-program-all'), ['id_kalender' => 'calendar-current'])->assertForbidden();
        }

        $this->assertSame('schedule-1', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')
            ->where('id_kalender', 'calendar-current')
            ->value('id_jadwal'));
        $this->assertSame('Ketua Prodi', DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->where('id_kalender', 'calendar-current')
            ->value('nama_kpro'));
    }

    public function test_monitoring_krs_uses_active_calendar_statuses_and_program_scope(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            [
                'id_mahasiswa' => 'student-2',
                'nim' => '2025002',
                'nama_mahasiswa' => 'Mahasiswa Dua',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'C',
                'nama_status_mahasiswa' => 'Cuti',
            ],
            [
                'id_mahasiswa' => 'student-3',
                'nim' => '2025003',
                'nama_mahasiswa' => 'Mahasiswa Tiga',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'M',
                'nama_status_mahasiswa' => 'Kampus Merdeka',
            ],
            [
                'id_mahasiswa' => 'student-inactive',
                'nim' => '2025999',
                'nama_mahasiswa' => 'Mahasiswa Nonaktif',
                'id_prodi' => 'program-1',
                'id_status_mahasiswa' => 'D',
                'nama_status_mahasiswa' => 'Nonaktif',
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-monitoring-2',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'tahun' => '20261',
            'id_mahasiswa' => 'student-2',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-monitoring-2',
            'id_mahasiswa' => 'student-2',
            'id_kalender' => 'calendar-current',
            'yang_mengesahkan' => 'Dosen PA',
            'nama_kpro' => 'Ketua Prodi',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-monitoring-3',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'tahun' => '20261',
            'id_mahasiswa' => 'student-3',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-monitoring-history',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-previous',
            'tahun' => '20251',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-monitoring-history',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-previous',
            'yang_mengesahkan' => 'Dosen PA',
            'nama_kpro' => 'Ketua Prodi',
        ]);

        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));
        $this->get(route('monitoring-krs.index'))
            ->assertOk()
            ->assertSee('Pilih program studi');

        $this->get(route('monitoring-krs.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertSee('Tahun akademik')
            ->assertSee('2025 Ganjil')
            ->assertSee('SKS Diprogram')
            ->assertSee('data-datatable="monitoring-krs"', false)
            ->assertSee('id="monitoring-calendar"', false);
        $dataResponse = $this->getJson(route('monitoring-krs.data', ['program' => 'program-1', 'draw' => 1, 'start' => 0, 'length' => 10]));
        $dataResponse->assertOk()
            ->assertJsonPath('draw', 1)
            ->assertJsonPath('recordsTotal', 3)
            ->assertJsonPath('recordsFiltered', 3);
        $rows = collect($dataResponse->json('data'));
        $this->assertEqualsCanonicalizing(
            ['Mahasiswa Satu', 'Mahasiswa Dua', 'Mahasiswa Tiga'],
            $rows->pluck('nama_mahasiswa')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['Belum Isi KRS', 'KRS Telah Disahkan Prodi', 'KRS Belum Disahkan Prodi'],
            $rows->pluck('krs_status')->all()
        );
        $this->assertSame(6.0, (float) $rows->firstWhere('nama_mahasiswa', 'Mahasiswa Dua')['sks_diprogram']);
        $this->assertStringContainsString(
            'calendar=calendar-current',
            $rows->firstWhere('nama_mahasiswa', 'Mahasiswa Dua')['print_url']
        );
        $historyResponse = $this->getJson(route('monitoring-krs.data', [
            'program' => 'program-1',
            'calendar' => 'calendar-previous',
            'draw' => 3,
            'start' => 0,
            'length' => 10,
        ]))->assertOk();
        $historyStudent = collect($historyResponse->json('data'))->firstWhere('nama_mahasiswa', 'Mahasiswa Satu');
        $this->assertSame('KRS Telah Disahkan Prodi', $historyStudent['krs_status']);
        $this->assertSame(6.0, (float) $historyStudent['sks_diprogram']);
        $this->assertStringContainsString('calendar=calendar-previous', $historyStudent['print_url']);
        $this->get(route('krs.print', ['student' => 'student-1', 'calendar' => 'calendar-previous']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $this->getJson(route('monitoring-krs.data', [
            'program' => 'program-1',
            'draw' => 2,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '2025002'],
        ]))->json('recordsFiltered'));

        $this->actingAs($this->user('pimfak', 'leader-faculty', 'leader-faculty-login', ['id_fakultas' => 'faculty-1']));
        $this->get(route('monitoring-krs.index', ['program' => 'program-2']))->assertNotFound();
        $this->get(route('monitoring-krs.index', ['program' => 'program-1']))
            ->assertOk()
            ->assertSee('data-datatable="monitoring-krs"', false)
            ->assertSee('id="monitoring-calendar"', false);

        $this->actingAs($this->user('pimuniv', 'leader-university', 'leader-university-login'));
        $this->get(route('monitoring-krs.index', ['program' => 'program-2']))
            ->assertOk()
            ->assertSee('Pilih program studi')
            ->assertSee('program-2', false);

        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));
        $this->get(route('monitoring-krs.index', ['program' => 'program-2']))
            ->assertOk()
            ->assertSee('2026 Ganjil')
            ->assertSee('id="monitoring-calendar"', false);

        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->get(route('monitoring-krs.index'))->assertForbidden();
        $this->getJson(route('monitoring-krs.data', ['program' => 'program-1']))->assertForbidden();
    }

    public function test_program_staff_can_edit_approved_krs_without_changing_approvals_and_reset_only_pa(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update([
                'nama_kpro' => 'Ketua Prodi',
                'tgl_pengesahan_kpro' => now()->toDateString(),
            ]);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('krs-selected-row', false)
            ->assertDontSee('Reset Pengesahan Dosen PA');
        $this->post(route('krs.save', 'student-1'), ['schedule_ids' => ['schedule-2']])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('schedule-2', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_jadwal'));
        $approval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->first();
        $this->assertSame('Dosen PA Lama', $approval->yang_mengesahkan);
        $this->assertSame('Ketua Prodi', $approval->nama_kpro);

        $this->post(route('krs.reset-approval', 'student-1'))->assertRedirect();
        $resetApproval = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->first();
        $this->assertNull($resetApproval->yang_mengesahkan);
        $this->assertNull($resetApproval->tgl_pengesahan);
        $this->assertSame('Ketua Prodi', $resetApproval->nama_kpro);
        $this->assertSame('schedule-2', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->value('id_jadwal'));
    }

    public function test_program_staff_can_bulk_approve_only_students_in_their_program(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-2',
            'nim' => '2025002',
            'nama_mahasiswa' => 'Mahasiswa Dua',
            'id_prodi' => 'program-1',
            'dosen_pa' => 'NIDN-2',
            'id_status_mahasiswa' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-second-student',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'student-2',
            'id_jadwal' => 'schedule-2',
            'id_matkul' => 'course-2',
            'tahun' => '20261',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-second-student',
            'id_mahasiswa' => 'student-2',
            'id_kalender' => 'calendar-current',
            'yang_mengesahkan' => 'Dosen PA Dua',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-3', 'nim' => '2025003', 'nama_mahasiswa' => 'Mahasiswa Tiga', 'id_prodi' => 'program-1', 'dosen_pa' => 'lecturer-1', 'id_status_mahasiswa' => 'A'],
            ['id_mahasiswa' => 'student-4', 'nim' => '2025004', 'nama_mahasiswa' => 'Mahasiswa Empat', 'id_prodi' => 'program-2', 'dosen_pa' => 'lecturer-1', 'id_status_mahasiswa' => 'A'],
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            'id_kalender' => 'calendar-other-active',
            'id_prodi' => 'program-2',
            'tahun' => '20261',
            'namatahun' => '2026 Ganjil',
            'status' => 'Y',
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            'id_registrasi' => 'registration-other',
            'id_mahasiswa' => 'student-4',
            'id_kalender' => 'calendar-other-active',
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_krs' => 'krs-previous-term', 'id_prodi' => 'program-1', 'id_kalender' => 'calendar-previous', 'id_mahasiswa' => 'student-3', 'id_jadwal' => 'schedule-1', 'id_matkul' => 'course-1', 'tahun' => '20251'],
            ['id_krs' => 'krs-other-program', 'id_prodi' => 'program-2', 'id_kalender' => 'calendar-other-active', 'id_mahasiswa' => 'student-4', 'id_jadwal' => 'schedule-1', 'id_matkul' => 'course-1', 'tahun' => '20261'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_pengesahan' => 'approval-previous-term', 'id_mahasiswa' => 'student-3', 'id_kalender' => 'calendar-previous', 'yang_mengesahkan' => 'Dosen PA Tiga'],
            ['id_pengesahan' => 'approval-other-program', 'id_mahasiswa' => 'student-4', 'id_kalender' => 'calendar-other-active', 'yang_mengesahkan' => 'Dosen PA Empat'],
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->post(route('krs.approve-program-all'), ['id_kalender' => 'calendar-current'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(2, DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('nama_kpro', 'Operator Prodi')->count());
        $this->assertNull(DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->whereIn('id_pengesahan', ['approval-previous-term', 'approval-other-program'])
            ->whereNotNull('nama_kpro')
            ->value('nama_kpro'));
        $activities = DB::connection('simak_main')->table('log_aktivitas')->get();
        $this->assertCount(2, $activities);
        $this->assertEqualsCanonicalizing(
            ['Mahasiswa Satu', 'Mahasiswa Dua'],
            $activities->map(fn (object $activity): string => json_decode(
                $activity->aktifitas,
                true,
                flags: JSON_THROW_ON_ERROR
            )['identitas_data']['nama'])->all()
        );
        $this->post(route('krs.approve-program-all'), ['id_kalender' => 'calendar-previous'])
            ->assertNotFound();
    }

    public function test_fully_approved_krs_prints_as_pdf_and_qr_opens_public_validation(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->update([
                'nama_kpro' => 'Ketua Prodi',
                'tgl_pengesahan_kpro' => now()->toDateString(),
            ]);
        $this->actingAs($this->user('mahasiswa', 'student-1', '2025001'));

        $response = $this->get(route('krs.print', 'student-1'))->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $verificationCode = DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')
            ->value('id_pengesahan');
        $this->get(route('document-verification.show', $verificationCode))
            ->assertOk()
            ->assertSee('Verifikasi Dokumen Valid')
            ->assertSee('images/landing/default-campus-logo.png')
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Dosen PA Lama')
            ->assertSee('Ketua Prodi');

        DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_pengesahan', $verificationCode)
            ->update(['nama_kpro' => null, 'tgl_pengesahan_kpro' => null]);
        $this->get(route('document-verification.show', $verificationCode))
            ->assertNotFound()
            ->assertSee('Dokumen Tidak Valid');
    }

    public function test_dosen_can_only_access_and_approve_their_own_advisees(): void
    {
        $this->insertKrsAndPaApproval();
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->delete();
        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertSee('Mahasiswa Satu')
            ->assertSee('Dosen Uji')
            ->assertSee('Status Pengisian KRS')
            ->assertSee('Registrasi Ulang')
            ->assertSee('Status Pengesahan PA')
            ->assertSee('Telah isi KRS')
            ->assertSee('Telah registrasi')
            ->assertSee('Belum disahkan PA')
            ->assertSee('data-datatable="krs-students"', false)
            ->assertSeeInOrder(['Cari mahasiswa.', 'data-datatable="krs-students"'], false)
            ->assertSee('aria-label="Sahkan KRS Mahasiswa Satu"', false)
            ->assertSee('data-krs-form="krs-approve-pa-student-1"', false)
            ->assertSee('data-krs-confirm-modal', false)
            ->assertDontSee('Belum ada mahasiswa PA')
            ->assertDontSee('Sahkan Semua KRS PA');
        $this->get(route('krs.edit', 'student-1'))
            ->assertOk()
            ->assertSee('Sahkan Dosen PA')
            ->assertDontSee('aria-label="Sahkan KRS sebagai dosen PA"', false);
        $this->post(route('krs.approve-pa', 'student-1'))->assertRedirect();
        $this->assertSame('Dosen Uji', DB::connection('simak_main')->table('s1m4k_pengesahankrs')
            ->where('id_mahasiswa', 'student-1')->value('yang_mengesahkan'));
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'krs-approved-pa',
        ], 'simak_main');

        $this->get(route('krs.edit', 'student-2'))
            ->assertNotFound();
        $this->post(route('krs.approve-pa', 'student-2'))->assertNotFound();
    }

    public function test_dosen_list_shows_missing_krs_and_registration_status(): void
    {
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->delete();
        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));

        $this->get(route('krs.index'))
            ->assertOk()
            ->assertSee('Belum isi KRS')
            ->assertSee('Belum registrasi')
            ->assertSee('Belum disahkan PA');
    }

    public function test_dosen_scope_resolves_pa_by_id_dosen_and_fails_closed_without_match(): void
    {
        $this->actingAs($this->user('dosen', 'lecturer-1', 'NIDN-1'));
        $this->get(route('krs.index'))->assertOk()->assertSee('Mahasiswa Satu');

        $this->actingAs($this->user('dosen', 'lecturer-unknown', 'NIDN-UNKNOWN'));
        $this->get(route('krs.index'))
            ->assertOk()
            ->assertDontSee('Mahasiswa Satu')
            ->assertSee('Belum ada mahasiswa PA');
        $this->post(route('krs.approve-pa', 'student-1'))->assertNotFound();
    }

    public function test_transfer_krs_can_be_searched_programmed_and_graded_with_audit(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'student-1')->update([
            'id_jenis_daftar' => '2',
            'sks_diakui' => '12',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->where('id_matkul', 'course-1')
            ->update(['id_prodi' => 'program-1', 'status' => 'A']);
        DB::connection('simak_main')->table('s1m4k_mastermk')->where('id_matkul', 'course-2')
            ->update(['id_prodi' => 'program-1', 'status' => 'A']);
        DB::connection('simak_main')->table('s1m4k_masterbobotnilai')->insert([
            ['id_bobot_nilai' => 'grade-e', 'nilai_huruf' => 'E', 'nilai_indeks' => '0.00', 'bobot_minimum' => '0', 'bobot_maksimum' => '39'],
            ['id_bobot_nilai' => 'grade-c', 'nilai_huruf' => 'C', 'nilai_indeks' => '2.00', 'bobot_minimum' => '40', 'bobot_maksimum' => '59'],
            ['id_bobot_nilai' => 'grade-b', 'nilai_huruf' => 'B', 'nilai_indeks' => '3.00', 'bobot_minimum' => '60', 'bobot_maksimum' => '79'],
            ['id_bobot_nilai' => 'grade-a', 'nilai_huruf' => 'A', 'nilai_indeks' => '4.00', 'bobot_minimum' => '80', 'bobot_maksimum' => '100'],
        ]);
        $this->actingAs($this->user('admin', 'admin-1', 'admin-login'));

        $this->get(route('krs-transfer.index'))->assertOk()->assertSee('Cari mahasiswa transfer');
        $this->get(route('krs-transfer.index', ['search' => '2025001']))
            ->assertRedirect(route('krs-transfer.show', 'student-1'));
        $this->get(route('krs-transfer.index', ['search' => '9999999']))
            ->assertRedirect(route('krs-transfer.index'))
            ->assertSessionHasErrors('search');
        $this->get(route('krs-transfer.show', 'student-1'))
            ->assertOk()->assertSee('Pindahan / Transfer')->assertSee('Program KRS Transfer');
        $this->put(route('krs-transfer.courses.save', 'student-1'), [
            'course_ids' => ['course-1', 'course-2'],
        ])->assertRedirect(route('krs-transfer.show', 'student-1'))->assertSessionHas('status');

        $transferRows = DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('krs_reguler', 'N')->get();
        $this->assertCount(2, $transferRows);
        $this->assertSame('00000', $transferRows->first()->id_kalender);
        $this->assertSame('-', $transferRows->first()->id_jadwal);
        $this->get(route('krs-transfer.show', 'student-1'))->assertSee('Cetak Nilai Transfer');

        $grades = [];
        foreach ($transferRows as $row) {
            $grades[$row->id_krs] = ['sks_asal' => '3', 'nilai_huruf_asal' => 'B+', 'nilai' => '80,5'];
        }
        $this->put(route('krs-transfer.grades.save', 'student-1'), ['grades' => $grades])
            ->assertRedirect(route('krs-transfer.show', 'student-1'))->assertSessionHas('status');
        $this->assertSame(81.0, (float) DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('krs_reguler', 'N')->value('nilai_angka'));
        $this->assertSame('A', DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('krs_reguler', 'N')->value('nilai_huruf'));
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')
            ->where('modul', 'KRS Transfer')->count());
        $this->get(route('krs-transfer.print', 'student-1'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_transfer_krs_enforces_credit_limit_program_scope_and_tu_read_only_access(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->where('id_mahasiswa', 'student-1')->update([
            'id_jenis_daftar' => '2',
            'sks_diakui' => '6',
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermk')->whereIn('id_matkul', ['course-1', 'course-2'])
            ->update(['id_prodi' => 'program-1', 'status' => 'A']);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->put(route('krs-transfer.courses.save', 'student-1'), [
            'course_ids' => ['course-1', 'course-2'],
        ])->assertSessionHasErrors('course_ids');
        $this->assertSame(0, DB::connection('simak_main')->table('s1m4k_krs')
            ->where('id_mahasiswa', 'student-1')->where('krs_reguler', 'N')->count());

        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-outside',
            'nim' => '2025999',
            'nama_mahasiswa' => 'Mahasiswa Di Luar Prodi',
            'id_prodi' => 'program-2',
            'id_jenis_daftar' => '2',
            'sks_diakui' => '6',
        ]);
        $this->get(route('krs-transfer.index', ['search' => '2025999']))
            ->assertRedirect(route('krs-transfer.index'))
            ->assertSessionHasErrors('search');
        $this->get(route('krs-transfer.show', 'student-outside'))->assertNotFound();

        $this->actingAs($this->user('usertu', 'tu-1', 'tu-login', ['id_prodi' => 'program-1']));
        $this->get(route('krs-transfer.index', ['search' => '2025001']))
            ->assertRedirect(route('krs-transfer.show', 'student-1'));
        $this->get(route('krs-transfer.courses.edit', 'student-1'))->assertForbidden();
        $this->put(route('krs-transfer.courses.save', 'student-1'), ['course_ids' => ['course-1']])->assertForbidden();
        $this->put(route('krs-transfer.grades.save', 'student-1'), ['grades' => []])->assertForbidden();
    }

    public function test_program_staff_cannot_access_students_outside_their_program(): void
    {
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-2',
            'nim' => '2025002',
            'nama_mahasiswa' => 'Mahasiswa Dua',
            'id_prodi' => 'program-2',
            'dosen_pa' => 'NIDN-2',
            'id_status_mahasiswa' => 'A',
        ]);
        $this->actingAs($this->user('userprodi', 'operator-1', 'prodi-login', ['id_prodi' => 'program-1']));

        $this->get(route('krs.edit', 'student-2'))
            ->assertNotFound();
        $this->post(route('krs.approve-program', 'student-2'))->assertNotFound();
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
            $table->string('id_jenis_daftar')->nullable();
            $table->string('sks_diakui')->nullable();
            $table->string('dosen_pa')->nullable();
            $table->string('id_status_mahasiswa')->nullable();
            $table->string('nama_status_mahasiswa')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('ketua_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('nip')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('id_prodi');
            $table->string('tahun');
            $table->string('namatahun')->nullable();
            $table->string('status')->nullable();
            $table->date('tgl_mulai_krs')->nullable();
            $table->date('tgl_akhir_krs')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_registrasiulang', function (Blueprint $table): void {
            $table->string('id_registrasi')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_pengesahan')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->date('tgl_pengesahan')->nullable();
            $table->string('yang_mengesahkan')->nullable();
            $table->date('tgl_pengesahan_kpro')->nullable();
            $table->string('nama_kpro')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermk', function (Blueprint $table): void {
            $table->string('id_matkul')->primary();
            $table->string('kode_mata_kuliah');
            $table->string('nama_mata_kuliah');
            $table->string('semester')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('status')->nullable();
            $table->decimal('sks_mata_kuliah', 5, 2);
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('id_prodi');
            $table->string('id_kalender');
            $table->string('id_matkul');
            $table->string('id_ruang')->nullable();
            $table->string('nama_kelas')->nullable();
            $table->string('hari')->nullable();
            $table->string('jammulai')->nullable();
            $table->string('jamselesai')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterruang', function (Blueprint $table): void {
            $table->string('id_ruang')->primary();
            $table->string('nama_ruang')->nullable();
            $table->string('kapasitas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_akm', function (Blueprint $table): void {
            $table->string('id_ipmhs')->primary();
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('ips')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_krs')->primary();
            foreach ([
                'id_perguruan_tinggi', 'id_fakultas', 'id_prodi', 'id_kalender', 'tahun',
                'id_mahasiswa', 'id_jadwal', 'id_matkul', 'status_krs', 'tgl_update', 'nilai_huruf',
                'nilai_bobot', 'nilai_angka', 'nilai_huruf_asal', 'sks_asal', 'input_nilai',
                'krs_reguler', 'krs_mbkm', 'isi_edom', 'user',
            ] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::connection('simak_main')->create('s1m4k_masterbobotnilai', function (Blueprint $table): void {
            $table->string('id_bobot_nilai')->primary();
            $table->string('nilai_huruf')->nullable();
            $table->string('nilai_indeks')->nullable();
            $table->string('bobot_minimum')->nullable();
            $table->string('bobot_maksimum')->nullable();
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
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->string('id_admin')->primary();
            $table->string('level');
            $table->string('id_prodi')->nullable();
            $table->string('blokir')->default('N');
        });
        Schema::connection('simak_main')->create('eskripsi_notifikasi', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('recipient_role', 30);
            $table->string('recipient_id', 100);
            $table->string('category', 80);
            $table->string('title', 255);
            $table->text('message');
            $table->string('url', 500);
            $table->boolean('is_read')->default(false);
            $table->dateTime('created_at');
            $table->dateTime('read_at')->nullable();
        });
    }

    private function seedStudentData(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'nama_program_studi' => 'Program Satu', 'id_fakultas' => 'faculty-1', 'ketua_prodi' => 'chair-1'],
            ['id_prodi' => 'program-2', 'nama_program_studi' => 'Program Dua', 'id_fakultas' => 'faculty-2', 'ketua_prodi' => 'chair-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen Uji', 'nip' => 'NIP-1'],
            ['id_dosen' => 'chair-1', 'nidn' => 'NIDN-CHAIR', 'nama_dosen' => 'Ketua Prodi', 'nip' => 'NIP-CHAIR'],
        ]);
        DB::connection('simak_main')->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-1',
            'nim' => '2025001',
            'nama_mahasiswa' => 'Mahasiswa Satu',
            'id_prodi' => 'program-1',
            'dosen_pa' => 'lecturer-1',
            'id_status_mahasiswa' => 'A',
        ]);
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-previous', 'id_prodi' => 'program-1', 'tahun' => '20251', 'namatahun' => '2025 Ganjil', 'status' => 'N', 'tgl_mulai_krs' => null, 'tgl_akhir_krs' => null],
            ['id_kalender' => 'calendar-break', 'id_prodi' => 'program-1', 'tahun' => '20252', 'namatahun' => '2025 Genap', 'status' => 'N', 'tgl_mulai_krs' => null, 'tgl_akhir_krs' => null],
            [
                'id_kalender' => 'calendar-current',
                'id_prodi' => 'program-1',
                'tahun' => '20261',
                'namatahun' => '2026 Ganjil',
                'status' => 'Y',
                'tgl_mulai_krs' => now()->subDay()->toDateString(),
                'tgl_akhir_krs' => now()->addMonth()->toDateString(),
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_registrasiulang')->insert([
            'id_registrasi' => 'registration-1',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-current',
        ]);
        DB::connection('simak_main')->table('s1m4k_akm')->insert([
            'id_ipmhs' => 'akm-previous',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-previous',
            'ips' => '3.20',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterruang')->insert([
            'id_ruang' => 'room-1',
            'nama_ruang' => 'Ruang Uji',
            'kapasitas' => '50',
        ]);
        foreach (range(1, 5) as $index) {
            DB::connection('simak_main')->table('s1m4k_mastermk')->insert([
                'id_matkul' => 'course-'.$index,
                'kode_mata_kuliah' => 'MK00'.$index,
                'nama_mata_kuliah' => 'Mata Kuliah '.['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'][$index - 1],
                'semester' => '1',
                'id_prodi' => 'program-1',
                'status' => 'A',
                'sks_mata_kuliah' => $index < 5 ? 6 : 3,
            ]);
            DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
                'id_jadwal' => 'schedule-'.$index,
                'id_prodi' => 'program-1',
                'id_kalender' => 'calendar-current',
                'id_matkul' => 'course-'.$index,
                'id_ruang' => 'room-1',
                'nama_kelas' => $index <= 2 ? 'PAKET-A' : 'KELAS-'.$index,
                'hari' => 'senin',
                'jammulai' => '08:00',
                'jamselesai' => '10:00',
            ]);
        }
    }

    private function insertKrsAndPaApproval(): void
    {
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            'id_krs' => 'krs-existing',
            'id_prodi' => 'program-1',
            'id_kalender' => 'calendar-current',
            'id_mahasiswa' => 'student-1',
            'id_jadwal' => 'schedule-1',
            'id_matkul' => 'course-1',
            'tahun' => '20261',
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            'id_pengesahan' => 'approval-existing',
            'id_mahasiswa' => 'student-1',
            'id_kalender' => 'calendar-current',
            'yang_mengesahkan' => 'Dosen PA Lama',
            'tgl_pengesahan' => now()->toDateString(),
        ]);
    }

    private function user(string $role, string $id, string $login, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $id,
            'legacy_id' => $id,
            'legacy_type' => $role,
            'role' => $role,
            'login' => $login,
            'name' => match ($role) {
                'dosen' => 'Dosen Uji',
                'userprodi', 'usertu' => 'Operator Prodi',
                default => 'Pengguna Uji',
            },
            ...$extra,
        ]);
    }
}
