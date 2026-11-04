<?php

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EkurikulumTest extends TestCase
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
        $this->seedPrograms();
    }

    public function test_program_manager_can_manage_only_its_curriculum_team(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('ekurikulum.teams.index'))
            ->assertOk()
            ->assertSee('Manajemen Tim Kurikulum')
            ->assertSee('data-dialog-open="create-team-dialog"', false);
        $this->post(route('ekurikulum.teams.store'), [
            'username' => '',
            'password' => 'initial-secret',
            'nama_lengkap' => 'Anggota Baru',
            'email' => 'baru@example.test',
        ])->assertRedirect(route('ekurikulum.teams.index', ['modal' => 'create']));
        $this->get(route('ekurikulum.teams.index', ['modal' => 'create']))
            ->assertOk()
            ->assertSee('data-open-on-load="true"', false);
        $this->post(route('ekurikulum.teams.store'), [
            'username' => 'tim-kurikulum',
            'password' => 'initial-secret',
            'nama_lengkap' => 'Anggota Tim',
            'email' => 'tim@example.test',
            'no_telp' => '0800',
        ])->assertRedirect(route('ekurikulum.teams.index'));
        $team = DB::connection('simak_main')->table('s1m4k_admin')->where('username', 'tim-kurikulum')->first();
        $this->assertSame('program-1', $team->id_prodi);
        $this->assertSame('timkur', $team->level);
        $this->assertTrue(Hash::check('initial-secret', $team->password));
        $this->put(route('ekurikulum.teams.update', $team->id_admin), [
            'nama_lengkap' => 'Anggota Tim Baru',
            'email' => 'tim-baru@example.test',
            'no_telp' => '0811',
            'password' => '',
            'blokir' => 'N',
        ])->assertRedirect(route('ekurikulum.teams.index'));
        $this->put(route('ekurikulum.teams.update', $team->id_admin), [
            'nama_lengkap' => '',
            'email' => 'tim-baru@example.test',
            'blokir' => 'N',
        ])->assertRedirect(route('ekurikulum.teams.index', ['modal' => 'edit', 'account' => $team->id_admin]));
        $this->get(route('ekurikulum.teams.index', ['modal' => 'edit', 'account' => $team->id_admin]))
            ->assertOk()
            ->assertSee('data-open-on-load="true"', false);

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-2']));
        $this->get(route('ekurikulum.teams.index'))->assertOk()->assertDontSee('tim-kurikulum');
        $this->put(route('ekurikulum.teams.update', $team->id_admin), [
            'nama_lengkap' => 'Tidak boleh lintas prodi',
            'email' => 'cross@example.test',
            'blokir' => 'N',
        ])->assertNotFound();
        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('ekurikulum.teams.index'))->assertForbidden();
    }

    public function test_profile_and_curriculum_access_are_scoped_and_prodi_can_edit(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->get(route('ekurikulum.profile.index'))
            ->assertOk()
            ->assertSee('Program Satu')
            ->assertSee('Universitas Khairun')
            ->assertSee('data-dialog-open="edit-program-profile"', false)
            ->assertSee('class="form-control profile-textarea"', false)
            ->assertSee('Gambarkan arah dan keunggulan keilmuan program studi.')
            ->assertSee('Jelaskan langkah utama untuk mewujudkan visi program studi.');
        $this->put(route('ekurikulum.profile.update'), [
            'izin_pendirian' => str_repeat('x', 256),
            'peringkat_akreditasi' => 'Baik Sekali',
        ])->assertRedirect(route('ekurikulum.profile.index', ['modal' => 'edit']));
        $this->get(route('ekurikulum.profile.index', ['modal' => 'edit']))
            ->assertOk()
            ->assertSee('id="edit-program-profile" data-open-on-load="true"', false);
        $this->get(route('ekurikulum.profile.index'))
            ->assertOk()
            ->assertDontSee('Program Dua');
        $this->put(route('ekurikulum.profile.update'), [
            'izin_pendirian' => 'SK-123',
            'peringkat_akreditasi' => 'Baik Sekali',
            'gelar_lulusan' => 'S.Kom.',
            'visi_keilmuan' => 'Visi prodi satu',
            'misi_prodi' => 'Misi prodi satu',
        ])->assertRedirect(route('ekurikulum.profile.index'));
        $this->assertDatabaseHas('s1m4k_masterprodi', [
            'id_prodi' => 'program-1',
            'visi_keilmuan' => 'Visi prodi satu',
        ], 'simak_main');

        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Kurikulum OBE Program Satu',
            'tahun' => '2026',
        ])->assertRedirect();
        $curriculumId = DB::connection('simak_main')->table('ekur_kurikulum')->value('id_kur');
        $this->assertDatabaseHas('ekur_kurikulum', [
            'id_kur' => $curriculumId,
            'id_prodi' => 'program-1',
            'id_fakultas' => 'faculty-1',
            'visi_keilmuan' => 'Visi prodi satu',
            'misi_prodi' => 'Misi prodi satu',
            'status_kur' => 'Draf',
        ], 'simak_main');
        $this->get(route('ekurikulum.curricula.index'))
            ->assertOk()
            ->assertSee('Kurikulum OBE Program Satu')
            ->assertSee('data-dialog-open="create-curriculum-dialog"', false)
            ->assertDontSee('Program Dua');
        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => '',
            'tahun' => '2026',
        ])->assertRedirect(route('ekurikulum.curricula.index', ['modal' => 'create']));
        $this->get(route('ekurikulum.curricula.index', ['modal' => 'create']))
            ->assertOk()
            ->assertSee('id="create-curriculum-dialog" data-open-on-load="true"', false);
        $this->get(route('ekurikulum.curricula.show', $curriculumId))
            ->assertOk()
            ->assertSee('Wizard Kurikulum OBE')
            ->assertSee('data-wizard-panel="final"', false)
            ->assertSee('id="ekur-curriculum-import-dialog"', false)
            ->assertSee('data-ekur-upload-form', false)
            ->assertSee('data-ekur-dropzone', false)
            ->assertSee('data-ekur-modal-form="Atur sebaran semester"', false)
            ->assertSee('width="480" height="170"', false);

        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-2']));
        $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertNotFound();
        $this->put(route('ekurikulum.profile.update'), [
            'visi_keilmuan' => 'Visi prodi dua yang diperbarui',
        ])->assertRedirect(route('ekurikulum.profile.index'));
        $this->assertDatabaseHas('s1m4k_masterprodi', [
            'id_prodi' => 'program-1',
            'visi_keilmuan' => 'Visi prodi satu',
        ], 'simak_main');
    }

    public function test_obe_workflow_persists_mappings_distribution_and_rps(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Kurikulum Uji OBE',
            'tahun' => '2026',
        ])->assertRedirect();
        $curriculumId = (string) DB::connection('simak_main')->table('ekur_kurikulum')->value('id_kur');

        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'profiles']), [
            'kode_pl' => 'PL01',
            'profil_lulusan' => 'Insinyur perangkat lunak',
            'deskripsi_pl' => 'Mampu mengembangkan sistem.',
            'aspek' => ['Pengetahuan', 'Keterampilan Khusus'],
            'referensi' => 'SN-DIKTI',
        ])->assertRedirect();
        $profileId = DB::connection('simak_main')->table('ekur_profil_lulusan')->value('id_pl');
        $this->assertDatabaseHas('ekur_profil_lulusan', ['id_pl' => $profileId, 'kode_pl' => 'PL-01'], 'simak_main');
        $this->put(route('ekurikulum.data.update', [
            'curriculum' => $curriculumId,
            'entity' => 'profiles',
            'record' => $profileId,
        ]), [
            'kode_pl' => 'PL-99',
            'profil_lulusan' => 'Insinyur perangkat lunak',
            'deskripsi_pl' => 'Mampu mengembangkan sistem.',
            'aspek' => ['Pengetahuan', 'Keterampilan Khusus'],
            'referensi' => 'SN-DIKTI',
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_profil_lulusan', ['id_pl' => $profileId, 'kode_pl' => 'PL-01'], 'simak_main');

        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpls']), [
            'kode_cpl' => 'CPL01',
            'aspek' => 'Pengetahuan',
            'deskripsi_cpl' => 'Menguasai konsep ilmu.',
            'referensi' => 'SN-DIKTI',
            'id_pl' => [$profileId],
        ])->assertRedirect();
        $cplId = DB::connection('simak_main')->table('ekur_cpl')->value('id_cpl');
        $this->assertDatabaseHas('ekur_cpl', ['id_cpl' => $cplId, 'kode_cpl' => 'CPL-01'], 'simak_main');
        $this->assertDatabaseHas('ekur_map_cpl_pl', ['id_cpl' => $cplId, 'id_pl' => $profileId], 'simak_main');
        $curriculumPage = $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk();
        $this->assertStringContainsString(
            '<td>CPL-01</td><td>Pengetahuan</td><td>Menguasai konsep ilmu.</td><td>PL-01</td><td>SN-DIKTI</td>',
            $curriculumPage->getContent()
        );

        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'bks']), [
            'kode_bk' => 'BK01',
            'nama_bk' => 'Rekayasa perangkat lunak',
            'referensi' => 'Buku OBE',
            'deskripsi_bk' => 'Dasar rekayasa perangkat lunak.',
            'kompetensi' => 'Utama',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $bkId = DB::connection('simak_main')->table('ekur_bk')->value('id_bk');
        $this->assertDatabaseHas('ekur_bk', ['id_bk' => $bkId, 'kode_bk' => 'BK-01'], 'simak_main');
        $curriculumPage = $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk();
        $this->assertStringContainsString(
            '<td>BK-01</td><td>Rekayasa perangkat lunak<span class="subtext">Dasar rekayasa perangkat lunak.</span></td><td>Utama</td><td>CPL-01</td><td>Buku OBE</td>',
            $curriculumPage->getContent()
        );

        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'courses']), [
            'kode_mk' => 'IF101',
            'kode_mk_live' => 'IF101',
            'nama_mk' => 'Pengantar Informatika',
            'sks' => 3,
            'sks_teori' => 2,
            'sks_praktek' => 1,
            'kategori_mk' => 'Wajib Prodi',
            'is_capstone' => 'Tidak',
            'id_bk' => [$bkId],
        ])->assertRedirect();
        $courseId = DB::connection('simak_main')->table('ekur_draft_mk')->value('id_mk_draft');
        $this->assertDatabaseHas('ekur_draft_mk', ['id_mk_draft' => $courseId, 'kode_mk' => 'MK-001'], 'simak_main');
        $this->assertDatabaseHas('ekur_map_mk_cpl', ['id_mk_draft' => $courseId, 'id_cpl' => $cplId], 'simak_main');
        $curriculumPage = $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk();
        $this->assertStringContainsString(
            '<td>IF101</td><td>Pengantar Informatika</td><td>3</td><td>Wajib</td><td>BK-01</td>',
            $curriculumPage->getContent()
        );
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'distribution']), [
            'semester' => [$courseId => 1],
            'jenis_mk' => [$courseId => 'Wajib'],
            'prasyarat' => [],
        ])->assertRedirect();
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'finalize']))
            ->assertRedirect();
        $this->assertDatabaseHas('ekur_kurikulum', ['id_kur' => $curriculumId, 'status_kur' => 'Final'], 'simak_main');

        $this->get(route('ekurikulum.rps.show', $curriculumId))->assertOk()->assertSee('Perangkat RPS');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'subcpmks']), [
            'id_mk_draft' => $courseId,
        ])->assertUnprocessable();
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpmks']), [
            'id_mk_draft' => $courseId,
            'kode_cpmk' => 'CPMK01',
            'deskripsi_cpmk' => 'Menerapkan konsep dasar.',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $this->get(route('ekurikulum.rps.show', $curriculumId))
            ->assertOk()
            ->assertSee('data-wizard-type="rps"', false)
            ->assertDontSee('id="rps-course"', false)
            ->assertSee('MK01')
            ->assertSee('data-rps-code-source="cpl"', false)
            ->assertSee('data-rps-generated-code="cpmk"', false)
            ->assertSee('data-wizard-panel="rps-info"', false)
            ->assertSee('data-map-storage-key="ekur-map:rps-'.$curriculumId.'-integration"', false)
            ->assertSee('data-map-storage-key="ekur-map:rps-'.$curriculumId.'-cpmk"', false)
            ->assertSee('data-map-storage-key="ekur-map:rps-'.$curriculumId.'-subcpmk"', false)
            ->assertSee('data-map-storage-key="ekur-map:rps-'.$curriculumId.'-assessment"', false)
            ->assertSee('data-map-storage-key="ekur-map:rps-'.$curriculumId.'-completeness"', false)
            ->assertSee('id="ekur-rps-import-dialog"', false)
            ->assertSee('data-ekur-upload-form', false)
            ->assertSee('width="158" height="42"', false);
        $cpmkId = DB::connection('simak_main')->table('ekur_cpmk')->value('id_cpmk');
        $this->assertDatabaseHas('ekur_cpmk', ['id_cpmk' => $cpmkId, 'kode_cpmk' => 'CPMK011'], 'simak_main');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'assessments']), [
            'id_mk_draft' => $courseId,
            'nilai' => [],
        ])->assertUnprocessable();
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'subcpmks']), [
            'id_mk_draft' => $courseId,
            'kode_subcpmk' => 'SUB0101',
            'deskripsi_subcpmk' => 'Menjelaskan konsep.',
            'id_cpmk' => [$cpmkId],
        ])->assertRedirect();
        $subcpmkId = DB::connection('simak_main')->table('ekur_subcpmk')->value('id_subcpmk');
        $this->assertDatabaseHas('ekur_subcpmk', ['id_subcpmk' => $subcpmkId, 'kode_subcpmk' => 'SUB0111'], 'simak_main');
        $this->assertDatabaseHas('ekur_map_subcpmk_cpmk', [
            'id_subcpmk' => $subcpmkId,
            'id_cpmk' => $cpmkId,
        ], 'simak_main');
        $this->putJson(route('ekurikulum.data.update', [
            'curriculum' => $curriculumId,
            'entity' => 'subcpmks',
            'record' => $subcpmkId,
        ]), [
            'id_mk_draft' => $courseId,
            'kode_subcpmk' => 'SUB9999',
            'deskripsi_subcpmk' => 'Menjelaskan konsep.',
            'id_cpmk' => [$cpmkId],
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_subcpmk', [
            'id_subcpmk' => $subcpmkId,
            'kode_subcpmk' => 'SUB0111',
        ], 'simak_main');
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'subcpmks']), [
            'id_mk_draft' => $courseId,
            'kode_subcpmk' => 'SUB0102',
            'deskripsi_subcpmk' => 'Menerapkan konsep.',
            'id_cpmk' => [$cpmkId],
        ])->assertRedirect();
        $secondSubcpmkId = DB::connection('simak_main')->table('ekur_subcpmk')
            ->where('kode_subcpmk', 'SUB0112')->value('id_subcpmk');

        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'assessments']), [
            'id_mk_draft' => $courseId,
            'nilai' => [$subcpmkId => [
                'partisipasi' => 10,
                'project' => 10,
                'tugas' => 20,
                'kuis' => 10,
                'uts' => 20,
                'uas' => 29,
            ]],
        ])->assertUnprocessable();
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'assessments']), [
            'id_mk_draft' => $courseId,
            'nilai' => [$subcpmkId => [
                'partisipasi' => 10,
                'project' => 10,
                'tugas' => 20,
                'kuis' => 10,
                'uts' => 20,
                'uas' => 30,
            ]],
        ])->assertUnprocessable();
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'assessments']), [
            'id_mk_draft' => $courseId,
            'nilai' => [
                $subcpmkId => [
                    'partisipasi' => 10,
                    'project' => 10,
                    'tugas' => 20,
                    'kuis' => 10,
                    'uts' => 20,
                    'uas' => 30,
                ],
                $secondSubcpmkId => [
                    'partisipasi' => 0,
                    'project' => 0,
                    'tugas' => 0,
                    'kuis' => 0,
                    'uts' => 0,
                    'uas' => 0,
                ],
            ],
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_penilaian', ['id_subcpmk' => $subcpmkId, 'uas' => 30], 'simak_main');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'materials']), [
            'id_mk_draft' => $courseId,
            'minggu_ke' => ['Minggu ke-1', 'Minggu ke-2'],
            'kemampuan_akhir' => [(string) $subcpmkId],
            'indikator' => 'Indikator uji',
            'kriteria_teknik' => 'Rubrik observasi',
            'bentuk_pembelajaran' => 'Diskusi dan praktik',
            'materi_pembelajaran' => 'Konsep dasar',
            'pustaka_pilihan' => ['Pustaka Utama', 'Pustaka Pendukung'],
            'bobot_penilaian' => 20,
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_rps_materi', [
            'id_mk_draft' => $courseId,
            'minggu_ke' => 'Minggu ke-1, Minggu ke-2',
            'kemampuan_akhir' => (string) $subcpmkId,
            'pustaka_pilihan' => 'Pustaka Utama, Pustaka Pendukung',
        ], 'simak_main');
        $this->get(route('ekurikulum.rps.show', $curriculumId))
            ->assertOk()
            ->assertSee('name="dsn_pengembang"', false)
            ->assertSee('name="koor_rmk"', false)
            ->assertSee('name="koor_prodi"', false)
            ->assertSee('name="wd1"', false)
            ->assertSee('value="dosen-1"', false);
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'completeness']), [
            'id_mk_draft' => $courseId,
            'rumpun_mk' => 'Rekayasa Perangkat Lunak',
            'deskripsi_singkat' => 'Pengantar informatika.',
            'dosen_pengampu' => ['dosen-1'],
            'dsn_pengembang' => 'dosen-1',
            'koor_rmk' => 'dosen-1',
            'koor_prodi' => 'dosen-1',
            'wd1' => 'dosen-1',
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_rps_kelengkapan', [
            'id_mk_draft' => $courseId,
            'dosen_pengampu' => 'dosen-1',
            'dsn_pengembang' => 'dosen-1',
        ], 'simak_main');
        $exportResponse = $this->get(route('ekurikulum.rps.export', [$curriculumId, $courseId]))->assertOk();
        $exportPath = tempnam(sys_get_temp_dir(), 'ekur-export-');
        file_put_contents($exportPath, $exportResponse->streamedContent());
        $exportedSpreadsheet = IOFactory::load($exportPath);
        $this->assertSame([
            'Identitas', 'CPMK', 'Sub-CPMK', 'Penilaian', 'Rencana Mingguan', 'Kelengkapan RPS',
        ], $exportedSpreadsheet->getSheetNames());
        $this->assertSame(['Kode MK Live', 'Kode CPMK', 'Deskripsi CPMK', 'Relasi Kode CPL'], $exportedSpreadsheet->getSheetByName('CPMK')->rangeToArray('A1:D1')[0]);
        $this->assertSame(['Kode MK Live', 'Kode Sub-CPMK', 'Deskripsi Sub-CPMK', 'Relasi Kode CPMK'], $exportedSpreadsheet->getSheetByName('Sub-CPMK')->rangeToArray('A1:D1')[0]);
        $this->assertSame(['Kode MK Live', 'Kode Sub-CPMK', 'Partisipasi (%)', 'Proyek (%)', 'Tugas (%)', 'Kuis (%)', 'UTS (%)', 'UAS (%)'], $exportedSpreadsheet->getSheetByName('Penilaian')->rangeToArray('A1:H1')[0]);
        $rpsRoundTrip = new UploadedFile(
            $exportPath,
            'rps-export.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
        $this->post(route('ekurikulum.import', [$curriculumId, 'rps']), [
            'mk' => $courseId,
            'file' => $rpsRoundTrip,
        ])->assertRedirect();
        unlink($exportPath);
        $this->get(route('ekurikulum.rps.print', [$curriculumId, $courseId]))
            ->assertOk()
            ->assertSee('RENCANA PEMBELAJARAN SEMESTER')
            ->assertSee('images/landing/default-campus-logo.png')
            ->assertSee('CPMK01')
            ->assertSee('Dosen Satu');
        $this->get(route('ekurikulum.template', 'rps'))->assertOk();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('CPMK');
        $spreadsheet->getActiveSheet()->fromArray([
            ['Kode MK Live', 'Kode CPMK', 'Deskripsi CPMK', 'Relasi Kode CPL'],
            ['IF101', 'CPMK011', 'Deskripsi dari template', 'CPL-01'],
        ]);
        $subcpmkSheet = $spreadsheet->createSheet();
        $subcpmkSheet->setTitle('Sub-CPMK');
        $subcpmkSheet->fromArray([
            ['Kode MK Live', 'Kode Sub-CPMK', 'Deskripsi Sub-CPMK', 'Relasi Kode CPMK'],
            ['IF101', 'SUB0111', 'Sub-CPMK dari template', 'CPMK011'],
        ]);
        $assessmentSheet = $spreadsheet->createSheet();
        $assessmentSheet->setTitle('Penilaian');
        $assessmentSheet->fromArray([
            ['Kode MK Live', 'Kode Sub-CPMK', 'Partisipasi', 'Proyek', 'Tugas', 'Kuis', 'UTS', 'UAS'],
            ['IF101', 'SUB0111', 5, 10, 15, 10, 25, 35],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'ekur-rps-');
        (new Xlsx($spreadsheet))->save($path);
        $uploadedFile = new UploadedFile(
            $path,
            'rps.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
        $this->post(route('ekurikulum.import', [$curriculumId, 'rps']), [
            'mk' => $courseId,
            'file' => $uploadedFile,
        ])->assertRedirect();
        unlink($path);
        $this->assertDatabaseHas('ekur_cpmk', [
            'id_cpmk' => $cpmkId,
            'deskripsi_cpmk' => 'Deskripsi dari template',
        ], 'simak_main');
        $this->assertDatabaseHas('ekur_subcpmk', [
            'id_subcpmk' => $subcpmkId,
            'deskripsi_subcpmk' => 'Sub-CPMK dari template',
        ], 'simak_main');
        $this->assertDatabaseHas('ekur_penilaian', [
            'id_subcpmk' => $subcpmkId,
            'partisipasi' => 5,
            'project' => 10,
            'tugas' => 15,
            'kuis' => 10,
            'uts' => 25,
            'uas' => 35,
        ], 'simak_main');
        $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk()->assertSee('Kurikulum Uji OBE');
    }

    public function test_curriculum_stage_gates_finalization_and_legacy_export_round_trip(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Kurikulum Validasi',
            'tahun' => '2026',
        ])->assertRedirect();
        $curriculumId = (string) DB::connection('simak_main')->table('ekur_kurikulum')->value('id_kur');
        $this->get(route('ekurikulum.curricula.show', $curriculumId))
            ->assertOk()
            ->assertSee('data-wizard-step="final" data-wizard-locked="true"', false);
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpls']), [
            'kode_cpl' => 'CPL01',
            'aspek' => 'Pengetahuan',
            'deskripsi_cpl' => 'Menguasai konsep.',
            'id_pl' => [1],
        ])->assertUnprocessable();

        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'profiles']), [
            'kode_pl' => 'PL01',
            'profil_lulusan' => 'Analis sistem',
            'deskripsi_pl' => 'Mampu menganalisis sistem.',
            'aspek' => ['Pengetahuan'],
            'referensi' => 'SN-DIKTI',
        ])->assertRedirect();
        $profileId = DB::connection('simak_main')->table('ekur_profil_lulusan')->value('id_pl');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpls']), [
            'kode_cpl' => 'CPL01',
            'aspek' => 'Pengetahuan',
            'deskripsi_cpl' => 'Menguasai konsep.',
            'id_pl' => [$profileId],
        ])->assertRedirect();
        $cplId = DB::connection('simak_main')->table('ekur_cpl')->value('id_cpl');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'courses']), [
            'kode_mk' => 'MK01',
            'nama_mk' => 'Analisis Sistem',
            'sks' => 3,
            'sks_teori' => 3,
            'sks_praktek' => 0,
            'is_capstone' => 'Tidak',
            'id_bk' => [1],
        ])->assertUnprocessable();

        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'bks']), [
            'kode_bk' => 'BK01',
            'nama_bk' => 'Analisis dan perancangan',
            'kompetensi' => 'Utama',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $bkId = DB::connection('simak_main')->table('ekur_bk')->value('id_bk');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'bks']), [
            'kode_bk' => 'BK02',
            'nama_bk' => 'Bahan kajian tambahan',
            'kompetensi' => 'Pendukung',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $courseIds = [];
        foreach ([['MK01', 'MK-001', 'Analisis Sistem'], ['MK02', 'MK-002', 'Perancangan Sistem']] as [$liveCode, $draftCode, $name]) {
            $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'courses']), [
                'kode_mk_live' => $liveCode,
                'nama_mk' => $name,
                'sks' => 3,
                'sks_teori' => 3,
                'sks_praktek' => 0,
                'is_capstone' => 'Tidak',
                'id_bk' => [$bkId],
            ])->assertRedirect();
            $courseIds[] = DB::connection('simak_main')->table('ekur_draft_mk')
                ->where('id_kur', $curriculumId)->where('kode_mk', $draftCode)->value('id_mk_draft');
        }
        [$firstCourse, $secondCourse] = $courseIds;
        $distributionRoute = route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'distribution']);
        $this->postJson($distributionRoute, [
            'semester' => [$firstCourse => 9, $secondCourse => 1],
            'jenis_mk' => [$firstCourse => 'Wajib', $secondCourse => 'Wajib'],
        ])->assertUnprocessable();
        $this->postJson($distributionRoute, [
            'semester' => [$firstCourse => 1, $secondCourse => 2],
            'jenis_mk' => [$firstCourse => 'Wajib', $secondCourse => 'Wajib'],
            'prasyarat' => [$firstCourse => [$firstCourse]],
        ])->assertUnprocessable();
        $this->postJson($distributionRoute, [
            'semester' => [$firstCourse => 1, $secondCourse => 2],
            'jenis_mk' => [$firstCourse => 'Wajib', $secondCourse => 'Wajib'],
            'prasyarat' => [$firstCourse => [$secondCourse]],
        ])->assertRedirect();

        $this->get(route('ekurikulum.curricula.show', $curriculumId))
            ->assertOk()
            ->assertSee('name="kode_pl" value="PL-02" readonly', false)
            ->assertSee('name="kode_cpl" value="CPL-02" readonly', false)
            ->assertSee('name="kode_bk" value="BK-03" readonly', false)
            ->assertSee('name="kode_mk" value="MK-003" readonly', false)
            ->assertSee('Peta Profil Lulusan')
            ->assertSee('Finalisasi bersifat permanen.')
            ->assertSee('data-wizard-step="final" data-wizard-locked="false"', false)
            ->assertSee('data-map-filename="Peta-PL-'.$curriculumId.'"', false)
            ->assertSee('data-map-filename="Peta-PL-CPL-'.$curriculumId.'"', false)
            ->assertSee('data-map-filename="Peta-PL-CPL-BK-'.$curriculumId.'"', false)
            ->assertSee('data-map-filename="Peta-PL-CPL-BK-MK-'.$curriculumId.'"', false)
            ->assertSee('data-map-filename="Peta-Sebaran-'.$curriculumId.'"', false)
            ->assertSee('data-map-filename="Peta-Final-'.$curriculumId.'"', false)
            ->assertSee('Ekspor data kurikulum');
        $rpsNotFinalMessage = 'RPS hanya dapat disusun setelah kurikulum difinalisasi.';
        $this->get(route('ekurikulum.rps.show', $curriculumId))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $this->get(route('ekurikulum.rps.print', [$curriculumId, $firstCourse]))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $this->get(route('ekurikulum.rps.export', [$curriculumId, $firstCourse]))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $this->post(route('ekurikulum.import', [$curriculumId, 'rps']))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpmks']))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $this->delete(route('ekurikulum.data.delete', [
            'curriculum' => $curriculumId,
            'entity' => 'cpmks',
            'record' => '999',
        ]))
            ->assertRedirect(route('ekurikulum.curricula.show', $curriculumId))
            ->assertSessionHas('status', $rpsNotFinalMessage);
        $notificationPage = $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk();
        $this->assertSame(1, substr_count($notificationPage->getContent(), $rpsNotFinalMessage));

        $this->post(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'finalize']))
            ->assertRedirect();
        $this->put(route('ekurikulum.data.update', ['curriculum' => $curriculumId, 'entity' => 'profiles', 'record' => $profileId]), [
            'profil_lulusan' => 'Data struktural terkunci',
            'deskripsi_pl' => 'Tidak boleh berubah.',
            'aspek' => ['Pengetahuan'],
        ])->assertForbidden();
        $this->get(route('ekurikulum.rps.show', $curriculumId))
            ->assertOk()
            ->assertSee('data-wizard-type="rps"', false)
            ->assertDontSee('id="rps-course"', false)
            ->assertSee('MK01')
            ->assertSee('MK02')
            ->assertSee('data-wizard-panel="rps-info"', false)
            ->assertSee('data-wizard-panel="rps-cpmk"', false)
            ->assertSee('data-wizard-panel="rps-subcpmk"', false)
            ->assertSee('data-wizard-panel="rps-assessment"', false)
            ->assertSee('data-wizard-panel="rps-completeness"', false);
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpmks']), [
            'id_mk_draft' => $firstCourse,
            'kode_cpmk' => 'CPMK01',
            'deskripsi_cpmk' => 'Capaian mata kuliah pertama.',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $cpmkId = (string) DB::connection('simak_main')->table('ekur_cpmk')->value('id_cpmk');
        $this->assertDatabaseHas('ekur_cpmk', ['id_cpmk' => $cpmkId, 'kode_cpmk' => 'CPMK011'], 'simak_main');
        $this->postJson(route('ekurikulum.data.store', ['curriculum' => $curriculumId, 'entity' => 'cpmks']), [
            'id_mk_draft' => $secondCourse,
            'kode_cpmk' => 'CPMK01',
            'deskripsi_cpmk' => 'Kode CPMK harus unik dalam kurikulum.',
            'id_cpl' => [$cplId],
        ])->assertRedirect();
        $this->assertDatabaseHas('ekur_cpmk', [
            'id_mk_draft' => $secondCourse,
            'kode_cpmk' => 'CPMK012',
        ], 'simak_main');
        $this->putJson(route('ekurikulum.data.update', [
            'curriculum' => $curriculumId,
            'entity' => 'cpmks',
            'record' => $cpmkId,
        ]), [
            'id_mk_draft' => $secondCourse,
            'kode_cpmk' => 'CPMK01',
            'deskripsi_cpmk' => 'Pemindahan tidak diizinkan.',
            'id_cpl' => [$cplId],
        ])->assertUnprocessable();

        $exportResponse = $this->get(route('ekurikulum.curricula.export', $curriculumId))->assertOk();
        $exportPath = tempnam(sys_get_temp_dir(), 'ekur-curriculum-export-');
        file_put_contents($exportPath, $exportResponse->streamedContent());
        $exportedSpreadsheet = IOFactory::load($exportPath);
        $this->assertSame(['Profil Lulusan', 'CPL', 'Bahan Kajian', 'Mata Kuliah'], $exportedSpreadsheet->getSheetNames());
        $this->assertSame('PL-01', $exportedSpreadsheet->getSheetByName('CPL')->getCell('E2')->getValue());
        $this->assertSame('BK-01', $exportedSpreadsheet->getSheetByName('Mata Kuliah')->getCell('I2')->getValue());

        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Kurikulum Hasil Impor',
            'tahun' => '2027',
        ])->assertRedirect();
        $targetCurriculumId = (string) DB::connection('simak_main')->table('ekur_kurikulum')
            ->where('nama_kur', 'Kurikulum Hasil Impor')->value('id_kur');
        $uploadedFile = new UploadedFile(
            $exportPath,
            'kurikulum.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
        $this->post(route('ekurikulum.import', [$targetCurriculumId, 'curriculum']), [
            'file' => $uploadedFile,
        ])->assertRedirect();
        unlink($exportPath);
        $importedCourseId = DB::connection('simak_main')->table('ekur_draft_mk')
            ->where('id_kur', $targetCurriculumId)->value('id_mk_draft');
        $this->assertDatabaseHas('ekur_map_mk_bk', [
            'id_mk_draft' => $importedCourseId,
            'id_bk' => DB::connection('simak_main')->table('ekur_bk')->where('id_kur', $targetCurriculumId)->value('id_bk'),
        ], 'simak_main');
        $this->assertDatabaseHas('ekur_map_mk_cpl', [
            'id_mk_draft' => $importedCourseId,
            'id_cpl' => DB::connection('simak_main')->table('ekur_cpl')->where('id_kur', $targetCurriculumId)->value('id_cpl'),
        ], 'simak_main');
    }

    public function test_read_only_and_unsupported_legacy_roles_cannot_mutate_e_kurikulum(): void
    {
        $this->actingAs($this->user('userprodi', ['id_prodi' => 'program-1']));
        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Kurikulum Readonly',
            'tahun' => '2025',
        ])->assertRedirect();
        $curriculumId = DB::connection('simak_main')->table('ekur_kurikulum')->value('id_kur');

        $this->actingAs($this->user('admin'));
        $this->get(route('ekurikulum.curricula.index'))->assertOk();
        $this->get(route('ekurikulum.curricula.show', $curriculumId))->assertOk();
        $this->post(route('ekurikulum.curricula.store'), [
            'nama_kur' => 'Tidak boleh dibuat admin',
            'tahun' => '2027',
        ])->assertForbidden();
        $this->put(route('ekurikulum.profile.update'), ['visi_keilmuan' => 'Tidak boleh diubah'])
            ->assertForbidden();

        $this->actingAs($this->user('usertu', ['id_prodi' => 'program-1']));
        $this->get(route('ekurikulum.curricula.index'))->assertForbidden();
        $this->get(route('ekurikulum.profile.index'))->assertForbidden();
    }

    private function user(string $role, array $extra = []): GenericUser
    {
        return new GenericUser([
            'id' => $role.'-user',
            'legacy_id' => $role.'-user',
            'legacy_type' => 'admin',
            'login' => $role.'-user',
            'name' => 'Pengguna '.$role,
            'role' => $role,
            'email' => $role.'@example.test',
            'password' => 'not-used',
            'remember_token' => null,
            ...$extra,
        ]);
    }

    private function createSchema(): void
    {
        Schema::connection('simak_main')->create('s1m4k_masterfakultas', function (Blueprint $table): void {
            $table->string('id_fakultas')->primary();
            $table->string('nama_fakultas')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterprodi', function (Blueprint $table): void {
            $table->string('id_prodi')->primary();
            $table->string('id_perguruan_tinggi')->nullable();
            $table->string('id_fakultas')->nullable();
            $table->string('nama_program_studi')->nullable();
            $table->string('nama_jenjang_pendidikan')->nullable();
            $table->string('izin_pendirian')->nullable();
            $table->string('peringkat_akreditasi')->nullable();
            $table->string('gelar_lulusan')->nullable();
            $table->text('visi_keilmuan')->nullable();
            $table->text('misi_prodi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_masterpt', function (Blueprint $table): void {
            $table->string('id_perguruan_tinggi')->primary();
            $table->string('nama_perguruan_tinggi')->nullable();
        });
        Schema::connection('simak_main')->create('s1m4k_admin', function (Blueprint $table): void {
            $table->bigIncrements('id_admin');
            $table->string('id_prodi')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('nama_lengkap')->nullable();
            $table->string('level')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->string('blokir')->default('N');
        });
        Schema::connection('simak_main')->create('s1m4k_masterdosen', function (Blueprint $table): void {
            $table->string('id_dosen')->primary();
            $table->string('nidn')->nullable();
            $table->string('id_prodi')->nullable();
            $table->string('nama_dosen')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
        });
        Schema::connection('simak_main')->create('log_aktivitas', function (Blueprint $table): void {
            $table->string('id_log')->primary();
            $table->string('id_user');
            $table->string('level_user');
            $table->string('nama_user');
            $table->string('modul');
            $table->text('aktifitas');
            $table->timestamp('created_at')->nullable();
        });
        Schema::connection('simak_main')->create('ekur_kurikulum', function (Blueprint $table): void {
            $table->bigIncrements('id_kur');
            $table->string('nama_kur');
            $table->string('id_fakultas');
            $table->string('id_prodi');
            $table->unsignedInteger('tahun');
            $table->text('visi_keilmuan')->nullable();
            $table->text('misi_prodi')->nullable();
            $table->string('status_kur');
            $table->timestamps();
        });
        Schema::connection('simak_main')->create('ekur_profesi', function (Blueprint $table): void {
            $table->bigIncrements('id_profesi');
            $table->unsignedBigInteger('id_kur');
            $table->string('nama_profesi');
            $table->string('referensi')->nullable();
        });
        Schema::connection('simak_main')->create('ekur_profil_lulusan', function (Blueprint $table): void {
            $table->increments('id_pl');
            $table->unsignedBigInteger('id_kur');
            $table->string('kode_pl');
            $table->text('profil_lulusan');
            $table->string('aspek')->nullable();
            $table->text('deskripsi_pl');
            $table->string('referensi')->nullable();
        });
        Schema::connection('simak_main')->create('ekur_cpl', function (Blueprint $table): void {
            $table->increments('id_cpl');
            $table->unsignedBigInteger('id_kur');
            $table->string('kode_cpl');
            $table->string('aspek');
            $table->text('deskripsi_cpl');
            $table->string('referensi')->nullable();
        });
        Schema::connection('simak_main')->create('ekur_bk', function (Blueprint $table): void {
            $table->increments('id_bk');
            $table->unsignedBigInteger('id_kur');
            $table->string('kode_bk');
            $table->string('nama_bk');
            $table->string('referensi')->nullable();
            $table->text('deskripsi_bk')->nullable();
            $table->string('kompetensi');
        });
        Schema::connection('simak_main')->create('ekur_draft_mk', function (Blueprint $table): void {
            $table->increments('id_mk_draft');
            $table->unsignedBigInteger('id_kur');
            $table->string('kode_mk');
            $table->string('kode_mk_live')->nullable();
            $table->string('nama_mk');
            $table->unsignedInteger('sks');
            $table->unsignedInteger('sks_teori');
            $table->unsignedInteger('sks_praktek');
            $table->string('kategori_mk')->nullable();
            $table->string('is_capstone');
            $table->string('jenis_mk')->nullable();
            $table->unsignedInteger('semester')->nullable();
            $table->string('prasyarat_mk');
        });
        Schema::connection('simak_main')->create('ekur_map_cpl_pl', function (Blueprint $table): void {
            $table->unsignedInteger('id_cpl');
            $table->unsignedInteger('id_pl');
        });
        Schema::connection('simak_main')->create('ekur_map_cpl_bk', function (Blueprint $table): void {
            $table->unsignedInteger('id_cpl');
            $table->unsignedInteger('id_bk');
        });
        Schema::connection('simak_main')->create('ekur_map_mk_bk', function (Blueprint $table): void {
            $table->unsignedInteger('id_mk_draft');
            $table->unsignedInteger('id_bk');
        });
        Schema::connection('simak_main')->create('ekur_map_mk_cpl', function (Blueprint $table): void {
            $table->unsignedInteger('id_mk_draft');
            $table->unsignedInteger('id_cpl');
        });
        Schema::connection('simak_main')->create('ekur_cpmk', function (Blueprint $table): void {
            $table->bigIncrements('id_cpmk');
            $table->unsignedBigInteger('id_mk_draft');
            $table->string('kode_cpmk');
            $table->text('deskripsi_cpmk');
        });
        Schema::connection('simak_main')->create('ekur_subcpmk', function (Blueprint $table): void {
            $table->bigIncrements('id_subcpmk');
            $table->unsignedBigInteger('id_mk_draft');
            $table->string('kode_subcpmk');
            $table->text('deskripsi_subcpmk');
        });
        Schema::connection('simak_main')->create('ekur_map_cpmk_cpl', function (Blueprint $table): void {
            $table->increments('id_map');
            $table->unsignedBigInteger('id_cpmk');
            $table->unsignedInteger('id_cpl');
        });
        Schema::connection('simak_main')->create('ekur_map_subcpmk_cpmk', function (Blueprint $table): void {
            $table->bigIncrements('id_map');
            $table->unsignedBigInteger('id_subcpmk');
            $table->unsignedBigInteger('id_cpmk');
        });
        Schema::connection('simak_main')->create('ekur_penilaian', function (Blueprint $table): void {
            $table->bigIncrements('id_penilaian');
            $table->unsignedBigInteger('id_subcpmk')->unique();
            foreach (['partisipasi', 'project', 'tugas', 'kuis', 'uts', 'uas'] as $field) {
                $table->decimal($field, 5, 2)->nullable();
            }
        });
        Schema::connection('simak_main')->create('ekur_rps_kelengkapan', function (Blueprint $table): void {
            $table->bigIncrements('id_kelengkapan');
            $table->unsignedBigInteger('id_mk_draft')->unique();
            $table->string('rumpun_mk')->nullable();
            $table->text('deskripsi_singkat')->nullable();
            $table->text('dosen_pengampu')->nullable();
            $table->string('dsn_pengembang')->nullable();
            $table->string('koor_rmk')->nullable();
            $table->string('koor_prodi')->nullable();
            $table->string('wd1')->nullable();
            $table->text('bahan_kajian')->nullable();
            $table->text('pustaka_utama')->nullable();
            $table->text('pustaka_pendukung')->nullable();
        });
        Schema::connection('simak_main')->create('ekur_rps_materi', function (Blueprint $table): void {
            $table->bigIncrements('id_materi');
            $table->unsignedBigInteger('id_mk_draft');
            foreach (['minggu_ke', 'kemampuan_akhir', 'indikator', 'kriteria_teknik', 'bentuk_pembelajaran', 'materi_pembelajaran'] as $field) {
                $table->text($field)->nullable();
            }
            $table->string('pustaka_pilihan')->nullable();
            $table->decimal('bobot_penilaian', 5, 2)->nullable();
        });
    }

    private function seedPrograms(): void
    {
        DB::connection('simak_main')->table('s1m4k_masterpt')->insert([
            'id_perguruan_tinggi' => 'university-1',
            'nama_perguruan_tinggi' => 'Universitas Khairun',
        ]);
        DB::connection('simak_main')->table('s1m4k_masterfakultas')->insert([
            ['id_fakultas' => 'faculty-1', 'nama_fakultas' => 'Fakultas Satu'],
            ['id_fakultas' => 'faculty-2', 'nama_fakultas' => 'Fakultas Dua'],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterprodi')->insert([
            [
                'id_prodi' => 'program-1', 'id_perguruan_tinggi' => 'university-1', 'id_fakultas' => 'faculty-1', 'nama_program_studi' => 'Program Satu',
                'nama_jenjang_pendidikan' => 'S1', 'izin_pendirian' => 'SK-1', 'visi_keilmuan' => 'Visi awal',
                'misi_prodi' => 'Misi awal',
            ],
            [
                'id_prodi' => 'program-2', 'id_perguruan_tinggi' => 'university-1', 'id_fakultas' => 'faculty-2', 'nama_program_studi' => 'Program Dua',
                'nama_jenjang_pendidikan' => 'S1', 'izin_pendirian' => 'SK-2', 'visi_keilmuan' => 'Visi dua',
                'misi_prodi' => 'Misi dua',
            ],
        ]);
        DB::connection('simak_main')->table('s1m4k_masterdosen')->insert([
            ['id_dosen' => 'dosen-1', 'nidn' => 'NIDN-1', 'id_prodi' => 'program-1', 'nama_dosen' => 'Dosen Satu'],
        ]);
    }
}
