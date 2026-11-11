export interface AccreditationItem {
  id: string;
  title: string;
  organization: string;
  organizationShort: string;
  regNumber: string;
  validity: string;
  image: string;
  category: 'eahea' | 'qahe' | 'iccac';
  categoryLabel: string;
  description: string;
  highlights: string[];
}

export const ACCREDITATION_ITEMS: AccreditationItem[] = [
  {
    id: 'eahea-cert',
    title: 'Certificate of Institutional Accreditation',
    organization: 'European Association for Higher Education Advancement',
    organizationShort: 'EAHEA',
    regNumber: 'Serial No: 250A0214390',
    validity: 'Official Status: Fully Accredited',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.38.webp',
    category: 'eahea',
    categoryLabel: 'European Accreditation',
    description:
      'Sertifikat akreditasi kelembagaan resmi dari European Association for Higher Education Advancement (EAHEA), mengonfirmasi kepatuhan penuh terhadap standar mutu pendidikan internasional, tanggung jawab administratif, dan integritas akademik.',
    highlights: [
      'Memenuhi standar pendidikan tinggi Eropa',
      'Verifikasi tata kelola & integritas institusional',
      'Pengakuan program sarjana, magister, dan doktoral',
    ],
  },
  {
    id: 'eahea-audit',
    title: 'Audit Report Result of Quality Assurance',
    organization: 'European Association for Higher Education Advancement',
    organizationShort: 'EAHEA',
    regNumber: 'Accreditation ID: 250A0214390',
    validity: 'Rating: 3.5 — Very Good (2025–2026)',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.38-1.webp',
    category: 'eahea',
    categoryLabel: 'Audit & Evaluation',
    description:
      'Laporan resmi hasil audit penjaminan mutu akademik oleh EAHEA dengan predikat 3.5 (Very Good). Menegaskan transparansi proses evaluasi, kualitas kurikulum, dan kompetensi tenaga pengajar global.',
    highlights: [
      'Predikat mutu: 3.5 / Very Good',
      'Audit menyeluruh fasilitas, kurikulum, dan pengajar',
      'Kategori institusi: Private, Avocational Higher Education',
    ],
  },
  {
    id: 'qahe-cert',
    title: 'Certificate of Higher Education Accreditation',
    organization: 'International Association for Quality Assurance in Pre-Tertiary & Higher Education',
    organizationShort: 'QAHE',
    regNumber: 'Institution ID: 120875',
    validity: 'Valid Until: 26 November 2026',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.37-2.webp',
    category: 'qahe',
    categoryLabel: 'International QAHE',
    description:
      'Akreditasi internasional oleh QAHE (International Association for Quality Assurance in Pre-Tertiary and Higher Education) yang memvalidasi ASEAN University International sebagai institusi pendidikan tinggi berstandar mutu global.',
    highlights: [
      'Nomor Registrasi Lembaga: ID 120875',
      'Masa berlaku aktif hingga 26 November 2026',
      'Verifikasi kepatuhan mekanisme penjaminan mutu independen',
    ],
  },
  {
    id: 'qahe-seal',
    title: 'QAHE Institutional Quality Recognition',
    organization: 'International Association for Quality Assurance in Pre-Tertiary & Higher Education',
    organizationShort: 'QAHE',
    regNumber: 'Recognized QA System',
    validity: 'Standard Institutional Seal',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.37.webp',
    category: 'qahe',
    categoryLabel: 'Quality Seal',
    description:
      'Segel pengakuan resmi penjaminan mutu QAHE untuk mekanisme evaluasi pengajaran, modul pembelajaran fleksibel online, dan pembimbingan riset akademik lintas negara.',
    highlights: [
      'Mekanisme evaluasi pengajaran berkelanjutan',
      'Standar bimbingan riset dan doktoral',
      'Peningkatan mutu mekanisme penjaminan kualitas',
    ],
  },
  {
    id: 'qahe-iso',
    title: 'ISO 17011:2017 Certified QA Agency Accreditation',
    organization: 'International Association for Quality Assurance in Higher Education',
    organizationShort: 'QAHE / ISO 17011:2017',
    regNumber: 'ISO 17011:2017 Certified QA Agency',
    validity: 'International Compliance',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.36-1.webp',
    category: 'qahe',
    categoryLabel: 'ISO Certified QA',
    description:
      'Akreditasi di bawah naungan badan penjaminan mutu bersertifikasi standar internasional ISO 17011:2017, menjamin kepatuhan sistem sertifikasi dan akreditasi pada tolok ukur global tertinggi.',
    highlights: [
      'Badan penjamin mutu bersertifikasi ISO 17011:2017',
      'Tolok ukur akreditasi global yang diakui secara internasional',
      'Audit independen berstandar ISO',
    ],
  },
  {
    id: 'iccac-cert',
    title: 'Commercial & Academic Certification Endorsement',
    organization: 'Industrial Commercial Certification & Accreditation Center',
    organizationShort: 'ICCAC',
    regNumber: 'Ins No. AS0501586 P',
    validity: 'Corporate & Regional Recognition',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.38-2.webp',
    category: 'iccac',
    categoryLabel: 'Industry & Commerce',
    description:
      'Pengakuan dan sertifikasi kelembagaan oleh ICCAC (Industrial Commercial Certification & Accreditation Center) yang menghubungkan kurikulum akademik AUI dengan standar kebutuhan dunia industri dan komersial internasional.',
    highlights: [
      'Nomor sertifikasi institusi: AS0501586 P',
      'Penyelarasan kurikulum dengan industri modern',
      'Jaringan korporasi dan riset terapan komersial',
    ],
  },
  {
    id: 'iccac-brics-asean',
    title: 'BRICS & ASEAN Chamber of Commerce Recognition',
    organization: 'ICCAC & BRICS ASEAN Chamber of Commerce',
    organizationShort: 'ICCAC / BRICS-ASEAN',
    regNumber: 'Regional Partnership Accreditation',
    validity: 'Inter-Regional Commerce Endorsement',
    image: '/img/accreditation/WhatsApp-Image-2026-04-17-at-11.08.37-1.webp',
    category: 'iccac',
    categoryLabel: 'Regional Recognition',
    description:
      'Endorsement dan kemitraan resmi antara ICCAC bersama BRICS & ASEAN Chamber of Commerce, memperkuat nilai ijazah dan kualifikasi lulusan AUI di pasar kerja kawasan Asia Tenggara dan negara mitra berkembang.',
    highlights: [
      'Kemitraan strategis dengan BRICS & ASEAN Chamber of Commerce',
      'Pengakuan kualifikasi lulusan di pasar regional',
      'Fasilitasi pertukaran profesional dan jejaring bisnis',
    ],
  },
];
