<?php

namespace Tests\Feature;

use App\Services\SimakNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimakLmsNotificationTest extends TestCase
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

        Schema::connection('simak_main')->create('s1m4k_kalender', function (Blueprint $table): void {
            $table->string('id_kalender')->primary();
            $table->string('status');
        });
        Schema::connection('simak_main')->create('s1m4k_jadwal', function (Blueprint $table): void {
            $table->string('id_jadwal')->primary();
            $table->string('id_kalender');
            $table->string('id_matkul');
        });
        Schema::connection('simak_main')->create('s1m4k_krs', function (Blueprint $table): void {
            $table->string('id_jadwal');
            $table->string('id_kalender');
            $table->string('id_mahasiswa');
        });
        Schema::connection('simak_main')->create('s1m4k_pengesahankrs', function (Blueprint $table): void {
            $table->string('id_mahasiswa');
            $table->string('id_kalender');
            $table->string('yang_mengesahkan')->nullable();
            $table->string('nama_kpro')->nullable();
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

    public function test_course_notification_targets_only_approved_students_in_active_calendars(): void
    {
        DB::connection('simak_main')->table('s1m4k_kalender')->insert([
            ['id_kalender' => 'calendar-active', 'status' => 'Y'],
            ['id_kalender' => 'calendar-inactive', 'status' => 'N'],
        ]);
        DB::connection('simak_main')->table('s1m4k_jadwal')->insert([
            ['id_jadwal' => 'schedule-active', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-1'],
            ['id_jadwal' => 'schedule-inactive', 'id_kalender' => 'calendar-inactive', 'id_matkul' => 'course-1'],
            ['id_jadwal' => 'schedule-other-course', 'id_kalender' => 'calendar-active', 'id_matkul' => 'course-2'],
        ]);
        DB::connection('simak_main')->table('s1m4k_krs')->insert([
            ['id_jadwal' => 'schedule-active', 'id_kalender' => 'calendar-active', 'id_mahasiswa' => 'student-approved'],
            ['id_jadwal' => 'schedule-active', 'id_kalender' => 'calendar-active', 'id_mahasiswa' => 'student-pending'],
            ['id_jadwal' => 'schedule-inactive', 'id_kalender' => 'calendar-inactive', 'id_mahasiswa' => 'student-old'],
            ['id_jadwal' => 'schedule-other-course', 'id_kalender' => 'calendar-active', 'id_mahasiswa' => 'student-other-course'],
        ]);
        DB::connection('simak_main')->table('s1m4k_pengesahankrs')->insert([
            ['id_mahasiswa' => 'student-approved', 'id_kalender' => 'calendar-active', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi'],
            ['id_mahasiswa' => 'student-old', 'id_kalender' => 'calendar-inactive', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi'],
            ['id_mahasiswa' => 'student-other-course', 'id_kalender' => 'calendar-active', 'yang_mengesahkan' => 'Dosen PA', 'nama_kpro' => 'Kaprodi'],
        ]);

        app(SimakNotificationService::class)->sendApprovedCourseStudents(
            'course-1',
            'lms-material-published',
            'Materi Baru',
            'Materi tersedia.',
            '/lms/materi/material-1',
        );

        $this->assertDatabaseCount('eskripsi_notifikasi', 1, 'simak_main');
        $this->assertDatabaseHas('eskripsi_notifikasi', [
            'recipient_role' => 'mahasiswa',
            'recipient_id' => 'student-approved',
            'category' => 'lms-material-published',
        ], 'simak_main');
        $this->assertDatabaseMissing('eskripsi_notifikasi', [
            'recipient_id' => 'student-pending',
        ], 'simak_main');
        $this->assertDatabaseMissing('eskripsi_notifikasi', [
            'recipient_id' => 'student-old',
        ], 'simak_main');
        $this->assertDatabaseMissing('eskripsi_notifikasi', [
            'recipient_id' => 'student-other-course',
        ], 'simak_main');
    }
}
