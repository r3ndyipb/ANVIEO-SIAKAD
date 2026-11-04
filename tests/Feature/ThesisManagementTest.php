<?php

namespace Tests\Feature;

use App\Mail\ThesisNotificationMail;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class ThesisManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.simak_main' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('simak_main');
        Mail::fake();
        $this->createSchema();
        $this->seedData();
    }

    public function test_bimbingan_chat_is_scoped_and_read_only_roles_cannot_send(): void
    {
        $this->actingAs($this->user('admin'));
        DB::connection('simak_main')->table('eskripsi_skripsi')->insert([
            'id' => 'thesis-pending', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-pending', 'judul' => 'Belum diplot',
            'pembimbing1' => 'lecturer-1', 'pembimbing2' => null, 'penguji1' => null, 'penguji2' => null,
            'penguji3' => null, 'status' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('theses.bimbingan.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Mahasiswa Satu')->assertSee('Judul Skripsi Satu')
            ->assertSee('data-notification-label="Notifikasi SIMAK"', false);
        $this->getJson(route('theses.bimbingan.messages', ['skripsi' => 'thesis-pending', 'contact' => 'lecturer-1']))
            ->assertNotFound();
        $this->get(route('theses.bimbingan.show', 'thesis-2'))->assertOk();
        $this->get(route('theses.bimbingan.show', 'thesis-1'))->assertOk()
            ->assertSee('data-thesis-chat', false);
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1', 'message' => 'Tidak boleh dikirim admin',
        ])->assertForbidden();

        $this->actingAs($this->user('pimfak', ['id_fakultas' => 'faculty-2']));
        $this->get(route('theses.bimbingan.show', 'thesis-1'))->assertNotFound();
        $this->get(route('theses.bimbingan.show', 'thesis-2'))->assertOk();

        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('theses.bimbingan.index'))->assertOk()->assertSee('Judul Skripsi Satu');
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1', 'message' => 'Operator hanya membaca',
        ])->assertForbidden();

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->get(route('theses.bimbingan.show', 'thesis-1'))->assertOk();
        $this->get(route('theses.bimbingan.show', 'thesis-2'))->assertNotFound();
        $brokenEmoji = "baik Pak\u{00F0}\u{0178}\u{2122}\u{008F}\u{00F0}\u{0178}\u{008F}\u{00BB}";
        $repairedEmoji = "baik Pak\u{1F64F}\u{1F3FB}";
        $longUrl = 'https://example.test/'.str_repeat('a', 280);
        $sendResponse = $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1', 'message' => $brokenEmoji, 'type' => 'biasa',
            'file_url' => $longUrl,
        ])->assertCreated();
        $messageId = $sendResponse->json('id');
        $this->assertDatabaseHas('eskripsi_bimbingan', [
            'id_bimbingan' => $messageId,
            'pesan' => 'baik Pak&#128591;&#127995;',
        ], 'simak_main');
        DB::connection('simak_main')->table('eskripsi_bimbingan')
            ->where('id_bimbingan', $messageId)->update(['pesan' => $brokenEmoji]);
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('lecturer1@example.test'));
        $this->getJson(route('theses.bimbingan.messages', ['skripsi' => 'thesis-1', 'contact' => 'lecturer-1']))
            ->assertOk()->assertJsonPath('messages.0.sender', 'mahasiswa')
            ->assertJsonPath('messages.0.content', $repairedEmoji)
            ->assertJsonPath('messages.0.file_url', $longUrl);

        $emojiMessage = 'Perhatian <b>penting</b><br>👍🏽😊';
        $emojiResponse = $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1',
            'message' => $emojiMessage,
        ])->assertCreated();
        $this->assertDatabaseHas('eskripsi_bimbingan', [
            'id_bimbingan' => $emojiResponse->json('id'),
            'pesan' => 'Perhatian <b>penting</b><br>&#128077;&#127997;&#128522;',
        ], 'simak_main');
        $this->getJson(route('theses.bimbingan.messages', ['skripsi' => 'thesis-1', 'contact' => 'lecturer-1']))
            ->assertOk()->assertJsonFragment(['content' => $emojiMessage]);

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $lecturerMessage = 'Pesan dari dosen 😊';
        $lecturerResponse = $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1',
            'message' => $lecturerMessage,
        ])->assertCreated();
        $this->assertDatabaseHas('eskripsi_bimbingan', [
            'id_bimbingan' => $lecturerResponse->json('id'),
            'pesan' => 'Pesan dari dosen &#128522;',
        ], 'simak_main');
        $this->getJson(route('theses.bimbingan.messages', ['skripsi' => 'thesis-1', 'contact' => 'lecturer-1']))
            ->assertOk()->assertJsonFragment(['content' => $lecturerMessage, 'sender' => 'dosen']);
    }

    public function test_eskripsi_allows_s1_and_d3_while_graduate_module_is_limited_to_s2_and_s3(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-s2',
            'id_fakultas' => 'faculty-1',
            'id_perguruan_tinggi' => 'pt-1',
            'nama_program_studi' => 'Program Magister',
            'nama_jenjang_pendidikan' => 'S2',
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-s2',
            'id_prodi' => 'program-s2',
            'id_fakultas' => 'faculty-1',
            'nim' => 'NIM-S2',
            'nama_mahasiswa' => 'Mahasiswa Magister',
            'email' => 'student-s2@example.test',
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            'id_prodi' => 'program-d3',
            'id_fakultas' => 'faculty-1',
            'id_perguruan_tinggi' => 'pt-1',
            'nama_program_studi' => 'Program Diploma',
            'nama_jenjang_pendidikan' => 'D3',
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            'id_mahasiswa' => 'student-d3',
            'id_prodi' => 'program-d3',
            'id_fakultas' => 'faculty-1',
            'nim' => 'NIM-D3',
            'nama_mahasiswa' => 'Mahasiswa Diploma',
            'email' => 'student-d3@example.test',
        ]);

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->get(route('graduate_theses.titles.index'))->assertNotFound();

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('E-Skripsi')
            ->assertDontSee('E-Tesis & Disertasi');
        $this->get(route('graduate_theses.dashboard'))->assertForbidden();

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-s2']));
        $this->get(route('theses.titles.index'))->assertNotFound();

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-s2']));
        $this->get(route('theses.dashboard'))->assertForbidden();

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-d3']));
        $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('E-Skripsi')
            ->assertDontSee('E-Tesis & Disertasi');
        $this->get(route('graduate_theses.dashboard'))->assertForbidden();

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-d3']));
        $this->get(route('theses.titles.index'))->assertOk()
            ->assertSee('E-Skripsi')
            ->assertDontSee('E-Tesis & Disertasi');
        $this->get(route('graduate_theses.titles.index'))->assertNotFound();
    }

    public function test_email_events_create_private_in_app_notifications_that_can_be_marked_read(): void
    {
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1',
            'message' => 'Mohon pemeriksaan revisi.',
        ])->assertCreated();

        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'message',
            'is_read' => 0,
        ], 'simak_main');

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $response = $this->getJson(route('theses.notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('can_mark_read', true)
            ->assertJsonPath('notifications.0.title', 'Pesan Bimbingan Baru: Mahasiswa Satu')
            ->assertJsonPath('notifications.0.is_read', false);
        $notificationId = $response->json('notifications.0.id');
        $this->patchJson(route('theses.notifications.read', $notificationId))->assertOk()
            ->assertJsonPath('message', 'Notifikasi ditandai sudah dibaca.');
        $this->getJson(route('theses.notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notifications.0.is_read', true);

        DB::connection('simak_main')->table('eskripsi_notifikasi')->insert([
            'id' => '00000000-0000-4000-8000-000000000001',
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'schedule',
            'title' => 'Jadwal ujian baru',
            'message' => 'Jadwal telah ditetapkan.',
            'url' => route('theses.exams.index'),
            'is_read' => 0,
            'created_at' => now(),
        ]);
        $this->patchJson(route('theses.notifications.read-all'))->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-2']));
        $this->getJson(route('theses.notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
        $this->patchJson(route('theses.notifications.read', $notificationId))->assertNotFound();
    }

    public function test_supervisor_acc_requires_all_active_supervisors_and_creates_proposal_exam(): void
    {
        $payload = [
            'contact' => 'lecturer-1',
            'type' => '[ACC] ACC Ujian Proposal',
            'message' => 'Proposal siap diuji',
        ];
        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), $payload)->assertCreated();
        $this->assertSame(1, (int) DB::connection('simak_main')->table('eskripsi_skripsi')->where('id', 'thesis-1')->value('status'));
        $this->assertSame(0, DB::connection('simak_main')->table('eskripsi_ujian')->where('id_skripsi', 'thesis-1')->count());

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-2', 'login' => 'NIDN-2']));
        $payload['contact'] = 'lecturer-2';
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), $payload)->assertCreated();
        $this->assertSame(2, (int) DB::connection('simak_main')->table('eskripsi_skripsi')->where('id', 'thesis-1')->value('status'));
        $this->assertDatabaseHas('eskripsi_ujian', [
            'id_skripsi' => 'thesis-1', 'jenis_ujian' => 'proposal',
        ], 'simak_main');
        $this->assertSame(2, DB::connection('simak_main')->table('log_aktivitas')->count());
    }

    public function test_lecturer_can_send_regular_guidance_messages_and_sees_students_grouped_by_role(): void
    {
        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1',
            'message' => 'Catatan revisi bimbingan biasa',
            'type' => 'biasa',
        ])->assertCreated();

        DB::connection('simak_main')->table('eskripsi_skripsi')->insert([
            'id' => 'thesis-examiner-group', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-examiner-group',
            'judul' => 'Skripsi untuk kelompok penguji', 'pembimbing1' => 'lecturer-2',
            'pembimbing2' => null, 'penguji1' => 'lecturer-1', 'penguji2' => null, 'penguji3' => null,
            'status' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('theses.bimbingan.index'))->assertOk()
            ->assertSee('Mahasiswa sebagai Pembimbing')
            ->assertSee('Mahasiswa sebagai Penguji')
            ->assertSee('Skripsi untuk kelompok penguji')
            ->assertSee('Dosen Satu')
            ->assertSee('Dosen Tiga')
            ->assertSee('data-datatable="thesis-list"', false);
        $this->assertDatabaseHas('eskripsi_bimbingan', [
            'id_skripsi' => 'thesis-1',
            'id_dosen_tujuan' => 'lecturer-1',
            'pengirim' => 'dosen',
            'pesan' => 'Catatan revisi bimbingan biasa',
        ], 'simak_main');
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-examiner-group'), [
            'contact' => 'lecturer-1',
            'message' => 'Respons untuk dosen penguji',
        ])->assertCreated();
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'message',
        ], 'simak_main');
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-examiner-group'), [
            'contact' => 'lecturer-1',
            'message' => 'Respons untuk dosen penguji',
        ])->assertCreated();
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'message',
            'message' => 'Respons untuk dosen penguji NIM: NIM-1 · Judul skripsi: Skripsi untuk kelompok penguji',
        ], 'simak_main');
    }

    public function test_completed_thesis_accepts_regular_chat_but_rejects_approval_actions(): void
    {
        DB::connection('simak_main')->table('eskripsi_skripsi')->where('id', 'thesis-1')->update(['status' => 6]);
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1', 'message' => 'Pesan lanjutan', 'type' => 'biasa',
        ])->assertCreated();

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-1', 'message' => 'ACC setelah selesai', 'type' => '[ACC] ACC Revisi Tutup',
        ])->assertUnprocessable();
    }

    public function test_lecturer_can_read_other_assigned_conversations_but_only_reply_as_self(): void
    {
        DB::connection('simak_main')->table('eskripsi_bimbingan')->insert([
            [
                'id_bimbingan' => 'message-own',
                'id_skripsi' => 'thesis-1',
                'id_dosen_tujuan' => 'lecturer-1',
                'pengirim' => 'mahasiswa',
                'pesan' => 'Pesan untuk dosen login',
                'waktu_kirim' => '2026-10-20 10:00:00',
            ],
            [
                'id_bimbingan' => 'message-other',
                'id_skripsi' => 'thesis-1',
                'id_dosen_tujuan' => 'lecturer-2',
                'pengirim' => 'mahasiswa',
                'pesan' => 'Pesan untuk dosen lain',
                'waktu_kirim' => '2026-10-20 12:00:00',
            ],
        ]);
        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));

        $this->get(route('theses.bimbingan.index'))->assertOk()
            ->assertSee('Sebagai Pembimbing')
            ->assertSee('Sebagai Penguji')
            ->assertSee('role="tablist"', false)
            ->assertSee('2026-10-20 10:00:00')
            ->assertDontSee('2026-10-20 12:00:00')
            ->assertSee('Pesan baru')
            ->assertSee('thesis-unread-badge', false)
            ->assertSee('background: #dc2626', false);
        $this->get(route('theses.bimbingan.show', 'thesis-1'))
            ->assertOk()
            ->assertSee('data-thesis-id="thesis-1"', false)
            ->assertSee('data-teacher-id="lecturer-1"', false)
            ->assertSee('height: min(58vh, 620px)', false)
            ->assertSee('overflow-y: auto', false)
            ->assertSee('lecturer-2', false);
        $this->getJson(route('theses.bimbingan.messages', ['skripsi' => 'thesis-1', 'contact' => 'lecturer-2']))
            ->assertOk()->assertJsonPath('messages.0.content', 'Pesan untuk dosen lain');
        $this->postJson(route('theses.bimbingan.send', 'thesis-1'), [
            'contact' => 'lecturer-2', 'message' => 'Tidak boleh membalas atas nama dosen lain',
        ])->assertForbidden();
    }

    public function test_program_staff_schedules_exams_and_assigned_lecturer_grades_and_sends_revision(): void
    {
        $examId = $this->createExam('thesis-1');
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->put(route('theses.exams.schedule', $examId), [
            'waktu_ujian' => '2026-11-02T10:30', 'ruang_ujian' => 'Ruang Sidang A',
        ])->assertRedirect()->assertSessionHas('status');
        Mail::assertSent(ThesisNotificationMail::class, 4);
        $this->assertSame(3, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'schedule')->where('recipient_role', 'dosen')->count());
        $this->assertSame(4, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'schedule')->count());
        foreach (['lecturer-1', 'lecturer-2', 'lecturer-3'] as $lecturerId) {
            $this->assertDatabaseHas('eskripsi_notifikasi', [
                'recipient_role' => 'dosen',
                'recipient_id' => $lecturerId,
                'category' => 'schedule',
                'is_read' => 0,
            ], 'simak_main');
        }
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'schedule',
            'title' => 'Undangan Ujian Skripsi: Mahasiswa Satu',
            'is_read' => 0,
        ], 'simak_main');
        $studentScheduleNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('recipient_role', 'mahasiswa')->where('recipient_id', 'student-1')
            ->where('category', 'schedule')->first();
        $this->assertStringContainsString('Ruang: Ruang Sidang A', $studentScheduleNotification->message);
        $this->assertSame(route('theses.bimbingan.show', 'thesis-1'), $studentScheduleNotification->url);
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('lecturer1@example.test'));
        $this->assertSame('Ruang Sidang A', DB::connection('simak_main')->table('eskripsi_ujian')->where('id_ujian', $examId)->value('ruang_ujian'));

        $this->put(route('theses.exams.schedule', $examId), [
            'waktu_ujian' => '2026-11-03T10:30', 'ruang_ujian' => 'Ruang Sidang B',
        ])->assertRedirect()->assertSessionHas('status');
        Mail::assertSent(ThesisNotificationMail::class, 8);
        $this->assertSame(8, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'schedule')->count());
        $this->assertSame(1, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('recipient_role', 'mahasiswa')->where('recipient_id', 'student-1')
            ->where('category', 'schedule')->where('message', 'like', '%Ruang: Ruang Sidang B%')->count());

        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->getJson(route('theses.notifications.index'))->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonFragment(['title' => 'Undangan Ujian Skripsi: Mahasiswa Satu']);

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-2']));
        $this->put(route('theses.exams.schedule', $examId), [
            'waktu_ujian' => '2026-11-03T10:30', 'ruang_ujian' => 'Ruang Lain',
        ])->assertNotFound();

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->get(route('theses.exams.index'))->assertOk()
            ->assertSee('<th>Keterangan</th>', false)
            ->assertDontSee('<th>Nilai</th>', false)
            ->assertSee('Belum menginput nilai')
            ->assertSee('<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Berita Acara</button>', false)
            ->assertSee('<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Nilai & Revisi</button>', false)
            ->assertDontSee(route('theses.exams.minutes', $examId), false);
        $this->get(route('theses.exams.minutes', $examId))->assertForbidden();
        $this->get(route('theses.exams.print', $examId))->assertForbidden();
        $this->put(route('theses.exams.grade', $examId), ['nilai' => 88, 'catatan' => 'Perbaiki landasan teori'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(88, (int) DB::connection('simak_main')->table('eskripsi_ujian')->where('id_ujian', $examId)->value('nilaipembimbing1'));
        $studentNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('recipient_role', 'mahasiswa')->where('recipient_id', 'student-1')
            ->where('category', 'message')->latest('created_at')->first();
        $this->assertNotNull($studentNotification);
        $this->assertSame('Nilai dan Revisi Ujian Telah Diberikan', $studentNotification->title);
        $this->assertStringContainsString('Dosen Satu telah menginput nilai dan memberikan revisi', $studentNotification->message);
        $this->assertStringContainsString('Catatan revisi: Perbaiki landasan teori', $studentNotification->message);
        $this->assertStringNotContainsString('88', $studentNotification->message);
        $this->get(route('theses.exams.index'))->assertOk()
            ->assertSee('Telah mengisi nilai')
            ->assertSee(route('theses.exams.minutes', $examId), false)
            ->assertDontSee('<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Berita Acara</button>', false)
            ->assertSee(route('theses.exams.print', $examId), false)
            ->assertDontSee('<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Nilai & Revisi</button>', false);
        $this->get(route('theses.exams.minutes', $examId))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('eskripsi_bimbingan', [
            'id_skripsi' => 'thesis-1',
            'id_dosen_tujuan' => 'lecturer-1',
            'pengirim' => 'dosen',
        ], 'simak_main');
        $studentNotification = DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('recipient_role', 'mahasiswa')->where('recipient_id', 'student-1')
            ->where('category', 'message')->latest('created_at')->first();
        $this->assertNotNull($studentNotification);
        $this->assertSame('Nilai dan Revisi Ujian Telah Diberikan', $studentNotification->title);
        $this->assertStringContainsString('Dosen Satu telah menginput nilai dan memberikan revisi', $studentNotification->message);
        $this->assertStringContainsString('Catatan revisi: Perbaiki landasan teori', $studentNotification->message);
        $this->assertStringNotContainsString('88', $studentNotification->message);
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-4', 'login' => 'NIDN-4']));
        $this->put(route('theses.exams.grade', $examId), ['nilai' => 70, 'catatan' => 'Tidak berwenang'])
            ->assertNotFound();

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-2', 'login' => 'NIDN-2']));
        $this->get(route('theses.exams.index'))->assertOk()
            ->assertSee('Belum menginput nilai')
            ->assertSee('<button class="button button-small" type="button" disabled aria-disabled="true">Cetak Nilai & Revisi</button>', false);
        $this->get(route('theses.exams.print', $examId))->assertForbidden();

        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->put(route('theses.exams.grade', $examId), ['nilai' => 0, 'catatan' => 'Nilai nol yang sah'])
            ->assertRedirect()->assertSessionHas('status');
        $this->get(route('theses.exams.index'))->assertOk()
            ->assertSee('Telah mengisi nilai')
            ->assertSee(route('theses.exams.minutes', $examId), false);
        $this->get(route('theses.exams.minutes', $examId))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('theses.exams.print', $examId))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(0, (int) DB::connection('simak_main')->table('eskripsi_ujian')
            ->where('id_ujian', $examId)->value('nilaipembimbing1'));

        $this->actingAs($this->user('pimuniv'));
        $this->get(route('theses.exams.index'))->assertOk()->assertSee('NIM-1')
            ->assertSee('Cetak Berita Acara');
        $this->get(route('theses.exams.show', $examId))->assertOk()
            ->assertDontSee('name="nilai"', false)
            ->assertDontSee('name="waktu_ujian"', false)
            ->assertSee('Nilai dan Catatan');
        $this->put(route('theses.exams.schedule', $examId), [
            'waktu_ujian' => '2026-11-03T10:30', 'ruang_ujian' => 'Ruang Lain',
        ])->assertForbidden();

        $this->actingAs($this->user('admin'));
        $this->get(route('theses.exams.show', $examId))->assertOk()
            ->assertDontSee('name="nilai"', false)
            ->assertDontSee('name="waktu_ujian"', false)
            ->assertSee('Nilai dan Catatan');

        $this->actingAs($this->user('pimfak', ['id_fakultas' => 'faculty-1']));
        $this->get(route('theses.exams.index'))->assertOk()->assertSee('NIM-1')
            ->assertSee('Cetak Berita Acara');
        $this->get(route('theses.exams.show', $examId))->assertOk()
            ->assertDontSee('name="nilai"', false)
            ->assertDontSee('name="waktu_ujian"', false)
            ->assertSee('Nilai dan Catatan');
    }

    public function test_lecturer_dashboard_only_reports_the_logged_in_lecturer(): void
    {
        DB::connection('simak_main')->table('eskripsi_skripsi')->insert([
            'id' => 'thesis-other-lecturer', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-other', 'judul' => 'Judul Privat Dosen Lain',
            'pembimbing1' => 'lecturer-2', 'pembimbing2' => null, 'penguji1' => null, 'penguji2' => null,
            'penguji3' => null, 'status' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->user('dosen', ['legacy_id' => 'lecturer-1', 'login' => 'NIDN-1']));
        $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('Membimbing Proposal')
            ->assertDontSee('Judul Privat Dosen Lain')
            ->assertSee('Dosen Satu')
            ->assertDontSee('Dosen Dua');
    }

    public function test_thesis_tables_are_paginated_and_print_actions_are_in_exam_rows(): void
    {
        $connection = DB::connection('simak_main');
        for ($index = 0; $index < 26; $index++) {
            $thesisId = 'thesis-page-'.$index;
            $connection->table('eskripsi_skripsi')->insert([
                'id' => $thesisId, 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
                'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-page-'.$index, 'judul' => 'Judul Halaman '.$index,
                'pembimbing1' => 'lecturer-1', 'pembimbing2' => null, 'penguji1' => null, 'penguji2' => null,
                'penguji3' => null, 'status' => 2, 'created_at' => now()->addSeconds($index), 'updated_at' => now(),
            ]);
            $connection->table('eskripsi_ujian')->insert([
                'id_ujian' => 'exam-page-'.$index, 'id_skripsi' => $thesisId, 'jenis_ujian' => 'proposal',
                'accpembimbing1' => 0, 'accpembimbing2' => 0, 'accpenguji1' => 0, 'accpenguji2' => 0,
                'accpenguji3' => 0, 'create_at' => now(), 'update_at' => now(),
            ]);
        }
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $bimbinganResponse = $this->get(route('theses.bimbingan.index'))->assertOk()
            ->assertSee('thesis-page-', false)
            ->assertSee('data-datatable="thesis-list"', false)
            ->assertSee('Dosen Satu');
        $examResponse = $this->get(route('theses.exams.index'))->assertOk()
            ->assertSee('data-datatable="thesis-list"', false)
            ->assertSee('Cetak Berita Acara')
            ->assertSee(route('theses.exams.minutes', 'exam-page-0'), false)
            ->assertSee('Pembimbing')
            ->assertSee('Penguji')
            ->assertSee('Dosen Satu')
            ->assertDontSee('Cetak Nilai &amp; Revisi');
        $this->assertSame(26, substr_count($examResponse->getContent(), 'Cetak Berita Acara'));
    }

    public function test_exam_list_search_filters_student_title_exam_type_and_preserves_program(): void
    {
        $this->createExam('thesis-1');
        $this->createExam('thesis-2');
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));

        $this->get(route('theses.exams.index', ['search' => 'NIM-1']))
            ->assertOk()->assertSee('NIM-1')->assertDontSee('NIM-2')
            ->assertSee('value="NIM-1"', false);
        $this->actingAs($this->user('admin'));
        $this->get(route('theses.exams.index', ['search' => 'proposal', 'program' => 'program-1']))
            ->assertOk()->assertSee('NIM-1')
            ->assertSee('name="program"', false)
            ->assertSee('value="program-1"', false);
        $this->get(route('theses.exams.index', ['search' => 'tidak ditemukan']))
            ->assertOk()->assertSee('Tidak ada data ujian yang sesuai dengan pencarian.');
    }

    public function test_exam_prints_use_legacy_layout_qr_and_public_validation(): void
    {
        $examId = $this->createExam('thesis-1');
        DB::connection('simak_main')->table('eskripsi_ujian')->where('id_ujian', $examId)->update([
            'waktu_ujian' => '2026-10-21 10:30:00',
            'ruang_ujian' => 'Ruang Sidang A',
            'nilaipembimbing1' => 88,
            'catatanpembimbing1' => 'Perbaiki pembahasan',
        ]);
        $this->actingAs($this->user('admin'));
        $this->get(route('theses.exams.minutes', $examId))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $gradeResponse = $this->get(route('theses.exams.print', $examId))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $gradeResponse->getContent());
        $this->get(route('theses.exams.show', $examId))
            ->assertOk()->assertDontSee('Cetak Nilai &amp; Revisi')->assertDontSee('Cetak Berita Acara');

        $validations = DB::connection('simak_main')->table('validasi_dok')
            ->where('id_dokumen', 'thesis-1')->get();
        $this->assertCount(2, $validations);
        $this->assertEqualsCanonicalizing([
            'BERITA_ACARA_UJIAN_PROPOSAL',
            'NILAI_REVISI_UJIAN_PROPOSAL',
        ], $validations->pluck('jenis_dokumen')->all());
        foreach ($validations as $validation) {
            $this->get(route('theses.documents.verify', $validation->id))
                ->assertOk()->assertSee('Dokumen Terverifikasi')->assertSee('NIM-1');
        }
        $this->get(route('theses.documents.verify', 'unknown-validation-code'))->assertNotFound();
    }

    public function test_stagnation_monitoring_includes_last_guidance_date_and_age_colors(): void
    {
        $connection = DB::connection('simak_main');
        foreach ([['week', 8], ['month', 31]] as [$key, $days]) {
            $thesisId = 'thesis-stagnant-'.$key;
            $connection->table('eskripsi_skripsi')->insert([
                'id' => $thesisId, 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
                'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-'.$thesisId,
                'judul' => 'Stagnan '.$key, 'pembimbing1' => 'lecturer-1',
                'pembimbing2' => null, 'penguji1' => null, 'penguji2' => null, 'penguji3' => null,
                'status' => 1, 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60),
            ]);
            $connection->table('eskripsi_bimbingan')->insert([
                'id_bimbingan' => 'message-'.$thesisId,
                'id_skripsi' => $thesisId,
                'id_dosen_tujuan' => 'lecturer-1',
                'pengirim' => 'mahasiswa',
                'pesan' => 'Bimbingan terakhir',
                'waktu_kirim' => now()->subDays($days),
            ]);
        }

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $response = $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('Tanggal terakhir bimbingan')
            ->assertSee(now()->subDays(8)->format('d-m-Y H:i'))
            ->assertSee(now()->subDays(31)->format('d-m-Y H:i'))
            ->assertSee('thesis-stagnation-week', false)
            ->assertSee('thesis-stagnation-month', false);
        $this->assertSame('Asia/Jayapura', config('app.timezone'));
        $this->assertStringContainsString('thesis-stagnation-week', $response->getContent());
    }

    public function test_dashboard_title_proposal_table_is_scoped_to_program(): void
    {
        DB::connection('simak_main')->table('eskripsi_judul')->insert([
            [
                'id' => 'proposal-title-program-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
                'id_mahasiswa' => 'student-1', 'judul' => 'Usulan Program Satu', 'deskripsi' => 'Deskripsi satu',
                'status' => 0, 'tanggal_usul' => now()->toDateString(),
            ],
            [
                'id' => 'proposal-title-program-2', 'id_fakultas' => 'faculty-2', 'id_prodi' => 'program-2',
                'id_mahasiswa' => 'student-2', 'judul' => 'Usulan Program Dua', 'deskripsi' => 'Deskripsi dua',
                'status' => 1, 'tanggal_usul' => now()->toDateString(),
            ],
        ]);
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));

        $this->get(route('theses.dashboard'))->assertOk()
            ->assertSee('Daftar Usulan Judul')
            ->assertSee('data-datatable="thesis-list"', false)
            ->assertSee('Usulan Program Satu')
            ->assertDontSee('Usulan Program Dua');
    }

    public function test_student_can_submit_title_proposal_only_when_no_pending_or_approved_proposal_exists(): void
    {
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->string('id_admin')->primary();
            $table->string('username');
            $table->string('level');
            $table->string('id_prodi')->nullable();
            $table->string('blokir')->default('N');
        });
        DB::connection('simak_main')->table('s1m4k_admin')->insert([
            'id_admin' => 'program-user-1',
            'username' => 'prodi1',
            'level' => 'userprodi',
            'id_prodi' => 'program-1',
            'blokir' => 'N',
        ]);
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));

        $this->get(route('theses.titles.index'))->assertOk()
            ->assertSee('Usul Judul Skripsi')
            ->assertSee('Mahasiswa Satu')
            ->assertSee('class="module-alert thesis-title-guidance" role="note"', false)
            ->assertSee('Buka formulir untuk mengisi judul dan deskripsi proposal.')
            ->assertSee('Email notifikasi: student1@example.test')
            ->assertSee('<ol>', false)
            ->assertSee('<li>', false)
            ->assertDontSee('<h2>Usulan Judul</h2>', false)
            ->assertSee('class="button button-primary thesis-title-submit"', false)
            ->assertSee('data-dialog-open="thesis-title-dialog"', false)
            ->assertDontSee('<table data-simple-search-table', false)
            ->assertSee('<dialog', false)
            ->assertSee('position: fixed; inset: 0;', false)
            ->assertDontSee('<dialog open', false);
        $this->post(route('theses.titles.store'), [
            'judul' => 'Usulan Judul Baru',
            'deskripsi' => 'Deskripsi usulan penelitian.',
        ])->assertRedirect(route('theses.titles.index'))->assertSessionHas('status');
        $proposal = DB::connection('simak_main')->table('eskripsi_judul')
            ->where('id_mahasiswa', 'student-1')->first();
        $this->assertNotNull($proposal);
        $this->assertSame('Usulan Judul Baru', $proposal->judul);
        $this->assertSame(0, (int) $proposal->status);
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'Usulan Judul Skripsi',
            'id_user' => 'student-1',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'title-proposal',
            'title' => 'Bukti Usulan Proposal Skripsi',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'userprodi',
            'recipient_id' => 'program-user-1',
            'category' => 'title-proposal-created',
            'title' => 'Usulan Judul Baru',
        ], 'simak_main');
        $this->actingAs($this->user('userprodi', [
            'legacy_id' => 'program-user-1',
            'id_prodi' => 'program-1',
        ]));
        $this->getJson(route('theses.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Usulan Judul Baru');
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));

        $this->post(route('theses.titles.store'), [
            'judul' => 'Duplikat',
            'deskripsi' => 'Usulan kedua.',
        ])->assertSessionHasErrors('judul');
        $this->assertSame(1, DB::connection('simak_main')->table('eskripsi_judul')
            ->where('id_mahasiswa', 'student-1')->count());
    }

    public function test_title_validation_rejects_proposal_then_approval_creates_thesis_and_notifies_student(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('eskripsi_skripsi')->where('id_mahasiswa', 'student-1')->delete();
        $connection->table('eskripsi_judul')->insert([
            'id' => 'proposal-validation-flow', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'judul' => 'Judul Untuk Diverifikasi',
            'deskripsi' => 'Deskripsi untuk verifikasi', 'status' => 0,
            'tanggal_usul' => now()->subDays(2)->toDateString(),
        ]);
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.titles.validation.index'))->assertOk()
            ->assertSee('Judul Untuk Diverifikasi')
            ->assertSee('Verifikasi Data')
            ->assertSee('title-proposal-status-0', false)
            ->assertSee('data-datatable="thesis-list"', false);
        $this->put(route('theses.titles.validation.update', 'proposal-validation-flow'), [
            'status' => 2,
            'komentar' => 'Mohon pertegas fokus penelitian.',
        ])->assertRedirect(route('theses.titles.validation.show', 'proposal-validation-flow'));
        $this->assertSame(2, (int) $connection->table('eskripsi_judul')
            ->where('id', 'proposal-validation-flow')->value('status'));
        $this->get(route('theses.titles.validation.index'))
            ->assertOk()->assertSee('title-proposal-status-2', false);
        $this->actingAs($this->user('mahasiswa', ['legacy_id' => 'student-1']));
        $this->post(route('theses.titles.store'), [
            'judul' => 'Judul Perbaikan',
            'deskripsi' => 'Deskripsi setelah perbaikan.',
        ])->assertRedirect(route('theses.titles.index'));
        $revisedId = (string) $connection->table('eskripsi_judul')
            ->where('id_mahasiswa', 'student-1')->where('judul', 'Judul Perbaikan')->value('id');

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.titles.validation.show', $revisedId))
            ->assertOk()
            ->assertSee('Dosen Pembimbing 1 *')
            ->assertSee('Dosen Pembimbing 2 (opsional)')
            ->assertSee('Dosen Penguji 1 (opsional)')
            ->assertSee('Dosen Penguji 2 (opsional)')
            ->assertSee('Dosen Penguji 3 (opsional)')
            ->assertSee('data-title-validation-status', false);
        $this->from(route('theses.titles.validation.show', $revisedId))
            ->put(route('theses.titles.validation.update', $revisedId), [
                'status' => 1,
                'komentar' => 'Disetujui.',
            ])->assertRedirect(route('theses.titles.validation.show', $revisedId))
            ->assertSessionHasErrors('pembimbing1');
        $this->assertDatabaseMissing('eskripsi_skripsi', [
            'id_proposal' => $revisedId,
        ], 'simak_main');

        $this->put(route('theses.titles.validation.update', $revisedId), [
            'status' => 1,
            'komentar' => 'Disetujui.',
            'pembimbing1' => 'lecturer-1',
        ])->assertRedirect(route('theses.titles.validation.show', $revisedId));
        $this->assertDatabaseHas('eskripsi_skripsi', [
            'id_proposal' => $revisedId,
            'id_mahasiswa' => 'student-1',
            'judul' => 'Judul Perbaikan',
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => null,
            'penguji1' => null,
            'penguji2' => null,
            'penguji3' => null,
            'status' => 1,
        ], 'simak_main');
        $this->get(route('theses.titles.validation.show', $revisedId))
            ->assertOk()
            ->assertSee('value="lecturer-1" selected', false);
        $this->assertDatabaseHas('eskripsi_judul', [
            'id' => $revisedId,
            'status' => 1,
            'komentar' => 'Disetujui.',
        ], 'simak_main');
        $this->get(route('theses.titles.validation.index'))
            ->assertOk()->assertSee('title-proposal-status-1', false);
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'Validasi Usulan Judul',
            'id_user' => 'userprodi-id',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-1',
            'category' => 'title-validation',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'dosen',
            'recipient_id' => 'lecturer-1',
            'category' => 'lecturer-assignment',
        ], 'simak_main');
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));
    }

    public function test_title_validation_respects_program_scope_and_pimpinan_is_read_only(): void
    {
        DB::connection('simak_main')->table('eskripsi_judul')->insert([
            'id' => 'proposal-readonly-program-1', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'judul' => 'Usulan untuk Operator Prodi',
            'deskripsi' => 'Deskripsi', 'status' => 0, 'tanggal_usul' => now()->toDateString(),
        ]);
        DB::connection('simak_main')->table('eskripsi_judul')->insert([
            'id' => 'proposal-outside-program', 'id_fakultas' => 'faculty-2', 'id_prodi' => 'program-2',
            'id_mahasiswa' => 'student-2', 'judul' => 'Usulan Di Luar Cakupan',
            'deskripsi' => 'Deskripsi', 'status' => 0, 'tanggal_usul' => now()->toDateString(),
        ]);
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.titles.validation.show', 'proposal-outside-program'))->assertNotFound();
        $this->put(route('theses.titles.validation.update', 'proposal-outside-program'), [
            'status' => 1, 'komentar' => 'Tidak boleh.',
        ])->assertNotFound();

        $this->actingAs($this->user('pimuniv'));
        $this->get(route('theses.titles.validation.index', ['program' => 'program-2']))
            ->assertOk()->assertSee('Usulan Di Luar Cakupan')->assertSee('Lihat');
        $this->get(route('theses.titles.validation.show', 'proposal-outside-program'))
            ->assertOk()->assertDontSee('Simpan Verifikasi');
        $this->put(route('theses.titles.validation.update', 'proposal-outside-program'), [
            'status' => 1, 'komentar' => 'Tidak boleh.',
        ])->assertForbidden();

        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('theses.titles.validation.index'))->assertOk()
            ->assertSee('Usulan untuk Operator Prodi');
        $this->get(route('theses.titles.validation.show', 'proposal-readonly-program-1'))
            ->assertOk()->assertDontSee('Simpan Verifikasi');
        $this->get(route('theses.titles.validation.show', 'proposal-outside-program'))->assertNotFound();
        $this->put(route('theses.titles.validation.update', 'proposal-readonly-program-1'), [
            'status' => 1, 'komentar' => 'Tidak boleh.',
        ])->assertForbidden();
    }

    public function test_lecturer_assignments_list_and_update_follow_legacy_flow(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('eskripsi_skripsi')->insert([
            'id' => 'thesis-assignment-target', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'id_proposal' => 'proposal-assignment-target',
            'judul' => 'Judul Sebelum Penugasan', 'status' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $connection->table('eskripsi_judul')->insert([
            'id' => 'proposal-assignment-target', 'id_fakultas' => 'faculty-1', 'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1', 'judul' => 'Judul Sebelum Penugasan',
            'deskripsi' => 'Deskripsi', 'status' => 1, 'tanggal_usul' => now()->toDateString(),
        ]);
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.assignments.index'))->assertOk()
            ->assertSee('Penugasan Dosen Pembimbing dan Penguji')
            ->assertSee('Judul Sebelum Penugasan')
            ->assertSee('data-datatable="thesis-list"', false);
        $this->get(route('theses.assignments.show', [
            'skripsi' => 'thesis-assignment-target',
            'program' => 'program-1',
        ]))->assertOk()->assertSee('Dosen Pembimbing 1')->assertSee('Dosen Penguji 3');

        $this->put(route('theses.assignments.update', 'thesis-assignment-target'), [
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => 'lecturer-2',
            'penguji1' => 'lecturer-3',
            'penguji2' => 'lecturer-4',
            'penguji3' => '',
        ])->assertRedirect(route('theses.assignments.show', [
            'skripsi' => 'thesis-assignment-target',
            'program' => 'program-1',
        ]));
        $this->assertDatabaseHas('eskripsi_skripsi', [
            'id' => 'thesis-assignment-target',
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => 'lecturer-2',
            'penguji1' => 'lecturer-3',
            'penguji2' => 'lecturer-4',
            'penguji3' => null,
            'status' => 1,
        ], 'simak_main');
        $this->assertDatabaseHas('log_aktivitas', [
            'modul' => 'Penugasan Dosen Skripsi',
            'id_user' => 'userprodi-id',
        ], 'simak_main');
        $this->assertSame(4, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'lecturer-assignment')->where('recipient_role', 'dosen')->count());
        $this->assertSame(4, DB::connection('simak_main')->table('eskripsi_notifikasi')
            ->where('category', 'lecturer-assignment')->where('recipient_role', 'dosen')->count());
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));

        $this->put(route('theses.assignments.update', 'thesis-assignment-target'), [
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => '',
            'penguji1' => '',
            'penguji2' => '',
            'penguji3' => '',
        ])->assertRedirect(route('theses.assignments.show', [
            'skripsi' => 'thesis-assignment-target',
            'program' => 'program-1',
        ]));
        $this->assertDatabaseHas('eskripsi_skripsi', [
            'id' => 'thesis-assignment-target',
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => null,
            'penguji1' => null,
            'penguji2' => null,
            'penguji3' => null,
        ], 'simak_main');

        $this->put(route('theses.assignments.title.update', 'thesis-assignment-target'), [
            'judul' => 'Judul Revisi Prodi',
        ])->assertRedirect(route('theses.assignments.show', [
            'skripsi' => 'thesis-assignment-target',
            'program' => 'program-1',
        ]));
        $this->assertDatabaseHas('eskripsi_skripsi', [
            'id' => 'thesis-assignment-target',
            'judul' => 'Judul Revisi Prodi',
        ], 'simak_main');
        $this->assertDatabaseHas('eskripsi_judul', [
            'id' => 'proposal-assignment-target',
            'judul' => 'Judul Revisi Prodi',
        ], 'simak_main');
    }

    public function test_lecturer_assignment_scope_and_read_only_roles_are_enforced(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.assignments.show', ['skripsi' => 'thesis-2', 'program' => 'program-2']))
            ->assertNotFound();
        $this->put(route('theses.assignments.update', 'thesis-2'), [
            'pembimbing1' => 'lecturer-1',
        ])->assertNotFound();

        $this->actingAs($this->user('pimuniv'));
        $this->get(route('theses.assignments.index', ['program' => 'program-1']))
            ->assertOk()->assertSee('Dosen Satu')->assertSee('Lihat');
        $this->get(route('theses.assignments.show', ['skripsi' => 'thesis-1', 'program' => 'program-1']))
            ->assertOk()->assertSee('Susunan Dosen')->assertDontSee('Simpan Penugasan Dosen');
        $this->put(route('theses.assignments.update', 'thesis-1'), [
            'pembimbing1' => 'lecturer-2',
        ])->assertForbidden();

        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('theses.assignments.index'))->assertOk()
            ->assertSee('Penugasan Dosen Pembimbing dan Penguji')
            ->assertSee('Judul Skripsi Satu');
        $this->get(route('theses.assignments.show', [
            'skripsi' => 'thesis-1', 'program' => 'program-1',
        ]))->assertOk()->assertDontSee('Simpan Penugasan Dosen')
            ->assertSee('Susunan Dosen');
        $this->get(route('theses.assignments.show', [
            'skripsi' => 'thesis-2', 'program' => 'program-2',
        ]))->assertNotFound();
        $this->put(route('theses.assignments.update', 'thesis-1'), [
            'pembimbing1' => 'lecturer-2',
        ])->assertForbidden();
    }

    public function test_assignment_save_remains_available_before_optional_notification_table_is_created(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('eskripsi_skripsi')->insert([
            'id' => 'thesis-assignment-without-notifications',
            'id_fakultas' => 'faculty-1',
            'id_prodi' => 'program-1',
            'id_mahasiswa' => 'student-1',
            'id_proposal' => 'proposal-assignment-without-notifications',
            'judul' => 'Judul Penugasan Tanpa Notifikasi',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $connection->getSchemaBuilder()->drop('eskripsi_notifikasi');
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));

        $this->put(route('theses.assignments.update', 'thesis-assignment-without-notifications'), [
            'pembimbing1' => 'lecturer-1',
            'pembimbing2' => '',
            'penguji1' => '',
            'penguji2' => '',
            'penguji3' => '',
        ])->assertRedirect(route('theses.assignments.show', [
            'skripsi' => 'thesis-assignment-without-notifications',
            'program' => 'program-1',
        ]));

        $this->assertDatabaseHas('eskripsi_skripsi', [
            'id' => 'thesis-assignment-without-notifications',
            'pembimbing1' => 'lecturer-1',
            'status' => 1,
        ], 'simak_main');
        Mail::assertSent(ThesisNotificationMail::class, fn ($mail): bool => $mail->hasTo('student1@example.test'));
    }

    public function test_dashboard_and_upload_follow_role_scopes(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('theses.dashboard'))->assertOk()->assertSee('Dashboard E-Skripsi')
            ->assertSee('Visualisasi Tahap Skripsi')
            ->assertSee('<strong>1</strong><span>Membimbing Proposal</span>', false);
        $this->get(route('theses.upload.index'))->assertForbidden();

        $this->actingAs($this->user('pimfak', ['id_fakultas' => 'faculty-1']));
        $this->get(route('theses.dashboard', ['program' => 'program-2']))->assertNotFound();
        $this->get(route('theses.dashboard', ['program' => 'program-1']))->assertOk();

        $this->actingAs($this->user('admin'));
        $this->get(route('theses.upload.index'))
            ->assertOk()
            ->assertSee('Import Data Skripsi')
            ->assertSee('data-thesis-upload-dropzone')
            ->assertSee('data-page-loader-skip')
            ->assertSee('accept=".xlsx"', false);
        $template = $this->get(route('theses.upload.template'))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'filename=format_eskripsi.xlsx',
            $template->headers->get('content-disposition')
        );
        $this->assertStringStartsWith('PK', file_get_contents(resource_path('templates/format_eskripsi.xlsx')));
    }

    public function test_admin_imports_legacy_excel_rows_and_creates_exam_for_status_two(): void
    {
        $spreadsheet = (new Xlsx)->load(resource_path('templates/format_eskripsi.xlsx'));
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('B3', 'NIM-1');
        $sheet->setCellValue('C3', 'Judul baru dari Excel');
        $sheet->setCellValue('D3', 'NIDN-1');
        $sheet->setCellValue('E3', 'NIDN-2');
        $sheet->setCellValue('F3', 'NIDN-3');
        $sheet->setCellValue('I3', '2');
        $path = tempnam(sys_get_temp_dir(), 'thesis-import-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
            $file = UploadedFile::fake()->createWithContent('import.xlsx', file_get_contents($path));
            $this->actingAs($this->user('admin'));
            $this->post(route('theses.upload.store'), ['file' => $file])->assertRedirect()
                ->assertSessionHas('status', 'Import selesai: 1 baris diproses, 0 baris dilewati.');
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        }

        $thesis = DB::connection('simak_main')->table('eskripsi_skripsi')->where('id_mahasiswa', 'student-1')->first();
        $this->assertNotNull($thesis);
        $this->assertSame('Judul baru dari Excel', $thesis->judul);
        $this->assertSame('lecturer-1', $thesis->pembimbing1);
        $this->assertSame(2, (int) $thesis->status);
        $this->assertDatabaseHas('eskripsi_ujian', [
            'id_skripsi' => $thesis->id,
            'jenis_ujian' => 'proposal',
        ], 'simak_main');
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas');
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_fakultas');
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('nama_program_studi');
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('ketua_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_mastermhs', function (Blueprint $table): void {
            $table->string('id_mahasiswa')->primary();
            $table->string('id_prodi');
            $table->string('id_fakultas');
            $table->string('nim');
            $table->string('nama_mahasiswa');
            $table->string('email')->nullable();
            $table->string('id_status_mahasiswa')->default('A');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn');
            $table->string('nama_dosen');
            $table->string('nip')->nullable();
            $table->string('email')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('alamat_feeder')->nullable();
            $table->string('user_feeder')->nullable();
            $table->string('password_feeder')->nullable();
        });
        Schema::connection('simak_main')->create('eskripsi_judul', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_fakultas');
            $table->string('id_prodi');
            $table->string('id_mahasiswa');
            $table->text('judul');
            $table->text('deskripsi');
            $table->integer('status');
            $table->text('komentar')->nullable();
            $table->date('tanggal_usul');
            $table->date('tanggal_verifikasi')->nullable();
        });
        Schema::connection('simak_main')->create('eskripsi_skripsi', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('id_fakultas');
            $table->string('id_prodi');
            $table->string('id_mahasiswa');
            $table->string('id_proposal');
            $table->text('judul');
            $table->string('pembimbing1')->nullable();
            $table->string('pembimbing2')->nullable();
            $table->string('penguji1')->nullable();
            $table->string('penguji2')->nullable();
            $table->string('penguji3')->nullable();
            $table->integer('status');
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
        Schema::connection('simak_main')->create('eskripsi_bimbingan', function (Blueprint $table): void {
            $table->string('id_bimbingan')->primary();
            $table->string('id_skripsi');
            $table->string('id_dosen_tujuan');
            $table->string('pengirim');
            $table->text('pesan');
            $table->string('url_berkas')->nullable();
            $table->dateTime('waktu_kirim');
        });
        Schema::connection('simak_main')->create('eskripsi_ujian', function (Blueprint $table): void {
            $table->string('id_ujian')->primary();
            $table->string('id_skripsi');
            $table->string('jenis_ujian')->nullable();
            $table->dateTime('waktu_ujian')->nullable();
            $table->string('ruang_ujian')->nullable();
            foreach (['nilaipembimbing1', 'nilaipembimbing2', 'nilaipenguji1', 'nilaipenguji2', 'nilaipenguji3'] as $field) {
                $table->integer($field)->nullable();
            }
            foreach (['catatanpembimbing1', 'catatanpembimbing2', 'catatanpenguji1', 'catatanpenguji2', 'catatanpenguji3'] as $field) {
                $table->text($field)->nullable();
            }
            foreach (['accpembimbing1', 'accpembimbing2', 'accpenguji1', 'accpenguji2', 'accpenguji3'] as $field) {
                $table->integer($field)->default(0);
            }
            $table->timestamp('update_at');
            $table->timestamp('create_at');
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
        Schema::connection('simak_main')->create('validasi_dok', function (Blueprint $table): void {
            $table->string('id');
            $table->string('id_dokumen');
            $table->string('jenis_dokumen');
            $table->timestamp('created_at')->useCurrent();
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
            $table->index(['recipient_role', 'recipient_id', 'is_read', 'created_at']);
        });
    }

    private function seedData(): void
    {
        $connection = DB::connection('simak_main');
        $connection->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        $connection->table('s1m4k_masterprodi')->insert([
            ['id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'id_perguruan_tinggi' => 'pt-1', 'nama_program_studi' => 'Program Satu', 'nama_jenjang_pendidikan' => 'S1'],
            ['id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'id_perguruan_tinggi' => 'pt-1', 'nama_program_studi' => 'Program Dua', 'nama_jenjang_pendidikan' => 'S1'],
        ]);
        $connection->table('s1m4k_mastermhs')->insert([
            ['id_mahasiswa' => 'student-1', 'id_prodi' => 'program-1', 'id_fakultas' => 'faculty-1', 'nim' => 'NIM-1', 'nama_mahasiswa' => 'Mahasiswa Satu', 'email' => 'student1@example.test'],
            ['id_mahasiswa' => 'student-2', 'id_prodi' => 'program-2', 'id_fakultas' => 'faculty-2', 'nim' => 'NIM-2', 'nama_mahasiswa' => 'Mahasiswa Dua', 'email' => 'student2@example.test'],
        ]);
        $connection->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'lecturer-1', 'nidn' => 'NIDN-1', 'nama_dosen' => 'Dosen Satu', 'gelar_depan' => 'Dr.', 'gelar_belakang' => null, 'email' => 'lecturer1@example.test'],
            ['id_dosen' => 'lecturer-2', 'nidn' => 'NIDN-2', 'nama_dosen' => 'Dosen Dua', 'gelar_depan' => null, 'gelar_belakang' => null, 'email' => 'lecturer2@example.test'],
            ['id_dosen' => 'lecturer-3', 'nidn' => 'NIDN-3', 'nama_dosen' => 'Dosen Tiga', 'gelar_depan' => null, 'gelar_belakang' => null, 'email' => 'lecturer3@example.test'],
            ['id_dosen' => 'lecturer-4', 'nidn' => 'NIDN-4', 'nama_dosen' => 'Dosen Empat', 'gelar_depan' => null, 'gelar_belakang' => null, 'email' => null],
        ]);
        foreach ([['thesis-1', 'student-1', 'program-1', 'faculty-1', 'lecturer-1', 'lecturer-2'], ['thesis-2', 'student-2', 'program-2', 'faculty-2', 'lecturer-3', null]] as [$id, $student, $program, $faculty, $supervisorOne, $supervisorTwo]) {
            $connection->table('eskripsi_skripsi')->insert([
                'id' => $id,
                'id_fakultas' => $faculty,
                'id_prodi' => $program,
                'id_mahasiswa' => $student,
                'id_proposal' => 'proposal-'.$id,
                'judul' => 'Judul Skripsi '.($id === 'thesis-1' ? 'Satu' : 'Dua'),
                'pembimbing1' => $supervisorOne,
                'pembimbing2' => $supervisorTwo,
                'penguji1' => 'lecturer-3',
                'penguji2' => null,
                'penguji3' => null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createExam(string $thesisId): string
    {
        $id = 'exam-'.$thesisId;
        DB::connection('simak_main')->table('eskripsi_skripsi')->where('id', $thesisId)->update(['status' => 2]);
        DB::connection('simak_main')->table('eskripsi_ujian')->insert([
            'id_ujian' => $id, 'id_skripsi' => $thesisId, 'jenis_ujian' => 'proposal',
            'accpembimbing1' => 0, 'accpembimbing2' => 0, 'accpenguji1' => 0, 'accpenguji2' => 0, 'accpenguji3' => 0,
            'create_at' => now(), 'update_at' => now(),
        ]);

        return $id;
    }

    private function user(string $role, array $extra = []): GenericUser
    {
        return new GenericUser(array_merge([
            'id' => $role.'-thesis-test',
            'legacy_id' => match ($role) {
                'mahasiswa' => 'student-1',
                'dosen' => 'lecturer-1',
                default => $role.'-id',
            },
            'legacy_type' => $role,
            'login' => $role === 'dosen' ? 'NIDN-1' : '',
            'role' => $role,
            'name' => 'Pengguna Skripsi',
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
        ], $extra));
    }
}
