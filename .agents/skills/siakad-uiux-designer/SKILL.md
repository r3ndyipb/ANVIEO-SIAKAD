---
name: siakad-uiux-designer
description: >-
  Design and build accessible, responsive, and high-performance UI/UX interfaces for SIAKAD Kampus (Tailwind CSS v4, Blade templates, DataTables, Select2/TomSelect, Vite, modal dialogs, academic dashboards, and student/lecturer portals). Use this skill whenever designing or modifying UI components, Blade views, CSS design systems, data tables, micro-interactions, responsive layouts, or accessibility in SIAKAD.
---

# SIAKAD UI/UX & Frontend Designer Skill

Panduan keahlian dan standar baku (*design system & frontend engineering*) bagi **UI/UX Designer & Senior Frontend Engineer** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)** berbasis **Laravel 12/13, Blade, Tailwind CSS v4, dan Vite**.

Keahlian ini menetapkan standar arsitektur antarmuka modern yang memadukan estetika akademis premium, efisiensi navigasi multi-role (14+ role), tabel data berdensitas tinggi (*high-density academic data tables*), form input mata kuliah/nilai yang ergonomis, serta kepatuhan aksesibilitas WCAG 2.1 AA.

---

## 1. Desain Sistem & Token Visual (Academic Campus Theme)

Antarmuka SIAKAD harus merefleksikan institusi pendidikan tinggi: **kredibel, bersih, terstruktur, dan minim distraksi kognitif**.

### 1.1 Palet Warna Semantik & Token Desain

Gunakan token CSS semantik agar konsisten dan mendukung mode terang maupun adaptasi gelap:

```css
:root {
  /* Canvas & Background */
  --academic-bg: #F8FAFC;         /* slate-50 */
  --academic-card: #FFFFFF;       /* white */
  --academic-border: #E2E8F0;     /* slate-200 */
  --academic-border-subtle: #F1F5F9;

  /* Typography */
  --academic-ink: #0F172A;        /* slate-900: Kontras judul & teks utama */
  --academic-ink-muted: #64748B;  /* slate-500: Subteks, label, keterangan */
  --academic-ink-subtle: #94A3B8; /* slate-400: Placeholder & disabled */

  /* Brand Academic Identity */
  --academic-navy: #0F2744;       /* Primary Brand: Header, Sidebar, Brand Button */
  --academic-navy-hover: #163860;
  --academic-navy-light: #EFF6FF; /* Primary tint: Highlight row terpilih */
  --academic-accent-gold: #D97706;/* Akreditasi Unggul, Pin Dosen, Yudisium */

  /* Status Akademik & Keuangan */
  --status-active: #059669;       /* Mahasiswa Aktif, KRS Disetujui, Lunas */
  --status-active-bg: #ECFDF5;
  --status-pending: #D97706;      /* Menunggu Validasi PA, Pending Pembayaran */
  --status-pending-bg: #FFFBEB;
  --status-danger: #DC2626;       /* Cuti, Drop Out, Ditolak, Tunggakan */
  --status-danger-bg: #FEF2F2;
  --status-neutral: #64748B;      /* Non-aktif, Arsip */
  --status-neutral-bg: #F1F5F9;

  /* Grade Score Indicators */
  --grade-a: #059669; /* Nilai A (4.00) */
  --grade-b: #2563EB; /* Nilai B (3.00) */
  --grade-c: #D97706; /* Nilai C (2.00) */
  --grade-d: #EA580C; /* Nilai D (1.00) */
  --grade-e: #DC2626; /* Nilai E (0.00) */
}
```

### 1.2 Hirarki Tipografi
- **Header & Title:** `Plus Jakarta Sans` atau `Inter` (Font-weight: 600, 700) dengan letter-spacing `-0.015em`.
- **Tabel Data & Form Input:** `Inter` (Font-weight: 400, 500) dengan line-height yang nyaman untuk densitas tinggi (`leading-snug`).
- **NIM, NIDN, Kode MK, SKS, Nilai Angka:** Tabular numbers (`font-mono` atau `tabular-nums`) untuk memastikan angka sejajar secara vertikal.

---

## 2. Arsitektur Komponen Inti SIAKAD (UI Archetypes)

### 2.1 Shell Layout: Sidebar, Header Akademik, & Sub-Header Periode
Setiap tampilan SIAKAD wajib menyertakan context header aktif (Tahun Akademik & Semester):

```blade
{{-- layouts/app.blade.php snippet --}}
<div class="min-h-screen bg-slate-50 flex">
  <!-- Sidebar Navigasi Berdasarkan Role -->
  <aside class="w-64 bg-slate-900 text-slate-100 flex-shrink-0 hidden md:flex flex-col">
    <div class="p-4 border-b border-slate-800 flex items-center gap-3">
      <img src="{{ asset('docs/logo.png') }}" alt="Logo Kampus" class="h-9 w-auto">
      <div>
        <h1 class="font-bold text-sm leading-tight text-white">SIAKAD KAMPUS</h1>
        <p class="text-xs text-slate-400 capitalize">Role: {{ auth()->user()->role ?? 'Operator' }}</p>
      </div>
    </div>
    <!-- Menu Links -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      @include('layouts.partials.sidebar-menu')
    </nav>
  </aside>

  <!-- Konten Utama -->
  <main class="flex-1 flex flex-col min-w-0">
    <!-- Topbar Banner Periode Aktif -->
    <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-20">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
          <i data-lucide="calendar" class="w-3.5 h-3.5 mr-1.5"></i>
          Semester Ganjil 2026/2027
        </span>
        <span class="text-xs font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
          Fase: Pengisian KRS
        </span>
      </div>
      <!-- User Profile & Notifikasi -->
      <div class="flex items-center gap-4">
        @include('layouts.partials.notification-bell')
        @include('layouts.partials.user-dropdown')
      </div>
    </header>

    <!-- Konten Halaman -->
    <div class="p-6">
      @yield('content')
    </div>
  </main>
</div>
```

---

### 2.2 Tabel Data Akademik Berdensitas Tinggi (High-Density DataTables)
Data SIAKAD memuat ribuan baris data mahasiswa, nilai, dan jadwal. Wajib menggunakan styling tabel berdensitas tinggi dengan search debounce dan sticky header:

```html
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
  <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h3 class="text-base font-bold text-slate-800">Daftar Mahasiswa Bimbingan Akademik</h3>
      <p class="text-xs text-slate-500">Total 45 Mahasiswa Aktif Angkatan 2024 - 2026</p>
    </div>
    <div class="flex items-center gap-2">
      <button class="btn btn-outline-slate text-xs flex items-center gap-1.5 px-3 py-2 border rounded-lg hover:bg-slate-50">
        <i data-lucide="download" class="w-4 h-4"></i> Ekspor Excel
      </button>
      <button class="btn btn-primary text-xs flex items-center gap-1.5 px-3 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800">
        <i data-lucide="check-check" class="w-4 h-4"></i> Setujui Semua KRS
      </button>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-left text-xs border-collapse">
      <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider">
        <tr>
          <th class="py-3 px-4 w-12 text-center">No</th>
          <th class="py-3 px-4">NIM & Nama Mahasiswa</th>
          <th class="py-3 px-4">Program Studi</th>
          <th class="py-3 px-4 text-center">SKS Diambil</th>
          <th class="py-3 px-4 text-center">IPK Terakhir</th>
          <th class="py-3 px-4 text-center">Status KRS</th>
          <th class="py-3 px-4 text-center">Status Keuangan</th>
          <th class="py-3 px-4 text-center">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 text-slate-700">
        <tr class="hover:bg-slate-50/80 transition-colors">
          <td class="py-2.5 px-4 text-center font-mono text-slate-400">1</td>
          <td class="py-2.5 px-4">
            <div class="font-bold text-slate-900">2024010045</div>
            <div class="text-slate-500">Muhammad Rendy Pratama</div>
          </td>
          <td class="py-2.5 px-4 font-medium">S1 Teknik Informatika</td>
          <td class="py-2.5 px-4 text-center font-mono font-bold text-blue-700">24 SKS</td>
          <td class="py-2.5 px-4 text-center font-mono font-semibold">3.88</td>
          <td class="py-2.5 px-4 text-center">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
              Menunggu ACC
            </span>
          </td>
          <td class="py-2.5 px-4 text-center">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
              Lunas (UKT 4)
            </span>
          </td>
          <td class="py-2.5 px-4 text-center">
            <a href="#" class="inline-flex items-center px-2.5 py-1 bg-white border border-slate-300 rounded text-slate-700 hover:bg-slate-100 font-medium">
              Review KRS
            </a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

---

### 2.3 Matriks Lembar Penilaian Dosen (Interactive Grade Sheet)

Komponen input nilai dosen dengan kalkulasi bobot otomatis instan di sisi klien sebelum submit:

```blade
<div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
  <div class="mb-4 flex items-center justify-between">
    <div>
      <h2 class="text-lg font-bold text-slate-900">Input Nilai: Pemrograman Web Lanjut (3 SKS)</h2>
      <p class="text-xs text-slate-500">Bobot: Kehadiran 10% | Tugas/Kuis 20% | UTS 30% | UAS 40%</p>
    </div>
    <div class="text-right">
      <span class="text-xs font-medium text-slate-600">Status Validasi Prodi:</span>
      <span class="ml-1 text-xs px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-semibold">Draft Terbuka</span>
    </div>
  </div>

  <form action="{{ route('grades.store') }}" method="POST" id="formGradeSheet">
    @csrf
    <table class="w-full text-xs text-left border border-slate-200">
      <thead class="bg-slate-100 text-slate-700 font-bold border-b">
        <tr>
          <th class="p-2 w-10 text-center">No</th>
          <th class="p-2 w-28">NIM</th>
          <th class="p-2">Nama</th>
          <th class="p-2 w-20 text-center">Hadir (10%)</th>
          <th class="p-2 w-20 text-center">Tugas (20%)</th>
          <th class="p-2 w-20 text-center">UTS (30%)</th>
          <th class="p-2 w-20 text-center">UAS (40%)</th>
          <th class="p-2 w-20 text-center bg-blue-50">Nilai Akhir</th>
          <th class="p-2 w-16 text-center bg-blue-50">Grade</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <tr class="grade-row" data-student-id="101">
          <td class="p-2 text-center">1</td>
          <td class="p-2 font-mono">2024010001</td>
          <td class="p-2 font-medium">Ahmad Fauzi</td>
          <td class="p-1"><input type="number" step="0.01" min="0" max="100" class="input-grade w-full p-1 text-center border rounded focus:ring-1 focus:ring-blue-500" name="presensi[101]" value="95"></td>
          <td class="p-1"><input type="number" step="0.01" min="0" max="100" class="input-grade w-full p-1 text-center border rounded focus:ring-1 focus:ring-blue-500" name="tugas[101]" value="85"></td>
          <td class="p-1"><input type="number" step="0.01" min="0" max="100" class="input-grade w-full p-1 text-center border rounded focus:ring-1 focus:ring-blue-500" name="uts[101]" value="80"></td>
          <td class="p-1"><input type="number" step="0.01" min="0" max="100" class="input-grade w-full p-1 text-center border rounded focus:ring-1 focus:ring-blue-500" name="uas[101]" value="88"></td>
          <td class="p-2 text-center font-mono font-bold bg-blue-50/50 final-score">85.70</td>
          <td class="p-2 text-center font-bold bg-blue-50/50 letter-grade text-emerald-700">A</td>
        </tr>
      </tbody>
    </table>
  </form>
</div>
```

---

## 3. Integrasi Library & Komponen Interaktif

1. **TomSelect / Select2 (Pencarian Mahasiswa & MK):**
   - Selalu gunakan `placeholder`, `allowClear`, dan paginasi AJAX jika data melebihi 100 baris.
   - Tampilkan kombinasi format `[KODE] Nama Mata Kuliah - X SKS (Kelas A)`.
2. **SweetAlert2 (Konfirmasi Aksi Destruktif):**
   - Operasi seperti hapus data KRS, penolakan judul skripsi, pembatalan pembayaran, atau reset password wajib melalui dialog modal konfirmasi SweetAlert2 dengan tombol *Cancel* berbobot visual setara.
3. **DataTables.net:**
   - Gunakan `scrollX: true` untuk tabel lebar.
   - Nonaktifkan sorting pada kolom "Aksi" dan "No".
   - Integrasikan tombol ekspor CSV/Excel dan Print khusus view cetak.

---

## 4. Checklist Kualitas UI/UX Sebelum Rilis (Acceptance Criteria)

- [ ] **Responsif Mobile & Tablet:** Seluruh halaman KRS, KHS, dan Presensi dapat diakses via smartphone tanpa tabel terpotong tanpa scrollbar horizontal.
- [ ] **Kontras Warna (WCAG AA):** Teks status di atas pill background memiliki rasio kontras minimal 4.5:1.
- [ ] **State Feedback Lengkap:** Tombol simpan memiliki status *loading spinner* & *disabled* saat request AJAX berjalan guna mencegah *double submit*.
- [ ] **Form Error Handling:** Error validasi dari Laravel `$errors` ditampilkan langsung di bawah input field dengan border merah dan teks deskriptif.
- [ ] **Tidak Ada Warna Hardcoded:** Menggunakan class Tailwind utility (`bg-slate-100`, `text-blue-700`) atau variable CSS root yang terstandarisasi.
