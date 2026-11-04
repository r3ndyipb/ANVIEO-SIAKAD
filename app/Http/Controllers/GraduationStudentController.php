<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class GraduationStudentController extends Controller
{
    private const DOCUMENTS = [
        'foto' => [
            'column' => 'foto',
            'directory' => 'foto',
            'label' => 'Pas foto berlatar merah',
            'type' => 'photo',
            'extension' => 'jpg',
        ],
        'bebas_perpus' => [
            'column' => 'bebas_perpus',
            'directory' => 'bebas_perpus',
            'label' => 'Surat bebas perpustakaan',
            'type' => 'pdf',
            'extension' => 'pdf',
        ],
        'bebas_spp' => [
            'column' => 'bebas_spp',
            'directory' => 'bebas_spp',
            'label' => 'Surat bebas biaya kuliah',
            'type' => 'pdf',
            'extension' => 'pdf',
        ],
        'skripsi' => [
            'column' => 'skripsi',
            'directory' => 'skripsi',
            'label' => 'Bukti penyerahan jurnal dan skripsi',
            'type' => 'pdf',
            'extension' => 'pdf',
        ],
    ];

    public function index(Request $request): View
    {
        $this->authorizeStudent($request);
        [$student, $period, $participant] = $this->context($request);
        $documents = [];
        foreach (self::DOCUMENTS as $key => $definition) {
            $path = $participant?->{$definition['column']};
            $location = $period && $participant
                ? $this->resolveDocument($period, $definition, $path)
                : null;
            $documents[$key] = [
                ...$definition,
                'available' => $location !== null,
                'uploaded' => filled($path),
                'downloadUrl' => $location ? route('graduation-student.documents.download', $key) : null,
            ];
        }
        $isOpen = $period !== null && $this->periodIsOpen($period);
        $verified = $participant !== null && filled($participant->lolos);

        return view('graduation-students.index', compact(
            'student', 'period', 'participant', 'documents', 'isOpen', 'verified'
        ));
    }

    public function upload(Request $request, string $document, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeStudent($request);
        $definition = $this->documentDefinition($document);
        $rules = $definition['type'] === 'photo'
            ? ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:1024']
            : ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:1024'];
        $validated = $request->validate([
            'document_file' => $rules,
            'judul_skripsi' => [$document === 'skripsi' ? 'required' : 'nullable', 'string', 'max:2000'],
            'handphone' => [$document === 'skripsi' ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]{8,30}$/'],
        ]);
        [$student, $period, $participant] = $this->context($request);
        if ($period === null || $participant === null) {
            return $this->notProposed();
        }
        if (! $this->periodIsOpen($period)) {
            return redirect()->route('graduation-student.index')
                ->with('error', 'Periode wisuda belum dibuka atau telah berakhir.');
        }
        if (filled($participant->lolos)) {
            return redirect()->route('graduation-student.index')
                ->with('error', 'Berkas wisuda sudah diverifikasi. Upload atau penggantian dokumen tidak dapat dilakukan.');
        }

        /** @var UploadedFile $file */
        $file = $validated['document_file'];
        $extension = $definition['type'] === 'photo' && $file->getMimeType() === 'image/png'
            ? 'png'
            : $definition['extension'];
        $periodDirectory = $this->periodDirectory($period);
        $directory = 'wisuda/'.$periodDirectory.'/'.hash('sha256', (string) $participant->id_peserta).'/'.$definition['directory'];
        $newPath = $file->storeAs($directory, (string) Str::uuid().'.'.$extension, 'local');
        if (! is_string($newPath) || $newPath === '') {
            return redirect()->route('graduation-student.index')
                ->with('error', 'Dokumen gagal disimpan. Silakan coba kembali.');
        }

        try {
            $connection = DB::connection('simak_main');
            $connection->transaction(function () use ($activityLogger, $connection, $definition, $document, $newPath, $period, $request, $student, $validated): void {
                $lockedPeriod = $connection->table('s1m4k_periode')->where('id_periode', $period->id_periode)
                    ->where('jenis', 'wisuda')->where('aktif', 'Y')->lockForUpdate()->first();
                abort_unless($this->periodIsOpen($lockedPeriod), 403, 'Periode wisuda tidak aktif.');
                $lockedParticipant = $connection->table('s1m4k_wisuda')
                    ->where('id_periode', $period->id_periode)
                    ->where('id_mahasiswa', $student->id_mahasiswa)
                    ->lockForUpdate()->first();
                abort_if($lockedParticipant === null, 404);
                abort_if(filled($lockedParticipant->lolos), 409,
                    'Berkas wisuda sudah diverifikasi. Upload atau penggantian dokumen tidak dapat dilakukan.');

                $values = [$definition['column'] => $newPath, 'status' => 0];
                if ($document === 'skripsi') {
                    $values['judul_skripsi'] = trim($validated['judul_skripsi']);
                    $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $student->id_mahasiswa)
                        ->update(['handphone' => trim($validated['handphone'])]);
                }
                $connection->table('s1m4k_wisuda')->where('id_peserta', $lockedParticipant->id_peserta)->update($values);
                $activityLogger->record($request->user(), 'Wisuda Mahasiswa', 'Mengunggah '.$definition['label'], [
                    'dokumen' => $definition['column'],
                    'nama_file' => basename($newPath),
                ], (string) $lockedParticipant->id_peserta, [
                    'jenis' => 'Mahasiswa', 'nama' => $student->nama_mahasiswa, 'nomor_identitas' => $student->nim,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        $oldPath = $participant->{$definition['column']};
        if (is_string($oldPath) && str_starts_with($oldPath, 'wisuda/')) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('graduation-student.index')
            ->with('status', $definition['label'].' berhasil diunggah.');
    }

    public function download(Request $request, string $document)
    {
        $this->authorizeStudent($request);
        $definition = $this->documentDefinition($document);
        [$student, $period, $participant] = $this->context($request);
        abort_if($period === null || $participant === null, 404);
        $location = $this->resolveDocument($period, $definition, $participant->{$definition['column']});
        abort_if($location === null, 404);
        $extension = $definition['extension'];
        if ($definition['type'] === 'photo' && pathinfo((string) $participant->{$definition['column']}, PATHINFO_EXTENSION) === 'png') {
            $extension = 'png';
        }
        $filename = $document.'-'.$student->nim.'.'.$extension;
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return response()->download($location, $filename, $headers);
    }

    private function context(Request $request): array
    {
        $connection = DB::connection('simak_main');
        $student = $connection->table('s1m4k_mastermhs as student')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'student.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('student.id_mahasiswa', $request->user()->legacy_id)
            ->first([
                'student.id_mahasiswa', 'student.nim', 'student.nama_mahasiswa', 'student.id_prodi',
                'student.id_fakultas', 'student.handphone', 'student.id_status_mahasiswa',
                'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
        abort_if($student === null, 404);
        $period = $connection->table('s1m4k_periode')->where('jenis', 'wisuda')->where('aktif', 'Y')->first();
        $participant = $period === null ? null : $connection->table('s1m4k_wisuda')
            ->where('id_periode', $period->id_periode)
            ->where('id_mahasiswa', $student->id_mahasiswa)
            ->first();

        return [$student, $period, $participant];
    }

    private function periodIsOpen(?object $period): bool
    {
        if ($period === null) {
            return false;
        }
        $today = today()->toDateString();

        return $today >= (string) $period->tanggal_mulai && $today <= (string) $period->tanggal_akhir;
    }

    private function documentDefinition(string $document): array
    {
        abort_unless(isset(self::DOCUMENTS[$document]), 404);

        return self::DOCUMENTS[$document];
    }

    private function resolveDocument(object $period, array $definition, mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }
        $periodId = (string) $period->id_periode;
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $periodId) !== 1) {
            return null;
        }
        $extensionPattern = $definition['type'] === 'photo' ? '(?:jpg|png)' : 'pdf';
        $privatePathPattern = '#\Awisuda/'.preg_quote($periodId, '#').'/[a-f0-9]{64}/'
            .preg_quote($definition['directory'], '#').'/[a-f0-9-]{36}\.'.$extensionPattern.'\z#i';
        if (preg_match($privatePathPattern, $path) === 1 && Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        return null;
    }

    private function periodDirectory(object $period): string
    {
        $periodId = (string) $period->id_periode;
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $periodId) === 1, 409, 'ID periode wisuda tidak valid.');

        return $periodId;
    }

    private function notProposed(): RedirectResponse
    {
        return redirect()->route('graduation-student.index')->with(
            'error',
            'Data usulan peserta wisuda untuk periode aktif belum ditemukan. Jika Anda sudah yudisium dan belum bisa melengkapi dokumen wisuda, hubungi operator prodi (Tuprodi) untuk diusulkan terlebih dahulu.'
        );
    }

    private function authorizeStudent(Request $request): void
    {
        abort_unless($request->user()?->role === 'mahasiswa', 403);
    }
}
