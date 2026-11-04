<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\InstitutionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment as PresentationAlignment;
use PhpOffice\PhpPresentation\Style\Color as PresentationColor;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as SpreadsheetDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GraduationValidationController extends Controller
{
    private const ROLES = ['admin', 'validasi', 'wisuda'];

    private const DOCUMENTS = [
        'foto' => ['column' => 'foto', 'directory' => 'foto', 'label' => 'Pas foto berlatar merah'],
        'bebas_perpus' => ['column' => 'bebas_perpus', 'directory' => 'bebas_perpus', 'label' => 'Surat bebas perpustakaan'],
        'bebas_spp' => ['column' => 'bebas_spp', 'directory' => 'bebas_spp', 'label' => 'Surat bebas biaya kuliah'],
        'skripsi' => ['column' => 'skripsi', 'directory' => 'skripsi', 'label' => 'Bukti penyerahan jurnal dan skripsi'],
    ];

    public function index(Request $request): View
    {
        $user = $this->authorizeRole($request);
        $connection = DB::connection('simak_main');
        /** @var Collection<int, object> $periods */
        $periods = $connection->table('s1m4k_periode')->where('jenis', 'wisuda')
            ->orderByDesc('tanggal_mulai')->get(['id_periode', 'nama_periode', 'tanggal_mulai', 'tanggal_akhir', 'aktif']);
        $periodId = trim((string) $request->query('periode', ''));
        if ($periodId === '') {
            $periodId = (string) ($periods->firstWhere('aktif', 'Y')->id_periode ?? $periods->first()->id_periode ?? '');
        }
        $period = $periodId === '' ? null : $connection->table('s1m4k_periode')
            ->where('jenis', 'wisuda')->where('id_periode', $periodId)->first();
        abort_if($periodId !== '' && $period === null, 404, 'Periode wisuda tidak ditemukan.');
        $rowsQuery = $period === null ? null : $this->participantsQuery($period->id_periode);
        $rows = $rowsQuery?->orderBy('program.nama_program_studi')->orderBy('student.nim')
            ->paginate(25)->withQueryString();
        if ($rows !== null) {
            $rows->getCollection()->transform(function (object $row): object {
                $row->verification_label = $this->verificationLabel($row->lolos);
                $row->document_count = collect(self::DOCUMENTS)
                    ->filter(fn (array $document): bool => filled($row->{$document['column']}))->count();

                return $row;
            });
        }

        return view('graduation-validations.index', [
            'periods' => $periods,
            'period' => $period,
            'rows' => $rows,
            'canVerify' => in_array($user->role, ['admin', 'validasi'], true),
        ]);
    }

    public function show(Request $request, string $period, string $participant): View
    {
        $user = $this->authorizeRole($request);
        $periodRecord = $this->findPeriod($period);
        $record = $this->findParticipant($periodRecord, $participant);
        $calendarId = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('id_prodi', $record->id_prodi)->orderByDesc('tahun')->value('id_kalender');
        $ipk = $calendarId === null ? null : DB::connection('simak_main')->table('s1m4k_akm')
            ->where('id_mahasiswa', $record->id_mahasiswa)->where('id_kalender', $calendarId)->value('ipk');
        $documents = [];
        foreach (self::DOCUMENTS as $key => $definition) {
            $location = $this->resolveDocument($periodRecord, $definition, $record->{$definition['column']});
            $documents[$key] = [
                ...$definition,
                'available' => $location !== null,
                'downloadUrl' => $location ? route('graduation-validations.documents.download', [$period, $participant, $key]) : null,
            ];
        }

        return view('graduation-validations.show', [
            'period' => $periodRecord,
            'record' => $record,
            'ipk' => $ipk,
            'documents' => $documents,
            'verificationLabel' => $this->verificationLabel($record->lolos),
            'canVerify' => in_array($user->role, ['admin', 'validasi'], true),
        ]);
    }

    public function update(Request $request, string $period, string $participant, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $this->authorizeRole($request);
        abort_unless(in_array($user->role, ['admin', 'validasi'], true), 403);
        $validated = $request->validate([
            'lolos' => ['required', 'in:0,1'],
            'komentar' => ['required', 'string', 'max:2000'],
        ]);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($activityLogger, $connection, $participant, $period, $user, $validated): void {
            $periodRecord = $connection->table('s1m4k_periode')->where('id_periode', $period)
                ->where('jenis', 'wisuda')->lockForUpdate()->first();
            abort_if($periodRecord === null, 404);
            $record = $connection->table('s1m4k_wisuda')->where('id_peserta', $participant)
                ->where('id_periode', $period)->lockForUpdate()->first();
            abort_if($record === null, 404);
            $student = $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $record->id_mahasiswa)
                ->first(['nim', 'nama_mahasiswa']);
            $values = [
                'lolos' => (string) $validated['lolos'],
                'komentar' => trim($validated['komentar']),
                'petugas' => (string) $user->name,
            ];
            $connection->table('s1m4k_wisuda')->where('id_peserta', $participant)->update($values);
            $activityLogger->record($user, 'Validasi Wisuda', 'Memverifikasi peserta wisuda', $values, $participant, [
                'jenis' => 'Mahasiswa',
                'nama' => $student?->nama_mahasiswa,
                'nomor_identitas' => $student?->nim,
            ]);
        });

        return redirect()->route('graduation-validations.show', [$period, $participant])
            ->with('status', 'Hasil verifikasi wisuda berhasil disimpan.');
    }

    public function download(Request $request, string $period, string $participant, string $document)
    {
        $user = $this->authorizeRole($request);
        $periodRecord = $this->findPeriod($period);
        $record = $this->findParticipant($periodRecord, $participant);
        abort_unless(isset(self::DOCUMENTS[$document]), 404);
        $definition = self::DOCUMENTS[$document];
        $path = $record->{$definition['column']};
        $location = $this->resolveDocument($periodRecord, $definition, $path);
        abort_if($location === null, 404);
        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);
        $studentNumber = (string) ($record->nim ?: $participant);
        $filename = $document.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $studentNumber).'.'.$extension;
        $contentType = strtolower($extension) === 'png' ? 'image/png'
            : (strtolower($extension) === 'pdf' ? 'application/pdf' : 'image/jpeg');

        return response()->file($location, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function exportExcel(Request $request, string $period): Response
    {
        $this->authorizeRole($request);
        $periodRecord = $this->findPeriod($period);
        $participants = $this->passedParticipants($period);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Peserta Wisuda');
        $headers = ['NPM', 'Nama', 'TTL', 'Prodi', 'Fakultas', 'IPK', 'Judul Skripsi', 'Foto', 'Email', 'HP'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).'1', $header, DataType::TYPE_STRING);
        }
        foreach (['A' => 16, 'B' => 30, 'C' => 26, 'D' => 30, 'E' => 28, 'F' => 10, 'G' => 42, 'H' => 16, 'I' => 30, 'J' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);

        foreach ($participants as $index => $record) {
            $row = $index + 2;
            $birthDate = $record->tanggal_lahir ? date('d-m-Y', strtotime((string) $record->tanggal_lahir)) : '';
            $values = [
                $record->nim, $record->nama_mahasiswa,
                trim((string) $record->tempat_lahir.($birthDate !== '' ? ', '.$birthDate : '')),
                $record->nama_program_studi, $record->nama_fakultas,
                is_numeric($record->ipk) && (float) $record->ipk > 0 ? number_format((float) $record->ipk, 2) : '-',
                $record->judul_skripsi ?: '-', '', $record->email, $record->handphone,
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).$row, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
            $photo = $this->photoForExport($periodRecord, $record->foto);
            $drawing = new SpreadsheetDrawing;
            $drawing->setName('Foto wisudawan');
            $drawing->setDescription('Foto '.$record->nama_mahasiswa);
            $drawing->setPath($photo);
            $drawing->setHeight(55);
            $drawing->setCoordinates('H'.$row);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension($row)->setRowHeight(48);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="peserta-wisuda-'.$this->exportPeriodLabel($periodRecord).'.xlsx"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportPdf(Request $request, string $period): Response
    {
        $this->authorizeRole($request);
        $periodRecord = $this->findPeriod($period);
        $participants = $this->passedParticipants($period);
        $temporaryDirectory = storage_path('app/private/mpdf');
        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0750, true);
        }
        $pdf = new Mpdf([
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => $temporaryDirectory,
        ]);
        $periodName = e((string) $periodRecord->nama_periode);
        $institutionName = e(app(InstitutionSettings::class)->institutionName());
        $logoPath = app(InstitutionSettings::class)->logoFilePath();
        $logo = is_file($logoPath)
            ? '<img src="file://'.e($logoPath).'" style="width:24mm;height:24mm" />'
            : '';
        $html = '<div style="text-align:center;margin:26mm 0 18mm">'.$logo.'<h1>Data Wisuda</h1><h2>Periode '.$periodName.'</h2><h3>'.$institutionName.'</h3></div>';
        $html .= '<h2 style="text-align:center">Daftar Peserta Wisuda - Lolos Verifikasi</h2>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:9pt"><thead><tr style="background:#e8eef5"><th>Foto</th><th>NPM</th><th>Nama</th><th>TTL</th><th>Program Studi</th><th>Fakultas</th><th>IPK</th><th>Judul Skripsi</th></tr></thead><tbody>';
        foreach ($participants as $record) {
            $photo = $this->photoForExport($periodRecord, $record->foto);
            $photoHtml = '<img src="file://'.e($photo).'" style="width:18mm;height:24mm" />';
            $birthDate = $record->tanggal_lahir ? date('d-m-Y', strtotime((string) $record->tanggal_lahir)) : '';
            $birth = trim((string) $record->tempat_lahir.($birthDate !== '' ? ', '.$birthDate : ''));
            $ipk = is_numeric($record->ipk) && (float) $record->ipk > 0 ? number_format((float) $record->ipk, 2) : '-';
            $html .= '<tr><td>'.$photoHtml.'</td><td>'.e((string) $record->nim).'</td><td>'.e((string) $record->nama_mahasiswa)
                .'</td><td>'.e($birth).'</td><td>'.e((string) $record->nama_program_studi).'</td><td>'.e((string) $record->nama_fakultas)
                .'</td><td>'.e($ipk).'</td><td>'.e((string) ($record->judul_skripsi ?: '-')).'</td></tr>';
        }
        $html .= '</tbody></table>';
        $pdf->WriteHTML($html);
        $contents = $pdf->Output('peserta-wisuda.pdf', Destination::STRING_RETURN);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="peserta-wisuda-'.$this->exportPeriodLabel($periodRecord).'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportPresentation(Request $request, string $period): Response
    {
        $this->authorizeRole($request);
        $periodRecord = $this->findPeriod($period);
        $participants = $this->passedParticipants($period)->groupBy('id_prodi');
        $presentation = new PhpPresentation;
        $presentation->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);

        $cover = $presentation->getActiveSlide();
        $this->addPresentationBackground($cover, 'FFF8F4E9', 'FFC9A45C');
        $coverTitle = $cover->createRichTextShape()->setHeight(150)->setWidth(860)->setOffsetX(50)->setOffsetY(145);
        $coverTitle->getActiveParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
        $coverTitle->createTextRun('WISUDA')->getFont()->setBold(true)->setSize(42)->setColor(new PresentationColor('FF173447'));
        $coverTitle->createParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
        $coverTitle->createTextRun((string) $periodRecord->nama_periode)->getFont()->setBold(true)->setSize(27)->setColor(new PresentationColor('FF9A7131'));
        $coverTitle->createParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
        $coverTitle->createTextRun(mb_strtoupper(app(InstitutionSettings::class)->institutionName()))->getFont()->setBold(true)->setSize(16)->setColor(new PresentationColor('FF435663'));

        $countShape = $cover->createRichTextShape()->setHeight(90)->setWidth(420)->setOffsetX(270)->setOffsetY(350);
        $countShape->getActiveParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
        $countShape->createTextRun((string) $participants->flatten(1)->count().' PESERTA WISUDA')->getFont()->setBold(true)->setSize(21)->setColor(new PresentationColor('FF173447'));
        $countShape->createParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
        $countShape->createTextRun('Lolos verifikasi · Siap prosesi')->getFont()->setSize(14)->setColor(new PresentationColor('FF596A70'));

        foreach ($participants as $programParticipants) {
            $firstParticipant = $programParticipants->first();
            $programName = trim((string) ($firstParticipant->nama_program_studi ?? 'Program Studi'));
            $facultyName = trim((string) ($firstParticipant->nama_fakultas ?? 'Fakultas'));
            $palette = $this->graduationPalette($firstParticipant);

            $sectionSlide = $presentation->createSlide();
            $this->addPresentationBackground($sectionSlide, 'FFF8F4E9', $palette['accent']);
            $sectionLabel = $sectionSlide->createRichTextShape()->setHeight(40)->setWidth(820)->setOffsetX(70)->setOffsetY(105);
            $sectionLabel->getActiveParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
            $sectionLabel->createTextRun('FAKULTAS '.strtoupper($facultyName))->getFont()->setBold(true)->setSize(15)->setColor(new PresentationColor($palette['deep']));
            $sectionHeader = $sectionSlide->createRichTextShape()->setHeight(110)->setWidth(820)->setOffsetX(70)->setOffsetY(175);
            $sectionHeader->getActiveParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
            $sectionHeader->createTextRun('WISUDAWAN')->getFont()->setBold(true)->setSize(18)->setColor(new PresentationColor($palette['accent']));
            $sectionHeader->createParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
            $sectionHeader->createTextRun($programName)->getFont()->setBold(true)->setSize(32)->setColor(new PresentationColor($palette['deep']));
            $sectionStats = $sectionSlide->createRichTextShape()->setHeight(55)->setWidth(560)->setOffsetX(200)->setOffsetY(315);
            $sectionStats->getActiveParagraph()->getAlignment()->setHorizontal(PresentationAlignment::HORIZONTAL_CENTER);
            $sectionStats->createTextRun($programParticipants->count().' PESERTA')->getFont()->setBold(true)->setSize(17)->setColor(new PresentationColor($palette['deep']));

            foreach ($programParticipants as $record) {
                $participantSlide = $presentation->createSlide();
                $this->addPresentationBackground($participantSlide, 'FFFBF9F3', $palette['accent']);
                $name = $participantSlide->createRichTextShape()->setHeight(90)->setWidth(540)->setOffsetX(65)->setOffsetY(85);
                $name->createTextRun((string) ($record->nama_mahasiswa ?? 'Nama Peserta'))
                    ->getFont()->setBold(true)->setSize(29)->setColor(new PresentationColor($palette['deep']));

                $details = $participantSlide->createRichTextShape()->setHeight(175)->setWidth(535)->setOffsetX(65)->setOffsetY(185);
                foreach ([
                    'NPM: '.($record->nim ?? '-'),
                    'Program Studi: '.$programName,
                    'Fakultas: '.$facultyName,
                    'IPK: '.(is_numeric($record->ipk) && (float) $record->ipk > 0 ? number_format((float) $record->ipk, 2) : '-'),
                ] as $line) {
                    $details->createTextRun($line."\n")->getFont()->setSize(17)->setColor(new PresentationColor('FF435663'));
                }

                $thesis = $participantSlide->createRichTextShape()->setHeight(110)->setWidth(535)->setOffsetX(65)->setOffsetY(365);
                $thesis->createTextRun("JUDUL SKRIPSI\n")->getFont()->setBold(true)->setSize(12)->setColor(new PresentationColor($palette['accent']));
                $thesis->createTextRun((string) ($record->judul_skripsi ?: '-'))->getFont()->setSize(15)->setColor(new PresentationColor('FF435663'));

                $photoMat = $participantSlide->createRichTextShape()->setHeight(370)->setWidth(270)->setOffsetX(635)->setOffsetY(95);
                $photoMat->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new PresentationColor($palette['deep']));
                $photoArea = $participantSlide->createRichTextShape()->setHeight(350)->setWidth(250)->setOffsetX(645)->setOffsetY(105);
                $photoArea->getFill()->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new PresentationColor($palette['light']));
                $photo = $this->photoForExport($periodRecord, $record->foto);
                $image = $participantSlide->createDrawingShape();
                $image->setName('Foto wisudawan')->setDescription('Foto '.$record->nama_mahasiswa)
                    ->setPath($photo)->setWidthAndHeight(230, 330);
                $image->setOffsetX(655 + (int) ((230 - $image->getWidth()) / 2));
                $image->setOffsetY(115 + (int) ((330 - $image->getHeight()) / 2));

                $participantFooter = $participantSlide->createRichTextShape()->setHeight(25)->setWidth(535)->setOffsetX(65)->setOffsetY(500);
                $participantFooter->createTextRun('WISUDA '.mb_strtoupper(app(InstitutionSettings::class)->institutionName()))->getFont()->setBold(true)->setSize(11)->setColor(new PresentationColor($palette['accent']));
            }
        }

        $writer = PresentationIOFactory::createWriter($presentation, 'PowerPoint2007');
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'Content-Disposition' => 'attachment; filename="peserta-wisuda-'.$this->exportPeriodLabel($periodRecord).'.pptx"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function passedParticipants(string $period)
    {
        $connection = DB::connection('simak_main');

        return $connection->table('s1m4k_wisuda as participant')
            ->leftJoin('s1m4k_mastermhs as student', 'participant.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'participant.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'participant.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('participant.id_periode', $period)
            ->where('participant.lolos', '1')
            ->select([
                'participant.id_peserta', 'participant.id_mahasiswa', 'participant.id_prodi', 'participant.id_fakultas', 'participant.foto',
                'participant.judul_skripsi', 'student.nim', 'student.nama_mahasiswa', 'student.tempat_lahir',
                'student.tanggal_lahir', 'student.handphone', 'student.email', 'program.nama_program_studi',
                'faculty.nama_fakultas',
            ])
            ->selectSub($connection->table('s1m4k_akm as akm')
                ->join('s1m4k_kalender as calendar', 'akm.id_kalender', '=', 'calendar.id_kalender')
                ->whereColumn('akm.id_mahasiswa', 'participant.id_mahasiswa')
                ->whereColumn('calendar.id_prodi', 'participant.id_prodi')
                ->orderByDesc('calendar.tahun')->limit(1)->select('akm.ipk'), 'ipk')
            ->orderBy('participant.id_prodi')->orderBy('student.nim')->get();
    }

    private function safePhotoPath(object $period, mixed $filename): ?string
    {
        $path = $this->resolveDocument($period, self::DOCUMENTS['foto'], $filename);
        if ($path === null) {
            return null;
        }
        $image = @getimagesize($path);
        if (! is_array($image) || ! in_array($image['mime'] ?? '', ['image/jpeg', 'image/png'], true)) {
            return null;
        }

        return $path;
    }

    private function photoForExport(object $period, mixed $filename): string
    {
        $participantPhoto = $this->safePhotoPath($period, $filename);
        if ($participantPhoto !== null) {
            return $participantPhoto;
        }

        $defaultPhoto = public_path('images/no_foto.jpg');
        abort_unless(is_file($defaultPhoto), 500, 'Foto default wisuda tidak ditemukan.');

        return $defaultPhoto;
    }

    private function addPresentationBackground(object $slide, string $baseColor = 'FFE6F0FA', string $accentColor = 'FFD5E6F8'): void
    {
        $background = $slide->createRichTextShape()->setHeight(540)->setWidth(960)->setOffsetX(0)->setOffsetY(0);
        $background->getFill()->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new PresentationColor($baseColor));

        $accent = $slide->createRichTextShape()->setHeight(10)->setWidth(960)->setOffsetX(0)->setOffsetY(0);
        $accent->getFill()->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new PresentationColor($accentColor));

        $footerAccent = $slide->createRichTextShape()->setHeight(5)->setWidth(960)->setOffsetX(0)->setOffsetY(535);
        $footerAccent->getFill()->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new PresentationColor($accentColor));
    }

    private function graduationPalette(object $participant): array
    {
        $palettes = [
            ['accent' => 'FF9A7131', 'deep' => 'FF263C48', 'light' => 'FFF1E4C8'],
            ['accent' => 'FF287C78', 'deep' => 'FF183D45', 'light' => 'FFDCECE4'],
            ['accent' => 'FF9A5360', 'deep' => 'FF492E3E', 'light' => 'FFF0DFDF'],
            ['accent' => 'FF3D6F9A', 'deep' => 'FF203B56', 'light' => 'FFDCE7F0'],
            ['accent' => 'FF777243', 'deep' => 'FF3D422D', 'light' => 'FFE9E7D6'],
            ['accent' => 'FF8A654F', 'deep' => 'FF49372F', 'light' => 'FFEDE0D4'],
        ];
        $key = (string) ($participant->id_fakultas ?? '').'|'.(string) ($participant->id_prodi ?? '');
        $index = hexdec(substr(hash('sha256', $key), 0, 8)) % count($palettes);

        return $palettes[$index];
    }

    private function exportPeriodLabel(object $period): string
    {
        $label = (string) ($period->tahun ?: $period->id_periode);

        return trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $label), '-') ?: 'wisuda';
    }

    private function participantsQuery(string $period)
    {
        return DB::connection('simak_main')->table('s1m4k_wisuda as participant')
            ->join('s1m4k_mastermhs as student', 'participant.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'participant.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'participant.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('participant.id_periode', $period)
            ->select([
                'participant.id_peserta', 'participant.id_periode', 'participant.id_mahasiswa',
                'participant.id_prodi', 'participant.foto', 'participant.bebas_perpus', 'participant.bebas_spp',
                'participant.judul_skripsi', 'participant.skripsi', 'participant.status', 'participant.pernyataan',
                'participant.tgl_daftar', 'participant.petugas', 'participant.lolos', 'participant.komentar',
                'student.nim', 'student.nama_mahasiswa', 'student.id_periode_masuk', 'student.nama_status_mahasiswa',
                'student.id_status_mahasiswa', 'student.handphone', 'student.email', 'student.nama_ibu', 'student.jalan',
                'student.kelurahan', 'student.jenis_kelamin', 'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
    }

    private function findPeriod(string $period): object
    {
        $record = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('id_periode', $period)->where('jenis', 'wisuda')->first();
        abort_if($record === null, 404, 'Periode wisuda tidak ditemukan.');

        return $record;
    }

    private function findParticipant(object $period, string $participant): object
    {
        $record = $this->participantsQuery((string) $period->id_periode)
            ->where('participant.id_peserta', $participant)->first();
        abort_if(! is_object($record), 404, 'Peserta wisuda tidak ditemukan pada periode ini.');

        return $record;
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
        $extensionPattern = $definition['directory'] === 'foto' ? '(?:jpg|png)' : 'pdf';
        $privatePattern = '#\\Awisuda/'.preg_quote($periodId, '#').'/[a-f0-9]{64}/'
            .preg_quote($definition['directory'], '#').'/[a-f0-9-]{36}\\.'.$extensionPattern.'\\z#i';
        if (preg_match($privatePattern, $path) === 1 && Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        return null;
    }

    private function verificationLabel(mixed $decision): string
    {
        return match ((string) $decision) {
            '0' => 'Tidak Lolos Verifikasi',
            '1' => 'Lolos Verifikasi',
            default => 'Belum Verifikasi',
        };
    }

    private function authorizeRole(Request $request): object
    {
        $user = $request->user();
        abort_unless($user !== null && in_array($user->role, self::ROLES, true), 403);

        return $user;
    }
}
