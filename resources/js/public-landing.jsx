import React, { startTransition, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import './page-loader.js';
import './auth-notification.js';
import '../css/public-landing.css';
import '../css/page-loader.css';

const translations = {
	id: {
		navDashboard: 'Dashboard',
		navFaculties: 'Fakultas',
		navPrograms: 'Program Studi',
		openMenu: 'Buka menu',
		closeMenu: 'Tutup menu',
		login: 'Login SIAKAD',
		loginTitle: 'Masuk ke SIAKAD',
		loginIntro: 'Gunakan username, NIM, NIDN, atau kode asisten Anda.',
		loginIdentity: 'Identitas akun',
		loginPassword: 'Kata sandi',
		loginSubmit: 'Masuk',
		showPassword: 'Tampilkan kata sandi',
		hidePassword: 'Sembunyikan kata sandi',
		loginHelp: 'Gunakan akun SIAKAD yang telah diberikan oleh pengelola.',
		forgotPassword: 'Lupa password?',
		resetTitle: 'Reset password',
		resetIntro: 'Masukkan username, NIM, NIDN, atau kode asisten. Jika akun memiliki email terdaftar, instruksi reset akan dikirim.',
		resetSubmit: 'Reset password',
		resetIdentity: 'Username, NIM, NIDN, Kode Asdos/Email',
		backToLogin: 'Kembali ke login',
		closeReset: 'Tutup dialog reset password',
		closeLogin: 'Tutup dialog login',
		tagline: 'Motto Perguruan Tinggi',
		heroEyebrow: 'SIAKAD · Sistem Informasi Akademik',
		heroTitle: 'Motto Perguruan Tinggi',
		heroDescription: '',
		explore: 'Jelajahi dashboard',
		exploreFaculties: 'Lihat fakultas',
		university: 'Dashboard Universitas',
		faculties: 'Dashboard Fakultas',
		programs: 'Dashboard Program Studi',
		faculty: 'Dashboard fakultas',
		program: 'Dashboard program studi',
		back: 'Kembali',
		snapshot: 'Ringkasan akademik',
		programCount: 'Program studi',
		students: 'Mahasiswa',
		studentStatusScope: 'Status A, N, C, dan M',
		lecturers: 'Dosen',
		dtps: 'DTPS',
		dtpsTitle: 'Sebaran DTPS per program studi',
		dtpsCopy: 'Dosen unik yang tercantum pada jadwal mengajar seluruh semester, dikelompokkan menurut homebase.',
		dtpsHomebase: 'Homebase',
		dtpsNonHomebase: 'Non-homebase',
		dtpsTotal: 'Total DTPS',
		dtpsDetails: 'Rincian DTPS',
		dtpsName: 'Nama dosen',
		dtpsCourses: 'Mata kuliah (tahun terakhir mengajar)',
		dtpsLatest: 'Tahun akademik terakhir',
		dtpsLoading: 'Memuat rincian dosen…',
		dtpsEmpty: 'Tidak ada dosen pada kategori ini.',
		dtpsAll: 'Semua kategori',
		closeDtps: 'Tutup rincian DTPS',
		courses: 'Mata kuliah',
		classes: 'Kelas kuliah',
		krs: 'Data KRS',
		proposals: 'Usulan judul',
		theses: 'Skripsi aktif',
		exams: 'Ujian skripsi',
		unitChart: 'Sebaran data per unit',
		unitCopy: 'Perbandingan mahasiswa, dosen, dan mata kuliah pada cakupan ini.',
		moduleChart: 'Aktivitas modul akademik',
		moduleCopy: 'Jumlah data agregat di layanan akademik, E-Skripsi, dan E-Kurikulum.',
		statusChartTooltip: 'Grafik membandingkan jumlah data untuk setiap status yang tercatat.',
		thesisChart: 'Tahapan E-Skripsi',
		proposalStatus: 'Status usulan judul',
		thesisStatus: 'Status skripsi',
		intakeChart: 'Tren mahasiswa per angkatan',
		intakeCopy: 'Jumlah data mahasiswa berdasarkan tahun pada empat digit awal periode masuk, tanpa filter status mahasiswa.',
		intakeTotal: 'Seluruh mahasiswa',
		intakeTooltipHint: 'Arahkan kursor atau fokuskan titik pada grafik untuk melihat jumlah mahasiswa per tahun.',
		intakeCount: 'mahasiswa',
		studentMap: 'Peta sebaran mahasiswa',
		studentMapCopy: 'Terpetakan {mapped} dari {total} mahasiswa status A, N, C, dan M; {missing} belum memiliki data provinsi.',
		mapNoData: 'Belum ada data wilayah mahasiswa untuk ditampilkan.',
		mapStudents: 'mahasiswa',
		status: 'Status',
		records: 'data',
		openDashboard: 'Buka dashboard',
		noData: 'Belum ada data untuk divisualisasikan.',
		visitors: 'Kunjungan situs',
		visitorDescription: 'Statistik dan tren kunjungan browser ke portal SIAKAD.',
		visitorsToday: 'Hari ini',
		visitorsMonth: 'Bulan ini',
		visitorsYear: 'Tahun ini',
		visitorsTotal: 'Browser unik',
		visitorTodayDetail: 'Browser unik hari ini',
		visitorMonthDetail: 'Browser unik bulan berjalan',
		visitorYearDetail: 'Browser unik tahun berjalan',
		visitorTotalDetail: 'Browser unik sejak pencatatan',
		visitorTrend: 'Grafik analitis trafik kunjungan',
		visitorTrendCopy: 'Pilih periode untuk melihat tren browser unik.',
		visitorDaily: 'Harian (7 hari)',
		visitorMonthly: 'Bulanan (12 bulan)',
		visitorYearly: 'Tahunan (5 tahun)',
		uniqueVisitors: 'browser unik',
		loadingDashboard: 'Memuat ringkasan akademik…',
		photoLabel: 'Suasana akademik perguruan tinggi',
	},
	en: {
		navDashboard: 'Dashboard',
		navFaculties: 'Faculties',
		navPrograms: 'Study Programs',
		openMenu: 'Open menu',
		closeMenu: 'Close menu',
		login: 'SIAKAD Login',
		loginTitle: 'Sign in to SIAKAD',
		loginIntro: 'Use your username, student ID, lecturer ID, or assistant code.',
		loginIdentity: 'Account identity',
		loginPassword: 'Password',
		loginSubmit: 'Sign in',
		showPassword: 'Show password',
		hidePassword: 'Hide password',
		loginHelp: 'Use the SIAKAD account provided by your administrator.',
		forgotPassword: 'Forgot password?',
		resetTitle: 'Reset password',
		resetIntro: 'Enter your username, student ID, lecturer ID, or assistant code. If the account has a registered email, reset instructions will be sent.',
		resetSubmit: 'Reset password',
		resetIdentity: 'Username, student ID, lecturer ID, assistant code/email',
		backToLogin: 'Back to login',
		closeReset: 'Close password reset dialog',
		closeLogin: 'Close login dialog',
		tagline: 'Motto Perguruan Tinggi',
		heroEyebrow: 'SIAKAD · Academic Information System',
		heroTitle: 'Motto Perguruan Tinggi',
		heroDescription: '',
		explore: 'Explore dashboard',
		exploreFaculties: 'Explore faculties',
		university: 'University Dashboard',
		faculties: 'Faculty Dashboard',
		programs: 'Study Program Dashboard',
		faculty: 'Faculty dashboard',
		program: 'Study program dashboard',
		back: 'Back',
		snapshot: 'Academic overview',
		programCount: 'Study programs',
		students: 'Students',
		studentStatusScope: 'Statuses A, N, C, and M',
		lecturers: 'Lecturers',
		dtps: 'Study-program teaching staff',
		dtpsTitle: 'Teaching staff by study program',
		dtpsCopy: 'Unique lecturers listed in teaching schedules across all semesters, grouped by home study program.',
		dtpsHomebase: 'Homebase',
		dtpsNonHomebase: 'Non-homebase',
		dtpsTotal: 'Total teaching staff',
		dtpsDetails: 'Teaching staff details',
		dtpsName: 'Lecturer name',
		dtpsCourses: 'Courses (latest teaching year)',
		dtpsLatest: 'Latest academic year',
		dtpsLoading: 'Loading lecturer details…',
		dtpsEmpty: 'No lecturers in this category.',
		dtpsAll: 'All categories',
		closeDtps: 'Close teaching staff details',
		courses: 'Courses',
		classes: 'Class schedules',
		krs: 'Course registrations',
		proposals: 'Thesis proposals',
		theses: 'Active theses',
		exams: 'Thesis exams',
		unitChart: 'Data by academic unit',
		unitCopy: 'Compare students, lecturers, and courses within this scope.',
		moduleChart: 'Academic module activity',
		moduleCopy: 'Aggregated records across academic services, E-Thesis, and E-Curriculum.',
		statusChartTooltip: 'This chart compares the number of records for each available status.',
		thesisChart: 'E-Thesis workflow',
		proposalStatus: 'Thesis proposal status',
		thesisStatus: 'Thesis status',
		intakeChart: 'Student intake by cohort',
		intakeCopy: 'Student records grouped by the first four digits of the entry period, regardless of student status.',
		intakeTotal: 'All students',
		intakeTooltipHint: 'Hover over or focus a chart point to see the student count for that year.',
		intakeCount: 'students',
		studentMap: 'Student distribution map',
		studentMapCopy: 'Mapped {mapped} of {total} students with statuses A, N, C, and M; {missing} have no province data.',
		mapNoData: 'There is no student region data to display yet.',
		mapStudents: 'students',
		status: 'Status',
		records: 'records',
		openDashboard: 'Open dashboard',
		noData: 'There is no data to visualize yet.',
		visitors: 'Site visits',
		visitorDescription: 'Statistics and browser visit trends for the SIAKAD portal.',
		visitorsToday: 'Today',
		visitorsMonth: 'This month',
		visitorsYear: 'This year',
		visitorsTotal: 'Unique browsers',
		visitorTodayDetail: 'Unique browsers today',
		visitorMonthDetail: 'Unique browsers this month',
		visitorYearDetail: 'Unique browsers this year',
		visitorTotalDetail: 'Unique browsers since tracking began',
		visitorTrend: 'Visitor traffic analytics',
		visitorTrendCopy: 'Choose a period to view unique browser trends.',
		visitorDaily: 'Daily (7 days)',
		visitorMonthly: 'Monthly (12 months)',
		visitorYearly: 'Yearly (5 years)',
		uniqueVisitors: 'unique browsers',
		loadingDashboard: 'Loading academic overview…',
		photoLabel: 'Academic life at the university',
	},
};

const slides = [
	{ image: '/images/landing/hero-01.jpg', title: 'Belajar dan bertumbuh bersama', subtitle: 'Ruang akademik untuk gagasan yang berdampak.' },
	{ image: '/images/landing/hero-02.jpg', title: 'Pengetahuan membuka kemungkinan', subtitle: 'Mendukung perjalanan belajar dan riset.' },
	{ image: '/images/landing/hero-03.jpg', title: 'Kolaborasi membangun masa depan', subtitle: 'Tumbuh sebagai komunitas akademik yang inklusif.' },
	{ image: '/images/landing/hero-04.jpg', title: 'Merayakan capaian, menatap tantangan', subtitle: 'Setiap langkah menjadi bekal untuk berkarya.' },
	{ image: '/images/landing/hero-05.jpg', title: 'Belajar dari ruang ke ruang', subtitle: 'Pendidikan bermutu dimulai dari rasa ingin tahu.' },
	{ image: '/images/landing/hero-06.jpg', title: 'Riset dan ilmu untuk masyarakat', subtitle: 'Menghubungkan pengetahuan dengan kebutuhan sekitar.' },
	{ image: '/images/landing/hero-07.jpg', title: 'Maju bersama dengan ilmu', subtitle: 'Tumbuh bersama melalui pendidikan tinggi.' },
];

const metricKeys = ['programs', 'students', 'lecturers', 'courses', 'classes', 'krs', 'proposals', 'theses', 'exams'];
const unitMetricKeys = ['students', 'lecturers', 'courses'];
const palette = ['#bb8a3b', '#427762', '#5281a0', '#9b6957', '#7788a5', '#a78b48', '#588c79', '#806e9a', '#b4745a', '#467c8e'];
const proposalLabels = { 0: { id: 'Sedang diverifikasi', en: 'Under review' }, 1: { id: 'Disetujui', en: 'Approved' }, 2: { id: 'Ditolak / revisi', en: 'Rejected / revision' } };
const thesisLabels = {
	0: { id: 'Menunggu penugasan', en: 'Awaiting assignment' },
	1: { id: 'Bimbingan proposal', en: 'Proposal supervision' },
	2: { id: 'Ujian proposal', en: 'Proposal exam' },
	3: { id: 'Bimbingan hasil', en: 'Results supervision' },
	4: { id: 'Ujian hasil', en: 'Results exam' },
	5: { id: 'Ujian skripsi', en: 'Thesis exam' },
	6: { id: 'Selesai bimbingan', en: 'Supervision complete' },
};

function formatNumber(value) {
	return new Intl.NumberFormat().format(Number(value || 0));
}

function formatVisitorLabel(label, period, language, full = false) {
	if (period === 'yearly') return label;
	const date = new Date(period === 'monthly' ? `${label}-01T00:00:00` : `${label}T00:00:00`);
	if (Number.isNaN(date.getTime())) return label;
	const locale = language === 'id' ? 'id-ID' : 'en-GB';
	const options = period === 'daily'
		? (full ? { weekday: 'long', day: 'numeric', month: 'long' } : { weekday: 'short' })
		: (full ? { month: 'long', year: 'numeric' } : { month: 'short', year: '2-digit' });
	return new Intl.DateTimeFormat(locale, options).format(date);
}

function LanguageSwitcher({ language, setLanguage }) {
	return <div className="language-switch" role="group" aria-label="Choose language">
		<button type="button" className={language === 'id' ? 'is-active' : ''} aria-label="Bahasa Indonesia" aria-pressed={language === 'id'} onClick={() => setLanguage('id')}><span aria-hidden="true">🇮🇩</span><span>ID</span></button>
		<button type="button" className={language === 'en' ? 'is-active' : ''} aria-label="English" aria-pressed={language === 'en'} onClick={() => setLanguage('en')}><span aria-hidden="true">🇬🇧</span><span>EN</span></button>
	</div>;
}

function Header({ data, language, setLanguage, pageType, onLogin, loginTriggerRef }) {
	const text = translations[language];
	const isHome = pageType === 'home';
	const [menuOpen, setMenuOpen] = useState(false);

	useEffect(() => {
		if (!menuOpen) return undefined;
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') setMenuOpen(false);
		};
		document.addEventListener('keydown', closeOnEscape);
		return () => document.removeEventListener('keydown', closeOnEscape);
	}, [menuOpen]);

	return <header className="public-header">
		<a className="public-brand" href={data.homeUrl} aria-label="SIAKAD home">
			<img src={data.logoUrl} alt={data.institutionName} />
			<span><strong>{data.appName}</strong><small>{data.appFullName}</small></span>
		</a>
		<nav id="public-nav" className={`public-nav${menuOpen ? ' is-open' : ''}`} aria-label={language === 'id' ? 'Navigasi utama' : 'Main navigation'}>
			<a href={data.homeUrl} aria-current={isHome ? 'page' : undefined} onClick={() => setMenuOpen(false)}>{text.navDashboard}</a>
			<a href={data.facultiesUrl} aria-current={pageType === 'faculties' || pageType === 'faculty' ? 'page' : undefined} onClick={() => setMenuOpen(false)}>{text.navFaculties}</a>
			<a href={data.programsUrl} aria-current={pageType === 'programs' || pageType === 'program' ? 'page' : undefined} onClick={() => setMenuOpen(false)}>{text.navPrograms}</a>
		</nav>
		<div className="public-header-actions">
			<LanguageSwitcher language={language} setLanguage={setLanguage} />
			<button className="public-menu-toggle" type="button" aria-label={menuOpen ? text.closeMenu : text.openMenu} aria-expanded={menuOpen} aria-controls="public-nav" onClick={() => setMenuOpen((open) => !open)}>
				<span /><span /><span />
			</button>
			<button ref={loginTriggerRef} className="login-button" type="button" onClick={onLogin}>{data.authenticated ? text.openDashboard : text.login}<span aria-hidden="true">↗</span></button>
		</div>
	</header>;
}

function LoginModal({ data, language, onClose, onForgot }) {
	const [passwordVisible, setPasswordVisible] = useState(false);
	const identityInputRef = useRef(null);
	const modalRef = useRef(null);
	const text = translations[language];
	const loginError = data.loginErrors?.login?.[0];
	const passwordError = data.loginErrors?.password?.[0];

	useEffect(() => {
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') {
				onClose();
				return;
			}
			if (event.key !== 'Tab') return;
			const focusable = [...modalRef.current.querySelectorAll('button, a[href], input')].filter((element) => !element.disabled);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		};
		document.addEventListener('keydown', closeOnEscape);
		document.body.classList.add('login-modal-open');
		identityInputRef.current?.focus();
		return () => {
			document.removeEventListener('keydown', closeOnEscape);
			document.body.classList.remove('login-modal-open');
		};
	}, [onClose]);

	return <div className="login-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
		<section ref={modalRef} className="login-modal" role="dialog" aria-modal="true" aria-labelledby="login-modal-title">
			<button className="login-modal-close" type="button" aria-label={text.closeLogin} onClick={onClose}>×</button>
			<div className="login-modal-brand">
				<img src={data.logoUrl} alt="" />
				<span><strong>{data.appName}</strong><small>{data.appFullName}</small></span>
			</div>
			<h2 id="login-modal-title">{text.loginTitle}</h2>
			<p className="login-modal-copy">{text.loginIntro}</p>
			<form method="POST" action={data.loginPostUrl}>
				<input type="hidden" name="_token" value={data.csrfToken} />
				<label htmlFor="public-login">{text.loginIdentity}</label>
				<input ref={identityInputRef} id="public-login" name="login" type="text" defaultValue={data.loginValue} autoComplete="username" maxLength="100" required aria-describedby={loginError ? 'public-login-error' : undefined} />
				{loginError && <p className="login-modal-error" id="public-login-error" role="alert">{loginError}</p>}
				<label htmlFor="public-password">{text.loginPassword}</label>
				<div className="login-password-field">
					<input id="public-password" name="password" type={passwordVisible ? 'text' : 'password'} autoComplete="current-password" maxLength="255" required aria-describedby={passwordError ? 'public-password-error' : undefined} />
					<button className="password-visibility" type="button" aria-label={passwordVisible ? text.hidePassword : text.showPassword} aria-pressed={passwordVisible} onClick={() => setPasswordVisible((visible) => !visible)}>
						<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
							{passwordVisible ? <><path d="M3 3l18 18" /><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" /><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.2 0 9 4.6 10 7-.4 1-1.3 2.2-2.6 3.3" /><path d="M6.2 6.2C3.9 7.7 2.5 9.7 2 12c1 2.4 4.8 7 10 7 1.3 0 2.5-.3 3.6-.8" /></>
								: <><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></>}
						</svg>
					</button>
				</div>
				{passwordError && <p className="login-modal-error" id="public-password-error" role="alert">{passwordError}</p>}
				<button className="login-modal-submit" type="submit">{text.loginSubmit}<span aria-hidden="true">↗</span></button>
			</form>
			<p className="login-modal-help">{text.loginHelp}</p>
			<button className="login-modal-forgot" type="button" onClick={onForgot}>{text.forgotPassword}</button>
			<p className="login-modal-copyright">© {new Date().getFullYear()} {data.institutionName} · SIAKAD</p>
		</section>
	</div>;
}

function ForgotPasswordModal({ data, language, onClose, onLogin }) {
	const identityInputRef = useRef(null);
	const modalRef = useRef(null);
	const text = translations[language];
	const resetError = data.resetErrors?.login?.[0];

	useEffect(() => {
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') {
				onClose();
				return;
			}
			if (event.key !== 'Tab') return;
			const focusable = [...modalRef.current.querySelectorAll('button, a[href], input')].filter((element) => !element.disabled);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		};
		document.addEventListener('keydown', closeOnEscape);
		document.body.classList.add('login-modal-open');
		identityInputRef.current?.focus();
		return () => {
			document.removeEventListener('keydown', closeOnEscape);
			document.body.classList.remove('login-modal-open');
		};
	}, [onClose]);

	return <div className="login-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
		<section ref={modalRef} className="login-modal" role="dialog" aria-modal="true" aria-labelledby="reset-modal-title">
			<button className="login-modal-close" type="button" aria-label={text.closeReset} onClick={onClose}>×</button>
			<div className="login-modal-brand">
				<img src={data.logoUrl} alt="" />
				<span><strong>{data.appName}</strong><small>{data.appFullName}</small></span>
			</div>
			<h2 id="reset-modal-title">{text.resetTitle}</h2>
			<p className="login-modal-copy">{text.resetIntro}</p>
			{data.resetPasswordStatus && <p className="login-modal-status" role="status">{data.resetPasswordStatus}</p>}
			<form method="POST" action={data.resetPasswordUrl}>
				<input type="hidden" name="_token" value={data.csrfToken} />
				<label htmlFor="reset-login">{text.resetIdentity}</label>
				<input ref={identityInputRef} id="reset-login" name="login" type="text" defaultValue={data.resetLoginValue} autoComplete="username" maxLength="100" required aria-describedby={resetError ? 'reset-login-error' : undefined} />
				{resetError && <p className="login-modal-error" id="reset-login-error" role="alert">{resetError}</p>}
				<button className="login-modal-submit" type="submit">{text.resetSubmit}<span aria-hidden="true">↗</span></button>
			</form>
			<button className="login-modal-forgot" type="button" onClick={onLogin}>{text.backToLogin}</button>
			<p className="login-modal-copyright">© {new Date().getFullYear()} {data.institutionName} · SIAKAD</p>
		</section>
	</div>;
}

function Hero({ language, pageType, heroImages, motto }) {
	const [activeSlide, setActiveSlide] = useState(0);
	const [paused, setPaused] = useState(false);
	const text = translations[language];
	const activeSlides = slides.map((slide, index) => ({ ...slide, image: heroImages?.[index] || slide.image }));

	useEffect(() => {
		if (paused) return undefined;
		const timer = window.setInterval(() => setActiveSlide((current) => (current + 1) % activeSlides.length), 6000);
		return () => window.clearInterval(timer);
	}, [paused]);

	const move = (offset) => setActiveSlide((current) => (current + offset + activeSlides.length) % activeSlides.length);
	return <section className="hero" aria-label={text.photoLabel} onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}>
		{activeSlides.map((slide, index) => <div className={`hero-slide ${index === activeSlide ? 'is-active' : ''}`} key={slide.image} aria-hidden={index !== activeSlide} style={index === activeSlide ? { backgroundImage: `url("${slide.image}")` } : undefined} />)}
		<div className="hero-shade" />
		<div className="hero-copy" key={`${activeSlide}-${language}`}>
			<p className="hero-eyebrow"><span />{text.heroEyebrow}</p>
			<h1>{pageType === 'home' ? motto : translations[language][pageType] || text.heroTitle}</h1>
			{(pageType === 'home' ? text.heroDescription : dataScopeName(pageType, language)) && <p className="hero-description">{pageType === 'home' ? text.heroDescription : dataScopeName(pageType, language)}</p>}
			{pageType === 'home' && <div className="hero-actions"><a className="hero-primary" href="#dashboard">{text.explore}<span aria-hidden="true">↓</span></a><a className="hero-secondary" href="/fakultas">{text.exploreFaculties}</a></div>}
		</div>
		<div className="hero-controls">
			<button className="hero-arrow" type="button" onClick={() => move(-1)} aria-label={language === 'id' ? 'Foto sebelumnya' : 'Previous photo'}>‹</button>
			<div className="hero-dots" role="tablist" aria-label={language === 'id' ? 'Pilih foto' : 'Choose a photo'}>
				{activeSlides.map((slide, index) => <button key={slide.image} type="button" role="tab" aria-label={`${language === 'id' ? 'Foto' : 'Photo'} ${index + 1}`} aria-selected={index === activeSlide} onClick={() => setActiveSlide(index)} />)}
			</div>
			<button className="hero-arrow" type="button" onClick={() => move(1)} aria-label={language === 'id' ? 'Foto berikutnya' : 'Next photo'}>›</button>
		</div>
		<span className="hero-counter">{String(activeSlide + 1).padStart(2, '0')} <i>/</i> {String(activeSlides.length).padStart(2, '0')}</span>
	</section>;
}

function dataScopeName(pageType, language) {
	return pageType === 'faculty' ? (language === 'id' ? 'data fakultas' : 'faculty data')
		: pageType === 'program' ? (language === 'id' ? 'data program studi' : 'study program data')
			: (language === 'id' ? 'seluruh unit akademik' : 'all academic units');
}

function MetricCards({ data, language }) {
	const text = translations[language];
	return <div className="metric-grid">
		{data.metrics.map(({ key, value }, index) => <article className="metric-card reveal" key={key} data-reveal style={{ '--metric-color': palette[index] }}>
			<span className="metric-icon" aria-hidden="true">{['⌂', '◉', '✳', '▤', '▦', '▧', '⌁', '◈', '▣', '◫'][index]}</span>
			<span className="metric-label">{text[key === 'programs' ? 'programCount' : key]}</span>
			<strong className="metric-value">{formatNumber(value)}</strong>
			{key === 'students' && <small className="metric-detail">{text.studentStatusScope}</small>}
			<span className="metric-accent" />
		</article>)}
	</div>;
}

function UnitChart({ rows, pageType, language }) {
	const text = translations[language];
	const metricKey = pageType === 'program' ? null : 'students';
	const sortedRows = useMemo(() => [...rows].sort((a, b) => Number(b[metricKey || 'students']) - Number(a[metricKey || 'students'])), [rows, metricKey]);
	const maxValue = Math.max(1, ...sortedRows.flatMap((row) => unitMetricKeys.map((key) => Number(row[key] || 0))));

	const chartDetails = sortedRows.map((row) => `${row.name}: ${unitMetricKeys.map((key) => `${text[key]} ${formatNumber(row[key])}`).join(' · ')}`).join('\n');

	return <section className="chart-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.unitCopy}>
		<div className="panel-heading"><div><span className="section-kicker">{language === 'id' ? 'PERBANDINGAN' : 'COMPARISON'}</span><h2>{text.unitChart}</h2><p>{text.unitCopy}</p></div><span className="panel-icon" aria-hidden="true">⌁</span></div>
		<div className="chart-legend">{unitMetricKeys.map((key, index) => <span key={key}><i style={{ '--legend-color': palette[index] }} />{text[key]}</span>)}</div>
		{sortedRows.length ? <div className="unit-chart-list" role="region" aria-label={text.unitChart} tabIndex="0">
			{sortedRows.map((row) => <a className="unit-chart-row reveal" data-reveal href={row.url} key={row.id}>
				<span className="unit-chart-name"><strong>{row.name}</strong><small>{row.faculty || (row.programCount !== undefined ? `${formatNumber(row.programCount)} ${text.programCount.toLowerCase()}` : '')}</small></span>
				<div className="unit-chart-bars">
					{unitMetricKeys.map((key, index) => <span className="unit-chart-track" key={key} title={`${row.name} · ${text[key]}: ${formatNumber(row[key])}`} aria-label={`${row.name} · ${text[key]}: ${formatNumber(row[key])}`}><i style={{ width: `${Math.max(row[key] ? 1.5 : 0, Number(row[key] || 0) / maxValue * 100)}%`, backgroundColor: palette[index] }} /></span>)}
				</div>
				<strong className="unit-chart-value">{formatNumber(row.students)}</strong>
				<span className="unit-chart-open" aria-hidden="true">↗</span>
			</a>)}
		</div> : <p className="empty-state">{text.noData}</p>}
	</section>;
}

function DtpsDistribution({ rows, language, onOpen }) {
	const text = translations[language];
	const sortedRows = [...rows].sort((a, b) => Number(b.dtps || 0) - Number(a.dtps || 0) || a.name.localeCompare(b.name));
	const chartDetails = sortedRows.map((row) => `${row.name}: ${text.dtpsTotal} ${formatNumber(row.dtps)} · ${text.dtpsHomebase} ${formatNumber(row.dtpsHomebase)} · ${text.dtpsNonHomebase} ${formatNumber(row.dtpsNonHomebase)}`).join('\n');

	return <section className="chart-panel reveal" data-reveal aria-labelledby="dtps-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.dtpsCopy}>
		<div className="panel-heading"><div><span className="section-kicker">{text.dtps}</span><h2 id="dtps-heading">{text.dtpsTitle}</h2><p>{text.dtpsCopy}</p></div><span className="panel-icon" aria-hidden="true">♙</span></div>
		{sortedRows.length ? <div className="dtps-table-scroll">
			<table className="dtps-table">
				<thead><tr><th>{text.navPrograms}</th><th>{text.dtpsTotal}</th><th>{text.dtpsHomebase}</th><th>{text.dtpsNonHomebase}</th></tr></thead>
				<tbody>{sortedRows.map((row) => <tr key={row.id}>
					<td><a href={row.url}>{row.name}</a><small>{row.faculty}</small></td>
					<td><button className="dtps-detail-trigger" type="button" onClick={(event) => onOpen(row, 'all', event)}>{formatNumber(row.dtps)}</button></td>
					<td><button className="dtps-detail-trigger" type="button" onClick={(event) => onOpen(row, 'homebase', event)}>{formatNumber(row.dtpsHomebase)}</button></td>
					<td><button className="dtps-detail-trigger" type="button" onClick={(event) => onOpen(row, 'non_homebase', event)}>{formatNumber(row.dtpsNonHomebase)}</button></td>
				</tr>)}</tbody>
			</table>
		</div> : <p className="empty-state">{text.noData}</p>}
	</section>;
}

function DtpsDetailsDialog({ selection, details, loading, error, language, onClose }) {
	if (!selection) return null;
	const text = translations[language];
	const category = selection.category === 'homebase' ? text.dtpsHomebase
		: selection.category === 'non_homebase' ? text.dtpsNonHomebase : text.dtpsAll;

	return <div className="dtps-details-backdrop" onMouseDown={(event) => {
		if (event.target === event.currentTarget) onClose();
	}}>
		<section className="dtps-details-dialog" role="dialog" aria-modal="true" aria-labelledby="dtps-details-title" onKeyDown={(event) => {
			if (event.key === 'Tab') {
				event.preventDefault();
				event.currentTarget.querySelector('button')?.focus();
			}
		}}>
			<header>
				<div><span>{text.dtpsDetails} · {category}</span><h2 id="dtps-details-title">{selection.name}</h2></div>
				<button type="button" autoFocus onClick={onClose} aria-label={text.closeDtps}>×</button>
			</header>
			{loading ? <p className="dtps-details-message" role="status">{text.dtpsLoading}</p>
				: error ? <p className="dtps-details-error" role="alert">{error}</p>
					: !details?.lecturers?.length ? <p className="dtps-details-message">{text.dtpsEmpty}</p>
						: <div className="dtps-details-table-scroll"><table className="dtps-details-table">
							<thead><tr><th>{text.dtpsName}</th><th>{text.dtpsCourses}</th><th>{text.dtpsLatest}</th></tr></thead>
							<tbody>{details.lecturers.map((lecturer) => <tr key={lecturer.id}>
								<td>{lecturer.name || '—'}</td>
								<td>{lecturer.courses?.length ? <ul>{lecturer.courses.map((course) => <li key={`${course.name}-${course.academic_year}`}>{course.name} ({course.academic_year || '-'})</li>)}</ul> : '-'}</td>
								<td>{lecturer.latest_academic_year || '-'}</td>
							</tr>)}</tbody>
						</table></div>}
		</section>
	</div>;
}

function ModuleChart({ metrics, language }) {
	const text = translations[language];
	const maxValue = Math.max(1, ...metrics.map(({ value }) => Number(value || 0)));
	const chartDetails = metrics.map(({ key, value }) => `${text[key === 'programs' ? 'programCount' : key]}: ${formatNumber(value)}`).join('\n');
	return <section className="chart-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.moduleCopy}>
		<div className="panel-heading"><div><span className="section-kicker">{language === 'id' ? 'LAYANAN SIAKAD' : 'SIAKAD SERVICES'}</span><h2>{text.moduleChart}</h2><p>{text.moduleCopy}</p></div><span className="panel-icon" aria-hidden="true">▦</span></div>
		<div className="module-chart">
			{metrics.map(({ key, value }, index) => <div className="module-row" key={key}>
				<div className="module-label"><span>{text[key]}</span><strong>{formatNumber(value)}</strong></div>
				<div className="module-track" title={`${text[key]}: ${formatNumber(value)} ${text.records}`} aria-label={`${text[key]}: ${formatNumber(value)} ${text.records}`}><i style={{ width: `${Math.max(value ? 1.5 : 0, Number(value || 0) / maxValue * 100)}%`, backgroundColor: palette[(index + 2) % palette.length] }} /></div>
			</div>)}
		</div>
	</section>;
}

function StatusChart({ rows, title, language, labels = {} }) {
	const text = translations[language];
	const maxValue = Math.max(1, ...rows.map((row) => row.value));
	const chartDetails = rows.map((row) => {
		const translated = labels[row.label];
		const label = translated?.[language] || `${text.status} ${row.label}`;
		return `${label}: ${formatNumber(row.value)}`;
	}).join('\n');
	return <section className="chart-panel status-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.statusChartTooltip}>
		<div className="panel-heading"><div><span className="section-kicker">{text.status}</span><h2>{title}</h2></div><span className="panel-icon" aria-hidden="true">◌</span></div>
		{rows.length ? <div className="status-chart">
			{rows.map((row, index) => {
				const translated = labels[row.label];
				const label = translated?.[language] || `${text.status} ${row.label}`;
				return <div className="status-row" key={row.label}>
					<div className="status-label"><span>{label}</span><strong>{formatNumber(row.value)}</strong></div>
					<div className="status-track" title={`${label}: ${formatNumber(row.value)} ${text.records}`} aria-label={`${label}: ${formatNumber(row.value)} ${text.records}`}><i style={{ width: `${Math.max(row.value ? 2 : 0, row.value / maxValue * 100)}%`, backgroundColor: palette[index + 3] || palette[0] }} /></div>
				</div>;
			})}
		</div> : <p className="empty-state">{text.noData}</p>}
	</section>;
}

function VisitorTrendChart({ visitors, language }) {
	const [period, setPeriod] = useState('daily');
	const [activeIndex, setActiveIndex] = useState(null);
	const text = translations[language];
	const rows = visitors.series?.[period] || [];
	const width = Math.max(700, rows.length * 92);
	const height = 280;
	const padding = { top: 24, right: 24, bottom: 42, left: 48 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const maxValue = Math.max(1, ...rows.map((row) => Number(row.value || 0)));
	const step = maxValue <= 5 ? 1 : Math.pow(10, Math.floor(Math.log10(maxValue)));
	const upperBound = Math.max(step, Math.ceil(maxValue / step) * step);
	const ticks = Array.from({ length: 5 }, (_, index) => Math.round(upperBound * index / 4));
	const points = rows.map((row, index) => ({
		...row,
		x: padding.left + (rows.length <= 1 ? plotWidth / 2 : index / (rows.length - 1) * plotWidth),
		y: padding.top + (1 - Number(row.value || 0) / upperBound) * plotHeight,
	}));
	const linePath = points.map((point, index) => {
		if (index === 0) return `M ${point.x} ${point.y}`;
		const previous = points[index - 1];
		const control = (point.x - previous.x) / 2;
		return `C ${previous.x + control} ${previous.y}, ${point.x - control} ${point.y}, ${point.x} ${point.y}`;
	}).join(' ');
	const baseline = padding.top + plotHeight;
	const areaPath = points.length ? `${linePath} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z` : '';
	const activePoint = activeIndex === null ? null : points[activeIndex];
	const periodOptions = [
		['daily', text.visitorDaily],
		['monthly', text.visitorMonthly],
		['yearly', text.visitorYearly],
	];

	const chartDetails = rows.map((row) => `${formatVisitorLabel(row.label, period, language, true)}: ${formatNumber(row.value)} ${text.uniqueVisitors}`).join('\n');

	return <section className="visitor-trend chart-panel reveal" data-reveal aria-labelledby="visitor-trend-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.visitorTrendCopy}>
		<div className="visitor-trend-heading">
			<div>
				<span className="section-kicker">{language === 'id' ? 'ANALITIK PENGUNJUNG' : 'VISITOR ANALYTICS'}</span>
				<h3 id="visitor-trend-heading">{text.visitorTrend}</h3>
				<p>{text.visitorTrendCopy}</p>
			</div>
			<div className="visitor-periods" role="group" aria-label={text.visitorTrend}>
				{periodOptions.map(([key, label]) => <button className={period === key ? 'is-active' : ''} type="button" aria-pressed={period === key} key={key} onClick={() => { setPeriod(key); setActiveIndex(null); }}>{label}</button>)}
			</div>
		</div>
		<div className="visitor-chart-scroll">
			<div className="visitor-chart-plot">
				<svg className="visitor-chart-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`${text.visitorTrend} · ${periodOptions.find(([key]) => key === period)?.[1]}`}>
					<defs><linearGradient id="visitor-area-gradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#36569d" stopOpacity=".28" /><stop offset="100%" stopColor="#36569d" stopOpacity=".025" /></linearGradient></defs>
					{ticks.map((tick) => {
						const y = padding.top + (1 - tick / upperBound) * plotHeight;
						return <g key={tick}>
							<line x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="visitor-chart-grid" />
							<text x={padding.left - 10} y={y + 4} textAnchor="end" className="visitor-chart-axis-label">{formatNumber(tick)}</text>
						</g>;
					})}
					{areaPath && <path d={areaPath} fill="url(#visitor-area-gradient)" className="visitor-chart-area" />}
					{linePath && <path d={linePath} className="visitor-chart-line" />}
					{points.map((point, index) => <g key={point.label}>
						<line x1={point.x} y1={point.y} x2={point.x} y2={baseline} className={`visitor-chart-guide ${activeIndex === index ? 'is-active' : ''}`} />
						<circle cx={point.x} cy={point.y} r={activeIndex === index ? 6 : 4.5} className={`visitor-chart-point ${activeIndex === index ? 'is-active' : ''}`} tabIndex="0" role="img" aria-label={`${formatVisitorLabel(point.label, period, language, true)}: ${formatNumber(point.value)} ${text.uniqueVisitors}`} onMouseEnter={() => setActiveIndex(index)} onMouseLeave={() => setActiveIndex(null)} onFocus={() => setActiveIndex(index)} onBlur={() => setActiveIndex(null)} />
						<text x={point.x} y={height - 14} textAnchor="middle" className="visitor-chart-axis-label">{formatVisitorLabel(point.label, period, language)}</text>
					</g>)}
				</svg>
				{activePoint && <div className="visitor-chart-tooltip" style={{ left: `${activePoint.x / width * 100}%`, top: `${activePoint.y / height * 100}%` }}>
					<strong>{formatVisitorLabel(activePoint.label, period, language, true)}</strong>
					<span>{formatNumber(activePoint.value)} {text.uniqueVisitors}</span>
				</div>}
			</div>
		</div>
		<p className="visitor-chart-footnote">{language === 'id' ? 'Browser yang sama dihitung satu kali pada tiap periode. Penghapusan cookie atau penggunaan browser/perangkat lain dapat memengaruhi hitungan.' : 'The same browser is counted once per period. Clearing cookies or using another browser/device can affect the count.'}</p>
	</section>;
}

function StudentIntakeChart({ intake, language }) {
	const text = translations[language];
	const [activePoint, setActivePoint] = useState(null);
	const width = 760;
	const height = 280;
	const padding = { top: 24, right: 24, bottom: 42, left: 68 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const series = intake.series || [];
	const values = series.flatMap((line) => line.values.map(Number));
	const maxValue = Math.max(1, ...values);
	const upperBound = Math.ceil(maxValue / 5) * 5 || 1;
	const lines = series.map((line, lineIndex) => {
		const points = line.values.map((value, index) => ({
			x: padding.left + (intake.years.length === 1 ? plotWidth / 2 : index / (intake.years.length - 1) * plotWidth),
			y: padding.top + (1 - Number(value) / upperBound) * plotHeight,
			value: Number(value),
			year: intake.years[index],
			label: line.id === 'scope' ? text.intakeTotal : line.label,
			color: palette[lineIndex % palette.length],
			key: `${line.id}-${intake.years[index]}`,
		}));
		return { ...line, color: palette[lineIndex % palette.length], points };
	});
	const chartDetails = lines.map((line) => `${line.id === 'scope' ? text.intakeTotal : line.label}: ${line.points.map((point) => `${point.year} ${formatNumber(point.value)}`).join(' · ')}`).join('\n');
	const ticks = [0, upperBound / 4, upperBound / 2, upperBound * 3 / 4, upperBound];

	return <section className="chart-panel student-intake-panel reveal" data-reveal aria-labelledby="student-intake-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.intakeCopy}>
		<div className="panel-heading"><div><span className="section-kicker">{language === 'id' ? 'ANGKATAN' : 'COHORTS'}</span><h2 id="student-intake-heading">{text.intakeChart}</h2><p>{text.intakeCopy}</p></div><span className="panel-icon" aria-hidden="true">⌁</span></div>
		<div className="student-intake-legend" aria-label={text.intakeChart}>
			{lines.map((line) => <span key={line.id}><i style={{ '--legend-color': line.color }} />{line.id === 'scope' ? text.intakeTotal : line.label}</span>)}
		</div>
		<div className="student-intake-scroll">
			<div className="student-intake-plot">
				<svg className="student-intake-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label={text.intakeChart}>
					{ticks.map((tick) => {
						const y = padding.top + (1 - tick / upperBound) * plotHeight;
						return <g key={tick}>
							<line x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="visitor-chart-grid" />
							<text x={padding.left - 10} y={y + 4} textAnchor="end" className="visitor-chart-axis-label">{formatNumber(tick)}</text>
						</g>;
					})}
					{lines.map((line) => <g key={line.id}>
						<path d={line.points.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.y}`).join(' ')} className="student-intake-line" style={{ '--series-color': line.color }} />
						{line.points.map((point) => <g key={point.key}>
							<line x1={point.x} y1={point.y} x2={point.x} y2={height - padding.bottom} className={`visitor-chart-guide${activePoint?.key === point.key ? ' is-active' : ''}`} />
							<circle
								cx={point.x}
								cy={point.y}
								r={activePoint?.key === point.key ? 6 : 4.5}
								className="student-intake-point"
								style={{ '--series-color': point.color }}
								tabIndex="0"
								role="img"
								aria-label={`${point.label}, ${point.year}: ${formatNumber(point.value)} ${text.intakeCount}`}
								onMouseEnter={() => setActivePoint(point)}
								onMouseLeave={() => setActivePoint(null)}
								onFocus={() => setActivePoint(point)}
								onBlur={() => setActivePoint(null)}
							/>
						</g>)}
					</g>)}
					{intake.years.map((year, index) => <text key={year} x={padding.left + (intake.years.length === 1 ? plotWidth / 2 : index / (intake.years.length - 1) * plotWidth)} y={height - 14} textAnchor="middle" className="visitor-chart-axis-label">{year}</text>)}
				</svg>
				{activePoint && <div className="visitor-chart-tooltip student-intake-tooltip" style={{ left: `${activePoint.x / width * 100}%`, top: `${activePoint.y / height * 100}%` }}>
					<strong>{activePoint.label}</strong>
					<span>{activePoint.year}: {formatNumber(activePoint.value)} {text.intakeCount}</span>
				</div>}
			</div>
		</div>
		<p className="student-intake-hint">{text.intakeTooltipHint}</p>
	</section>;
}

function StudentDistributionMap({ rows, studentTotal, language }) {
	const text = translations[language];
	const mappedTotal = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);
	const mapSummary = text.studentMapCopy
		.replace('{mapped}', formatNumber(mappedTotal))
		.replace('{total}', formatNumber(studentTotal))
		.replace('{missing}', formatNumber(Math.max(0, studentTotal - mappedTotal)));
	const chartDetails = rows.map((row) => {
		const units = (row.units || []).map((unit) => `${unit.label}: ${formatNumber(unit.total)}`).join(' · ');
		return `${row.province}: ${formatNumber(row.total)} ${text.mapStudents}${units ? ` · ${units}` : ''}`;
	}).join('\n');
	const mapElement = useRef(null);
	const mapInstance = useRef(null);
	const markerLayer = useRef(null);
	const formatBreakdown = (row) => {
		const source = row.units ?? row.faculties ?? [];
		const entries = Array.isArray(source)
			? source
			: Object.entries(source).map(([key, value]) => (value && typeof value === 'object'
				? { ...value, key, label: value.label ?? value.name ?? key, total: value.total ?? value.count }
				: { key, label: key, total: value }));

		return entries.flatMap((entry) => {
			if (Array.isArray(entry)) {
				const [label, total] = entry;
				entry = { label, total };
			} else if (typeof entry === 'string') {
				return [];
			}

			const candidate = entry?.label ?? entry?.name ?? entry?.key;
			const label = typeof candidate === 'string' ? candidate.trim() : '';
			const total = Number(entry?.total ?? entry?.count);
			if (!label || !Number.isFinite(total) || total <= 0) return [];
			return [{ label, total }];
		});
	};

	useEffect(() => {
		const map = L.map(mapElement.current, { scrollWheelZoom: false }).setView([1.5709922, 127.8087685], 4);
		mapInstance.current = map;
		markerLayer.current = L.layerGroup().addTo(map);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 18,
		}).addTo(map);
		window.requestAnimationFrame(() => map.invalidateSize());
		return () => {
			map.remove();
			mapInstance.current = null;
			markerLayer.current = null;
		};
	}, []);

	useEffect(() => {
		const map = mapInstance.current;
		const layer = markerLayer.current;
		if (!map || !layer) return;
		layer.clearLayers();
		const validRows = rows.filter((row) => Number.isFinite(Number(row.latitude))
			&& Number.isFinite(Number(row.longitude))
			&& Number(row.latitude) !== 0
			&& Number(row.longitude) !== 0);
		const maxTotal = Math.max(1, ...validRows.map((row) => Number(row.total) || 0));
		const bounds = [];

		validRows.forEach((row) => {
			const latitude = Number(row.latitude);
			const longitude = Number(row.longitude);
			const total = Number(row.total) || 0;
			const marker = L.circleMarker([latitude, longitude], {
				radius: Math.min(24, Math.max(7, 8 + Math.sqrt(total / maxTotal) * 16)),
				color: '#fff',
				weight: 2,
				fillColor: '#1d795f',
				fillOpacity: 0.8,
			}).addTo(layer);
			const popup = document.createElement('div');
			const heading = document.createElement('strong');
			heading.textContent = row.province;
			const count = document.createElement('p');
			count.textContent = `${formatNumber(total)} ${text.mapStudents}`;
			popup.append(heading, count);
			const breakdown = formatBreakdown(row);
			breakdown.forEach(({ label, total: unitTotal }) => {
				const item = document.createElement('div');
				item.textContent = `${label}: ${formatNumber(unitTotal)}`;
				popup.append(item);
			});
			const tooltip = document.createElement('div');
			const tooltipHeading = document.createElement('strong');
			tooltipHeading.textContent = row.province;
			const tooltipCount = document.createElement('div');
			tooltipCount.textContent = `${formatNumber(total)} ${text.mapStudents}`;
			tooltip.append(tooltipHeading, tooltipCount);
			breakdown.forEach(({ label, total: unitTotal }) => {
				const item = document.createElement('div');
				item.textContent = `${label}: ${formatNumber(unitTotal)}`;
				tooltip.append(item);
			});
			marker.bindTooltip(tooltip, { direction: 'top', offset: [0, -8], opacity: 1 });
			marker.bindPopup(popup);
			bounds.push([latitude, longitude]);
		});

		if (bounds.length > 1) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 7 });
		else if (bounds.length === 1) map.setView(bounds[0], 5);
		else map.setView([1.5709922, 127.8087685], 4);
		window.requestAnimationFrame(() => map.invalidateSize());
	}, [rows, text.mapStudents]);

	return <section className="chart-panel student-map-panel reveal" data-reveal aria-labelledby="student-map-heading" data-chart-tooltip={chartDetails || text.mapNoData} aria-description={mapSummary}>
		<div className="panel-heading"><div><span className="section-kicker">{language === 'id' ? 'DOMISILI' : 'RESIDENCE'}</span><h2 id="student-map-heading">{text.studentMap}</h2><p>{mapSummary}</p></div><span className="panel-icon" aria-hidden="true">◎</span></div>
		<div ref={mapElement} className="student-distribution-map" role="application" aria-label={text.studentMap} />
		{rows.length === 0 && <p className="student-map-empty">{text.mapNoData}</p>}
	</section>;
}

function DashboardContent({ data, pageType, language }) {
	const text = translations[language];
	const dtpsTriggerRef = useRef(null);
	const [dtpsSelection, setDtpsSelection] = useState(null);
	const [dtpsDetails, setDtpsDetails] = useState(null);
	const [dtpsLoading, setDtpsLoading] = useState(false);
	const [dtpsError, setDtpsError] = useState('');
	useEffect(() => {
		const elements = [...document.querySelectorAll('[data-reveal]')];
		if (!('IntersectionObserver' in window)) {
			elements.forEach((element) => element.classList.add('is-visible'));
			return undefined;
		}
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => entry.target.classList.toggle('is-visible', entry.isIntersecting));
		}, { threshold: 0.12, rootMargin: '0px 0px -28px 0px' });
		elements.forEach((element, index) => {
			element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`);
			observer.observe(element);
		});
		return () => observer.disconnect();
	}, [data, pageType]);
	useEffect(() => {
		if (!dtpsSelection) return undefined;
		const abortController = new AbortController();
		const url = new URL(dtpsSelection.url, window.location.origin);
		url.searchParams.set('category', dtpsSelection.category);
		setDtpsDetails(null);
		setDtpsError('');
		setDtpsLoading(true);
		fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal: abortController.signal,
		})
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || `Rincian DTPS gagal dimuat (${response.status}).`);
				setDtpsDetails(payload);
			})
			.catch((requestError) => {
				if (requestError.name !== 'AbortError') setDtpsError(requestError.message);
			})
			.finally(() => {
				if (!abortController.signal.aborted) setDtpsLoading(false);
			});
		return () => abortController.abort();
	}, [dtpsSelection]);
	useEffect(() => {
		if (!dtpsSelection) return undefined;
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') setDtpsSelection(null);
		};
		document.addEventListener('keydown', closeOnEscape);
		return () => document.removeEventListener('keydown', closeOnEscape);
	}, [dtpsSelection]);
	const closeDtpsDetails = () => {
		setDtpsSelection(null);
		window.requestAnimationFrame(() => dtpsTriggerRef.current?.focus());
	};
	if (!data) return <main id="dashboard" className="landing-main"><div className="dashboard-loading" role="status" aria-live="polite">
		<span className="dashboard-loading-flash" aria-hidden="true"><i /><i /><i /><i /><i /></span>
		<strong>{text.loadingDashboard}</strong>
	</div></main>;
	const isUnitPage = ['faculty', 'program'].includes(pageType);
	const unitTitle = pageType === 'faculty' ? text.faculty : text.program;
	return <main id="dashboard" className="landing-main">
		<section className="overview-head reveal" data-reveal>
			<div>
				<span className="section-kicker">{text.snapshot}</span>
				<h2>{data.scopeName || text.university}</h2>
			</div>
			{data.parentUrl && <a className="back-link" href={data.parentUrl}>← {text.back} · {data.parentName}</a>}
		</section>
		<MetricCards data={data} language={language} />
		<div className="charts-grid">
			<UnitChart rows={data.unitRows} pageType={pageType} language={language} />
			<ModuleChart metrics={data.metrics} language={language} />
			<StatusChart rows={data.proposalStatuses} title={text.proposalStatus} labels={proposalLabels} language={language} />
			<StatusChart rows={data.thesisStatuses} title={text.thesisStatus} labels={thesisLabels} language={language} />
		</div>
		<DtpsDistribution
			rows={data.programRows || []}
			language={language}
			onOpen={(row, category, event) => {
				dtpsTriggerRef.current = event.currentTarget;
				setDtpsSelection({ url: row.dtpsDetailsUrl, name: row.name, category });
			}}
		/>
		<StudentDistributionMap rows={data.studentDistribution || []} studentTotal={Number(data.studentTotal) || 0} language={language} />
		{data.studentIntake?.available && <StudentIntakeChart intake={data.studentIntake} language={language} />}
		{isUnitPage && <p className="scope-caption">{unitTitle} · {data.scopeName}</p>}
		<DtpsDetailsDialog selection={dtpsSelection} details={dtpsDetails} loading={dtpsLoading} error={dtpsError} language={language} onClose={closeDtpsDetails} />
		<section className="visitor-section" aria-labelledby="visitor-heading">
			<div className="visitor-section-heading reveal" data-reveal>
				<span className="visitor-mark" aria-hidden="true">◉</span>
				<div><span className="section-kicker">{language === 'id' ? 'AKSES PUBLIK' : 'PUBLIC ACCESS'}</span><h2 id="visitor-heading">{text.visitors}</h2><p>{text.visitorDescription}</p></div>
			</div>
			<div className="visitor-stats-grid">
				{[
					['today', text.visitorsToday, text.visitorTodayDetail, '♟', '#36569d'],
					['month', text.visitorsMonth, text.visitorMonthDetail, '▦', '#6c65cf'],
					['year', text.visitorsYear, text.visitorYearDetail, '▥', '#269778'],
					['total', text.visitorsTotal, text.visitorTotalDetail, '◎', '#9b55ca'],
				].map(([key, label, detail, icon, color]) => <article className="visitor-stat-card reveal" data-reveal key={key} style={{ '--visitor-color': color }}>
					<span className="visitor-stat-icon" aria-hidden="true">{icon}</span>
					<span className="visitor-stat-label">{label}</span>
					<strong>{formatNumber(data.visitors[key])}</strong>
					<small>{detail}</small>
				</article>)}
			</div>
			<VisitorTrendChart visitors={data.visitors} language={language} />
		</section>
	</main>;
}

function PublicLanding({ root, data }) {
	const [dashboard, setDashboard] = useState(null);
	const [dashboardError, setDashboardError] = useState('');
	const [language, setLanguage] = useState(() => {
		try {
			return window.localStorage.getItem('simak-public-language') === 'en' ? 'en' : 'id';
		} catch {
			return 'id';
		}
	});
	const resetRequested = data.openResetPassword || Object.keys(data.resetErrors || {}).length > 0;
	const [loginOpen, setLoginOpen] = useState(() => !resetRequested && (data.openLogin || Object.keys(data.loginErrors || {}).length > 0));
	const [resetOpen, setResetOpen] = useState(() => resetRequested);
	const loginTriggerRef = useRef(null);
	const pageType = root.dataset.pageType;
	useEffect(() => {
		const refreshForAuthenticationChange = (event) => {
			if (event.key === 'simak-auth-changed') window.location.reload();
		};
		window.addEventListener('storage', refreshForAuthenticationChange);
		if (data.authChanged) {
			try {
				window.localStorage.setItem('simak-auth-changed', String(Date.now()));
			} catch (error) {
				console.error('Authentication changes could not be synchronized across tabs.', error);
			}
		}
		return () => window.removeEventListener('storage', refreshForAuthenticationChange);
	}, [data.authChanged]);
	useEffect(() => {
		const abortController = new AbortController();
		fetch(data.dataUrl, { headers: { Accept: 'application/json' }, signal: abortController.signal })
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || 'Ringkasan akademik gagal dimuat.');
				startTransition(() => setDashboard(payload));
			})
			.catch((error) => {
				if (error.name !== 'AbortError') setDashboardError(error.message);
			});
		return () => abortController.abort();
	}, [data.dataUrl]);
	useEffect(() => {
		document.documentElement.lang = language;
		try {
			window.localStorage.setItem('simak-public-language', language);
		} catch {
			// Language preference remains available for the current page session.
		}
	}, [language]);

	const closeLogin = () => {
		setLoginOpen(false);
		window.requestAnimationFrame(() => loginTriggerRef.current?.focus());
	};
	const closeReset = () => {
		setResetOpen(false);
		window.requestAnimationFrame(() => loginTriggerRef.current?.focus());
	};
	const openReset = () => {
		setLoginOpen(false);
		setResetOpen(true);
	};
	const openLogin = () => {
		if (data.authenticated) {
			window.location.assign(data.dashboardUrl);
			return;
		}
		setResetOpen(false);
		setLoginOpen(true);
	};
	const text = translations[language];
	const pageData = dashboard ? { ...data, ...dashboard } : null;
	return <div className="public-page">
		<Header data={data} language={language} setLanguage={setLanguage} pageType={pageType} onLogin={openLogin} loginTriggerRef={loginTriggerRef} />
		{pageType === 'home' && <Hero language={language} pageType={pageType} heroImages={data.heroImages} motto={data.motto} />}
		{pageType !== 'home' && <div className="inner-hero">
			<div className="inner-hero-backdrop" style={{ '--inner-hero-image': `url("${data.heroImages?.[0] || '/images/landing/hero-01.jpg'}")` }} />
			<div className="inner-hero-copy"><span>{text.heroEyebrow}</span><h1>{pageType === 'faculty' ? text.faculties : pageType === 'program' ? text.programs : data.pageTitle}</h1><p>{data.scopeName}</p></div>
		</div>}
		{dashboardError ? <main className="landing-main"><p className="empty-state" role="alert">{dashboardError}</p></main> : <DashboardContent data={pageData} pageType={pageType} language={language} />}
		<footer className="public-footer"><div className="public-footer-inner"><a className="footer-brand" href={data.homeUrl}><img src={data.logoUrl} alt="" /><span><strong>{data.appName}</strong><small>{data.motto}</small></span></a><span className="footer-copyright">© {new Date().getFullYear()} {data.institutionName} · SIAKAD · Created by <a href="https://www.facebook.com/Salkin.Luthfi" target="_blank" rel="noopener noreferrer">Salkin</a></span></div></footer>
		{loginOpen && <LoginModal data={data} language={language} onClose={closeLogin} onForgot={openReset} />}
		{resetOpen && <ForgotPasswordModal data={data} language={language} onClose={closeReset} onLogin={openLogin} />}
	</div>;
}

const root = document.getElementById('public-landing');
const dataElement = document.getElementById('public-landing-data');
if (root && dataElement) {
	createRoot(root).render(<PublicLanding root={root} data={JSON.parse(dataElement.textContent)} />);
}
