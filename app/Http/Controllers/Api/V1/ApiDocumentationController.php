<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiOperationCatalog;
use App\Services\InstitutionSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ApiDocumentationController extends Controller
{
    public function index(ApiOperationCatalog $catalog)
    {
        return view('api-docs.index', [
            'operationsUrl' => route('api.v1.operations'),
            'ssoUrl' => route('api.v1.auth.sso'),
            'tokenUrl' => route('api.v1.auth.token'),
            'sandboxUrl' => route('api.v1.sandbox.execute'),
            'openApiUrl' => route('api.v1.openapi'),
            'guideUrl' => route('api.v1.guide'),
            'operations' => $catalog->all(),
        ]);
    }

    public function guide(): Response
    {
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 17,
            'margin_right' => 17,
        ]);
        $pdf->SetTitle('Panduan API Web Service SIAKAD v1');
        $pdf->SetAuthor(app(InstitutionSettings::class)->institutionName().' - SIAKAD');
        $pdf->WriteHTML(view('api-docs.guide-pdf', [
            'baseUrl' => url('/api/v1'),
            'generatedAt' => now()->locale('id')->translatedFormat('d F Y'),
            'logoPath' => app(InstitutionSettings::class)->logoFilePath(),
        ])->render());

        return response($pdf->Output('panduan-api-ws-simak-v1.pdf', Destination::STRING_RETURN))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="panduan-api-ws-simak-v1.pdf"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-store, private');
    }

    public function operations(ApiOperationCatalog $catalog): JsonResponse
    {
        return response()->json([
            'versi' => '1.0.0',
            'jumlah' => count($catalog->all()),
            'data' => $catalog->all(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function openApi(ApiOperationCatalog $catalog): Response
    {
        $paths = [];
        foreach ($catalog->all() as $operation) {
            $path = '/'.ltrim($operation['path'], '/');
            $parameters = array_map(static fn (string $name): array => [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ], $operation['parameters']);
            $paths[$path][strtolower($operation['method'])] = [
                'operationId' => Str::camel($operation['id']),
                'summary' => $operation['summary'],
                'description' => 'Permintaan diteruskan ke controller SIAKAD yang sudah ada. Field dan aturan validasi mengikuti proses bisnis modul. Respons berbentuk JSON.',
                'parameters' => $parameters,
                'security' => [['BearerAuth' => []]],
                'responses' => [
                    '200' => ['description' => 'Operasi berhasil'],
                    '201' => ['description' => 'Data berhasil dibuat'],
                    '204' => ['description' => 'Operasi berhasil tanpa isi respons'],
                    '401' => ['description' => 'Token tidak valid atau kedaluwarsa'],
                    '403' => ['description' => 'Aksi tidak diizinkan'],
                    '404' => ['description' => 'Rute atau data tidak ditemukan'],
                    '409' => ['description' => 'Konflik status data'],
                    '422' => ['description' => 'Validasi permintaan gagal'],
                    '429' => ['description' => 'Batas permintaan terlampaui'],
                    '500' => ['description' => 'Kesalahan internal; detail sensitif tidak dikembalikan'],
                ],
            ];
            if (in_array($operation['method'], ['POST', 'PUT', 'PATCH'], true)) {
                $paths[$path][strtolower($operation['method'])]['requestBody'] = [
                    'required' => false,
                    'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                ];
            }
        }
        $paths['/auth/token']['post'] = [
            'operationId' => 'issueAdminWsToken',
            'summary' => 'Menerbitkan token akses admin_ws (berlaku 60 menit)',
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['username', 'password'],
                    'properties' => [
                        'username' => ['type' => 'string', 'maxLength' => 100],
                        'password' => ['type' => 'string', 'writeOnly' => true],
                    ],
                ]]],
            ],
            'responses' => [
                '200' => ['description' => 'Token akses berhasil diterbitkan.'],
                '401' => ['description' => 'Kredensial tidak valid atau akun tidak aktif.'],
                '429' => ['description' => 'Batas permintaan token terlampaui.'],
            ],
        ];
        $paths['/auth/sso']['post'] = [
            'operationId' => 'ssoLogin',
            'summary' => 'SSO login SIAKAD dan mengambil biodata pengguna',
            'description' => 'Memverifikasi kredensial login SIAKAD dan mengembalikan ID, level, serta biodata pengguna. Biodata dosen, asisten dosen, dan mahasiswa menyertakan profil akademik dan kontak yang tersedia; password dan data keluarga tidak disertakan.',
            'requestBody' => [
                'required' => true,
                'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['login', 'password'],
                    'properties' => [
                        'login' => ['type' => 'string', 'maxLength' => 100, 'description' => 'Username admin/operator, NIDN, kode asisten, atau NIM.'],
                        'password' => ['type' => 'string', 'writeOnly' => true],
                    ],
                ]]],
            ],
            'responses' => [
                '200' => ['description' => 'Login berhasil. Mengembalikan user.id, user.level, dan biodata terperinci sesuai jenis akun pada user.biodata.'],
                '401' => ['description' => 'Identitas atau kata sandi tidak valid.'],
                '422' => ['description' => 'Validasi permintaan gagal.'],
                '429' => ['description' => 'Batas permintaan login terlampaui.'],
            ],
        ];

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API Layanan Web SIAKAD',
                'version' => '1.0.0',
                'description' => 'API produksi menjalankan controller dan validasi bisnis SIAKAD. Mode uji coba portal memakai data simulasi dan tidak menulis ke basis data.',
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/').'/api/v1']],
            'components' => ['securitySchemes' => ['BearerAuth' => [
                'type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Token SIAKAD terenkripsi',
            ]], 'schemas' => [
                'ValidationError' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
                    ],
                ],
            ]],
            'paths' => $paths,
        ];

        return response(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
            ->header('Content-Type', 'application/yaml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="simak-api-v1.yaml"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function sandbox(ApiOperationCatalog $catalog): JsonResponse
    {
        abort_if(strlen((string) request()->getContent()) > 65536, 413, 'Body sandbox maksimal 64 KB.');
        $validated = request()->validate([
            'operation' => ['required', 'string', 'max:200'],
            'method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'path' => ['required', 'string', 'max:500'],
            'body' => ['nullable', 'array'],
        ]);
        abort_if($catalog->find($validated['operation'], $validated['method']) === null, 404);

        $method = $validated['method'];
        $response = match ($method) {
            'GET' => ['data' => [['id' => 'sandbox-001', 'nama' => 'Contoh Data Sandbox']], 'meta' => ['total' => 1]],
            'POST' => ['pesan' => 'Simulasi insert berhasil. Tidak ada data yang disimpan.', 'data' => ['id' => 'sandbox-created-001', ...($validated['body'] ?? [])]],
            'PUT', 'PATCH' => ['pesan' => 'Simulasi update berhasil. Tidak ada data production yang berubah.', 'data' => ['id' => 'sandbox-001', ...($validated['body'] ?? [])]],
            'DELETE' => ['pesan' => 'Simulasi delete berhasil. Tidak ada data yang dihapus.'],
        };

        return response()->json([
            'mode' => 'sandbox',
            'status_http' => $method === 'POST' ? 201 : 200,
            'permintaan' => ['metode' => $method, 'jalur' => $validated['path'], 'body' => $this->redact($validated['body'] ?? null)],
            'respons' => $this->redact($response),
        ])->header('Cache-Control', 'no-store');
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $safe = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|token|secret|api[_-]?key|authorization/i', $key) === 1) {
                continue;
            }
            $safe[$key] = $this->redact($item);
        }

        return $safe;
    }
}
