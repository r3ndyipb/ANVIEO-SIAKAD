export interface LeaderMember {
  id: string;
  name: string;
  role: string;
  category: 'executive' | 'advisory' | 'chancellor' | 'director' | 'deans';
  categoryLabel: string;
  image: string;
  facultyOrDivision?: string;
  isHead?: boolean;
}

export interface StructureTier {
  key: 'executive' | 'advisory' | 'chancellor' | 'director' | 'deans';
  title: string;
  subtitle: string;
  description: string;
  badge: string;
}

export const STRUCTURE_TIERS: StructureTier[] = [
  {
    key: 'executive',
    title: 'Executive Leadership',
    subtitle: 'Asian International Education SDN BHD',
    description:
      'Pimpinan eksekutif yayasan pendidikan yang memimpin visi strategis, tata kelola kelembagaan, dan pengembangan global AUI.',
    badge: 'Executive Board',
  },
  {
    key: 'advisory',
    title: 'Advisory Board',
    subtitle: 'Dewan Pertimbangan & Penasehat Kehormatan',
    description:
      'Tokoh-tokoh akademisi dan negarawan terkemuka yang memberikan bimbingan independen serta panduan strategis jangka panjang bagi AUI.',
    badge: 'Advisory',
  },
  {
    key: 'chancellor',
    title: 'Board of Chancellor',
    subtitle: 'Kanselir & Wakil Kanselir Universitas',
    description:
      'Pucuk pimpinan universitas yang memimpin arah akademik, tata kelola riset doktoral, keuangan, dan relasi kelembagaan.',
    badge: 'Chancellor Office',
  },
  {
    key: 'director',
    title: 'Board of Director',
    subtitle: 'Dewan Direktur & Tata Kelola Operasional',
    description:
      'Direksi operasional yang mengawasi efektivitas manajerial, penjaminan mutu mutu akademik, dan ekspansi program.',
    badge: 'Directors',
  },
  {
    key: 'deans',
    title: "Faculty Dean's Board",
    subtitle: 'Dewan Dekan & Direktur Program Fakultas',
    description:
      'Para Dekan dan Direktur Program yang memimpin keunggulan kurikulum, riset fakultas, dan bimbingan mahasiswa di seluruh disiplin ilmu.',
    badge: 'Deans Board',
  },
];

export const STRUCTURE_MEMBERS: LeaderMember[] = [
  // 1. EXECUTIVE LEADERSHIP (6 members)
  {
    id: 'suhendar',
    name: 'Prof. Dr. Suhendar, S.E., S.H., LL.M.',
    role: 'Chairman of ASEAN International Education SDN BHD',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/iuwebkfuwe-.png',
    facultyOrDivision: 'ASEAN International Education SDN BHD',
    isHead: true,
  },
  {
    id: 'faizal-mokhtar',
    name: 'Prof. Dr. Dato’ Mohammad Faizal Bin Mohammad Mokhtar, M.Sc., Ph.D.',
    role: 'Financial - Vice President I',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/WhatsApp-Image-2026-07-24-at-17.22.08.jpeg',
    facultyOrDivision: 'Financial & Fiscal Governance',
  },
  {
    id: 'mohammad-hatta',
    name: 'Prof. Dr. Drs. H. Mohammad Hatta, M.Si., MBA., Ph.D.',
    role: 'Diplomatic Relations - Vice President II',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/Prof-Hatta.png',
    facultyOrDivision: 'Diplomatic & Diplomatic Institutional Affairs',
  },
  {
    id: 'firmansyah',
    name: 'Prof. H. Firmansyah, S.H., S.E., M.M., Ph.D.',
    role: 'Collaboration and Institutional Development - Vice President III',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/Screenshot-2026-09-18-at-15.11.38.png',
    facultyOrDivision: 'Strategic Partnerships & Development',
  },
  {
    id: 'mulyadi',
    name: 'Prof. Dr. MULYADI, SE., SH., MM., MH., C.TL.',
    role: 'Student Affairs and Alumni – Vice President IV',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/jwhevfjfhwe.png',
    facultyOrDivision: 'Student Affairs & Global Alumni Network',
  },
  {
    id: 'sinan-yegul',
    name: 'Prof. Sinan Yegul',
    role: 'International Relations – Vice President V',
    category: 'executive',
    categoryLabel: 'Executive Leadership',
    image: '/img/about/WhatsApp-Image-2026-07-22-at-22.34.38.jpeg',
    facultyOrDivision: 'Global Alliances & International Affairs',
  },

  // 2. ADVISORY BOARD (5 members)
  {
    id: 'yunus-rahmadsyah',
    name: 'Prof. Dr. TSDYM. Sultan TBA M. Yunus Abdullah Rahmadsyah Al Haj',
    role: 'Senior Member of Advisory Board',
    category: 'advisory',
    categoryLabel: 'Advisory Board',
    image: '/img/about/Screenshot-2026-05-11-at-13.14.27.webp',
    facultyOrDivision: 'Advisory Board',
    isHead: true,
  },
  {
    id: 'yahya-ibrahim',
    name: 'Prof. Dr. Datuk Yahya Ibrahim',
    role: 'Member of Advisory Board',
    category: 'advisory',
    categoryLabel: 'Advisory Board',
    image: '/img/about/Screenshot-2026-05-11-at-13.15.21.png',
    facultyOrDivision: 'Advisory Board',
  },
  {
    id: 'dzahir-bahari',
    name: 'Prof. Mohd Dzahir Bin Bahari, Ph.D.',
    role: 'Member of Advisory Board',
    category: 'advisory',
    categoryLabel: 'Advisory Board',
    image: '/img/about/Screenshot-2026-05-11-at-11.40.56.png',
    facultyOrDivision: 'Advisory Board',
  },
  {
    id: 'agus-setiadji',
    name: 'Prof. Dr. Agus Setiadji, SAP., MA',
    role: 'Member of Advisory Board',
    category: 'advisory',
    categoryLabel: 'Advisory Board',
    image: '/img/about/Screenshot-2026-05-11-at-11.43.52.png',
    facultyOrDivision: 'Advisory Board',
  },
  {
    id: 'ariawan-gunadi',
    name: 'Prof. Dr. Ariawan Gunadi, SH., MH',
    role: 'Member of Advisory Board',
    category: 'advisory',
    categoryLabel: 'Advisory Board',
    image: '/img/about/wjkehvfkjhwvklcjew-.png',
    facultyOrDivision: 'Advisory Board',
  },

  // 3. BOARD OF CHANCELLOR (5 members)
  {
    id: 'manuel-freire',
    name: 'Prof. Dr. Sir Manuel Freire Garabal Y Nunez, LLB., MBA., Ph.D.',
    role: 'Chancellor',
    category: 'chancellor',
    categoryLabel: 'Board of Chancellor',
    image: '/img/about/Screenshot-2026-05-11-at-11.56.02.png',
    facultyOrDivision: 'Office of the Chancellor',
    isHead: true,
  },
  {
    id: 'taryono',
    name: 'Prof. Dr. H. Taryono, SE., SH., MBA., MSi., MHi.',
    role: 'Financial Director – VC II',
    category: 'chancellor',
    categoryLabel: 'Board of Chancellor',
    image: '/img/about/ksjefkfwe.png',
    facultyOrDivision: 'Vice Chancellor II / Finance',
  },
  {
    id: 'hasbullah-salleh',
    name: 'Prof. Dr. Hasbullah bin Mohd Salleh',
    role: 'Doctoral Program Study Director – VC III',
    category: 'chancellor',
    categoryLabel: 'Board of Chancellor',
    image: '/img/about/wkjebfckjbewjc.png',
    facultyOrDivision: 'Vice Chancellor III / Doctoral Studies',
  },
  {
    id: 'makin-kusuma',
    name: 'Prof. Dr. Makin Perdana Kusuma, SE., MM.',
    role: 'Development Director – VC V',
    category: 'chancellor',
    categoryLabel: 'Board of Chancellor',
    image: '/img/about/jhwvefkbwen.png',
    facultyOrDivision: 'Vice Chancellor V / Development',
  },
  {
    id: 'taufik-suhardiman',
    name: 'Prof. Taufik Suhardiman, S.Sos., MM., PhD., CPS., CCP.',
    role: 'Human Resources & Institutional Relations Director – VC IV',
    category: 'chancellor',
    categoryLabel: 'Board of Chancellor',
    image: '/img/about/uywevfiycuqiebc.png',
    facultyOrDivision: 'Vice Chancellor IV / HR & Institutional Relations',
  },

  // 4. BOARD OF DIRECTOR (4 members)
  {
    id: 'adji-husodo',
    name: 'Prof. Dr. dr. Adji Husodo, SH., M.Si.',
    role: 'Director',
    category: 'director',
    categoryLabel: 'Board of Director',
    image: '/img/about/prof-adji-hoesodo.png',
    facultyOrDivision: 'Board of Director',
    isHead: true,
  },
  {
    id: 'sunarno-wibowo',
    name: 'Prof. Dr. H. Sunarno Edy Wibowo, S.H., M.Hum.',
    role: 'Director',
    category: 'director',
    categoryLabel: 'Board of Director',
    image: '/img/about/Prof.Dr_.H.-Sunarno-Edy-Wibowo-S.H.-MHum.png',
    facultyOrDivision: 'Board of Director',
  },
  {
    id: 'rizki-adam',
    name: 'Prof. Rizki Adam, S.E., MBA., Ph.D.',
    role: 'Director',
    category: 'director',
    categoryLabel: 'Board of Director',
    image: '/img/about/Screenshot-2026-05-11-at-12.26.22.png',
    facultyOrDivision: 'Board of Director',
  },
  {
    id: 'muhammad-zakariya',
    name: 'Prof. Dr. Dato Hj Muhammad Bin Zakariya',
    role: 'Director',
    category: 'director',
    categoryLabel: 'Board of Director',
    image: '/img/about/Screenshot-2026-05-11-at-13.13.12.png',
    facultyOrDivision: 'Board of Director',
  },

  // 5. FACULTY DEAN'S BOARD (12 members)
  {
    id: 'erman-anom',
    name: 'Drs. Erman Anom, M.M., Ph.D.',
    role: 'Dean of Communication',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/IVADKBKBADW.png',
    facultyOrDivision: 'Faculty of Communication',
  },
  {
    id: 'lalu-pharmanegara',
    name: 'Dr. Lalu Pharmanegara, S.Pd., S.H., LL.M.',
    role: 'Dean of the Faculty of Humanities',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/KWQVEKLJR.png',
    facultyOrDivision: 'Faculty of Humanities',
  },
  {
    id: 'tri-agus-yuarsa',
    name: 'Prof. Dr. Dr. dr. Tri Agus Yuarsa, Sp.P(K)Onk, M.Kes, MARS, Ph.D.',
    role: 'Dean of the Faculty of Medicine and Health Science',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/IWEUBBFWEDOCS.png',
    facultyOrDivision: 'Faculty of Medicine and Health Science',
  },
  {
    id: 'teguh-budiharso',
    name: 'Prof. Dr. Dr. KPHAd. Teguh Budiharso, M.Pd, Ph.D., DMS, Ph.D.',
    role: 'Program Director of the Faculty of Education',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/KJEKLJVLKEQWV.png',
    facultyOrDivision: 'Faculty of Education',
  },
  {
    id: 'imroatus-solikhah',
    name: 'Prof. Dr. KRAy. Imroatus Solikhah, S.Pd., M.Pd., ME., DMS.',
    role: 'Dean of the Faculty of Languages and Linguistics',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/WIUEGUFEW.png',
    facultyOrDivision: 'Faculty of Languages and Linguistics',
  },
  {
    id: 'samuel-zacharias',
    name: 'Prof. Dr. dr. Samuel Zacharias, SpB., MA., MM., PhD.',
    role: 'Dean of the Faculty of Public Health',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/IQWVEFKFQKJEV.png',
    facultyOrDivision: 'Faculty of Public Health',
  },
  {
    id: 'fakhruddin-siswopranoto',
    name: 'Prof. Dr. Mokh Fakhruddin Siswopranoto, M.Pd.I., PhD.',
    role: 'Program Director of the Faculty of Islamic Studies',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/JHWVHHCJB-EC.png',
    facultyOrDivision: 'Faculty of Islamic Studies',
  },
  {
    id: 'thomas-kartomo',
    name: 'Prof. Dr. T.K. Thomas Kartomo, D.Min., DMS., D.Th., PhD.',
    role: 'Dean of the Faculty of Theology',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/LAWEBLKJVCJQWR.png',
    facultyOrDivision: 'Faculty of Theology',
  },
  {
    id: 'eddy-sumartono',
    name: 'Prof. Dr. Capt. Eddy Sumartono, S.SIT., SH., LL.M., M.MAR., MBA., DBA., Ph.D.',
    role: 'Dean of the Faculty of Law',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/kauysdfjaff.png',
    facultyOrDivision: 'Faculty of Law',
  },
  {
    id: 'mastur-thoyib',
    name: 'Prof. Dr. Mastur Thoyib, S.Pd., M.Pd., MM.',
    role: 'Dean of the Faculty of Islamic Studies',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/iuodsoooi.png',
    facultyOrDivision: 'Faculty of Islamic Studies',
  },
  {
    id: 'werdhi-sutisari',
    name: 'Prof. Dr. WERDHI SUTISARI, S.H., M.H., Ph.D.',
    role: 'Dean of the Faculty of Philosophy',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/wkee.png',
    facultyOrDivision: 'Faculty of Philosophy',
  },
  {
    id: 'datep-purwa-saputra',
    name: 'Prof. Dr. H. DATEP PURWA SAPUTRA, S.Sos., M.M., MBA',
    role: 'Dean of the Faculty of Economic',
    category: 'deans',
    categoryLabel: "Faculty Dean's Board",
    image: '/img/about/Screenshot-2026-05-29-at-13.39.13.png',
    facultyOrDivision: 'Faculty of Economic',
  },
];
