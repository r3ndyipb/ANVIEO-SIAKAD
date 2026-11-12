<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LmsMateriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conn = DB::connection('simak_main');

        if (! Schema::connection('simak_main')->hasTable('lms_materi_kuliah')) {
            return;
        }

        // 1. Pastikan berkas fisik contoh tersedia di storage disk lokal
        $this->ensurePhysicalSampleFiles();

        // 2. Daftar mata kuliah dengan dosen pengampu dan prodi
        $courseCatalog = [
            // Fakultas Bisnis & Ekonomi (FBE)
            [
                'id_matkul' => 'MK-BE-101',
                'kode_mata_kuliah' => 'BE101',
                'nama_mata_kuliah' => 'Strategic Management & Global Business',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'id_dosen' => 'DOSEN-BE-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Pengantar Manajemen Strategis & Analisis Lingkungan Bisnis',
                        'deskripsi' => 'Pengenalan kerangka kerja manajemen strategis, analisis PESTEL, dan dynamic capabilities perusahaan dalam lanskap bisnis global.',
                        'file' => 'Pertemuan-01-Pengantar-Manajemen-Strategis.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2450000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul 02: Analisis Rantai Nilai (Value Chain) & Keunggulan Kompetitif',
                        'deskripsi' => 'Modul praktikal Porter Five Forces, resource-based view (RBV), dan formulasi competitive advantage berkelanjutan.',
                        'file' => 'Modul-02-Value-Chain-Competitive-Advantage.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1820000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Strategi Diversifikasi Korporat, Merger & Aliansi Global',
                        'deskripsi' => 'Bahan tayang pertemuan ketiga mengenai strategi korporat multidivisi, aliansi strategis lintas negara, dan M&A integration.',
                        'file' => 'Slide-03-Strategi-Diversifikasi-Korporat.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3650000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Studi Kasus: Transformasi Model Bisnis Digital pada Pasar Berkembang',
                        'deskripsi' => 'Kompilasi studi kasus empiris transformasi industri ritel dan logistik menghadapi disrupsi platform digital di Asia Tenggara.',
                        'file' => 'Studi-Kasus-Model-Bisnis-Digital.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 3120000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Rencana Pembelajaran Semester (RPS) & Panduan Penugasan Mandiri',
                        'deskripsi' => 'Silabus perkuliahan, rubrik asesmen OBE berbasis CPMK, dan petunjuk penyusunan strategic audit report.',
                        'file' => 'RPS-Manajemen-Strategis-BE101.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 890000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-BE-102',
                'kode_mata_kuliah' => 'BE102',
                'nama_mata_kuliah' => 'Managerial Economics & Market Analysis',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'id_dosen' => 'DOSEN-BE-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Teori Permintaan, Elastisitas & Perilaku Konsumen Modern',
                        'deskripsi' => 'Dasar ekonomi manajerial, estimasi fungsi permintaan, elastisitas harga, dan pemodelan regresi permintaan pasar.',
                        'file' => 'Ekonomi-Manajerial-Modul-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 1950000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Analisis Biaya Produksi Jangka Pendek & Skala Ekonomis',
                        'deskripsi' => 'Kurva biaya rata-rata, marginal cost, economies of scale, dan optimasi kapasitas output pabrikasi modern.',
                        'file' => 'Slide-02-Biaya-Produksi-Skala-Ekonomis.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 2850000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Studi Empiris: Strategi Penataan Harga Dinamis pada Pasar Oligopoli',
                        'deskripsi' => 'Game theory, Nash equilibrium, dan strategi pricing model pada industri telekomunikasi dan penerbangan komersial.',
                        'file' => 'Studi-Empiris-Pricing-Strategy.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2200000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-BE-201',
                'kode_mata_kuliah' => 'BE801',
                'nama_mata_kuliah' => 'Executive Leadership & Corporate Transformation',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'id_dosen' => 'DOSEN-BE-02',
                'materials' => [
                    [
                        'judul' => 'Modul Eksekutif 01: Paradigma Kepemimpinan Transformasional di Era AI',
                        'deskripsi' => 'Framework kepemimpinan adaptif bagi C-Level, menavigasi disrupsi teknologi, dan membangun resiliensi organisasi.',
                        'file' => 'Modul-Eksekutif-01-Transformational-Leadership.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2750000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Manajemen Perubahan Organisasi & Budaya Kerja Tangkas',
                        'deskripsi' => 'Model 8-tahap Kotter, mitigasi resistensi budaya, dan orkestrasi agile mindset pada korporasi skala enterprise.',
                        'file' => 'Slide-02-Corporate-Transformation.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 4200000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Executive Reading: Tata Kelola ESG & Sustainable Corporate Strategy',
                        'deskripsi' => 'Environmental, Social, and Governance (ESG) compliance, stakeholder value creation, dan pelaporan keberlanjutan terintegrasi.',
                        'file' => 'Executive-Reading-ESG-Governance.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 3400000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Rekayasa & Teknologi Informasi (FET)
            [
                'id_matkul' => 'MK-ET-101',
                'kode_mata_kuliah' => 'ET101',
                'nama_mata_kuliah' => 'Artificial Intelligence & Deep Learning',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'id_dosen' => 'DOSEN-ET-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Fondasi Kecerdasan Buatan & Arsitektur Deep Neural Networks',
                        'deskripsi' => 'Pengantar perceptron multi-lapis, fungsi aktivasi non-linier, optimasi backpropagation, dan gradien konvergen.',
                        'file' => 'AI-DeepLearning-Pertemuan-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 3800000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul Praktikum: Pemodelan CNN & Transfer Learning dengan PyTorch',
                        'deskripsi' => 'Panduan hands-on computer vision: arsitektur ResNet/EfficientNet, data augmentation, dan evaluasi matriks confusion.',
                        'file' => 'Modul-Praktikum-CNN-PyTorch.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 2100000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Arsitektur Transformer, Self-Attention & LLM Foundations',
                        'deskripsi' => 'Mekanisme perhatian (attention mechanism), posisi encoding, arsitektur encoder-decoder, dan fine-tuning model generatif.',
                        'file' => 'Slide-03-Transformers-LLM-Architecture.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 5400000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Paper Referensi: Teknik Optimasi Inferences, Quantization & LoRA',
                        'deskripsi' => 'Kumpulan riset terkini mengenai kuantisasi 4-bit, parameter-efficient fine-tuning (PEFT), dan deployment pada edge device.',
                        'file' => 'Paper-Referensi-Model-Quantization.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2900000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Panduan Proyek Akhir: Pembangunan Autonomous Intelligent Agent',
                        'deskripsi' => 'Spesifikasi teknis, rubrik penilaian kode, dan standar repositori Git untuk proyek kolaboratif semester.',
                        'file' => 'Panduan-Proyek-Akhir-AI-ET101.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 1150000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-ET-102',
                'kode_mata_kuliah' => 'ET102',
                'nama_mata_kuliah' => 'Modern Software Architecture & Cloud Systems',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'id_dosen' => 'DOSEN-ET-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Clean Architecture, Domain-Driven Design & Hexagonal Pattern',
                        'deskripsi' => 'Prinsip pemisahan domain logic, ports and adapters, inpresi ketergantungan (IoC), dan arsitektur modular terukur.',
                        'file' => 'Clean-Architecture-Cloud-Systems-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2600000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Event-Driven Architecture, Kafka & Message Brokers',
                        'deskripsi' => 'Pola komunikasi asinkron, idempotency, event sourcing, CQRS, dan penanganan distributed transaction.',
                        'file' => 'Slide-02-Event-Driven-Architecture.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3800000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul Laboratorium: Orkestrasi Docker, Kubernetes & CI/CD Pipeline',
                        'deskripsi' => 'Tutorial deployment containerized microservices, ingress controller, horizontal pod autoscaler, dan GitOps.',
                        'file' => 'Modul-Kubernetes-Deployment.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 2400000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-TIF101',
                'kode_mata_kuliah' => 'TIF101',
                'nama_mata_kuliah' => 'Algoritma dan Pemrograman Dasar',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'id_dosen' => 'DOSEN-ET-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Pengantar Algoritma, Flowchart & Notasi Pseudocode',
                        'deskripsi' => 'Dasar pemikiran komputasional, struktur sekuensial, percabangan logika, dan perulangan standar.',
                        'file' => 'Algoritma-Pemrograman-Dasar-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 1950000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Tipe Data Primitif, Variabel & Operasi Aritmatika',
                        'deskripsi' => 'Manajemen memori dasar, alokasi tipe data, konversi tipe (casting), dan operator perbandingan.',
                        'file' => 'Slide-02-Tipe-Data-Variabel.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 2750000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul Praktikum: Pemrograman C/C++ Dasar di Lingkungan Terminal',
                        'deskripsi' => 'Panduan instalasi compiler GCC/Clang, penulisan fungsi main, kompilasi berkas sumber, dan debugging dasar.',
                        'file' => 'Modul-Praktikum-Pemrograman-Dasar.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1650000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-TIF502',
                'kode_mata_kuliah' => 'TIF502',
                'nama_mata_kuliah' => 'Pembelajaran Mesin dan Kecerdasan Buatan',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'id_dosen' => 'DOSEN-004',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Dasar Supervised Learning & Regresi Linear Berganda',
                        'deskripsi' => 'Fungsi biaya (cost function), penurunan gradien (gradient descent), dan evaluasi metrik MSE/R-Squared.',
                        'file' => 'Machine-Learning-Modul-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2450000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Model Klasifikasi: Decision Tree, Random Forest & SVM',
                        'deskripsi' => 'Gini impurity, information gain, hyperparameter tuning, dan pemisahan hyperplane non-linier.',
                        'file' => 'Slide-02-Klasifikasi-Random-Forest.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3850000,
                        'status' => 'terbit',
                    ],
                ],
            ],
            [
                'id_matkul' => 'MK-ET-201',
                'kode_mata_kuliah' => 'ET801',
                'nama_mata_kuliah' => 'Autonomous Systems & Advanced Neural Computing',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'id_dosen' => 'DOSEN-ET-02',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Reinforcement Learning & Deep Q-Networks untuk Robotika',
                        'deskripsi' => 'Markov decision processes (MDP), Bellman equation, policy gradients, dan simulasi fisika robotik otonom.',
                        'file' => 'Autonomous-Systems-Lecture-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 3200000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Sensor Fusion, Kalman Filter & SLAM Navigation',
                        'deskripsi' => 'Integrasi sensor multi-modal LiDAR, kamera stereo, IMU, dan algoritma Simultaneous Localization and Mapping.',
                        'file' => 'Slide-02-Sensor-Fusion-SLAM.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 4900000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Studi Riset: Safety Guarantees & Formal Verification in Autonomous Vehicles',
                        'deskripsi' => 'Kajian verifikasi formal keselamatan sistem otonom dan mitigasi kegagalan pada lingkungan tidak terduga.',
                        'file' => 'Formal-Verification-Autonomous-Vehicles.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2800000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Ilmu Sosial & Humaniora (FSH)
            [
                'id_matkul' => 'MK-SH-101',
                'kode_mata_kuliah' => 'SH101',
                'nama_mata_kuliah' => 'Global Digital Communication & Social Media',
                'id_prodi' => 'PRODI-SH-S1',
                'id_fakultas' => 'FK-FSH',
                'id_dosen' => 'DOSEN-SH-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Ekologi Komunikasi Digital & Fenomena Ruang Siber',
                        'deskripsi' => 'Evolusi media massa menuju jejaring sosial digital, budaya partisipatoris, dan pergeseran pola konsumsi informasi publik.',
                        'file' => 'Digital-Communication-Modul-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2100000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Manajemen Krisis Reputasi Korporat di Media Sosial',
                        'deskripsi' => 'Strategi respon cepat, sentiment monitoring, komunikasi pemulihan citra, dan mitigasi viralitas negatif daring.',
                        'file' => 'Slide-02-Social-Media-Crisis-Management.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3300000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul Riset: Analisis Jaringan Sosial (SNA) & Algoritma Rekomendasi',
                        'deskripsi' => 'Teknik pemetaan sentralitas jaringan, identifikasi opinion leader, dan pemahaman algoritma feed sosial.',
                        'file' => 'Modul-SNA-Media-Sosial.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1750000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Hukum & Tata Kelola (FLW)
            [
                'id_matkul' => 'MK-LW-101',
                'kode_mata_kuliah' => 'LW101',
                'nama_mata_kuliah' => 'International Regulatory Standards & Trade Law',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'id_dosen' => 'DOSEN-LW-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Rezim Hukum Perdagangan Internasional & Ketentuan GATT/WTO',
                        'deskripsi' => 'Prinsip Non-Discrimination (MFN dan National Treatment), tarif dan non-tarif barrier, serta instrumen perlindungan dagang.',
                        'file' => 'Hukum-Dagang-Internasional-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2300000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul 02: Klausul Kontrak Dagang Transnasional (CISG & Incoterms 2020)',
                        'deskripsi' => 'Penyusunan kontrak ekspor-impor, alokasi risiko pengapalan, letter of credit (L/C), dan force majeure internasional.',
                        'file' => 'Modul-Kontrak-Dagang-Incoterms.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1950000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Penyelesaian Sengketa Dagang melalui Arbitrase Internasional',
                        'deskripsi' => 'Prosedur arbitrase ICSID, SIAC, dan ICC, klausul pilihan hukum (choice of law), dan eksekusi putusan arbitrase asing.',
                        'file' => 'Slide-03-Arbitrase-Internasional.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3100000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Humaniora & Budaya (FHM)
            [
                'id_matkul' => 'MK-HM-101',
                'kode_mata_kuliah' => 'HM101',
                'nama_mata_kuliah' => 'World Literature & Cross-Cultural Perspectives',
                'id_prodi' => 'PRODI-HM-S1',
                'id_fakultas' => 'FK-FHM',
                'id_dosen' => 'DOSEN-HM-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Kanon Kesusastraan Dunia & Perspektif Post-Kolonial',
                        'deskripsi' => 'Eksplorasi representasi identitas, hegemoni kultural, dan kritik sastra komparatif lintas benua.',
                        'file' => 'World-Literature-Pertemuan-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2050000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 02: Analisis Semiotika Naratif & Transkulturalisme Sastra',
                        'deskripsi' => 'Struktur mitos, arketipe karakter, dan adaptasi karya klasik dalam media kontemporer lintas budaya.',
                        'file' => 'Slide-02-Semiotika-Naratif-Sastra.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 2950000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Kesehatan Masyarakat (FHS)
            [
                'id_matkul' => 'MK-HS-101',
                'kode_mata_kuliah' => 'HS101',
                'nama_mata_kuliah' => 'Public Wellness & Healthcare Systems Management',
                'id_prodi' => 'PRODI-HS-S1',
                'id_fakultas' => 'FK-FHS',
                'id_dosen' => 'DOSEN-HS-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Tata Kelola Sistem Pelayanan Kesehatan & Universal Health Coverage',
                        'deskripsi' => 'Pondasi sistem kesehatan nasional, pembiayaan kapitasi, mitigasi disparitas layanan, dan evaluasi program promkes.',
                        'file' => 'Public-Health-Management-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2500000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul 02: Epidemiologi Terapan & Surveilans Penyakit Berbasis Komunitas',
                        'deskripsi' => 'Metode investigasi wabah, desain studi case-control dan kohort, serta pengukuran odds ratio dan relative risk.',
                        'file' => 'Modul-Epidemiologi-Komunitas.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1900000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Kebijakan Preventif & Intervensi Nutrisi Berkelanjutan',
                        'deskripsi' => 'Strategi nasional percepatan penurunan stunting, sanitasi total berbasis masyarakat, dan diplomasi kesehatan global.',
                        'file' => 'Slide-03-Kebijakan-Preventif-Nutrisi.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3400000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Ekonomi Syariah & Peradaban Islam (FIS)
            [
                'id_matkul' => 'MK-IS-101',
                'kode_mata_kuliah' => 'IS101',
                'nama_mata_kuliah' => 'Islamic Jurisprudence & Contemporary Civilizations',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'id_dosen' => 'DOSEN-IS-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Prinsip Ushul Fiqih & Rekonstruksi Hukum Muamalah Modern',
                        'deskripsi' => 'Kaidah asasiyah, maqashid syariah (hifdzul mal), analogi qiyas, dan istihsan dalam merespon transaksi finansial modern.',
                        'file' => 'Ushul-Fiqih-Muamalah-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2400000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul 02: Arsitektur Kontrak Keuangan Syariah (Mudharabah & Musyarakah)',
                        'deskripsi' => 'Karakteristik akad profit-and-loss sharing, mitigasi moral hazard, dan tata kelola transparansi pelaporan bagi hasil.',
                        'file' => 'Modul-Akad-Keuangan-Syariah.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1850000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Sukuk Hijau (Green Sukuk) & Pembiayaan Berkelanjutan Syariah',
                        'deskripsi' => 'Mekanisme penerbitan obligasi syariah berbasis aset ramah lingkungan, standar AAOIFI, dan audit syariah terpadu.',
                        'file' => 'Slide-03-Green-Sukuk-Finance.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3250000,
                        'status' => 'terbit',
                    ],
                ],
            ],

            // Fakultas Hubungan Internasional & Tata Negara (FPS)
            [
                'id_matkul' => 'MK-PS-101',
                'kode_mata_kuliah' => 'PS101',
                'nama_mata_kuliah' => 'International Relations & Global Governance',
                'id_prodi' => 'PRODI-PS-S1',
                'id_fakultas' => 'FK-FPS',
                'id_dosen' => 'DOSEN-PS-01',
                'materials' => [
                    [
                        'judul' => 'Pertemuan 01: Dinamika Keamanan Kawasan & Arsitektur Multilateral Indo-Pasifik',
                        'deskripsi' => 'Teori realisme ofensif vs liberal institusionalisme, sentralitas ASEAN, dan persaingan strategis kekuatan global.',
                        'file' => 'Hubungan-Internasional-Modul-01.pdf',
                        'tipe' => 'application/pdf',
                        'ukuran' => 2350000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Modul 02: Negosiasi Diplomatik Multilateral & Perumusan Traktat Internasional',
                        'deskripsi' => 'Simulasi perundingan PBB, teknik drafting resolusi, diplomasi jalur dua (Track II Diplomacy), dan konsensus voting.',
                        'file' => 'Modul-Negosiasi-Diplomatik.docx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'ukuran' => 1900000,
                        'status' => 'terbit',
                    ],
                    [
                        'judul' => 'Slide Kuliah 03: Diplomasi Transisi Energi & Tata Kelola Perubahan Iklim Global',
                        'deskripsi' => 'Analisis Paris Agreement, perdagangan karbon (carbon trading), dan geoekonomi mineral kritis untuk transisi hijau.',
                        'file' => 'Slide-03-Diplomasi-Transisi-Energi.pptx',
                        'tipe' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'ukuran' => 3600000,
                        'status' => 'terbit',
                    ],
                ],
            ],
        ];

        // 3. Masukkan ke dalam tabel lms_materi_kuliah
        $now = Carbon::now('Asia/Jayapura');
        $seededCount = 0;

        foreach ($courseCatalog as $courseIndex => $course) {
            // Pastikan jadwal kuliah ada agar muncul di materialLibraryForViewer
            $scheduleId = 'JAD-DEMO-'.Str::upper(Str::slug($course['kode_mata_kuliah']));
            $existingSchedule = $conn->table('s1m4k_jadwal')
                ->where('id_matkul', $course['id_matkul'])
                ->where('id_prodi', $course['id_prodi'])
                ->first();

            if (! $existingSchedule) {
                $conn->table('s1m4k_jadwal')->updateOrInsert(
                    ['id_jadwal' => $scheduleId],
                    [
                        'id_perguruan_tinggi' => 'PT-001',
                        'id_fakultas' => $course['id_fakultas'],
                        'id_prodi' => $course['id_prodi'],
                        'id_kalender' => '20261',
                        'id_matkul' => $course['id_matkul'],
                        'dosen1' => $course['id_dosen'],
                        'id_ruang' => 'R-AUI-'.($courseIndex + 1),
                        'nama_kelas' => 'Kelas '.chr(65 + ($courseIndex % 3)),
                        'hari' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'][$courseIndex % 5],
                        'jammulai' => '08:30:00',
                        'jamselesai' => '11:00:00',
                        'jml_mahasiswa' => 35,
                        'input_nilai' => 'N',
                    ]
                );
            }

            foreach ($course['materials'] as $mIndex => $mat) {
                $materialId = 'MAT-'.Str::upper(Str::slug($course['kode_mata_kuliah'])).'-'.str_pad((string) ($mIndex + 1), 2, '0', STR_PAD_LEFT);
                $storagePath = 'materi/'.$this->pickStorageFile($mat['file']);
                $createdAt = (clone $now)->subDays(rand(1, 45))->subHours(rand(1, 12));

                $conn->table('lms_materi_kuliah')->updateOrInsert(
                    ['id_materi' => $materialId],
                    [
                        'id_dosen' => $course['id_dosen'],
                        'id_matkul' => $course['id_matkul'],
                        'id_prodi' => $course['id_prodi'],
                        'judul' => $mat['judul'],
                        'deskripsi' => $mat['deskripsi'],
                        'nama_berkas' => $mat['file'],
                        'path_berkas' => $storagePath,
                        'tipe_berkas' => $mat['tipe'],
                        'ukuran_berkas' => $mat['ukuran'],
                        'status_publikasi' => $mat['status'],
                        'dibuat_pada' => $createdAt,
                        'diperbarui_pada' => $createdAt,
                    ]
                );

                $seededCount++;
            }
        }

        $this->command?->info("Berhasil menginjeksi {$seededCount} data materi kuliah LMS random untuk demo.");
    }

    /**
     * Memilih berkas fisik mock yang sesuai pada storage lokal
     */
    private function pickStorageFile(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'sample-materi-kuliah.pdf',
            'docx' => 'sample-modul-kuliah.docx',
            'pptx' => 'sample-slide-presentasi.pptx',
            default => 'sample-materi-kuliah.pdf',
        };
    }

    /**
     * Memastikan berkas fisik mock siap unduh pada storage
     */
    private function ensurePhysicalSampleFiles(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists('materi/sample-materi-kuliah.pdf')) {
            $pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 595 842]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000102 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n182\n%%EOF\n";
            $disk->put('materi/sample-materi-kuliah.pdf', $pdfContent);
        }

        if (! $disk->exists('materi/sample-slide-presentasi.pptx')) {
            $disk->put('materi/sample-slide-presentasi.pptx', "PK\x03\x04AUI LMS Presentation Demo File");
        }

        if (! $disk->exists('materi/sample-modul-kuliah.docx')) {
            $disk->put('materi/sample-modul-kuliah.docx', "PK\x03\x04AUI LMS Module Document Demo File");
        }
    }
}
