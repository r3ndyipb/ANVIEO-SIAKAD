<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CamabaVerificationController extends Controller
{
    private const ROLES = ['admin', 'regulang'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{5}$/'],
            'program' => ['nullable', 'string', 'max:100'],
        ]);
        $latestPeriod = (string) (DB::connection('simak_main')->table('s1m4k_kalender')->max('tahun') ?? '');
        $period = (string) ($filters['period'] ?? $latestPeriod);
        if ($period !== '') {
            abort_unless(preg_match('/^\d{5}$/', $period) === 1, 422);
        }
        $programs = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->orderBy('nama_program_studi')
            ->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        $selectedProgram = (string) ($filters['program'] ?? '');
        if ($selectedProgram !== '') {
            abort_unless($programs->contains('id_prodi', $selectedProgram), 404);
        }
        $periods = DB::connection('simak_main')->table('s1m4k_camaba')
            ->whereNotNull('id_periode_masuk')->distinct()
            ->orderByDesc('id_periode_masuk')->pluck('id_periode_masuk');

        return view('camaba-verification.index', [
            'period' => $period,
            'periods' => $periods,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'camabaYear' => (int) now()->format('Y'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorizeRole($request->user());
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{5}$/'],
            'program' => ['nullable', 'string', 'max:100'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:120'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:4'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);
        $query = $this->candidateQuery($validated['period'], (string) ($validated['program'] ?? ''));
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where('camaba.nim', 'like', $like)
                    ->orWhere('camaba.nama_mahasiswa', 'like', $like)
                    ->orWhere('camaba.no_peserta', 'like', $like)
                    ->orWhere('program.nama_program_studi', 'like', $like);
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = ['camaba.nim', 'camaba.nim', 'camaba.nama_mahasiswa', 'program.nama_program_studi', 'camaba.status'];
        $sortIndex = (int) $request->input('order.0.column', 2);
        $sortDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->orderBy($sortFields[$sortIndex], $sortDirection)
            ->offset($start)->limit($length)
            ->get(['camaba.id', 'camaba.nim', 'camaba.nama_mahasiswa', 'camaba.no_peserta', 'camaba.status', 'program.nama_program_studi']);

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $records->map(fn (object $record): array => [
                'id' => $record->id,
                'nim' => $record->nim,
                'nama_mahasiswa' => $record->nama_mahasiswa,
                'no_peserta' => $record->no_peserta,
                'nama_program_studi' => $record->nama_program_studi,
                'status' => (int) $record->status === 1 ? 'Terverifikasi' : 'Belum diverifikasi',
                'verified' => (int) $record->status === 1,
                'verify_url' => route('camaba-verification.show', $record->id),
            ]),
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizeRole($request->user());
        $headers = [
            'No', 'Nomor Peserta', 'NPM', 'NISN', 'NIK', 'Email', 'Telepon',
            'Nama Mahasiswa', 'Jenis Kelamin', 'Agama', 'Jalur', 'Tempat Lahir',
            'Tanggal Lahir', 'Nama Ibu', 'Kelurahan', 'Kategori Uang Kuliah', 'Nominal Uang Kuliah',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Data MABA');
            $sheet->fromArray([$headers]);
            $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
            $sheet->getStyle('B2:E1000')->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('G2:G1000')->getNumberFormat()->setFormatCode('@');
            foreach (range('A', 'Q') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');

            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Petunjuk');
            $instructions->fromArray([
                ['Kolom', 'Keterangan'],
                ['Nomor Peserta', 'Wajib; harus konsisten untuk setiap peserta dan jalur masuk.'],
                ['NPM', 'Wajib; simpan sebagai teks agar angka di awal tidak hilang.'],
                ['Jenis Kelamin', 'Wajib: L atau P.'],
                ['Agama', 'Wajib; gunakan nama agama yang dikenali SIMAK.'],
                ['Jalur', 'Wajib: SNBP, SNBT, atau MANDIRI.'],
                ['Tanggal Lahir', 'Gunakan format tanggal Excel atau YYYY-MM-DD.'],
                ['Kategori Uang Kuliah', 'Isi KIP-K untuk pembiayaan KIP-K; nilai lain memakai pembiayaan reguler.'],
                ['Nominal Uang Kuliah', 'Isi angka nominal uang kuliah tanpa simbol mata uang.'],
                ['Program studi', 'Pilih prodi tujuan sebelum mengunggah file.'],
                ['Catatan', 'Baris pertama adalah header. Baris kosong dan jalur yang tidak dikenal dilewati.'],
            ]);
            $instructions->getStyle('A1:B1')->getFont()->setBold(true);
            $instructions->getColumnDimension('A')->setAutoSize(true);
            $instructions->getColumnDimension('B')->setAutoSize(true);
            $spreadsheet->setActiveSheetIndex(0);
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-data-maba.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'program' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'extensions:xlsx,csv', 'max:10240'],
        ]);
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $validated['program'])
            ->first(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        abort_if($program === null, 422, 'Program studi tidak ditemukan.');
        $universityId = trim((string) ($program->id_perguruan_tinggi ?: DB::connection('simak_main')->table('s1m4k_masterpt')->value('id_perguruan_tinggi')));
        abort_if($universityId === '', 422, 'Data perguruan tinggi belum tersedia.');

        try {
            $extension = strtolower($validated['file']->getClientOriginalExtension());
            $reader = $extension === 'csv'
                ? new Csv
                : new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
            $reader->setReadDataOnly(true);
            $sheetData = $reader->load($validated['file']->getRealPath())->getActiveSheet()->toArray('', true, true, false);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'File MABA tidak dapat dibaca. Gunakan template Excel yang disediakan.']);
        }
        if (count($sheetData) > 1001) {
            throw ValidationException::withMessages(['file' => 'Maksimal 1000 baris data MABA dapat diproses sekali unggah.']);
        }

        $year = (int) now()->format('Y');
        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($sheetData, $connection, $program, $universityId, $year, &$counts): void {
            foreach (array_slice($sheetData, 1) as $row) {
                if (count(array_filter($row, static fn (mixed $value): bool => filled($value))) === 0) {
                    continue;
                }
                $values = $this->camabaUploadValues($row, $program, $universityId, $year);
                if ($values === null) {
                    $counts['skipped']++;

                    continue;
                }
                $existing = $connection->table('s1m4k_camaba')
                    ->where('no_peserta', $values['no_peserta'])
                    ->where('id_jalur_daftar', $values['id_jalur_daftar'])
                    ->first(['id']);
                if ($existing === null) {
                    $connection->table('s1m4k_camaba')->insert([
                        'id' => (string) Str::uuid(),
                        ...$values,
                        'status' => 0,
                    ]);
                    $counts['inserted']++;

                    continue;
                }
                $connection->table('s1m4k_camaba')->where('id', $existing->id)->update($values);
                $counts['updated']++;
            }
        });
        $activityLogger->record($user, 'Verifikasi CAMABA', 'Upload data CAMABA', $counts, null, [
            'program_studi' => $program->id_prodi,
        ]);

        return redirect()->route('camaba-verification.index', ['period' => $year.'1'])
            ->with('status', "Upload selesai: {$counts['inserted']} data baru, {$counts['updated']} diperbarui, {$counts['skipped']} dilewati.");
    }

    public function show(Request $request, string $candidate): View
    {
        $this->authorizeRole($request->user());
        $record = DB::connection('simak_main')->table('s1m4k_camaba')
            ->where('id', $candidate)->first();
        abort_if($record === null, 404);
        abort_if((int) $record->status === 1, 409, 'CAMABA ini sudah diverifikasi.');

        $connection = DB::connection('simak_main');
        $programs = $connection->table('s1m4k_masterprodi')->orderBy('nama_program_studi')
            ->get(['id_prodi', 'id_fakultas', 'nama_program_studi', 'nama_jenjang_pendidikan']);
        $references = $connection->table('s1m4k_kodereferensi')
            ->whereIn('keterangan', ['GetAgama', 'GetNegara', 'GetJenisPendaftaran', 'GetJalurMasuk', 'GetPembiayaan'])
            ->orderBy('kode_referensi')->get(['kode_referensi', 'ket_referensi', 'keterangan'])->groupBy('keterangan');
        $regions = $this->regions((string) ($record->id_negara ?: 'ID'));

        return view('camaba-verification.show', compact('record', 'programs', 'references', 'regions'));
    }

    public function regionOptions(Request $request): JsonResponse
    {
        $this->authorizeRole($request->user());
        $validated = $request->validate(['country' => ['required', 'string', 'max:3']]);

        return response()->json($this->regions($validated['country'])->map(static fn (object $region): array => [
            'id' => $region->kode_referensi,
            'name' => trim(($region->ket_referensi2 ?? '').' - '.($region->nama_provinsi ?? ''), ' -'),
        ])->values());
    }

    public function verify(Request $request, string $candidate, LegacyFeederService $feeder, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeRole($user);
        $validated = $request->validate([
            'nim' => ['required', 'string', 'max:24'],
            'nama_mahasiswa' => ['required', 'string', 'max:50'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:32'],
            'tanggal_lahir' => ['required', 'date'],
            'nama_ibu' => ['required', 'string', 'max:100'],
            'id_agama' => ['required', 'string', 'max:11'],
            'id_negara' => ['required', 'string', 'max:3'],
            'id_wilayah' => ['required', 'string', 'max:8'],
            'kelurahan' => ['required', 'string', 'max:60'],
            'nik' => ['nullable', 'digits_between:1,16'],
            'nisn' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:60'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'handphone' => ['required', 'string', 'max:20'],
            'penerima_kps' => ['required', 'in:0,1'],
            'id_jenis_daftar' => ['required', 'string', 'max:2'],
            'id_jalur_daftar' => ['required', 'string', 'max:4'],
            'id_periode_masuk' => ['required', 'regex:/^\d{5}$/'],
            'tanggal_daftar' => ['required', 'date'],
            'id_pembiayaan' => ['required', 'string', 'max:50'],
            'biaya_masuk_kuliah' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'id_prodi' => ['required', 'string', 'max:100'],
        ]);
        $connection = DB::connection('simak_main');
        $camaba = $connection->table('s1m4k_camaba')->where('id', $candidate)->first();
        abort_if($camaba === null, 404);
        abort_if((int) $camaba->status === 1, 409, 'CAMABA ini sudah diverifikasi.');
        $program = $connection->table('s1m4k_masterprodi')->where('id_prodi', $validated['id_prodi'])
            ->first(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        abort_if($program === null, 422, 'Program studi tidak ditemukan.');
        foreach ([
            'id_agama' => 'GetAgama',
            'id_negara' => 'GetNegara',
            'id_jenis_daftar' => 'GetJenisPendaftaran',
            'id_jalur_daftar' => 'GetJalurMasuk',
            'id_pembiayaan' => 'GetPembiayaan',
        ] as $field => $referenceType) {
            abort_unless($this->referenceExists($referenceType, $validated[$field]), 422, 'Referensi '.$field.' tidak tersedia di SIMAK.');
        }
        $regionExists = $connection->table('s1m4k_kodereferensi')->where('keterangan', 'GetWilayah')
            ->where('kode_referensi', $validated['id_wilayah'])
            ->where('ket_referensi', $validated['id_negara'])->exists();
        abort_unless($regionExists, 422, 'Wilayah tidak sesuai dengan negara yang dipilih.');
        $existingStudent = $connection->table('s1m4k_mastermhs')
            ->where('nim', trim($validated['nim']))->first(['nim', 'nama_mahasiswa']);
        if ($existingStudent !== null) {
            throw ValidationException::withMessages([
                'nim' => 'NPM sudah digunakan oleh '.$existingStudent->nama_mahasiswa.'.',
            ]);
        }
        $universityId = trim((string) ($program->id_perguruan_tinggi ?: $connection->table('s1m4k_masterpt')->value('id_perguruan_tinggi')));
        abort_if($universityId === '', 422, 'Data perguruan tinggi belum tersedia.');

        $biodata = $this->feederBiodata($validated);
        $history = [
            'nim' => trim($validated['nim']),
            'id_jenis_daftar' => $validated['id_jenis_daftar'],
            'id_jalur_daftar' => $validated['id_jalur_daftar'],
            'id_periode_masuk' => $validated['id_periode_masuk'],
            'tanggal_daftar' => $validated['tanggal_daftar'],
            'id_perguruan_tinggi' => $universityId,
            'id_prodi' => $program->id_prodi,
            'sks_diakui' => '0',
            'id_perguruan_tinggi_asal' => '',
            'id_prodi_asal' => '',
            'id_pembiayaan' => $validated['id_pembiayaan'],
            'biaya_masuk' => (int) ($validated['biaya_masuk_kuliah'] ?? 0),
        ];

        try {
            $feederIds = $feeder->verifyProspectiveStudent($biodata, $history);
        } catch (RuntimeException $exception) {
            Log::warning('CAMABA Feeder verification failed.', [
                'candidate_id' => $candidate,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['feeder' => $exception->getMessage()]);
        }

        $masterStudent = [
            'id_mahasiswa' => $feederIds['id_mahasiswa'],
            'id_registrasi_mahasiswa' => $feederIds['id_registrasi_mahasiswa'],
            'id_perguruan_tinggi' => $universityId,
            'id_fakultas' => $program->id_fakultas,
            'id_prodi' => $program->id_prodi,
            'dosen_pa' => '',
            'nim' => trim($validated['nim']),
            'nama_mahasiswa' => trim($validated['nama_mahasiswa']),
            'password' => Hash::make(trim($validated['nim'])),
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'jalan' => '', 'rt' => '', 'rw' => '', 'dusun' => '',
            'kelurahan' => trim($validated['kelurahan']), 'kode_pos' => '',
            'nisn' => trim((string) ($validated['nisn'] ?? '')),
            'nik' => trim((string) ($validated['nik'] ?? '')),
            'tempat_lahir' => trim($validated['tempat_lahir']),
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'nama_ayah' => '', 'tanggal_lahir_ayah' => '', 'nik_ayah' => '',
            'id_pendidikan_ayah' => '', 'id_pekerjaan_ayah' => '', 'id_penghasilan_ayah' => '',
            'id_kebutuhan_khusus_ayah' => '0', 'nama_ibu' => trim($validated['nama_ibu']),
            'tanggal_lahir_ibu' => '', 'nik_ibu' => '', 'id_pendidikan_ibu' => '',
            'id_pekerjaan_ibu' => '', 'id_penghasilan_ibu' => '', 'id_kebutuhan_khusus_ibu' => '0',
            'nama_wali' => '', 'tanggal_lahir_wali' => '', 'id_pendidikan_wali' => '',
            'id_pekerjaan_wali' => '', 'id_penghasilan_wali' => '', 'id_kebutuhan_khusus_mahasiswa' => '0',
            'telepon' => trim((string) ($validated['telepon'] ?? '')),
            'handphone' => trim($validated['handphone']), 'email' => trim($validated['email']),
            'penerima_kps' => $validated['penerima_kps'], 'nomor_kps' => '', 'npwp' => '',
            'id_wilayah' => $validated['id_wilayah'], 'id_jenis_tinggal' => '',
            'id_agama' => $validated['id_agama'], 'id_alat_transportasi' => '',
            'id_negara' => $validated['id_negara'],
            'id_jenis_daftar' => $validated['id_jenis_daftar'],
            'id_jalur_daftar' => $validated['id_jalur_daftar'],
            'id_periode_masuk' => $validated['id_periode_masuk'],
            'tanggal_daftar' => $validated['tanggal_daftar'], 'sks_diakui' => '0',
            'id_perguruan_tinggi_asal' => '', 'id_prodi_asal' => '',
            'id_pembiayaan' => $validated['id_pembiayaan'],
            'biaya_masuk_kuliah' => (int) ($validated['biaya_masuk_kuliah'] ?? 0),
            'id_status_mahasiswa' => 'A', 'nama_status_mahasiswa' => 'AKTIF',
        ];

        $connection->transaction(function () use ($connection, $masterStudent, $camaba, $activityLogger, $user, $candidate, $validated): void {
            $connection->table('s1m4k_mastermhs')->insert($masterStudent);
            $connection->table('s1m4k_camaba')->where('id', $candidate)->update(['status' => 1]);
            $activityLogger->record($user, 'Verifikasi CAMABA', 'Memverifikasi calon mahasiswa', [
                'status' => ['sebelum' => $camaba->status, 'sesudah' => 1],
            ], $candidate, [
                'nama' => trim($validated['nama_mahasiswa']),
                'nomor_identitas' => trim($validated['nim']),
            ]);
        });

        return redirect()->route('camaba-verification.index')
            ->with('status', 'CAMABA berhasil diverifikasi dan dikirim ke Feeder.');
    }

    private function candidateQuery(string $period, string $program)
    {
        return DB::connection('simak_main')->table('s1m4k_camaba as camaba')
            ->leftJoin('s1m4k_masterprodi as program', 'camaba.id_prodi', '=', 'program.id_prodi')
            ->where('camaba.id_periode_masuk', $period)
            ->when($program !== '', fn ($query) => $query->where('camaba.id_prodi', $program));
    }

    private function feederBiodata(array $data): array
    {
        return [
            'nama_mahasiswa' => trim($data['nama_mahasiswa']),
            'tempat_lahir' => trim($data['tempat_lahir']),
            'tanggal_lahir' => $data['tanggal_lahir'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'id_agama' => $data['id_agama'],
            'nik' => trim((string) ($data['nik'] ?? '')),
            'nisn' => trim((string) ($data['nisn'] ?? '')),
            'npwp' => '', 'jalan' => '', 'dusun' => '', 'rt' => '', 'rw' => '',
            'kelurahan' => trim($data['kelurahan']), 'id_wilayah' => $data['id_wilayah'],
            'kode_pos' => '', 'id_jenis_tinggal' => '',
            'telepon' => trim((string) ($data['telepon'] ?? '')),
            'handphone' => trim($data['handphone']), 'kewarganegaraan' => $data['id_negara'],
            'email' => trim($data['email']), 'penerima_kps' => $data['penerima_kps'],
            'nomor_kps' => '', 'id_kebutuhan_khusus_ayah' => '0',
            'nama_ibu_kandung' => trim($data['nama_ibu']), 'id_kebutuhan_khusus_ibu' => '0',
            'id_kebutuhan_khusus_mahasiswa' => '0', 'id_alat_transportasi' => '',
            'nik_ayah' => '', 'nama_ayah' => '', 'tanggal_lahir_ayah' => '',
            'id_pendidikan_ayah' => '', 'id_pekerjaan_ayah' => '', 'nik_ibu' => '',
            'tanggal_lahir_ibu' => '', 'id_pendidikan_ibu' => '', 'id_pekerjaan_ibu' => '',
            'id_penghasilan_ibu' => '', 'nama_wali' => '', 'tanggal_lahir_wali' => '',
            'id_pendidikan_wali' => '', 'id_pekerjaan_wali' => '', 'id_penghasilan_wali' => '',
        ];
    }

    private function camabaUploadValues(array $row, object $program, string $universityId, int $year): ?array
    {
        $participantNumber = trim((string) ($row[1] ?? ''));
        $nim = trim((string) ($row[2] ?? ''));
        $name = trim((string) ($row[7] ?? ''));
        $routeId = match (strtoupper(trim((string) ($row[10] ?? '')))) {
            'SNBP' => '15',
            'SNBT' => '14',
            'MANDIRI' => '12',
            default => null,
        };
        if ($participantNumber === '' || $nim === '' || $name === '' || $routeId === null) {
            return null;
        }
        $gender = strtoupper(trim((string) ($row[8] ?? '')));
        if (! in_array($gender, ['L', 'P'], true)) {
            return null;
        }
        $birthDate = $row[12] ?? '';
        if (is_numeric($birthDate)) {
            $birthDate = Date::excelToDateTimeObject((float) $birthDate)->format('Y-m-d');
        } else {
            $birthDate = str_replace('/', '-', trim((string) $birthDate));
        }
        if ($birthDate === '' || strtotime((string) $birthDate) === false) {
            return null;
        }
        $amount = preg_replace('/\D+/', '', (string) ($row[16] ?? '')) ?? '';
        $identity = substr(str_replace('-', '', trim((string) ($row[4] ?? ''))), 0, 16);
        $studentNumber = $nim;

        return [
            'id_perguruan_tinggi' => $universityId,
            'id_fakultas' => $program->id_fakultas,
            'id_prodi' => $program->id_prodi,
            'no_peserta' => $participantNumber,
            'nim' => $studentNumber,
            'nama_mahasiswa' => trim($name),
            'jenis_kelamin' => $gender,
            'tempat_lahir' => trim((string) ($row[11] ?? '')),
            'tanggal_lahir' => $birthDate,
            'nama_ibu' => trim((string) ($row[13] ?? '')),
            'id_agama' => match (trim((string) ($row[9] ?? ''))) {
                'Islam' => '1', 'Kristen Protestan' => '2', 'Kristen Katolik' => '3',
                'Hindu' => '4', 'Budha' => '5', default => '6',
            },
            'id_negara' => 'ID',
            'nik' => $identity,
            'nisn' => str_replace('-', '', trim((string) ($row[3] ?? ''))),
            'telepon' => trim((string) ($row[6] ?? '')),
            'handphone' => trim((string) ($row[6] ?? '')),
            'kelurahan' => mb_substr(trim((string) ($row[14] ?? '')), 20),
            'email' => trim((string) ($row[5] ?? '')),
            'penerima_kps' => '0',
            'id_jenis_daftar' => '17',
            'id_jalur_daftar' => $routeId,
            'id_periode_masuk' => $year.'1',
            'tanggal_daftar' => $year.'-08-11',
            'id_pembiayaan' => strtoupper(trim((string) ($row[15] ?? ''))) === 'KIP-K' ? '4' : '1',
            'biaya_masuk_kuliah' => $amount === '' ? 0 : (int) $amount,
        ];
    }

    private function referenceExists(string $type, string $code): bool
    {
        return DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->where('keterangan', $type)
            ->where('kode_referensi', $code)
            ->exists();
    }

    private function regions(string $country): Collection
    {
        return DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->where('keterangan', 'GetWilayah')
            ->where('ket_referensi', $country)
            ->orderBy('ket_referensi2')
            ->get(['kode_referensi', 'ket_referensi2', 'nama_provinsi']);
    }

    private function authorizeRole(object $user): void
    {
        abort_unless(in_array($user->role, self::ROLES, true), 403);
    }
}
