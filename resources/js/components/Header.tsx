
import React, { useState, useEffect, useRef } from 'react';
import Link from './Link';

export type AppView =
  | 'home'
  | 'detail'
  | 'about'
  | 'structure'
  | 'admissions'
  | 'academic'
  | 'accreditation'
  | 'payment'
  | 'contact'
  | 'privacy'
  | 'terms'
  | 'accessibility';

interface HeaderProps {
  currentView?: AppView;
  onNavigate?: (view: AppView) => void;
  logoUrl?: string;
}

export default function Header({ currentView = 'home', onNavigate, logoUrl }: HeaderProps) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Close search on Esc key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && searchOpen) {
        setSearchOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [searchOpen]);

  // Auto-focus input when search opens
  useEffect(() => {
    if (searchOpen) {
      setTimeout(() => {
        searchInputRef.current?.focus();
      }, 50);
    }
  }, [searchOpen]);

  const handleLinkClick = (
    e: React.MouseEvent,
    view: AppView
  ) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      setMobileMenuOpen(false);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery.trim()) return;

    const query = searchQuery.trim().toLowerCase();
    setSearchOpen(false);

    if (
      query.includes('struct') ||
      query.includes('struktur') ||
      query.includes('leader') ||
      query.includes('dekan') ||
      query.includes('chancellor') ||
      query.includes('suhendar')
    ) {
      if (onNavigate) {
        onNavigate('structure');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/structure';
      }
    } else if (
      query.includes('accredit') ||
      query.includes('akreditasi') ||
      query.includes('eahea') ||
      query.includes('qahe') ||
      query.includes('iccac') ||
      query.includes('sertifikat') ||
      query.includes('certif')
    ) {
      if (onNavigate) {
        onNavigate('accreditation');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/accreditation';
      }
    } else if (
      query.includes('admission') ||
      query.includes('daftar') ||
      query.includes('syarat') ||
      query.includes('visa') ||
      query.includes('track')
    ) {
      if (onNavigate) {
        onNavigate('admissions');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/admissions';
      }
    } else if (query.includes('about') || query.includes('sejarah') || query.includes('history')) {
      if (onNavigate) {
        onNavigate('about');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/about';
      }
    } else if (
      query.includes('program') ||
      query.includes('academic') ||
      query.includes('fakultas') ||
      query.includes('facult') ||
      query.includes('master') ||
      query.includes('bachelor') ||
      query.includes('phd') ||
      query.includes('doctor') ||
      query.includes('cs') ||
      query.includes('business') ||
      query.includes('course')
    ) {
      if (onNavigate) {
        onNavigate('academic');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/academic';
      }
    } else if (
      query.includes('news') ||
      query.includes('berita') ||
      query.includes('artikel') ||
      query.includes('article')
    ) {
      if (currentView !== 'home' && onNavigate) {
        onNavigate('home');
      }
      setTimeout(() => {
        const el = document.getElementById('news');
        if (el) el.scrollIntoView({ behavior: 'smooth' });
      }, 100);
    } else if (
      query.includes('fee') ||
      query.includes('payment') ||
      query.includes('bayar') ||
      query.includes('rekening') ||
      query.includes('bank') ||
      query.includes('cimb') ||
      query.includes('bni') ||
      query.includes('mandiri') ||
      query.includes('tuition') ||
      query.includes('biaya')
    ) {
      if (onNavigate) {
        onNavigate('payment');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/payment-information';
      }
    } else if (
      query.includes('contact') ||
      query.includes('hubungi') ||
      query.includes('peta') ||
      query.includes('map') ||
      query.includes('maps') ||
      query.includes('lokasi') ||
      query.includes('alamat') ||
      query.includes('telepon') ||
      query.includes('phone') ||
      query.includes('whatsapp') ||
      query.includes('email') ||
      query.includes('bangi')
    ) {
      if (onNavigate) {
        onNavigate('contact');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/contact';
      }
    } else if (
      query.includes('privacy') ||
      query.includes('privasi') ||
      query.includes('pdpa') ||
      query.includes('dpo')
    ) {
      if (onNavigate) {
        onNavigate('privacy');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/privacy';
      }
    } else if (
      query.includes('term') ||
      query.includes('syarat') ||
      query.includes('ketentuan') ||
      query.includes('tos') ||
      query.includes('legal')
    ) {
      if (onNavigate) {
        onNavigate('terms');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/terms';
      }
    } else if (
      query.includes('accessib') ||
      query.includes('aksesibilitas') ||
      query.includes('wcag') ||
      query.includes('disabilitas') ||
      query.includes('a11y')
    ) {
      if (onNavigate) {
        onNavigate('accessibility');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/accessibility';
      }
    } else {
      if (onNavigate) {
        onNavigate('academic');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/academic';
      }
    }
  };

  const handleQuickSearch = (term: string) => {
    setSearchQuery(term);
    const query = term.toLowerCase();
    setSearchOpen(false);

    if (query.includes('struct') || query.includes('struktur') || query.includes('leader')) {
      if (onNavigate) {
        onNavigate('structure');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/structure';
      }
    } else if (
      query.includes('accredit') ||
      query.includes('akreditasi') ||
      query.includes('sertifikat')
    ) {
      if (onNavigate) {
        onNavigate('accreditation');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/accreditation';
      }
    } else if (query.includes('admission') || query.includes('persyaratan')) {
      if (onNavigate) {
        onNavigate('admissions');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/admissions';
      }
    } else if (query.includes('news') || query.includes('berita') || query.includes('article')) {
      if (currentView !== 'home' && onNavigate) {
        onNavigate('home');
      }
      setTimeout(() => {
        const el = document.getElementById('news');
        if (el) el.scrollIntoView({ behavior: 'smooth' });
      }, 100);
    } else if (query.includes('tuition') || query.includes('payment') || query.includes('bank')) {
      if (onNavigate) {
        onNavigate('payment');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/payment-information';
      }
    } else if (query.includes('contact') || query.includes('map') || query.includes('lokasi')) {
      if (onNavigate) {
        onNavigate('contact');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/contact';
      }
    } else if (query.includes('privacy')) {
      if (onNavigate) {
        onNavigate('privacy');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/privacy';
      }
    } else if (query.includes('term')) {
      if (onNavigate) {
        onNavigate('terms');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/terms';
      }
    } else if (query.includes('accessib')) {
      if (onNavigate) {
        onNavigate('accessibility');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/accessibility';
      }
    } else {
      if (onNavigate) {
        onNavigate('academic');
        window.scrollTo({ top: 0, behavior: 'instant' });
      } else {
        window.location.href = '/academic';
      }
    }
  };

  return (
    <header className={`hdr ${mobileMenuOpen ? 'nav-open' : ''}`} id="hdr">
      <div className="wrap">
        <Link
          href="/"
          className="logo brand-header-logo"
          aria-label="ASEAN University International — Home"
          onClick={(e) => handleLinkClick(e, 'home')}
        >
          <img
            src={logoUrl || '/images/landing/icon.png'}
            alt="Logo ASEAN University International"
            className="brand-logo-img"
            width={48}
            height={48}
            onError={(e) => {
              const target = e.currentTarget;
              target.onerror = null;
              target.src = '/images/landing/icon.png';
            }}
          />
          <div className="brand-text">
            <span className="brand-title">
              <span className="sr-only">ASEAN UNIVERSITU </span>
              ASEAN UNIVERSITY
            </span>
            <div className="brand-subtitle" aria-label="INTERNATIONAL">
              <span>I</span>
              <span>N</span>
              <span>T</span>
              <span>E</span>
              <span>R</span>
              <span>N</span>
              <span>A</span>
              <span>T</span>
              <span>I</span>
              <span>O</span>
              <span>N</span>
              <span>A</span>
              <span>L</span>
            </div>
          </div>
        </Link>
        <nav>
          <ul className="nav">
            <li>
              <Link
                href="/"
                className={currentView === 'home' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'home')}
              >
                Home
              </Link>
            </li>
            <li>
              <Link
                href="/about"
                className={currentView === 'about' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'about')}
              >
                About
              </Link>
            </li>
            <li>
              <Link
                href="/structure"
                className={currentView === 'structure' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'structure')}
              >
                Structure
              </Link>
            </li>
            <li>
              <Link
                href="/admissions"
                className={currentView === 'admissions' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'admissions')}
              >
                Admissions
              </Link>
            </li>
            <li>
              <Link
                href="/academic"
                className={currentView === 'academic' || currentView === 'detail' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'academic')}
              >
                Academic
              </Link>
            </li>
            <li>
              <Link
                href="/accreditation"
                className={currentView === 'accreditation' ? 'is-active' : ''}
                onClick={(e) => handleLinkClick(e, 'accreditation')}
              >
                Accreditation
              </Link>
            </li>
          </ul>
        </nav>
        <div className="hdr-cta">
          <button
            type="button"
            className={`hdr-search-btn ${searchOpen ? 'is-active' : ''}`}
            onClick={() => setSearchOpen((prev) => !prev)}
            aria-label={searchOpen ? 'Close search' : 'Open search'}
            title="Search programmes, admissions, and more"
          >
            <svg
              width="17"
              height="17"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
          <a href="/login" className="btn btn-gold login-button" onClick={() => setMobileMenuOpen(false)}>
            LOGIN PORTAL &rarr;
          </a>
          <button
            type="button"
            className="burger"
            id="burger"
            aria-label="Menu"
            aria-expanded={mobileMenuOpen}
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>

      {/* Global Header Search Bar Dropdown */}
      {searchOpen && (
        <div className="hdr-search-bar" role="search">
          <div className="wrap">
            <form onSubmit={handleSearchSubmit} className="hdr-search-form">
              <svg
                width="19"
                height="19"
                viewBox="0 0 24 24"
                fill="none"
                stroke="var(--gold)"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="search-icon"
              >
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
              </svg>
              <input
                ref={searchInputRef}
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search programmes, scholarships, fees, payment, faculty…"
                aria-label="Search programmes and university information"
              />
              <button type="submit" className="btn btn-gold btn-search-submit">
                SEARCH
              </button>
              <button
                type="button"
                className="hdr-search-close"
                onClick={() => setSearchOpen(false)}
                aria-label="Close search"
                title="Close (Esc)"
              >
                ✕
              </button>
            </form>
            <div className="hdr-search-quick">
              <span>Quick suggestions:</span>
              <button type="button" onClick={() => handleQuickSearch('Accreditation')}>
                Accreditation
              </button>
              <button type="button" onClick={() => handleQuickSearch('Leadership Structure')}>
                Leadership Structure
              </button>
              <button type="button" onClick={() => handleQuickSearch('Master of Business')}>
                Master of Business
              </button>
              <button type="button" onClick={() => handleQuickSearch('Tuition Fees & Scholarship')}>
                Tuition Fees
              </button>
              <button type="button" onClick={() => handleQuickSearch('Computer Science')}>
                Computer Science
              </button>
              <button type="button" onClick={() => handleQuickSearch('Doctor of Philosophy')}>
                PhD
              </button>
              <button type="button" onClick={() => handleQuickSearch('Payment Guide')}>
                Payment Guide
              </button>
              <button type="button" onClick={() => handleQuickSearch('News & Articles')}>
                News &amp; Articles
              </button>
            </div>
          </div>
        </div>
      )}
    </header>
  );
}
