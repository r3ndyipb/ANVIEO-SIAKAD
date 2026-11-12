---
name: siakad-security-compliance-auditor
description: >-
  Audit academic information security, prevent grade tampering, and enforce compliance with Indonesia's Personal Data Protection Law (UU PDP No. 27/2022) for SIAKAD Kampus. Verifies cryptographic SHA-256 hash chains on database audit logs (simak_audit_log), enforces immutable grade lock states across lecturer gradebooks, audits Laravel authorization gates and middleware against privilege escalation (IDOR), applies sensitive data masking (NIK, parent financial records), and executes emergency security containment protocols. Use this skill whenever auditing academic access logs, investigating unauthorized score alterations, hardening authorization gates, or enforcing data privacy compliance.
---

# SIAKAD Academic Security & PDP Compliance Auditor Specialist Skill

Panduan keahlian keamanan siber akademik dan audit kepatuhan privasi bagi **Academic Data Privacy & Grade Tamper Auditor** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar audit keamanan berlapis (*multi-layer academic security*), integritas catatan audit anti-manipulasi berbasis rantai hash SHA-256 (*cryptographic hash chains* pada `simak_audit_log`), penegakan status penguncian nilai mutlak (*immutable gradebook locking*), audit izin otorisasi Laravel middleware/gates terhadap eskalasi hak akses (*IDOR & Privilege Escalation*), serta kepatuhan menyeluruh terhadap **Undang-Undang No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)**.

---

## 1. Arsitektur Audit Anti-Kecurangan Nilai (Anti-Tampering Gradebook Engine)

Nilai akademik mahasiswa adalah aset hukum perguruan tinggi yang paling rentan terhadap kecurangan. SIAKAD menerapkan perlindungan database tingkat rendah:

```
┌─────────────────────────┐       Perubahan Nilai di Aplikasi       ┌─────────────────────────┐
│     Tabel t_nilai       │ ──────────────────────────────────────> │ Trigger PostgreSQL      │
│ (course_grades / krs)   │                                         │ trg_audit_grade_tamper  │
└─────────────────────────┘                                         └───────────┬─────────────┘
                                                                                │
                                                                                ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────┐
│                          TABEL AUDIT LOG KRIPTOGRAFIS (simak_audit_log)                     │
├─────────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. Mencatat Nilai Lama vs Nilai Baru (old_score -> new_score).                              │
│ 2. Mencatat ID Pengguna, Role, Alamat IP Asli, dan User-Agent.                              │
│ 3. Menghitung Hash Kriptografis Berantai:                                                   │
│    SHA-256(RecordID + OldVal + NewVal + UserID + Timestamp + PreviousRecordHash)            │
│ 4. Menjamin baris audit bersifat WORM (Write Once, Read Many) — Tidak dapat diedit / dihapus│
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

### Implementasi Trigger Audit PostgreSQL:
```sql
CREATE OR REPLACE FUNCTION audit_grade_mutation()
RETURNS TRIGGER AS $$
DECLARE
    last_hash VARCHAR(64);
    new_hash VARCHAR(64);
BEGIN
    -- Ambil hash terakhir dari log untuk membentuk rantai hash (blockchain-like chain)
    SELECT record_hash INTO last_hash 
    FROM simak_audit_log 
    ORDER BY id DESC LIMIT 1;
    
    IF last_hash IS NULL THEN
        last_hash := 'GENESIS_HASH_SIAKAD_2026';
    END IF;

    -- Hitung hash baru secara deterministik
    new_hash := encode(digest(
        COALESCE(OLD.id::text, '') || 
        COALESCE(OLD.final_score::text, '') || 
        COALESCE(NEW.final_score::text, '') || 
        current_setting('app.current_user_id', true) || 
        clock_timestamp()::text || 
        last_hash, 
        'sha256'
    ), 'hex');

    -- Simpan entri audit log yang tidak dapat diubah
    INSERT INTO simak_audit_log (
        table_name,
        record_id,
        action,
        old_values,
        new_values,
        modified_by,
        ip_address,
        previous_hash,
        record_hash,
        created_at
    ) VALUES (
        'course_grades',
        NEW.id,
        TG_OP,
        row_to_json(OLD),
        row_to_json(NEW),
        NULLIF(current_setting('app.current_user_id', true), '')::bigint,
        inet_client_addr(),
        last_hash,
        new_hash,
        clock_timestamp()
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Pasang trigger pada tabel penilaian
CREATE TRIGGER trg_audit_grades
AFTER UPDATE OR DELETE ON course_grades
FOR EACH ROW EXECUTE FUNCTION audit_grade_mutation();
```

---

## 2. Kebijakan Penguncian Nilai Mutlak (Grade Lock State Machine)

Untuk mencegah dosen mengubah nilai setelah masa komplain berakhir:

```
[Dosen Input Nilai] ──> [Simpan Draft (Bebas Edit)]
                                │
                                ▼
                        [Submit ke Kaprodi (Review OBE)]
                                │
                                ▼
                        [Kaprodi Approve ──> Status: LOCKED]
```

### Aturan Pasca-Lock:
1. Status `LOCKED` secara permanen menonaktifkan form edit nilai di antarmuka web dosen.
2. Setiap percobaan perubahan status nilai yang terkunci melalui API akan dilemparkan `AuthorizationException: Gradebook is locked`.
3. Pembukaan kembali kunci (*grade unlock*) memerlukan dekret persetujuan resmi dari **Dekan Fakultas** disertai alasan tertulis yang dicatat dalam berita acara audit.

---

## 3. Kepatuhan UU No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)

SIAKAD mengelola Data Pribadi Berspesifikasi Khusus (Data Finansial Orang Tua, Data Kesehatan/Disabilitas, Catatan Disiplin) dan Data Pribadi Umum (NIK, NIM, Nama, Nomor Telepon, Alamat).

### 3.1 Protokol Masking Data Sensitif di Antarmuka Pengguna
Data sensitif wajib disamarkan (*masked*) di seluruh tampilan frontend kecuali bagi staf BAAK/Keuangan yang memiliki izin resmi:

| Atribut Data | Tampilan Masking (Umum / Dosen) | Tampilan Berizin Khusus |
| :--- | :--- | :--- |
| **NIK Mahasiswa** | `320102**********` | `3201021204010005` (Akses BAAK Resmi) |
| **Nomor Telepon** | `+62 812-****-5678` | Nomor lengkap |
| **Penghasilan Orang Tua**| `[Tersamarkan / Terkunci]` | Nilai nominal (Khusus Verifikator UKT) |
| **IPK Mahasiswa Lain** | `[Akses Ditolak]` | Mahasiswa hanya boleh melihat nilai pribadi |

### 3.2 Hak Subjek Data (Data Subject Rights)
1. **Hak Akses & Transparansi**: Mahasiswa memiliki hak mengunduh riwayat lengkap data pribadi dan log aktivitas akademik mereka dalam format JSON/PDF.
2. **Hak Koreksi Data**: Mahasiswa dapat mengajukan perbaikan biodata (Nama, NIK, Tanggal Lahir) dengan melampirkan bukti KTP/Akta Lahir yang divalidasi oleh operator BAAK sebelum disinkronkan ke PDDIKTI.

---

## 4. Audit Kerentanan Otorisasi Laravel (IDOR & Privilege Escalation)

Auditor memindai seluruh *route* dan *controller* untuk mencegah kebocoran horizontal (mahasiswa A melihat data KRS mahasiswa B):

### Standar Penulisan Controller Aman:
```php
// SALAH: Rentan IDOR (Insecure Direct Object Reference)
public function getKrs(int $id)
{
    return Krs::findOrFail($id); // Mahasiswa mana pun bisa membaca KRS ID orang lain!
}

// BENAR: Menggunakan Laravel Gate Policy & User Scoping
public function getKrs(Request $request, Krs $krs)
{
    $this->authorize('view', $krs); // Memvalidasi apakah $request->user()->id === $krs->student_id

    return response()->json($krs->load(['details.schedule.course']));
}
```

### Policy Definition (`KrsPolicy.php`):
```php
public function view(User $user, Krs $krs): bool
{
    // Superadmin dan BAAK memiliki akses baca
    if ($user->hasRole(['superadmin', 'baak', 'kaprodi'])) {
        return true;
    }

    // Dosen PA hanya bisa melihat jika mahasiswa tersebut adalah bimbingannya
    if ($user->hasRole('dosen_pa')) {
        return $krs->student->academic_advisor_id === $user->lecturer->id;
    }

    // Mahasiswa HANYA bisa melihat KRS miliknya sendiri
    return $user->hasRole('mahasiswa') && $krs->student_id === $user->student->id;
}
```

---

## 5. Protokol Kedaruratan & Lockdown Akses (Emergency Response)

Jika terdeteksi anomali keamanan kritis (misal: serangan injeksi SQL, kebocoran kredensial admin, atau manipulasi massal nilai):

1. **Pembekuan Sesi Seketika (Instant Session Invalidation)**:
   ```bash
   php artisan siakad:emergency-lockdown --revoke-tokens --scope=all
   ```
2. **Penguncian Akses Tulis Database (Read-Only Mode)**:
   Mengalihkan status database ke `READ ONLY` untuk mencegah kerusakan lebih lanjut selama investigasi forensik.
3. **Preservasi Bukti Digital (Digital Forensics)**:
   Menyimpan snapshot log web server Nginx, PostgreSQL WAL (*Write-Ahead Logs*), dan tabel `simak_audit_log` dengan tanda tangan hash SHA-256 sebagai alat bukti hukum yang sah menurut UU ITE.

---

## 6. Checklist Audit Keamanan & Kepatuhan

- [ ] Trigger PostgreSQL `simak_audit_log` aktif dan membentuk rantai hash SHA-256 yang tidak terputus.
- [ ] Penguncian nilai semesteran (`LOCKED`) tidak dapat ditembus oleh pengubahan langsung via API.
- [ ] Seluruh endpoint API menerapkan policy check kepemilikan data untuk mengeliminasi kerentanan IDOR.
- [ ] NIK dan data finansial orang tua tersamarkan (*masked*) pada tampilan publik dan dosen.
- [ ] Sesi login mahasiswa dibatasi maksimal 1 perangkat aktif bersamaan saat periode KRS War.
