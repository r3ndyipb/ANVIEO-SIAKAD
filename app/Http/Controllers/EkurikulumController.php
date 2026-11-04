<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Support\LecturerName;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EkurikulumController extends Controller
{
    private const VIEW_ROLES = ['admin', 'userprodi', 'timkur', 'pimuniv', 'pimfak'];

    private const EDIT_ROLES = ['userprodi', 'timkur'];

    private const RPS_NOT_FINAL_MESSAGE = 'RPS hanya dapat disusun setelah kurikulum difinalisasi.';

    private const ASPECTS = ['Sikap', 'Pengetahuan', 'Keterampilan Umum', 'Keterampilan Khusus'];

    public function teams(Request $request): View
    {
        $user = $request->user();
        $this->ensureRole($user, ['userprodi']);
        $programId = $this->programId($user);
        $teams = DB::connection('simak_main')->table('s1m4k_admin as account')
            ->leftJoin('s1m4k_masterprodi as program', 'account.id_prodi', '=', 'program.id_prodi')
            ->where('account.level', 'timkur')
            ->where('account.id_prodi', $programId)
            ->select([
                'account.id_admin', 'account.username', 'account.nama_lengkap',
                'account.email', 'account.no_telp', 'account.blokir',
                'program.nama_program_studi',
            ])
            ->orderBy('account.username')
            ->paginate(25)
            ->withQueryString();

        return view('ekurikulum.teams', compact('teams'));
    }

    public function storeTeam(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, ['userprodi']);
        $programId = $this->programId($user);
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:100', 'regex:/^\S+$/'],
            'password' => ['required', 'string', 'max:100'],
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:60'],
            'no_telp' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('ekurikulum.teams.index', ['modal' => 'create'])
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();

        $connection = DB::connection('simak_main');
        if ($connection->table('s1m4k_admin')->where('username', $validated['username'])->exists()) {
            return redirect()->route('ekurikulum.teams.index', ['modal' => 'create'])
                ->withErrors(['username' => 'Username telah digunakan.'])
                ->withInput();
        }
        $id = $connection->table('s1m4k_admin')->insertGetId([
            'id_prodi' => $programId,
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'nama_lengkap' => $validated['nama_lengkap'],
            'level' => 'timkur',
            'no_telp' => $validated['no_telp'] ?? null,
            'email' => $validated['email'],
            'blokir' => 'N',
        ]);
        $activityLogger->record($user, 'E-Kurikulum', 'Menambahkan akun Tim Kurikulum', [
            'username' => $validated['username'],
            'nama_lengkap' => $validated['nama_lengkap'],
        ], (string) $id);

        return redirect()->route('ekurikulum.teams.index')->with('status', 'Akun Tim Kurikulum berhasil ditambahkan.');
    }

    public function updateTeam(Request $request, string $account, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, ['userprodi']);
        $programId = $this->programId($user);
        $record = DB::connection('simak_main')->table('s1m4k_admin')
            ->where('id_admin', $account)
            ->where('id_prodi', $programId)
            ->where('level', 'timkur')
            ->first();
        abort_if($record === null, 404);
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:60'],
            'no_telp' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'max:100'],
            'blokir' => ['required', Rule::in(['Y', 'N'])],
        ]);
        if ($validator->fails()) {
            return redirect()->route('ekurikulum.teams.index', ['modal' => 'edit', 'account' => $account])
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();
        $values = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'no_telp' => $validated['no_telp'] ?? null,
            'blokir' => $validated['blokir'],
        ];
        if (filled($validated['password'] ?? null)) {
            $values['password'] = Hash::make($validated['password']);
        }
        DB::connection('simak_main')->table('s1m4k_admin')
            ->where('id_admin', $account)
            ->where('id_prodi', $programId)
            ->where('level', 'timkur')
            ->update($values);
        $activityLogger->record($user, 'E-Kurikulum', 'Memperbarui akun Tim Kurikulum', [
            'username' => $record->username,
            'perubahan' => $activityLogger->diff($record, $values),
        ], $account);

        return redirect()->route('ekurikulum.teams.index')->with('status', 'Akun Tim Kurikulum berhasil diperbarui.');
    }

    public function profile(Request $request): View
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $programs = $this->programs($user);
        $selectedProgram = $this->selectedProgram($request, $user, $programs);
        $profile = $selectedProgram === ''
            ? null
            : DB::connection('simak_main')->table('s1m4k_masterprodi as program')
                ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
                ->leftJoin('s1m4k_masterpt as university', 'program.id_perguruan_tinggi', '=', 'university.id_perguruan_tinggi')
                ->where('program.id_prodi', $selectedProgram)
                ->select([
                    'program.*',
                    'faculty.nama_fakultas',
                    'university.nama_perguruan_tinggi',
                ])
                ->first();

        return view('ekurikulum.profile', [
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'profile' => $profile,
            'canEdit' => in_array(data_get($user, 'role'), self::EDIT_ROLES, true),
        ]);
    }

    public function updateProfile(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $programId = $this->programId($user);
        $validator = Validator::make($request->all(), [
            'izin_pendirian' => ['nullable', 'string', 'max:255'],
            'peringkat_akreditasi' => ['nullable', 'string', 'max:255'],
            'gelar_lulusan' => ['nullable', 'string', 'max:255'],
            'visi_keilmuan' => ['nullable', 'string'],
            'misi_prodi' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('ekurikulum.profile.index', ['modal' => 'edit'])
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();
        $connection = DB::connection('simak_main');
        $profile = $connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)->first();
        abort_if($profile === null, 404);
        $connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)->update($validated);
        $activityLogger->record($user, 'E-Kurikulum', 'Memperbarui profil program studi', [
            'perubahan' => $activityLogger->diff($profile, $validated),
        ], $programId);

        return redirect()->route('ekurikulum.profile.index')->with('status', 'Profil program studi berhasil diperbarui.');
    }

    public function curricula(Request $request): View
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $programs = $this->programs($user);
        $selectedProgram = $this->selectedProgram($request, $user, $programs);
        $query = DB::connection('simak_main')->table('ekur_kurikulum as curriculum')
            ->leftJoin('s1m4k_masterprodi as program', 'curriculum.id_prodi', '=', 'program.id_prodi')
            ->select([
                'curriculum.id_kur', 'curriculum.nama_kur', 'curriculum.id_fakultas',
                'curriculum.id_prodi', 'curriculum.tahun', 'curriculum.status_kur',
                'program.nama_program_studi', 'program.nama_jenjang_pendidikan',
            ])
            ->orderByDesc('curriculum.tahun')
            ->orderByDesc('curriculum.id_kur');
        $this->scopePrograms($query, $user, 'curriculum');
        if ($selectedProgram !== '') {
            $query->where('curriculum.id_prodi', $selectedProgram);
        }
        $curricula = $query->paginate(25)->withQueryString();

        return view('ekurikulum.curricula', [
            'curricula' => $curricula,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'canEdit' => in_array(data_get($user, 'role'), self::EDIT_ROLES, true),
        ]);
    }

    public function storeCurriculum(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $programId = $this->programId($user);
        $validator = Validator::make($request->all(), [
            'nama_kur' => ['required', 'string', 'max:100'],
            'tahun' => ['required', 'integer', 'between:2000,2099'],
        ]);
        if ($validator->fails()) {
            return redirect()->route('ekurikulum.curricula.index', ['modal' => 'create'])
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();
        $connection = DB::connection('simak_main');
        $program = $connection->table('s1m4k_masterprodi')->where('id_prodi', $programId)->first();
        abort_if($program === null, 404);
        if ($connection->table('ekur_kurikulum')
            ->where('id_prodi', $programId)
            ->where('tahun', $validated['tahun'])
            ->exists()) {
            return redirect()->route('ekurikulum.curricula.index', ['modal' => 'create'])
                ->withErrors(['tahun' => 'Kurikulum untuk tahun tersebut sudah tersedia di program studi ini.'])
                ->withInput();
        }
        $id = $connection->table('ekur_kurikulum')->insertGetId([
            'nama_kur' => $validated['nama_kur'],
            'id_fakultas' => $program->id_fakultas,
            'id_prodi' => $programId,
            'tahun' => $validated['tahun'],
            'visi_keilmuan' => filled($program->visi_keilmuan) ? $program->visi_keilmuan : '-',
            'misi_prodi' => filled($program->misi_prodi) ? $program->misi_prodi : '-',
            'status_kur' => 'Draf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activityLogger->record($user, 'E-Kurikulum', 'Membuat draf kurikulum', [
            'nama_kur' => $validated['nama_kur'],
            'tahun' => $validated['tahun'],
            'id_prodi' => $programId,
        ], (string) $id);

        return redirect()->route('ekurikulum.curricula.show', $id)->with('status', 'Draf kurikulum berhasil dibuat.');
    }

    public function createCurriculum(Request $request): View
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $this->programId($user))
            ->first(['id_prodi', 'nama_program_studi', 'id_fakultas']);
        abort_if($program === null, 404);

        return view('ekurikulum.curriculum-create', compact('program'));
    }

    public function showCurriculum(Request $request, string $curriculum): View
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $record = $this->findCurriculum($user, $curriculum);
        $connection = DB::connection('simak_main');
        $profiles = $connection->table('ekur_profil_lulusan')->where('id_kur', $curriculum)->orderBy('kode_pl')->get();
        $professions = $connection->table('ekur_profesi')->where('id_kur', $curriculum)->orderBy('nama_profesi')->get();
        $cpls = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->orderBy('kode_cpl')->get();
        $bks = $connection->table('ekur_bk')->where('id_kur', $curriculum)->orderBy('kode_bk')->get();
        $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->orderBy('semester')->orderBy('kode_mk')->get();
        $profileMaps = $connection->table('ekur_map_cpl_pl')->join('ekur_cpl', 'ekur_map_cpl_pl.id_cpl', '=', 'ekur_cpl.id_cpl')->where('ekur_cpl.id_kur', $curriculum)->get();
        $cplMaps = $connection->table('ekur_map_cpl_bk')->join('ekur_bk', 'ekur_map_cpl_bk.id_bk', '=', 'ekur_bk.id_bk')->where('ekur_bk.id_kur', $curriculum)->get();
        $courseBkMaps = $connection->table('ekur_map_mk_bk')->join('ekur_draft_mk', 'ekur_map_mk_bk.id_mk_draft', '=', 'ekur_draft_mk.id_mk_draft')->where('ekur_draft_mk.id_kur', $curriculum)->get();
        $courseCplMaps = $connection->table('ekur_map_mk_cpl')->join('ekur_draft_mk', 'ekur_map_mk_cpl.id_mk_draft', '=', 'ekur_draft_mk.id_mk_draft')->where('ekur_draft_mk.id_kur', $curriculum)->get();
        $workflow = $this->curriculumWorkflow($connection, $curriculum);

        return view('ekurikulum.curriculum-show', [
            'curriculum' => $record,
            'profiles' => $profiles,
            'professions' => $professions,
            'cpls' => $cpls,
            'bks' => $bks,
            'courses' => $courses,
            'nextCodes' => [
                'profiles' => $this->nextManualCode($connection, $curriculum, 'profiles'),
                'cpls' => $this->nextManualCode($connection, $curriculum, 'cpls'),
                'bks' => $this->nextManualCode($connection, $curriculum, 'bks'),
                'courses' => $this->nextManualCode($connection, $curriculum, 'courses'),
            ],
            'profileMaps' => $profileMaps,
            'cplMaps' => $cplMaps,
            'courseBkMaps' => $courseBkMaps,
            'courseCplMaps' => $courseCplMaps,
            'workflow' => $workflow,
            'canEdit' => in_array(data_get($user, 'role'), self::EDIT_ROLES, true) && $record->status_kur !== 'Final',
        ]);
    }

    public function rps(Request $request, string $curriculum): View|RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $record = $this->findCurriculum($user, $curriculum);
        if ($record->status_kur !== 'Final') {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        $connection = DB::connection('simak_main');
        $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)
            ->where('semester', '>', 0)
            ->orderBy('semester')->orderBy('kode_mk')->get();
        $cpls = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->orderBy('kode_cpl')->get();
        $lecturers = $connection->table('s1m4k_masterdosen')
            ->whereNotNull('nama_dosen')
            ->where('nama_dosen', '<>', '')
            ->orderBy('nama_dosen')
            ->get(['id_dosen', 'nidn', 'nama_dosen', 'gelar_depan', 'gelar_belakang']);
        $rpsItems = $this->rpsItems($connection, $courses);

        return view('ekurikulum.rps-workflow', [
            'curriculum' => $record,
            'courses' => $courses,
            'rpsItems' => $rpsItems,
            'cpls' => $cpls,
            'lecturers' => $lecturers,
            'canEdit' => in_array(data_get($user, 'role'), self::EDIT_ROLES, true) && $record->status_kur === 'Final',
        ]);
    }

    public function exportRps(Request $request, string $curriculum, string $course): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $record = $this->findCurriculum($user, $curriculum);
        if ($record->status_kur !== 'Final') {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        $connection = DB::connection('simak_main');
        $selectedCourse = $connection->table('ekur_draft_mk')
            ->where('id_kur', $curriculum)
            ->where('id_mk_draft', $course)
            ->first();
        abort_if($selectedCourse === null, 404);
        $rpsData = $this->rpsData($connection, $course);
        $lecturerNames = $connection->table('s1m4k_masterdosen')
            ->whereNotNull('nama_dosen')
            ->where('nama_dosen', '<>', '')
            ->get(['id_dosen', 'nidn', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
            ->mapWithKeys(function (object $lecturer): array {
                $label = LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang);
                $labels = [(string) $lecturer->id_dosen => $label];
                if (filled($lecturer->nidn)) {
                    $labels[(string) $lecturer->nidn] = $label;
                }

                return $labels;
            })
            ->all();
        $spreadsheet = new Spreadsheet;
        $courseCode = (string) ($selectedCourse->kode_mk_live ?: $selectedCourse->kode_mk);
        $sheets = [
            'Identitas' => [
                ['Kurikulum', $record->nama_kur],
                ['Program studi', $record->nama_program_studi],
                ['Mata kuliah', $selectedCourse->nama_mk],
                ['Kode MK', $selectedCourse->kode_mk_live ?: $selectedCourse->kode_mk],
                ['SKS', $selectedCourse->sks],
                ['Semester', $selectedCourse->semester],
            ],
            'CPMK' => [['Kode MK Live', 'Kode CPMK', 'Deskripsi CPMK', 'Relasi Kode CPL']],
            'Sub-CPMK' => [['Kode MK Live', 'Kode Sub-CPMK', 'Deskripsi Sub-CPMK', 'Relasi Kode CPMK']],
            'Penilaian' => [['Kode MK Live', 'Kode Sub-CPMK', 'Partisipasi (%)', 'Proyek (%)', 'Tugas (%)', 'Kuis (%)', 'UTS (%)', 'UAS (%)']],
            'Rencana Mingguan' => [['Minggu', 'Kemampuan akhir', 'Indikator', 'Kriteria/teknik', 'Bentuk pembelajaran', 'Materi', 'Pustaka', 'Bobot']],
            'Kelengkapan RPS' => [['Rumpun MK', 'Deskripsi singkat', 'Dosen pengampu', 'Dosen pengembang', 'Koordinator RMK', 'Koordinator prodi', 'Wakil dekan I', 'Bahan kajian', 'Pustaka utama', 'Pustaka pendukung']],
        ];
        $cplNames = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->pluck('kode_cpl', 'id_cpl')->all();
        $cpmkNames = $rpsData['cpmks']->pluck('kode_cpmk', 'id_cpmk')->all();
        foreach ($rpsData['cpmks'] as $cpmk) {
            $ids = $rpsData['cpmkMaps']->where('id_cpmk', $cpmk->id_cpmk)->pluck('id_cpl')->all();
            $sheets['CPMK'][] = [$courseCode, $cpmk->kode_cpmk, $cpmk->deskripsi_cpmk, implode(', ', array_intersect_key($cplNames, array_flip($ids)))];
        }
        foreach ($rpsData['subcpmks'] as $subcpmk) {
            $ids = $rpsData['subcpmkMaps']->where('id_subcpmk', $subcpmk->id_subcpmk)->pluck('id_cpmk')->all();
            $sheets['Sub-CPMK'][] = [$courseCode, $subcpmk->kode_subcpmk, $subcpmk->deskripsi_subcpmk, implode(', ', array_intersect_key($cpmkNames, array_flip($ids)))];
            $weights = $rpsData['assessments']->get($subcpmk->id_subcpmk);
            $sheets['Penilaian'][] = [
                $courseCode,
                $subcpmk->kode_subcpmk,
                $weights->partisipasi ?? 0,
                $weights->project ?? 0,
                $weights->tugas ?? 0,
                $weights->kuis ?? 0,
                $weights->uts ?? 0,
                $weights->uas ?? 0,
            ];
        }
        foreach ($rpsData['materials'] as $material) {
            $sheets['Rencana Mingguan'][] = [
                $material->minggu_ke, $material->kemampuan_akhir, $material->indikator,
                $material->kriteria_teknik, $material->bentuk_pembelajaran,
                $material->materi_pembelajaran, $material->pustaka_pilihan, $material->bobot_penilaian,
            ];
        }
        $completeness = $rpsData['completeness'];
        if ($completeness !== null) {
            $lecturerNamesFor = static fn (?string $ids): string => collect(explode(',', (string) $ids))
                ->map(fn (string $id): string => $lecturerNames[trim($id)] ?? trim($id))
                ->filter()
                ->implode(', ');
            $sheets['Kelengkapan RPS'][] = [
                $completeness->rumpun_mk, $completeness->deskripsi_singkat,
                $lecturerNamesFor($completeness->dosen_pengampu), $lecturerNamesFor($completeness->dsn_pengembang),
                $lecturerNamesFor($completeness->koor_rmk), $lecturerNamesFor($completeness->koor_prodi), $lecturerNamesFor($completeness->wd1),
                $completeness->bahan_kajian, $completeness->pustaka_utama, $completeness->pustaka_pendukung,
            ];
        }
        $sheetIndex = 0;
        foreach ($sheets as $sheetTitle => $sheetData) {
            $sheet = $sheetIndex++ === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);
            foreach ($sheetData as $rowIndex => $row) {
                foreach ($row as $columnIndex => $value) {
                    $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                    $sheet->setCellValue($column.($rowIndex + 1), $value);
                }
            }
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            foreach (range(1, max(1, count($sheetData[0]))) as $columnIndex) {
                $column = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }
        $filename = 'RPS_'.Str::slug((string) $courseCode).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportCurriculum(Request $request, string $curriculum): StreamedResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $record = $this->findCurriculum($user, $curriculum);
        $connection = DB::connection('simak_main');
        $profiles = $connection->table('ekur_profil_lulusan')->where('id_kur', $curriculum)->orderBy('kode_pl')->get();
        $cpls = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->orderBy('kode_cpl')->get();
        $bks = $connection->table('ekur_bk')->where('id_kur', $curriculum)->orderBy('kode_bk')->get();
        $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->orderBy('semester')->orderBy('kode_mk')->get();
        $profileMaps = $connection->table('ekur_map_cpl_pl')->whereIn('id_cpl', $cpls->pluck('id_cpl'))->get();
        $cplMaps = $connection->table('ekur_map_cpl_bk')->whereIn('id_bk', $bks->pluck('id_bk'))->get();
        $courseMaps = $connection->table('ekur_map_mk_bk')->whereIn('id_mk_draft', $courses->pluck('id_mk_draft'))->get();
        $profileCodes = $profiles->keyBy('id_pl')->map(fn (object $profile): string => (string) $profile->kode_pl);
        $cplCodes = $cpls->keyBy('id_cpl')->map(fn (object $cpl): string => (string) $cpl->kode_cpl);
        $bkCodes = $bks->keyBy('id_bk')->map(fn (object $bk): string => (string) $bk->kode_bk);
        $sheets = [
            'Profil Lulusan' => [
                ['Kode PL', 'Profil Lulusan Singkat', 'Deskripsi PL Lengkap', 'Aspek', 'Referensi'],
                ...$profiles->map(fn (object $profile): array => [
                    $profile->kode_pl, $profile->profil_lulusan, $profile->deskripsi_pl,
                    $profile->aspek, $profile->referensi,
                ])->all(),
            ],
            'CPL' => [
                ['Kode CPL', 'Deskripsi CPL', 'Aspek', 'Referensi', 'Relasi Kode PL'],
                ...$cpls->map(fn (object $cpl): array => [
                    $cpl->kode_cpl,
                    $cpl->deskripsi_cpl,
                    $cpl->aspek,
                    $cpl->referensi,
                    $profileMaps->where('id_cpl', $cpl->id_cpl)->pluck('id_pl')->map(fn ($id): string => $profileCodes[$id] ?? '')->filter()->implode(', '),
                ])->all(),
            ],
            'Bahan Kajian' => [
                ['Kode BK', 'Nama Bahan Kajian', 'Referensi', 'Relasi Kode CPL'],
                ...$bks->map(fn (object $bk): array => [
                    $bk->kode_bk,
                    $bk->nama_bk,
                    $bk->referensi,
                    $cplMaps->where('id_bk', $bk->id_bk)->pluck('id_cpl')->map(fn ($id): string => $cplCodes[$id] ?? '')->filter()->implode(', '),
                ])->all(),
            ],
            'Mata Kuliah' => [
                ['Kode MK Live', 'Nama Mata Kuliah', 'SKS Total', 'SKS Teori', 'SKS Praktek', 'Kategori MK', 'Semester', 'Jenis', 'Relasi Kode BK'],
                ...$courses->map(fn (object $course): array => [
                    $course->kode_mk_live ?: $course->kode_mk,
                    $course->nama_mk,
                    $course->sks,
                    $course->sks_teori,
                    $course->sks_praktek,
                    $course->kategori_mk,
                    $course->semester,
                    $course->jenis_mk ?: 'Wajib',
                    $courseMaps->where('id_mk_draft', $course->id_mk_draft)->pluck('id_bk')->map(fn ($id): string => $bkCodes[$id] ?? '')->filter()->implode(', '),
                ])->all(),
            ],
        ];
        $spreadsheet = new Spreadsheet;
        $sheetIndex = 0;
        foreach ($sheets as $sheetTitle => $rows) {
            $sheet = $sheetIndex++ === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);
            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $columnIndex => $value) {
                    $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                    $sheet->setCellValue($column.($rowIndex + 1), $value);
                }
            }
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
            foreach (range(1, count($rows[0])) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }
        }
        $filename = 'Kurikulum_'.Str::slug((string) $record->nama_kur).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function printRps(Request $request, string $curriculum, string $course): View|RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::VIEW_ROLES);
        $curriculumRecord = $this->findCurriculum($user, $curriculum);
        if ($curriculumRecord->status_kur !== 'Final') {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        $connection = DB::connection('simak_main');
        $selectedCourse = $connection->table('ekur_draft_mk')
            ->where('id_kur', $curriculum)
            ->where('id_mk_draft', $course)
            ->first();
        abort_if($selectedCourse === null, 404);
        $rpsData = $this->rpsData($connection, $course);
        $cpls = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->get()->keyBy('id_cpl');
        $cpmks = $rpsData['cpmks']->keyBy('id_cpmk');
        $lecturers = $connection->table('s1m4k_masterdosen')
            ->whereNotNull('nama_dosen')
            ->where('nama_dosen', '<>', '')
            ->get(['id_dosen', 'nidn', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
            ->mapWithKeys(function (object $lecturer): array {
                $label = LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang);
                $labels = [(string) $lecturer->id_dosen => $label];
                if (filled($lecturer->nidn)) {
                    $labels[(string) $lecturer->nidn] = $label;
                }

                return $labels;
            });

        return view('ekurikulum.rps-print', compact('curriculumRecord', 'selectedCourse', 'rpsData', 'cpls', 'cpmks', 'lecturers'));
    }

    public function saveData(Request $request, string $curriculum, string $entity, ActivityLogger $activityLogger, ?string $record = null): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $curriculumRecord = $this->findCurriculum($user, $curriculum);
        if (
            in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)
            && $curriculumRecord->status_kur !== 'Final'
        ) {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        abort_if(
            $curriculumRecord->status_kur === 'Final' && ! in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true),
            403,
            'Kurikulum berstatus Final dan data dasar tidak dapat diubah.'
        );
        $connection = DB::connection('simak_main');
        if (in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)) {
            $this->ensureRpsStageUnlocked(
                $connection,
                $curriculum,
                $entity,
                $this->rpsCourseId($connection, $entity, $request->input('id_mk_draft'), $record),
            );
        }

        if ($entity === 'finalize') {
            $connection->transaction(function () use ($connection, $curriculum): void {
                $lockedRecord = $connection->table('ekur_kurikulum')->where('id_kur', $curriculum)->lockForUpdate()->first();
                abort_if($lockedRecord === null, 404);
                abort_if($lockedRecord->status_kur === 'Final', 409, 'Kurikulum sudah final dan tidak dapat difinalisasi ulang.');
                $this->ensureFinalizable($connection, $curriculum);
                $connection->table('ekur_kurikulum')->where('id_kur', $curriculum)->update([
                    'status_kur' => 'Final',
                    'updated_at' => now(),
                ]);
            });
            $activityLogger->record($user, 'E-Kurikulum', 'Mengesahkan kurikulum', ['nama_kur' => $curriculumRecord->nama_kur], $curriculum);

            return redirect()->route('ekurikulum.curricula.show', $curriculum)->with('status', 'Kurikulum berhasil disahkan.');
        }

        if (! in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)) {
            $this->ensureStageUnlocked($connection, $curriculum, $entity);
        }

        if ($entity === 'distribution') {
            $validated = $request->validate([
                'semester' => ['required', 'array'],
                'semester.*' => ['required', 'integer', 'between:0,8'],
                'jenis_mk' => ['required', 'array'],
                'jenis_mk.*' => ['required', Rule::in(['Wajib', 'Pilihan'])],
                'prasyarat' => ['nullable', 'array'],
                'prasyarat.*' => ['nullable', 'array'],
            ]);
            $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->get(['id_mk_draft', 'semester']);
            $courseIds = $courses->pluck('id_mk_draft')->map(fn ($id): string => (string) $id)->all();
            $submittedIds = array_map('strval', array_keys($validated['semester']));
            abort_unless(
                array_diff($courseIds, $submittedIds) === []
                    && array_diff($submittedIds, $courseIds) === []
                    && array_diff(array_map('strval', array_keys($validated['jenis_mk'])), $courseIds) === []
                    && array_diff($courseIds, array_map('strval', array_keys($validated['jenis_mk']))) === [],
                422,
                'Daftar mata kuliah tidak sesuai dengan kurikulum.'
            );
            foreach ($courseIds as $id) {
                $prerequisites = array_map('strval', $validated['prasyarat'][$id] ?? []);
                abort_unless(array_diff($prerequisites, $courseIds) === [], 422, 'Prasyarat harus berasal dari kurikulum yang sama.');
                abort_unless(count($prerequisites) === count(array_unique($prerequisites)), 422, 'Prasyarat mata kuliah tidak boleh duplikat.');
                abort_unless(! in_array($id, $prerequisites, true), 422, 'Mata kuliah tidak dapat menjadi prasyarat bagi dirinya sendiri.');
            }
            $connection->transaction(function () use ($connection, $curriculum, $validated): void {
                foreach ($validated['semester'] as $id => $semester) {
                    $prerequisites = array_map('strval', $validated['prasyarat'][$id] ?? []);
                    $connection->table('ekur_draft_mk')
                        ->where('id_kur', $curriculum)
                        ->where('id_mk_draft', $id)
                        ->update([
                            'semester' => $semester,
                            'jenis_mk' => $validated['jenis_mk'][$id],
                            'prasyarat_mk' => implode(',', $prerequisites),
                        ]);
                }
            });
            $activityLogger->record($user, 'E-Kurikulum', 'Memperbarui sebaran kurikulum', ['jumlah_mata_kuliah' => count($validated['semester'])], $curriculum);

            return redirect()->route('ekurikulum.curricula.show', [$curriculum, 'tab' => 'sebaran'])->with('status', 'Sebaran kurikulum berhasil disimpan.');
        }

        $data = $this->validatedEntity($request, $entity, $curriculum, $record);
        if (in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)) {
            $courseId = (string) $data['id_mk_draft'];
            $this->assertRpsRecordCourse($connection, $entity, $record, $courseId);
            if ($entity === 'cpmks') {
                $allowedCplIds = $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $courseId)->pluck('id_cpl')
                    ->map('strval')->all();
                $submittedCplIds = array_map('strval', $data['id_cpl']);
                abort_unless(array_diff($submittedCplIds, $allowedCplIds) === [], 422, 'CPMK hanya dapat dipetakan ke CPL yang sudah dibebankan pada mata kuliah.');
            }
        }
        if ($record !== null) {
            $codeField = $this->manualCodeDefinition($entity)['column'] ?? match ($entity) {
                'cpmks' => 'kode_cpmk',
                'subcpmks' => 'kode_subcpmk',
                default => null,
            };
            if ($codeField !== null) {
                unset($data[$codeField]);
            }
        }
        $connection->transaction(function () use ($connection, $curriculum, $entity, $record, &$data): void {
            $codeDefinition = $this->manualCodeDefinition($entity);
            $hasGeneratedCode = $codeDefinition !== null || in_array($entity, ['cpmks', 'subcpmks'], true);
            if ($record === null && $hasGeneratedCode) {
                $connection->table('ekur_kurikulum')
                    ->where('id_kur', $curriculum)
                    ->lockForUpdate()
                    ->first();
            }
            if ($record === null && $codeDefinition !== null) {
                $data[$codeDefinition['column']] = $this->nextManualCode($connection, $curriculum, $entity);
            }
            if ($record === null && in_array($entity, ['cpmks', 'subcpmks'], true)) {
                $data[$entity === 'cpmks' ? 'kode_cpmk' : 'kode_subcpmk'] = $this->nextRpsCode($connection, $curriculum, $entity, $data);
            }
            $this->assertUniqueEntity($connection, $curriculum, $entity, $data, $record);
            $this->persistEntity($connection, $curriculum, $entity, $record, $data);
        });
        $activityLogger->record($user, 'E-Kurikulum', $record ? 'Memperbarui '.$entity.' E-Kurikulum' : 'Menambahkan '.$entity.' E-Kurikulum', [
            'jenis_data' => $entity,
            'data' => $data,
        ], $record ?? $curriculum);
        $destination = in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)
            ? route('ekurikulum.rps.show', [$curriculum, 'mk' => $request->input('id_mk_draft')])
            : route('ekurikulum.curricula.show', [$curriculum, 'tab' => $entity]);

        return redirect($destination)->with('status', 'Data E-Kurikulum berhasil disimpan.');
    }

    public function deleteData(Request $request, string $curriculum, string $entity, string $record, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $curriculumRecord = $this->findCurriculum($user, $curriculum);
        if (
            in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)
            && $curriculumRecord->status_kur !== 'Final'
        ) {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        abort_if(
            $curriculumRecord->status_kur === 'Final' && ! in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true),
            403,
            'Kurikulum berstatus Final dan data dasar tidak dapat diubah.'
        );
        $connection = DB::connection('simak_main');
        if (in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)) {
            $this->ensureRpsStageUnlocked(
                $connection,
                $curriculum,
                $entity,
                $this->rpsCourseId($connection, $entity, $request->query('mk'), $record),
            );
        } else {
            $this->ensureStageUnlocked($connection, $curriculum, $entity);
        }
        $tableInfo = $this->ownedRecord($connection, $curriculum, $entity, $record);
        $connection->transaction(function () use ($connection, $entity, $record, $tableInfo): void {
            $idColumn = $tableInfo['id'];
            if ($entity === 'profiles') {
                abort_if($connection->table('ekur_map_cpl_pl')->where('id_pl', $record)->exists(), 422, 'Profil lulusan sudah digunakan dalam pemetaan CPL.');
            } elseif ($entity === 'cpls') {
                abort_if($connection->table('ekur_map_cpl_bk')->where('id_cpl', $record)->exists() || $connection->table('ekur_map_mk_cpl')->where('id_cpl', $record)->exists(), 422, 'CPL masih digunakan pada pemetaan.');
                $connection->table('ekur_map_cpl_pl')->where('id_cpl', $record)->delete();
            } elseif ($entity === 'bks') {
                abort_if($connection->table('ekur_map_mk_bk')->where('id_bk', $record)->exists(), 422, 'Bahan kajian masih digunakan oleh mata kuliah.');
                $connection->table('ekur_map_cpl_bk')->where('id_bk', $record)->delete();
            } elseif ($entity === 'courses') {
                abort_if($connection->table('ekur_cpmk')->where('id_mk_draft', $record)->exists() || $connection->table('ekur_rps_kelengkapan')->where('id_mk_draft', $record)->exists() || $connection->table('ekur_rps_materi')->where('id_mk_draft', $record)->exists(), 422, 'Mata kuliah masih memiliki data RPS.');
                $connection->table('ekur_map_mk_bk')->where('id_mk_draft', $record)->delete();
                $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $record)->delete();
            } elseif ($entity === 'cpmks') {
                abort_if($connection->table('ekur_map_subcpmk_cpmk')->where('id_cpmk', $record)->exists(), 422, 'CPMK masih digunakan oleh Sub-CPMK.');
                $connection->table('ekur_map_cpmk_cpl')->where('id_cpmk', $record)->delete();
            } elseif ($entity === 'subcpmks') {
                $connection->table('ekur_map_subcpmk_cpmk')->where('id_subcpmk', $record)->delete();
                $connection->table('ekur_penilaian')->where('id_subcpmk', $record)->delete();
            }
            $connection->table($tableInfo['table'])->where($idColumn, $record)->delete();
        });
        $activityLogger->record($user, 'E-Kurikulum', 'Menghapus '.$entity.' E-Kurikulum', ['jenis_data' => $entity], $record);
        $destination = in_array($entity, ['cpmks', 'subcpmks', 'assessments', 'completeness', 'materials'], true)
            ? route('ekurikulum.rps.show', [$curriculum, 'mk' => $request->query('mk')])
            : route('ekurikulum.curricula.show', [$curriculum, 'tab' => $entity]);

        return redirect($destination)->with('status', 'Data E-Kurikulum berhasil dihapus.');
    }

    public function template(Request $request, string $kind): StreamedResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        abort_unless(in_array($kind, ['curriculum', 'rps'], true), 404);
        $spreadsheet = new Spreadsheet;
        $worksheets = $kind === 'curriculum'
            ? [
                'Profil Lulusan' => ['Kode PL', 'Profil Lulusan Singkat', 'Deskripsi PL Lengkap', 'Aspek', 'Referensi'],
                'CPL' => ['Kode CPL', 'Deskripsi CPL', 'Aspek', 'Referensi', 'Relasi Kode PL'],
                'Bahan Kajian' => ['Kode BK', 'Nama Bahan Kajian', 'Referensi', 'Relasi Kode CPL'],
                'Mata Kuliah' => ['Kode MK Live', 'Nama Mata Kuliah', 'SKS Total', 'SKS Teori', 'SKS Praktek', 'Kategori MK', 'Semester', 'Jenis', 'Relasi Kode BK'],
            ]
            : [
                'CPMK' => ['Kode MK Live', 'Kode CPMK', 'Deskripsi CPMK', 'Relasi Kode CPL'],
                'Sub-CPMK' => ['Kode MK Live', 'Kode Sub-CPMK', 'Deskripsi Sub-CPMK', 'Relasi Kode CPMK'],
                'Penilaian' => [
                    'Kode MK Live', 'Kode Sub-CPMK', 'Partisipasi (%)', 'Proyek (%)',
                    'Tugas (%)', 'Kuis (%)', 'UTS (%)', 'UAS (%)',
                ],
            ];
        $sheetIndex = 0;
        foreach ($worksheets as $sheetName => $headers) {
            $sheet = $sheetIndex++ === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);
            foreach ($headers as $column => $header) {
                $letter = Coordinate::stringFromColumnIndex($column + 1);
                $sheet->setCellValue($letter.'1', $header);
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }
            $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        }
        $filename = $kind === 'curriculum' ? 'Template_Kurikulum_OBE.xlsx' : 'Template_RPS_OBE.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request, string $curriculum, string $kind, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->ensureRole($user, self::EDIT_ROLES);
        $curriculumRecord = $this->findCurriculum($user, $curriculum);
        abort_if($curriculumRecord->status_kur === 'Final' && $kind !== 'rps', 403, 'Kurikulum berstatus Final dan data dasar tidak dapat diubah.');
        abort_unless(in_array($kind, ['curriculum', 'rps'], true), 404);
        if ($kind === 'rps' && $curriculumRecord->status_kur !== 'Final') {
            return redirect()->route('ekurikulum.curricula.show', $curriculum)
                ->with('status', self::RPS_NOT_FINAL_MESSAGE);
        }
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ]);
        $connection = DB::connection('simak_main');

        try {
            $spreadsheet = IOFactory::load($validated['file']->getRealPath());
            $this->assertImportSheets($spreadsheet, $kind);
            $connection->transaction(function () use ($spreadsheet, $connection, $curriculum, $kind): void {
                if ($kind === 'curriculum') {
                    $this->importCurriculumSpreadsheet($spreadsheet, $connection, $curriculum);
                } else {
                    $this->importRpsSpreadsheet($spreadsheet, $connection, $curriculum);
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('E-Kurikulum spreadsheet import failed', [
                'curriculum_id' => $curriculum,
                'kind' => $kind,
                'actor_id' => data_get($user, 'legacy_id'),
                'exception' => $exception::class,
            ]);
            throw $exception;
        }
        $activityLogger->record($user, 'E-Kurikulum', 'Mengimpor template '.$kind, ['id_kur' => $curriculum], $curriculum);

        $redirect = redirect()->route($kind === 'rps' ? 'ekurikulum.rps.show' : 'ekurikulum.curricula.show', [$curriculum]);
        if ($kind === 'rps' && filled($request->input('mk'))) {
            $redirect->setTargetUrl(route('ekurikulum.rps.show', [$curriculum, 'mk' => $request->input('mk')]));
        }

        return $redirect
            ->with('status', 'Template berhasil diimpor.');
    }

    private function importCurriculumSpreadsheet(Spreadsheet $spreadsheet, mixed $connection, string $curriculum): void
    {
        $profileIds = $connection->table('ekur_profil_lulusan')->where('id_kur', $curriculum)->pluck('id_pl', 'kode_pl')->all();
        foreach ($this->sheetRows($spreadsheet, 'Profil Lulusan') as $row) {
            $code = strtoupper(trim((string) ($row[0] ?? '')));
            if ($code === '') {
                continue;
            }
            $values = [
                'profil_lulusan' => trim((string) ($row[1] ?? '')),
                'deskripsi_pl' => trim((string) ($row[2] ?? '')),
                'aspek' => trim((string) ($row[3] ?? '')),
                'referensi' => trim((string) ($row[4] ?? '')) ?: null,
            ];
            if (isset($profileIds[$code])) {
                $connection->table('ekur_profil_lulusan')->where('id_pl', $profileIds[$code])->update($values);
            } else {
                $profileIds[$code] = $connection->table('ekur_profil_lulusan')->insertGetId([
                    'id_kur' => $curriculum, 'kode_pl' => $code, ...$values,
                ]);
            }
        }
        $cplIds = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->pluck('id_cpl', 'kode_cpl')->all();
        foreach ($this->sheetRows($spreadsheet, 'CPL') as $row) {
            $code = strtoupper(trim((string) ($row[0] ?? '')));
            if ($code === '') {
                continue;
            }
            $values = [
                'deskripsi_cpl' => trim((string) ($row[1] ?? '')),
                'aspek' => trim((string) ($row[2] ?? '')),
                'referensi' => trim((string) ($row[3] ?? '')) ?: null,
            ];
            $id = $cplIds[$code] ?? null;
            if ($id === null) {
                $id = $connection->table('ekur_cpl')->insertGetId(['id_kur' => $curriculum, 'kode_cpl' => $code, ...$values]);
                $cplIds[$code] = $id;
            } else {
                $connection->table('ekur_cpl')->where('id_cpl', $id)->update($values);
            }
            $profileMap = $this->resolveCodes((string) ($row[4] ?? ''), $profileIds);
            if ($profileMap !== []) {
                $connection->table('ekur_map_cpl_pl')->where('id_cpl', $id)->delete();
                foreach ($profileMap as $profileId) {
                    $connection->table('ekur_map_cpl_pl')->insertOrIgnore(['id_cpl' => $id, 'id_pl' => $profileId]);
                }
            }
        }
        $bkIds = $connection->table('ekur_bk')->where('id_kur', $curriculum)->pluck('id_bk', 'kode_bk')->all();
        foreach ($this->sheetRows($spreadsheet, 'Bahan Kajian') as $row) {
            $code = strtoupper(trim((string) ($row[0] ?? '')));
            if ($code === '') {
                continue;
            }
            $values = [
                'nama_bk' => trim((string) ($row[1] ?? '')),
                'referensi' => trim((string) ($row[2] ?? '')) ?: null,
                'deskripsi_bk' => null,
                'kompetensi' => 'Utama',
            ];
            $id = $bkIds[$code] ?? null;
            if ($id === null) {
                $id = $connection->table('ekur_bk')->insertGetId(['id_kur' => $curriculum, 'kode_bk' => $code, ...$values]);
                $bkIds[$code] = $id;
            } else {
                $connection->table('ekur_bk')->where('id_bk', $id)->update($values);
            }
            $cplMap = $this->resolveCodes((string) ($row[3] ?? ''), $cplIds);
            if ($cplMap !== []) {
                $connection->table('ekur_map_cpl_bk')->where('id_bk', $id)->delete();
                foreach ($cplMap as $cplId) {
                    $connection->table('ekur_map_cpl_bk')->insertOrIgnore(['id_cpl' => $cplId, 'id_bk' => $id]);
                }
            }
        }
        foreach ($this->sheetRows($spreadsheet, 'Mata Kuliah') as $row) {
            $liveCode = strtoupper(trim((string) ($row[0] ?? '')));
            if ($liveCode === '') {
                continue;
            }
            $bkMap = $this->resolveCodes((string) ($row[8] ?? ''), $bkIds);
            $total = (int) ($row[2] ?? 0);
            $theory = (int) ($row[3] ?? 0);
            $practice = (int) ($row[4] ?? 0);
            abort_unless($total === $theory + $practice && $bkMap !== [], 422, 'Template mata kuliah harus memiliki SKS valid dan bahan kajian.');
            $values = [
                'nama_mk' => trim((string) ($row[1] ?? '')),
                'sks' => $total,
                'sks_teori' => $theory,
                'sks_praktek' => $practice,
                'kategori_mk' => trim((string) ($row[5] ?? '')),
                'semester' => (int) ($row[6] ?? 0),
                'jenis_mk' => trim((string) ($row[7] ?? 'Wajib')),
            ];
            $course = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->where('kode_mk_live', $liveCode)->first();
            if ($course === null) {
                $draftCode = $liveCode;
                $courseId = $connection->table('ekur_draft_mk')->insertGetId([
                    'id_kur' => $curriculum, 'kode_mk' => $draftCode, 'kode_mk_live' => $liveCode,
                    'is_capstone' => 'Tidak', 'prasyarat_mk' => '', ...$values,
                ]);
            } else {
                $courseId = $course->id_mk_draft;
                $connection->table('ekur_draft_mk')->where('id_mk_draft', $courseId)->update($values);
            }
            $connection->table('ekur_map_mk_bk')->where('id_mk_draft', $courseId)->delete();
            $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $courseId)->delete();
            foreach ($bkMap as $bkId) {
                $connection->table('ekur_map_mk_bk')->insertOrIgnore(['id_mk_draft' => $courseId, 'id_bk' => $bkId]);
            }
            $autoCplIds = $connection->table('ekur_map_cpl_bk')->whereIn('id_bk', $bkMap)->distinct()->pluck('id_cpl');
            foreach ($autoCplIds as $cplId) {
                $connection->table('ekur_map_mk_cpl')->insertOrIgnore(['id_mk_draft' => $courseId, 'id_cpl' => $cplId]);
            }
        }
    }

    private function importRpsSpreadsheet(Spreadsheet $spreadsheet, mixed $connection, string $curriculum): void
    {
        $courseIds = [];
        foreach ($connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->get(['id_mk_draft', 'kode_mk', 'kode_mk_live']) as $course) {
            $courseCode = strtoupper((string) ($course->kode_mk_live ?: $course->kode_mk));
            $courseIds[$courseCode] = (string) $course->id_mk_draft;
        }
        $cplIds = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->pluck('id_cpl', 'kode_cpl')->all();
        $courseCplIds = $connection->table('ekur_map_mk_cpl')->whereIn('id_mk_draft', array_values($courseIds))
            ->get(['id_mk_draft', 'id_cpl'])->groupBy('id_mk_draft');
        $cpmkIds = [];
        foreach ($this->sheetRows($spreadsheet, 'CPMK') as $row) {
            $liveCode = strtoupper(trim((string) ($row[0] ?? '')));
            $code = strtoupper(trim((string) ($row[1] ?? '')));
            if ($liveCode === '' && $code === '') {
                continue;
            }
            abort_unless($liveCode !== '' && $code !== '' && isset($courseIds[$liveCode]), 422, 'Kode mata kuliah dan CPMK harus sesuai dengan kurikulum.');
            $courseId = $courseIds[$liveCode];
            $values = ['deskripsi_cpmk' => trim((string) ($row[2] ?? ''))];
            $existing = $connection->table('ekur_cpmk')->where('id_mk_draft', $courseId)->where('kode_cpmk', $code)->first();
            $id = $existing
                ? tap($existing->id_cpmk, fn ($value) => $connection->table('ekur_cpmk')->where('id_cpmk', $value)->update($values))
                : $connection->table('ekur_cpmk')->insertGetId(['id_mk_draft' => $courseId, 'kode_cpmk' => $code, ...$values]);
            $cpmkIds[$liveCode][$code] = $id;
            $resolved = $this->resolveCodes((string) ($row[3] ?? ''), $cplIds);
            $allowedCplIds = ($courseCplIds->get($courseId) ?? collect())->pluck('id_cpl')->map('strval')->all();
            abort_unless(array_diff($resolved, $allowedCplIds) === [], 422, 'CPMK hanya dapat dipetakan ke CPL yang sudah dibebankan pada mata kuliah.');
            if ($resolved !== []) {
                $connection->table('ekur_map_cpmk_cpl')->where('id_cpmk', $id)->delete();
                foreach ($resolved as $cplId) {
                    $connection->table('ekur_map_cpmk_cpl')->insertOrIgnore(['id_cpmk' => $id, 'id_cpl' => $cplId]);
                }
            }
        }
        foreach ($this->sheetRows($spreadsheet, 'Sub-CPMK') as $row) {
            $liveCode = strtoupper(trim((string) ($row[0] ?? '')));
            $code = strtoupper(trim((string) ($row[1] ?? '')));
            if ($liveCode === '' && $code === '') {
                continue;
            }
            abort_unless($liveCode !== '' && $code !== '' && isset($courseIds[$liveCode]), 422, 'Kode mata kuliah dan Sub-CPMK harus sesuai dengan kurikulum.');
            $courseId = $courseIds[$liveCode];
            $values = ['deskripsi_subcpmk' => trim((string) ($row[2] ?? ''))];
            $existing = $connection->table('ekur_subcpmk')->where('id_mk_draft', $courseId)->where('kode_subcpmk', $code)->first();
            $subId = $existing
                ? tap($existing->id_subcpmk, fn ($value) => $connection->table('ekur_subcpmk')->where('id_subcpmk', $value)->update($values))
                : $connection->table('ekur_subcpmk')->insertGetId(['id_mk_draft' => $courseId, 'kode_subcpmk' => $code, ...$values]);
            $resolved = $this->resolveCodes((string) ($row[3] ?? ''), $cpmkIds[$liveCode] ?? []);
            if ($resolved !== []) {
                $connection->table('ekur_map_subcpmk_cpmk')->where('id_subcpmk', $subId)->delete();
                foreach ($resolved as $cpmkId) {
                    $connection->table('ekur_map_subcpmk_cpmk')->insertOrIgnore(['id_subcpmk' => $subId, 'id_cpmk' => $cpmkId]);
                }
            }
        }
        foreach ($this->sheetRows($spreadsheet, 'Penilaian') as $row) {
            $liveCode = strtoupper(trim((string) ($row[0] ?? '')));
            $subCode = strtoupper(trim((string) ($row[1] ?? '')));
            if ($liveCode === '' && $subCode === '') {
                continue;
            }
            abort_unless($liveCode !== '' && $subCode !== '' && isset($courseIds[$liveCode]), 422, 'Kode mata kuliah dan Sub-CPMK pada penilaian harus sesuai.');
            $subcpmkId = $connection->table('ekur_subcpmk')
                ->where('id_mk_draft', $courseIds[$liveCode])
                ->where('kode_subcpmk', $subCode)
                ->value('id_subcpmk');
            if ($subcpmkId === null) {
                abort(422, 'Sub-CPMK pada sheet penilaian tidak ditemukan.');
            }
            $weights = [
                'partisipasi' => (float) ($row[2] ?? 0),
                'project' => (float) ($row[3] ?? 0),
                'tugas' => (float) ($row[4] ?? 0),
                'kuis' => (float) ($row[5] ?? 0),
                'uts' => (float) ($row[6] ?? 0),
                'uas' => (float) ($row[7] ?? 0),
            ];
            abort_unless(min($weights) >= 0 && max($weights) <= 100, 422, 'Bobot penilaian harus berada pada rentang 0–100.');
            if ($connection->table('ekur_penilaian')->where('id_subcpmk', $subcpmkId)->exists()) {
                $connection->table('ekur_penilaian')->where('id_subcpmk', $subcpmkId)->update($weights);
            } elseif (array_sum($weights) > 0) {
                $connection->table('ekur_penilaian')->insert(['id_subcpmk' => $subcpmkId, ...$weights]);
            }
        }
    }

    private function sheetRows(Spreadsheet $spreadsheet, string $sheet): array
    {
        $worksheet = $spreadsheet->getSheetByName($sheet);

        return $worksheet ? array_slice($worksheet->toArray(), 1) : [];
    }

    private function resolveCodes(string $codes, array $lookup): array
    {
        $codes = array_values(array_filter(array_map('trim', explode(',', $codes)), static fn (string $code): bool => $code !== ''));
        $resolved = [];
        foreach ($codes as $code) {
            $key = strtoupper($code);
            abort_unless(array_key_exists($key, $lookup), 422, "Kode relasi {$code} tidak ditemukan dalam kurikulum.");
            $resolved[] = $lookup[$key];
        }

        return array_values(array_unique($resolved));
    }

    private function assertImportSheets(Spreadsheet $spreadsheet, string $kind): void
    {
        $requiredSheets = $kind === 'curriculum'
            ? ['Profil Lulusan' => 5, 'CPL' => 5, 'Bahan Kajian' => 4, 'Mata Kuliah' => 9]
            : ['CPMK' => 4, 'Sub-CPMK' => 4, 'Penilaian' => 8];

        foreach ($requiredSheets as $sheetName => $requiredColumns) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            abort_if($sheet === null, 422, "Sheet {$sheetName} wajib tersedia dalam file impor.");
            $headers = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0] ?? [];
            abort_unless(
                count(array_filter($headers, static fn (mixed $header): bool => trim((string) $header) !== '')) >= $requiredColumns,
                422,
                "Sheet {$sheetName} tidak memiliki kolom sesuai format template."
            );
        }
    }

    private function rpsData(mixed $connection, string $courseId): array
    {
        $cpmks = $connection->table('ekur_cpmk')->where('id_mk_draft', $courseId)->orderBy('kode_cpmk')->get();
        $subcpmks = $connection->table('ekur_subcpmk')->where('id_mk_draft', $courseId)->orderBy('kode_subcpmk')->get();

        return [
            'cpmks' => $cpmks,
            'subcpmks' => $subcpmks,
            'cpmkMaps' => $connection->table('ekur_map_cpmk_cpl')->whereIn('id_cpmk', $cpmks->pluck('id_cpmk'))->get(),
            'subcpmkMaps' => $connection->table('ekur_map_subcpmk_cpmk')->whereIn('id_subcpmk', $subcpmks->pluck('id_subcpmk'))->get(),
            'courseCplMaps' => $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $courseId)->get(),
            'assessments' => $connection->table('ekur_penilaian')->whereIn('id_subcpmk', $subcpmks->pluck('id_subcpmk'))->get()->keyBy('id_subcpmk'),
            'completeness' => $connection->table('ekur_rps_kelengkapan')->where('id_mk_draft', $courseId)->first(),
            'materials' => $connection->table('ekur_rps_materi')->where('id_mk_draft', $courseId)->orderBy('id_materi')->get(),
            'total_assessment_weight' => $connection->table('ekur_penilaian')
                ->whereIn('id_subcpmk', $subcpmks->pluck('id_subcpmk'))
                ->selectRaw('COALESCE(SUM(partisipasi + project + tugas + kuis + uts + uas), 0) as total')
                ->value('total'),
        ];
    }

    private function rpsItems(mixed $connection, Collection $courses): Collection
    {
        $courseIds = $courses->pluck('id_mk_draft')->map('strval')->values();
        if ($courseIds->isEmpty()) {
            return collect();
        }
        $cpmks = $connection->table('ekur_cpmk')->whereIn('id_mk_draft', $courseIds)->orderBy('kode_cpmk')->get();
        $subcpmks = $connection->table('ekur_subcpmk')->whereIn('id_mk_draft', $courseIds)->orderBy('kode_subcpmk')->get();
        $cpmkIds = $cpmks->pluck('id_cpmk');
        $subcpmkIds = $subcpmks->pluck('id_subcpmk');
        $courseCplMaps = $connection->table('ekur_map_mk_cpl')->whereIn('id_mk_draft', $courseIds)->get();
        $cpmkMaps = $cpmkIds->isEmpty() ? collect() : $connection->table('ekur_map_cpmk_cpl')->whereIn('id_cpmk', $cpmkIds)->get();
        $subcpmkMaps = $subcpmkIds->isEmpty() ? collect() : $connection->table('ekur_map_subcpmk_cpmk')->whereIn('id_subcpmk', $subcpmkIds)->get();
        $assessments = $subcpmkIds->isEmpty() ? collect() : $connection->table('ekur_penilaian')->whereIn('id_subcpmk', $subcpmkIds)->get()->keyBy('id_subcpmk');
        $completeness = $connection->table('ekur_rps_kelengkapan')->whereIn('id_mk_draft', $courseIds)->get()->keyBy('id_mk_draft');
        $materials = $connection->table('ekur_rps_materi')->whereIn('id_mk_draft', $courseIds)->orderBy('id_materi')->get()->groupBy('id_mk_draft');

        return $courses->map(function (object $course) use (
            $courseCplMaps, $cpmks, $subcpmks, $cpmkMaps, $subcpmkMaps, $assessments, $completeness, $materials
        ): array {
            $courseId = (string) $course->id_mk_draft;
            $courseCpls = $courseCplMaps->where('id_mk_draft', $courseId)->values();
            $courseCpmks = $cpmks->where('id_mk_draft', $courseId)->values();
            $courseSubcpmks = $subcpmks->where('id_mk_draft', $courseId)->values();
            $courseCpmkIds = $courseCpmks->pluck('id_cpmk')->map('strval')->all();
            $courseSubcpmkIds = $courseSubcpmks->pluck('id_subcpmk')->map('strval')->all();
            $coveredCplIds = $cpmkMaps->whereIn('id_cpmk', $courseCpmkIds)->pluck('id_cpl')->map('strval')->all();
            $coveredCpmkIds = $subcpmkMaps->whereIn('id_subcpmk', $courseSubcpmkIds)->pluck('id_cpmk')->map('strval')->all();
            $requiredCplIds = $courseCpls->pluck('id_cpl')->map('strval')->all();
            $cpmksReady = $requiredCplIds !== [] && array_diff($requiredCplIds, $coveredCplIds) === [];
            $subcpmksReady = $cpmksReady && $courseCpmkIds !== [] && array_diff($courseCpmkIds, $coveredCpmkIds) === [];
            $courseAssessments = $assessments->only($courseSubcpmkIds);

            return [
                'course' => $course,
                'data' => [
                    'cpmks' => $courseCpmks,
                    'subcpmks' => $courseSubcpmks,
                    'courseCplMaps' => $courseCpls,
                    'cpmkMaps' => $cpmkMaps->whereIn('id_cpmk', $courseCpmkIds)->values(),
                    'subcpmkMaps' => $subcpmkMaps->whereIn('id_subcpmk', $courseSubcpmkIds)->values(),
                    'assessments' => $courseAssessments,
                    'completeness' => $completeness->get($courseId),
                    'materials' => $materials->get($courseId, collect()),
                    'total_assessment_weight' => $courseAssessments->sum(fn (object $assessment): float => (float) $assessment->partisipasi + (float) $assessment->project + (float) $assessment->tugas + (float) $assessment->kuis + (float) $assessment->uts + (float) $assessment->uas),
                ],
                'workflow' => [
                    'cpmks' => ['locked' => $requiredCplIds === [], 'message' => 'Bebankan CPL pada mata kuliah terlebih dahulu.'],
                    'subcpmks' => ['locked' => ! $cpmksReady, 'message' => 'Petakan seluruh CPL mata kuliah ke CPMK terlebih dahulu.'],
                    'assessments' => ['locked' => ! $subcpmksReady, 'message' => 'Petakan setiap CPMK ke minimal satu Sub-CPMK terlebih dahulu.'],
                    'completeness' => ['locked' => ! $subcpmksReady, 'message' => 'Lengkapi pemetaan CPMK dan Sub-CPMK terlebih dahulu.'],
                    'materials' => ['locked' => ! $subcpmksReady, 'message' => 'Lengkapi pemetaan CPMK dan Sub-CPMK terlebih dahulu.'],
                ],
            ];
        });
    }

    private function rpsWorkflow(mixed $connection, string $curriculum, string $courseId): array
    {
        $courseCplIds = $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $courseId)->pluck('id_cpl')
            ->map('strval')->all();
        $cpmks = $connection->table('ekur_cpmk')->where('id_mk_draft', $courseId)->pluck('id_cpmk')->map('strval')->all();
        $coveredCplIds = $cpmks === [] ? [] : $connection->table('ekur_map_cpmk_cpl as mapping')
            ->join('ekur_cpmk as cpmk', 'mapping.id_cpmk', '=', 'cpmk.id_cpmk')
            ->where('cpmk.id_mk_draft', $courseId)->distinct()->pluck('mapping.id_cpl')->map('strval')->all();
        $coveredCpmkIds = $connection->table('ekur_map_subcpmk_cpmk as mapping')
            ->join('ekur_subcpmk as subcpmk', 'mapping.id_subcpmk', '=', 'subcpmk.id_subcpmk')
            ->where('subcpmk.id_mk_draft', $courseId)->distinct()->pluck('mapping.id_cpmk')->map('strval')->all();
        $cpmksReady = $courseCplIds !== [] && array_diff($courseCplIds, $coveredCplIds) === [];
        $subcpmksReady = $cpmksReady && $cpmks !== [] && array_diff($cpmks, $coveredCpmkIds) === [];

        return [
            'course_cpl_ids' => $courseCplIds,
            'cpmks' => ['locked' => $courseCplIds === [], 'message' => 'Bebankan CPL pada mata kuliah terlebih dahulu.'],
            'subcpmks' => ['locked' => ! $cpmksReady, 'message' => 'Petakan seluruh CPL mata kuliah ke CPMK terlebih dahulu.'],
            'assessments' => ['locked' => ! $subcpmksReady, 'message' => 'Petakan setiap CPMK ke minimal satu Sub-CPMK terlebih dahulu.'],
            'completeness' => ['locked' => ! $subcpmksReady, 'message' => 'Lengkapi pemetaan CPMK dan Sub-CPMK terlebih dahulu.'],
            'materials' => ['locked' => ! $subcpmksReady, 'message' => 'Lengkapi pemetaan CPMK dan Sub-CPMK terlebih dahulu.'],
        ];
    }

    private function rpsCourseId(mixed $connection, string $entity, mixed $submittedCourseId, ?string $record): string
    {
        if (filled($submittedCourseId)) {
            return (string) $submittedCourseId;
        }
        abort_if($record === null, 422, 'Pilih mata kuliah untuk mengelola RPS.');
        $meta = match ($entity) {
            'cpmks' => ['table' => 'ekur_cpmk', 'id' => 'id_cpmk', 'column' => 'id_mk_draft'],
            'subcpmks' => ['table' => 'ekur_subcpmk', 'id' => 'id_subcpmk', 'column' => 'id_mk_draft'],
            'completeness' => ['table' => 'ekur_rps_kelengkapan', 'id' => 'id_kelengkapan', 'column' => 'id_mk_draft'],
            'materials' => ['table' => 'ekur_rps_materi', 'id' => 'id_materi', 'column' => 'id_mk_draft'],
            'assessments' => ['table' => 'ekur_penilaian', 'id' => 'id_penilaian', 'column' => 'id_subcpmk'],
            default => abort(404),
        };
        $courseId = $connection->table($meta['table'])->where($meta['id'], $record)->value($meta['column']);
        if ($entity === 'assessments' && $courseId !== null) {
            $courseId = $connection->table('ekur_subcpmk')->where('id_subcpmk', $courseId)->value('id_mk_draft');
        }
        abort_if($courseId === null, 404);

        return (string) $courseId;
    }

    private function ensureRpsStageUnlocked(mixed $connection, string $curriculum, string $entity, string $courseId): void
    {
        abort_unless($connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->where('id_mk_draft', $courseId)->exists(), 404);
        $stage = match ($entity) {
            'cpmks' => 'cpmks',
            'subcpmks' => 'subcpmks',
            'assessments' => 'assessments',
            'completeness' => 'completeness',
            'materials' => 'materials',
            default => abort(404),
        };
        $workflow = $this->rpsWorkflow($connection, $curriculum, $courseId);
        abort_if($workflow[$stage]['locked'], 422, $workflow[$stage]['message']);
    }

    private function validatedEntity(Request $request, string $entity, string $curriculum, ?string $record): array
    {
        $lecturerExists = static fn () => Rule::exists('simak_main.s1m4k_masterdosen', 'id_dosen');

        $rules = match ($entity) {
            'profiles' => [
                'kode_pl' => ['sometimes', 'nullable', 'string', 'max:20'],
                'profil_lulusan' => ['required', 'string'],
                'deskripsi_pl' => ['required', 'string'],
                'aspek' => ['required', 'array', 'min:1'],
                'aspek.*' => [Rule::in(self::ASPECTS)],
                'referensi' => ['nullable', 'string', 'max:150'],
            ],
            'professions' => ['nama_profesi' => ['required', 'string', 'max:255'], 'referensi' => ['nullable', 'string', 'max:150']],
            'cpls' => [
                'kode_cpl' => ['sometimes', 'nullable', 'string', 'max:20'],
                'aspek' => ['required', Rule::in(self::ASPECTS)],
                'deskripsi_cpl' => ['required', 'string'],
                'referensi' => ['nullable', 'string', 'max:150'],
                'id_pl' => ['required', 'array', 'min:1'],
                'id_pl.*' => ['integer', Rule::exists('simak_main.ekur_profil_lulusan', 'id_pl')->where('id_kur', $curriculum)],
            ],
            'bks' => [
                'kode_bk' => ['sometimes', 'nullable', 'string', 'max:20'],
                'nama_bk' => ['required', 'string', 'max:150'],
                'referensi' => ['nullable', 'string', 'max:150'],
                'deskripsi_bk' => ['nullable', 'string'],
                'kompetensi' => ['nullable', Rule::in(['Utama', 'Pendukung', 'Umum'])],
                'id_cpl' => ['required', 'array', 'min:1'],
                'id_cpl.*' => ['integer', Rule::exists('simak_main.ekur_cpl', 'id_cpl')->where('id_kur', $curriculum)],
            ],
            'courses' => [
                'kode_mk' => ['sometimes', 'nullable', 'string', 'max:30'],
                'kode_mk_live' => ['nullable', 'string', 'max:20'],
                'nama_mk' => ['required', 'string', 'max:150'],
                'sks' => ['required', 'integer', 'between:0,99'],
                'sks_teori' => ['required', 'integer', 'between:0,99'],
                'sks_praktek' => ['required', 'integer', 'between:0,99'],
                'kategori_mk' => ['nullable', 'string', 'max:100'],
                'is_capstone' => ['required', 'string', 'max:50'],
                'id_bk' => ['required', 'array', 'min:1'],
                'id_bk.*' => ['integer', Rule::exists('simak_main.ekur_bk', 'id_bk')->where('id_kur', $curriculum)],
            ],
            'cpmks' => [
                'id_mk_draft' => ['required', Rule::exists('simak_main.ekur_draft_mk', 'id_mk_draft')->where('id_kur', $curriculum)],
                'kode_cpmk' => ['sometimes', 'nullable', 'string', 'max:15'],
                'deskripsi_cpmk' => ['required', 'string'],
                'id_cpl' => ['required', 'array', 'min:1'],
                'id_cpl.*' => ['integer', Rule::exists('simak_main.ekur_cpl', 'id_cpl')->where('id_kur', $curriculum)],
            ],
            'subcpmks' => [
                'id_mk_draft' => ['required', Rule::exists('simak_main.ekur_draft_mk', 'id_mk_draft')->where('id_kur', $curriculum)],
                'kode_subcpmk' => ['sometimes', 'nullable', 'string', 'max:10'],
                'deskripsi_subcpmk' => ['required', 'string'],
                'id_cpmk' => ['required', 'array', 'min:1'],
                'id_cpmk.*' => ['integer', Rule::exists('simak_main.ekur_cpmk', 'id_cpmk')->where('id_mk_draft', $request->input('id_mk_draft'))],
            ],
            'completeness' => [
                'id_mk_draft' => ['required', Rule::exists('simak_main.ekur_draft_mk', 'id_mk_draft')->where('id_kur', $curriculum)],
                'rumpun_mk' => ['nullable', 'string', 'max:150'],
                'deskripsi_singkat' => ['nullable', 'string'],
                'dosen_pengampu' => ['required', 'array', 'min:1'],
                'dosen_pengampu.*' => ['string', 'max:50', $lecturerExists()],
                'dsn_pengembang' => ['nullable', 'string', 'max:50', $lecturerExists()],
                'koor_rmk' => ['nullable', 'string', 'max:50', $lecturerExists()],
                'koor_prodi' => ['nullable', 'string', 'max:50', $lecturerExists()],
                'wd1' => ['nullable', 'string', 'max:50', $lecturerExists()],
                'bahan_kajian' => ['nullable', 'string'],
                'pustaka_utama' => ['nullable', 'string'],
                'pustaka_pendukung' => ['nullable', 'string'],
            ],
            'materials' => [
                'id_mk_draft' => ['required', Rule::exists('simak_main.ekur_draft_mk', 'id_mk_draft')->where('id_kur', $curriculum)],
                'minggu_ke' => ['required'],
                'minggu_ke.*' => ['string', 'max:100'],
                'kemampuan_akhir' => ['required'],
                'kemampuan_akhir.*' => ['string', 'max:100', Rule::exists('simak_main.ekur_subcpmk', 'id_subcpmk')->where('id_mk_draft', $request->input('id_mk_draft'))],
                'indikator' => ['nullable', 'string'],
                'kriteria_teknik' => ['nullable', 'string'],
                'bentuk_pembelajaran' => ['nullable', 'string'],
                'materi_pembelajaran' => ['nullable', 'string'],
                'pustaka_pilihan' => ['required'],
                'pustaka_pilihan.*' => [Rule::in(['Pustaka Utama', 'Pustaka Pendukung'])],
                'bobot_penilaian' => ['nullable', 'numeric', 'between:0,100'],
            ],
            'assessments' => [
                'id_mk_draft' => ['required', Rule::exists('simak_main.ekur_draft_mk', 'id_mk_draft')->where('id_kur', $curriculum)],
                'nilai' => ['required', 'array'],
                'nilai.*.partisipasi' => ['nullable', 'numeric', 'between:0,100'],
                'nilai.*.project' => ['nullable', 'numeric', 'between:0,100'],
                'nilai.*.tugas' => ['nullable', 'numeric', 'between:0,100'],
                'nilai.*.kuis' => ['nullable', 'numeric', 'between:0,100'],
                'nilai.*.uts' => ['nullable', 'numeric', 'between:0,100'],
                'nilai.*.uas' => ['nullable', 'numeric', 'between:0,100'],
            ],
            default => abort(404),
        };
        $validated = $request->validate($rules);
        if ($entity === 'courses') {
            abort_unless((int) $validated['sks_teori'] + (int) $validated['sks_praktek'] === (int) $validated['sks'], 422, 'Jumlah SKS teori dan praktik harus sama dengan total SKS.');
            $validated['kode_mk_live'] = strtoupper(trim((string) ($validated['kode_mk_live'] ?? ''))) ?: null;
        }
        if ($entity === 'materials') {
            foreach (['minggu_ke', 'kemampuan_akhir', 'pustaka_pilihan'] as $field) {
                if (is_array($validated[$field] ?? null)) {
                    $values = array_values(array_unique(array_filter(array_map(
                        static fn (mixed $value): string => trim((string) $value),
                        $validated[$field],
                    ), static fn (string $value): bool => $value !== '')));
                    abort_unless($values !== [], 422, 'Minggu, Sub-CPMK, dan pustaka acuan wajib dipilih.');
                    $validated[$field] = implode(', ', $values);
                }
            }
        }
        foreach (['kode_pl', 'kode_cpl', 'kode_bk', 'kode_mk', 'kode_mk_live', 'kode_cpmk', 'kode_subcpmk'] as $codeField) {
            if (isset($validated[$codeField])) {
                $validated[$codeField] = strtoupper(trim((string) $validated[$codeField]));
            }
        }

        return $validated;
    }

    private function persistEntity(mixed $connection, string $curriculum, string $entity, ?string $record, array $data): void
    {
        $meta = $this->ownedRecord($connection, $curriculum, $entity, $record);
        $mapFields = match ($entity) {
            'profiles' => [],
            'cpls' => ['id_pl' => $data['id_pl']],
            'bks' => ['id_cpl' => $data['id_cpl']],
            'courses' => ['id_bk' => $data['id_bk']],
            'cpmks' => ['id_cpl' => $data['id_cpl']],
            'subcpmks' => ['id_cpmk' => $data['id_cpmk']],
            default => [],
        };
        unset($data['id_pl'], $data['id_cpl'], $data['id_bk'], $data['id_cpmk']);
        if ($entity === 'profiles') {
            $data['aspek'] = implode(', ', $data['aspek']);
            $data['profil_lulusan'] = $data['profil_lulusan'] ?? $data['deskripsi_pl'];
        } elseif ($entity === 'completeness') {
            $data['dosen_pengampu'] = implode(',', $data['dosen_pengampu']);
        } elseif ($entity === 'assessments') {
            $expectedSubcpmkIds = $connection->table('ekur_subcpmk')
                ->where('id_mk_draft', $data['id_mk_draft'])
                ->pluck('id_subcpmk')
                ->map('strval')
                ->sort()
                ->values()
                ->all();
            $submittedSubcpmkIds = array_map('strval', array_keys($data['nilai']));
            sort($submittedSubcpmkIds);
            abort_unless(
                $expectedSubcpmkIds !== [] && $submittedSubcpmkIds === $expectedSubcpmkIds,
                422,
                'Matriks penilaian harus mencakup seluruh Sub-CPMK pada mata kuliah.'
            );
            $totalWeight = array_sum(array_map(
                static fn (array $weights): float => array_sum(array_map(static fn (mixed $value): float => (float) ($value ?? 0), $weights)),
                $data['nilai'],
            ));
            abort_unless(abs($totalWeight - 100) < 0.005, 422, 'Total bobot penilaian mata kuliah harus tepat 100%.');
            foreach ($data['nilai'] as $subcpmkId => $weights) {
                $this->assertSubCpmkBelongsToCourse($connection, (string) $subcpmkId, (string) $data['id_mk_draft']);
                $values = array_map(static fn (mixed $value): float => (float) ($value ?? 0), $weights);
                $existing = $connection->table('ekur_penilaian')->where('id_subcpmk', $subcpmkId)->exists();
                if ($existing) {
                    $connection->table('ekur_penilaian')->where('id_subcpmk', $subcpmkId)->update($values);
                } elseif (array_sum($values) > 0) {
                    $connection->table('ekur_penilaian')->insert(['id_subcpmk' => $subcpmkId, ...$values]);
                }
            }

            return;
        }

        if ($entity === 'courses' && $record === null) {
            $data['semester'] = 0;
            $data['prasyarat_mk'] = '';
            $data['jenis_mk'] = 'Wajib';
        }
        if ($entity === 'bks') {
            $data['kompetensi'] = $data['kompetensi'] ?? 'Utama';
            $data['deskripsi_bk'] = $data['deskripsi_bk'] ?? null;
        }
        if ($entity === 'professions') {
            $data['id_kur'] = $curriculum;
        } elseif ($entity === 'profiles' || $entity === 'cpls' || $entity === 'bks') {
            $data['id_kur'] = $curriculum;
        } elseif ($entity === 'courses') {
            $data['id_kur'] = $curriculum;
        }
        if ($entity === 'courses' && $record !== null) {
            unset($data['kode_mk']);
        }
        $table = $meta['table'];
        $idColumn = $meta['id'];
        if ($entity === 'completeness' && $record === null) {
            $existingId = $connection->table($table)->where('id_mk_draft', $data['id_mk_draft'])->value($idColumn);
            if ($existingId !== null) {
                $record = (string) $existingId;
            }
        }
        if ($record === null) {
            $id = $connection->table($table)->insertGetId($data);
        } else {
            $id = $record;
            $connection->table($table)->where($idColumn, $record)->update($data);
        }

        foreach ($mapFields as $foreignKey => $ids) {
            if ($foreignKey === 'id_pl' && $entity === 'profiles') {
                continue;
            }
            $map = match ([$entity, $foreignKey]) {
                ['cpls', 'id_pl'] => ['table' => 'ekur_map_cpl_pl', 'self' => 'id_cpl'],
                ['bks', 'id_cpl'] => ['table' => 'ekur_map_cpl_bk', 'self' => 'id_bk'],
                ['courses', 'id_bk'] => ['table' => 'ekur_map_mk_bk', 'self' => 'id_mk_draft'],
                ['cpmks', 'id_cpl'] => ['table' => 'ekur_map_cpmk_cpl', 'self' => 'id_cpmk'],
                ['subcpmks', 'id_cpmk'] => ['table' => 'ekur_map_subcpmk_cpmk', 'self' => 'id_subcpmk'],
                default => null,
            };
            if ($map === null) {
                continue;
            }
            $connection->table($map['table'])->where($map['self'], $id)->delete();
            foreach ($ids as $foreignId) {
                $connection->table($map['table'])->insertOrIgnore([$map['self'] => $id, $foreignKey => $foreignId]);
            }
        }
        if ($entity === 'courses') {
            $connection->table('ekur_map_mk_cpl')->where('id_mk_draft', $id)->delete();
            $cplIds = $connection->table('ekur_map_cpl_bk')->whereIn('id_bk', $mapFields['id_bk'])->distinct()->pluck('id_cpl');
            foreach ($cplIds as $cplId) {
                $connection->table('ekur_map_mk_cpl')->insertOrIgnore(['id_mk_draft' => $id, 'id_cpl' => $cplId]);
            }
        }
    }

    private function assertUniqueEntity(mixed $connection, string $curriculum, string $entity, array $data, ?string $record): void
    {
        $uniqueFields = match ($entity) {
            'profiles' => ['kode_pl' => ['table' => 'ekur_profil_lulusan', 'id' => 'id_pl', 'parent' => 'id_kur']],
            'cpls' => ['kode_cpl' => ['table' => 'ekur_cpl', 'id' => 'id_cpl', 'parent' => 'id_kur']],
            'bks' => ['kode_bk' => ['table' => 'ekur_bk', 'id' => 'id_bk', 'parent' => 'id_kur']],
            'courses' => [
                'kode_mk' => ['table' => 'ekur_draft_mk', 'id' => 'id_mk_draft', 'parent' => 'id_kur'],
                'kode_mk_live' => ['table' => 'ekur_draft_mk', 'id' => 'id_mk_draft', 'parent' => 'id_kur'],
            ],
            'cpmks' => ['kode_cpmk' => ['table' => 'ekur_cpmk', 'id' => 'id_cpmk', 'parent' => 'id_mk_draft']],
            'subcpmks' => ['kode_subcpmk' => ['table' => 'ekur_subcpmk', 'id' => 'id_subcpmk', 'parent' => 'id_mk_draft']],
            default => [],
        };
        foreach ($uniqueFields as $field => $meta) {
            if (! array_key_exists($field, $data) || ! filled($data[$field])) {
                continue;
            }
            if ($field === 'kode_cpmk') {
                $query = $connection->table('ekur_cpmk as cpmk')
                    ->join('ekur_draft_mk as course', 'course.id_mk_draft', '=', 'cpmk.id_mk_draft')
                    ->where('course.id_kur', $curriculum)
                    ->where('cpmk.kode_cpmk', $data[$field]);
            } else {
                $query = $connection->table($meta['table'])->where($field, $data[$field]);
            }
            if ($field === 'kode_subcpmk') {
                $query->where($meta['parent'], $data['id_mk_draft']);
            } else {
                if ($field !== 'kode_cpmk') {
                    $query->where($meta['parent'], $curriculum);
                }
            }

            if ($record !== null) {
                $query->where($field === 'kode_cpmk' ? 'cpmk.'.$meta['id'] : $meta['id'], '!=', $record);
            }
            abort_if($query->exists(), 422, 'Kode '.$field.' sudah digunakan dalam kurikulum ini.');
        }
    }

    private function manualCodeDefinition(string $entity): ?array
    {
        return match ($entity) {
            'profiles' => ['table' => 'ekur_profil_lulusan', 'column' => 'kode_pl', 'prefix' => 'PL', 'padding' => 2],
            'cpls' => ['table' => 'ekur_cpl', 'column' => 'kode_cpl', 'prefix' => 'CPL', 'padding' => 2],
            'bks' => ['table' => 'ekur_bk', 'column' => 'kode_bk', 'prefix' => 'BK', 'padding' => 2],
            'courses' => ['table' => 'ekur_draft_mk', 'column' => 'kode_mk', 'prefix' => 'MK', 'padding' => 3],
            default => null,
        };
    }

    private function nextManualCode(mixed $connection, string $curriculum, string $entity): string
    {
        $definition = $this->manualCodeDefinition($entity);
        abort_if($definition === null, 404);

        $prefixPattern = preg_quote($definition['prefix'], '/');
        $maxSequence = $connection->table($definition['table'])
            ->where('id_kur', $curriculum)
            ->pluck($definition['column'])
            ->reduce(static function (int $max, mixed $code) use ($prefixPattern): int {
                if (preg_match('/^'.$prefixPattern.'-?(\\d+)$/i', (string) $code, $matches) !== 1) {
                    return $max;
                }

                return max($max, (int) $matches[1]);
            }, 0);

        return $definition['prefix'].'-'.str_pad((string) ($maxSequence + 1), $definition['padding'], '0', STR_PAD_LEFT);
    }

    private function nextRpsCode(mixed $connection, string $curriculum, string $entity, array $data): string
    {
        if ($entity === 'cpmks') {
            $cplId = (string) $data['id_cpl'][0];
            $cpl = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->where('id_cpl', $cplId)->first(['kode_cpl']);
            abort_if($cpl === null, 422, 'CPL yang dipilih tidak ditemukan dalam kurikulum.');

            $codes = $connection->table('ekur_map_cpmk_cpl as mapping')
                ->join('ekur_cpmk as cpmk', 'mapping.id_cpmk', '=', 'cpmk.id_cpmk')
                ->join('ekur_draft_mk as course', 'cpmk.id_mk_draft', '=', 'course.id_mk_draft')
                ->where('course.id_kur', $curriculum)
                ->where('mapping.id_cpl', $cplId)
                ->pluck('cpmk.kode_cpmk');
            $sequence = $this->nextTrailingDigit($codes);
            $baseCode = preg_replace('/\D/', '', (string) $cpl->kode_cpl);
            $code = 'CPMK'.$baseCode.$sequence;
            abort_unless(strlen($code) <= 15, 422, 'Kode CPL terlalu panjang untuk membentuk kode CPMK otomatis.');

            return $code;
        }

        $cpmkId = (string) $data['id_cpmk'][0];
        $cpmk = $connection->table('ekur_cpmk')
            ->where('id_mk_draft', $data['id_mk_draft'])
            ->where('id_cpmk', $cpmkId)
            ->first(['kode_cpmk']);
        abort_if($cpmk === null, 422, 'CPMK yang dipilih tidak ditemukan pada mata kuliah.');

        $codes = $connection->table('ekur_map_subcpmk_cpmk as mapping')
            ->join('ekur_subcpmk as subcpmk', 'mapping.id_subcpmk', '=', 'subcpmk.id_subcpmk')
            ->where('mapping.id_cpmk', $cpmkId)
            ->where('subcpmk.id_mk_draft', $data['id_mk_draft'])
            ->pluck('subcpmk.kode_subcpmk');
        $sequence = $this->nextTrailingDigit($codes);
        $baseCode = preg_replace('/\D/', '', (string) $cpmk->kode_cpmk);
        $code = 'SUB'.$baseCode.$sequence;
        abort_unless(strlen($code) <= 10, 422, 'Kode CPMK terlalu panjang untuk membentuk kode Sub-CPMK otomatis.');

        return $code;
    }

    private function nextTrailingDigit(mixed $codes): int
    {
        $maxSequence = $codes->reduce(static function (int $max, mixed $code): int {
            $lastDigit = substr((string) $code, -1);

            return ctype_digit($lastDigit) ? max($max, (int) $lastDigit) : $max;
        }, 0);

        return $maxSequence + 1;
    }

    private function ownedRecord(mixed $connection, string $curriculum, string $entity, ?string $record): array
    {
        $meta = match ($entity) {
            'profiles' => ['table' => 'ekur_profil_lulusan', 'id' => 'id_pl', 'parent' => 'id_kur'],
            'professions' => ['table' => 'ekur_profesi', 'id' => 'id_profesi', 'parent' => 'id_kur'],
            'cpls' => ['table' => 'ekur_cpl', 'id' => 'id_cpl', 'parent' => 'id_kur'],
            'bks' => ['table' => 'ekur_bk', 'id' => 'id_bk', 'parent' => 'id_kur'],
            'courses' => ['table' => 'ekur_draft_mk', 'id' => 'id_mk_draft', 'parent' => 'id_kur'],
            'cpmks' => ['table' => 'ekur_cpmk', 'id' => 'id_cpmk', 'parent' => 'id_mk_draft'],
            'subcpmks' => ['table' => 'ekur_subcpmk', 'id' => 'id_subcpmk', 'parent' => 'id_mk_draft'],
            'completeness' => ['table' => 'ekur_rps_kelengkapan', 'id' => 'id_kelengkapan', 'parent' => 'id_mk_draft'],
            'materials' => ['table' => 'ekur_rps_materi', 'id' => 'id_materi', 'parent' => 'id_mk_draft'],
            'assessments' => ['table' => 'ekur_penilaian', 'id' => 'id_penilaian', 'parent' => 'id_subcpmk'],
            default => abort(404),
        };
        if ($record === null) {
            return $meta;
        }
        $query = $connection->table($meta['table'])->where($meta['id'], $record);
        if ($entity === 'cpmks' || $entity === 'subcpmks' || $entity === 'completeness' || $entity === 'materials') {
            $query->whereIn($meta['parent'], $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->select('id_mk_draft'));
        } elseif ($entity === 'assessments') {
            $query->whereIn($meta['parent'], $connection->table('ekur_subcpmk')
                ->whereIn('id_mk_draft', $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->select('id_mk_draft'))
                ->select('id_subcpmk'));
        } else {
            $query->where($meta['parent'], $curriculum);
        }
        abort_if($query->first() === null, 404);

        return $meta;
    }

    private function assertRpsRecordCourse(mixed $connection, string $entity, ?string $record, string $courseId): void
    {
        if ($record === null) {
            return;
        }
        $meta = match ($entity) {
            'cpmks' => ['table' => 'ekur_cpmk', 'id' => 'id_cpmk'],
            'subcpmks' => ['table' => 'ekur_subcpmk', 'id' => 'id_subcpmk'],
            'completeness' => ['table' => 'ekur_rps_kelengkapan', 'id' => 'id_kelengkapan'],
            'materials' => ['table' => 'ekur_rps_materi', 'id' => 'id_materi'],
            default => null,
        };
        if ($meta === null) {
            return;
        }
        $existingCourseId = $connection->table($meta['table'])->where($meta['id'], $record)->value('id_mk_draft');
        abort_unless((string) $existingCourseId === $courseId, 422, 'Data RPS tidak dapat dipindahkan ke mata kuliah lain.');
    }

    private function assertSubCpmkBelongsToCourse(mixed $connection, string $subcpmkId, string $courseId): void
    {
        abort_unless($connection->table('ekur_subcpmk')
            ->where('id_subcpmk', $subcpmkId)
            ->where('id_mk_draft', $courseId)
            ->exists(), 404);
    }

    private function findCurriculum(Authenticatable $user, string $id): object
    {
        $query = DB::connection('simak_main')->table('ekur_kurikulum as curriculum')
            ->leftJoin('s1m4k_masterprodi as program', 'curriculum.id_prodi', '=', 'program.id_prodi')
            ->where('curriculum.id_kur', $id)
            ->select(['curriculum.*', 'program.nama_program_studi', 'program.nama_jenjang_pendidikan']);
        $this->scopePrograms($query, $user, 'curriculum');
        $record = $query->first();
        abort_if($record === null, 404);

        return $record;
    }

    private function programs(Authenticatable $user): mixed
    {
        $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->select(['id_prodi', 'id_fakultas', 'nama_program_studi', 'nama_jenjang_pendidikan'])
            ->orderBy('nama_program_studi');
        $this->scopePrograms($query, $user);

        return $query->get();
    }

    private function selectedProgram(Request $request, Authenticatable $user, mixed $programs): string
    {
        if (in_array(data_get($user, 'role'), ['userprodi', 'timkur'], true)) {
            return $this->programId($user);
        }
        $requested = trim((string) $request->query('prodi', ''));
        if ($requested !== '' && ! $programs->contains(fn (object $program): bool => (string) $program->id_prodi === $requested)) {
            abort(404);
        }

        return $requested;
    }

    private function scopePrograms(mixed $query, Authenticatable $user, string $alias = ''): void
    {
        $prefix = $alias !== '' ? $alias.'.' : '';
        if (in_array(data_get($user, 'role'), ['userprodi', 'timkur'], true)) {
            $query->where($prefix.'id_prodi', $this->programId($user));
        } elseif (data_get($user, 'role') === 'pimfak') {
            $facultyId = trim((string) data_get($user, 'id_fakultas', ''));
            abort_unless($facultyId !== '', 403);
            $query->where($prefix.'id_fakultas', $facultyId);
        }
    }

    private function programId(Authenticatable $user): string
    {
        $programId = trim((string) data_get($user, 'id_prodi', ''));
        abort_unless($programId !== '', 403, 'Akun tidak memiliki cakupan program studi.');

        return $programId;
    }

    private function ensureRole(Authenticatable $user, array $roles): void
    {
        abort_unless(in_array(data_get($user, 'role'), $roles, true), 403);
    }

    private function curriculumWorkflow(mixed $connection, string $curriculum): array
    {
        $profiles = $connection->table('ekur_profil_lulusan')->where('id_kur', $curriculum)->pluck('id_pl');
        $cpls = $connection->table('ekur_cpl')->where('id_kur', $curriculum)->pluck('id_cpl');
        $bks = $connection->table('ekur_bk')->where('id_kur', $curriculum)->pluck('id_bk');
        $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)
            ->get(['id_mk_draft', 'semester', 'prasyarat_mk']);
        $mappedProfiles = $profiles->isEmpty() ? 0 : $connection->table('ekur_map_cpl_pl as mapping')
            ->join('ekur_cpl as cpl', 'cpl.id_cpl', '=', 'mapping.id_cpl')
            ->where('cpl.id_kur', $curriculum)
            ->whereIn('mapping.id_pl', $profiles)
            ->distinct()->count('mapping.id_pl');
        $mappedCplsToBks = $cpls->isEmpty() ? 0 : $connection->table('ekur_map_cpl_bk')
            ->whereIn('id_cpl', $cpls)
            ->whereIn('id_bk', $bks)
            ->distinct()->count('id_cpl');
        $mappedCplsToCourses = $cpls->isEmpty() ? 0 : $connection->table('ekur_map_mk_cpl')
            ->whereIn('id_cpl', $cpls)
            ->whereIn('id_mk_draft', $courses->pluck('id_mk_draft'))
            ->distinct()->count('id_cpl');
        $profilesReady = $profiles->isNotEmpty()
            && $cpls->isNotEmpty()
            && $mappedProfiles === $profiles->count();
        $bksReady = $profilesReady && $bks->isNotEmpty() && $mappedCplsToBks === $cpls->count();
        $coursesReady = $bksReady && $courses->isNotEmpty();
        $distributionReady = $coursesReady && $courses->every(fn (object $course): bool => (int) $course->semester > 0);
        $finalizable = $coursesReady
            && $mappedCplsToCourses === $cpls->count()
            && $distributionReady;

        return [
            'profil' => ['locked' => false, 'message' => ''],
            'profesi' => ['locked' => false, 'message' => ''],
            'cpl' => [
                'locked' => $profiles->isEmpty(),
                'message' => 'Tambahkan minimal satu profil lulusan terlebih dahulu.',
            ],
            'bk' => [
                'locked' => ! $profilesReady,
                'message' => 'Lengkapi profil lulusan, CPL, dan pemetaan setiap profil/CPL terlebih dahulu.',
            ],
            'mk' => [
                'locked' => ! $bksReady,
                'message' => 'Lengkapi bahan kajian dan pastikan setiap CPL telah dipetakan ke bahan kajian.',
            ],
            'sebaran' => [
                'locked' => ! $coursesReady || $mappedCplsToCourses !== $cpls->count(),
                'message' => 'Tambahkan mata kuliah dan petakan setiap CPL ke mata kuliah sebelum menyusun sebaran.',
            ],
            'final' => [
                'locked' => ! $finalizable,
                'message' => 'Lengkapi seluruh pemetaan dan sebaran sebelum finalisasi.',
            ],
            'finalizable' => $finalizable,
        ];
    }

    private function ensureStageUnlocked(mixed $connection, string $curriculum, string $entity): void
    {
        $stage = match ($entity) {
            'profiles' => 'profil',
            'professions' => 'profesi',
            'cpls' => 'cpl',
            'bks' => 'bk',
            'courses' => 'mk',
            'distribution' => 'sebaran',
            default => abort(404),
        };
        $workflow = $this->curriculumWorkflow($connection, $curriculum);
        abort_if($workflow[$stage]['locked'], 422, $workflow[$stage]['message']);
    }

    private function ensureFinalizable(mixed $connection, string $curriculum): void
    {
        $workflow = $this->curriculumWorkflow($connection, $curriculum);
        abort_unless($workflow['finalizable'], 422, 'Lengkapi pemetaan profil, CPL, bahan kajian, mata kuliah, dan sebaran sebelum finalisasi.');
        $courses = $connection->table('ekur_draft_mk')->where('id_kur', $curriculum)->get();
        foreach ($courses as $course) {
            abort_unless((int) $course->semester > 0, 422, 'Setiap mata kuliah harus memiliki semester sebelum finalisasi.');
        }
    }
}
