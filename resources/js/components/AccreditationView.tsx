

import React, { useState, useMemo, useEffect, useRef } from 'react';
import Link from './Link';
import {
  ACCREDITATION_ITEMS,
  AccreditationItem,
} from '../data/accreditationData';

interface AccreditationViewProps {
  onNavigate?: (
    view:
      | 'home'
      | 'detail'
      | 'about'
      | 'structure'
      | 'admissions'
      | 'academic'
      | 'accreditation'
  ) => void;
}

export default function AccreditationView({ onNavigate }: AccreditationViewProps) {
  const [activeCategory, setActiveCategory] = useState<'all' | 'eahea' | 'qahe' | 'iccac'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCert, setSelectedCert] = useState<AccreditationItem | null>(null);
  const [zoomLevel, setZoomLevel] = useState<number>(1);
  const containerRef = useRef<HTMLDivElement>(null);

  // IntersectionObserver for scroll-reveal animations (.rv)
  useEffect(() => {
    if (!containerRef.current) return;

    const rvElements = containerRef.current.querySelectorAll<HTMLElement>('.rv:not(.in)');
    const rvObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('in');
            rvObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1 }
    );

    rvElements.forEach((el) => rvObserver.observe(el));

    return () => {
      rvObserver.disconnect();
    };
  }, []);

  // Keyboard navigation for modal (Esc to close)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && selectedCert) {
        setSelectedCert(null);
        setZoomLevel(1);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [selectedCert]);

  // Reset zoom when modal changes
  useEffect(() => {
    setZoomLevel(1);
  }, [selectedCert]);

  // Filter items based on active category & search query
  const filteredItems = useMemo(() => {
    return ACCREDITATION_ITEMS.filter((item) => {
      const matchesCategory =
        activeCategory === 'all' || item.category === activeCategory;
      const q = searchQuery.trim().toLowerCase();
      const matchesQuery =
        q === '' ||
        item.title.toLowerCase().includes(q) ||
        item.organization.toLowerCase().includes(q) ||
        item.organizationShort.toLowerCase().includes(q) ||
        item.regNumber.toLowerCase().includes(q) ||
        item.description.toLowerCase().includes(q) ||
        item.highlights.some((h) => h.toLowerCase().includes(q));
      return matchesCategory && matchesQuery;
    });
  }, [activeCategory, searchQuery]);

  // Category counts
  const counts = useMemo(() => {
    return {
      all: ACCREDITATION_ITEMS.length,
      eahea: ACCREDITATION_ITEMS.filter((i) => i.category === 'eahea').length,
      qahe: ACCREDITATION_ITEMS.filter((i) => i.category === 'qahe').length,
      iccac: ACCREDITATION_ITEMS.filter((i) => i.category === 'iccac').length,
    };
  }, []);

  const handleLink = (
    e: React.MouseEvent,
    view:
      | 'home'
      | 'detail'
      | 'about'
      | 'structure'
      | 'admissions'
      | 'academic'
      | 'accreditation'
  ) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  const toggleZoom = () => {
    setZoomLevel((prev) => (prev === 1 ? 1.5 : prev === 1.5 ? 2 : 1));
  };

  return (
    <main className="view is-on" id="view-accreditation" ref={containerRef}>
      {/* Skip to Content Anchor */}
      <div id="content" tabIndex={-1} style={{ outline: 'none' }} />

      {/* HERO SECTION */}
      <section className="ahero accred-hero">
        <img
          className="ahero-img"
          src="/img/Perayaan%20Wisuda%20di%20Bawah%20Menara%20Kota.png"
          alt="Perayaan Wisuda di Bawah Menara Kota - ASEAN University International Accreditation"
        />
        <div className="wrap">
          <div className="eyebrow">ACCREDITATION &amp; QUALITY ASSURANCE</div>
          <h1>Institutional Accreditation &amp; Global Standards</h1>
          <div className="sub">Internationally Audited, Validated &amp; Certified</div>
          <p className="lede">
            ASEAN University International (AUI) beroperasi di bawah audit mutu ketat dan akreditasi
            independen dari badan penjaminan mutu internasional terkemuka di Eropa dan dunia, memastikan
            derajat ijazah, kurikulum, dan kompetensi lulusan diakui secara global.
          </p>
        </div>
      </section>

      {/* QUICK STATS / CREDENTIALS STRIP */}
      <div className="qstrip accred-qstrip">
        <div className="wrap">
          <div className="qs">
            <b>EAHEA Accredited</b>
            <span>Rating 3.5 — Very Good (Serial No: 250A0214390)</span>
          </div>
          <div className="qs">
            <b>QAHE Validated</b>
            <span>Institution ID: 120875 · Valid to Nov 2026</span>
          </div>
          <div className="qs">
            <b>ISO 17011:2017 QA Agency</b>
            <span>International Education Benchmark</span>
          </div>
          <div className="qs">
            <b>ICCAC &amp; Chambers</b>
            <span>BRICS &amp; ASEAN Chamber Recognition</span>
          </div>
        </div>
      </div>

      {/* ACCREDITATION DIRECTORY */}
      <section className="sec" id="accreditation-certificates">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">Official Verification &amp; Registry</div>
            <h2>Sertifikat &amp; Pengakuan Resmi Kelembagaan</h2>
            <p>
              Seluruh dokumen akreditasi, audit mutu, dan sertifikasi resmi dapat diverifikasi
              langsung oleh mahasiswa, alumni, kedutaan besar, dan institusi rekanan internasional.
            </p>
          </div>

          {/* SEARCH & FILTER CONTROLS */}
          <div className="accred-toolbar rv">
            <div className="accred-tabs" role="tablist">
              <button
                type="button"
                role="tab"
                aria-selected={activeCategory === 'all'}
                className={`accred-tab ${activeCategory === 'all' ? 'is-active' : ''}`}
                onClick={() => setActiveCategory('all')}
              >
                All Certificates <span className="tab-pill">{counts.all}</span>
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeCategory === 'eahea'}
                className={`accred-tab ${activeCategory === 'eahea' ? 'is-active' : ''}`}
                onClick={() => setActiveCategory('eahea')}
              >
                EAHEA European <span className="tab-pill">{counts.eahea}</span>
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeCategory === 'qahe'}
                className={`accred-tab ${activeCategory === 'qahe' ? 'is-active' : ''}`}
                onClick={() => setActiveCategory('qahe')}
              >
                QAHE International <span className="tab-pill">{counts.qahe}</span>
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeCategory === 'iccac'}
                className={`accred-tab ${activeCategory === 'iccac' ? 'is-active' : ''}`}
                onClick={() => setActiveCategory('iccac')}
              >
                ICCAC &amp; Industry <span className="tab-pill">{counts.iccac}</span>
              </button>
            </div>

            <div className="accred-search">
              <span className="search-icon">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
              </span>
              <input
                type="text"
                placeholder="Cari sertifikat, nomor registrasi, atau lembaga..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                aria-label="Cari sertifikat akreditasi"
              />
              {searchQuery && (
                <button
                  type="button"
                  className="clear-search-btn"
                  onClick={() => setSearchQuery('')}
                  aria-label="Hapus pencarian"
                >
                  ✕
                </button>
              )}
            </div>
          </div>

          {/* CERTIFICATES GRID */}
          {filteredItems.length === 0 ? (
            <div className="accred-empty rv">
              <p>Tidak ada sertifikat yang cocok dengan pencarian &quot;{searchQuery}&quot;.</p>
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => {
                  setSearchQuery('');
                  setActiveCategory('all');
                }}
              >
                Reset Pencarian
              </button>
            </div>
          ) : (
            <div className="accred-grid">
              {filteredItems.map((item) => (
                <article key={item.id} className="accred-card rv">
                  {/* Image Thumbnail Container */}
                  <div
                    className="accred-img-wrap"
                    onClick={() => setSelectedCert(item)}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setSelectedCert(item);
                      }
                    }}
                    aria-label={`Buka pratinjau resolusi tinggi ${item.title}`}
                  >
                    <img
                      src={item.image}
                      alt={item.title}
                      loading="lazy"
                      className="accred-thumb"
                    />
                    <div className="accred-overlay">
                      <span className="zoom-badge">
                        <svg
                          width="18"
                          height="18"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="2.2"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        >
                          <circle cx="11" cy="11" r="8" />
                          <line x1="21" y1="21" x2="16.65" y2="16.65" />
                          <line x1="11" y1="8" x2="11" y2="14" />
                          <line x1="8" y1="11" x2="14" y2="11" />
                        </svg>
                        Zoom Certificate
                      </span>
                    </div>
                  </div>

                  {/* Card Body */}
                  <div className="accred-body">
                    <div className="accred-header">
                      <span className={`accred-chip chip-${item.category}`}>
                        {item.categoryLabel}
                      </span>
                      <span className="accred-org-badge">{item.organizationShort}</span>
                    </div>

                    <h3 className="accred-title">{item.title}</h3>
                    <p className="accred-org">{item.organization}</p>

                    <div className="accred-meta">
                      <div className="meta-row">
                        <span className="meta-label">ID / Serial:</span>
                        <span className="meta-val">{item.regNumber}</span>
                      </div>
                      <div className="meta-row">
                        <span className="meta-label">Status:</span>
                        <span className="meta-val status-val">{item.validity}</span>
                      </div>
                    </div>

                    <p className="accred-desc">{item.description}</p>

                    <ul className="accred-highlights">
                      {item.highlights.map((h, idx) => (
                        <li key={idx}>
                          <span className="check-icon">✓</span>
                          <span>{h}</span>
                        </li>
                      ))}
                    </ul>

                    <div className="accred-actions">
                      <button
                        type="button"
                        className="btn btn-outline-brand"
                        onClick={() => setSelectedCert(item)}
                      >
                        Pratinjau Dokumen ↗
                      </button>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* QUALITY ASSURANCE PILLARS */}
      <section className="sec sec-dark" id="qa-framework">
        <div className="wrap">
          <div className="sec-head sec-head-light rv">
            <div className="eyebrow">Our Assurance Framework</div>
            <h2>Pilar Penjaminan Mutu &amp; Kepatuhan Standar Global</h2>
            <p>
              Prinsip-prinsip operasional yang memastikan setiap kualifikasi yang diterbitkan oleh
              ASEAN University International berbobot, terpercaya, dan relevan di kancah global.
            </p>
          </div>

          <div className="accred-pillars-grid">
            <div className="pillar-card rv">
              <div className="pillar-icon ic">◉</div>
              <h3>Penyelarasan Kurikulum Eropa &amp; Internasional</h3>
              <p>
                Kurikulum program sarjana, magister, dan doktoral AUI dirancang dengan mengadopsi
                standar ECTS (European Credit Transfer and Accumulation System) serta pedoman mutu
                EAHEA, mempermudah transfer kredit dan artikulasi akademik antarnegara.
              </p>
            </div>

            <div className="pillar-card rv">
              <div className="pillar-icon ic">✦</div>
              <h3>Audit Berkala Mutu Independen (Rating 3.5)</h3>
              <p>
                Evaluasi penjaminan mutu kelembagaan dilakukan secara berkala oleh auditor independen.
                Audit resmi EAHEA 2025–2026 memberikan nilai 3.5 (Very Good) dalam transparansi tata kelola,
                kelayakan materi ajar, serta kompetensi dosen lintas kawasan.
              </p>
            </div>

            <div className="pillar-card rv">
              <div className="pillar-icon ic">◎</div>
              <h3>Validasi QAHE &amp; Standar ISO 17011:2017</h3>
              <p>
                Status keanggotaan dan akreditasi QAHE (Institution ID 120875) menjamin kepatuhan sistem
                evaluasi pembelajaran pada instrumen penjaminan mutu tinggi yang diawasi oleh badan bersertifikasi
                ISO 17011:2017 di bidang pendidikan tinggi.
              </p>
            </div>

            <div className="pillar-card rv">
              <div className="pillar-icon ic">◐</div>
              <h3>Relevansi Industri &amp; Kemitraan Dagang Regional</h3>
              <p>
                Sertifikasi institusional dari ICCAC dan pengakuan oleh BRICS &amp; ASEAN Chamber of
                Commerce menghubungkan luaran riset dan keahlian mahasiswa langsung dengan kebutuhan
                korporasi, wirausaha, serta pasar kerja regional.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* VERIFICATION & ADMISSIONS CTA */}
      <section className="sec accred-verify-sec" id="verification-banner">
        <div className="wrap">
          <div className="accred-verify-box rv">
            <div className="verify-content">
              <div className="eyebrow" style={{ color: 'var(--gold)' }}>
                Direct Verification Services
              </div>
              <h2>Verifikasi Ijazah &amp; Status Akreditasi Institusi</h2>
              <p>
                Untuk keperluan verifikasi ijazah oleh instansi pemerintah, perusahaan multinasional,
                lembaga penyetaraan akademik, atau kedutaan besar, hubungi Sekretariat Penjaminan Mutu
                AUI atau langsung ke portal verifikasi masing-masing badan akreditasi (EAHEA / QAHE).
              </p>
              <div className="verify-badges">
                <span className="verify-badge">✓ EAHEA Serial Verification</span>
                <span className="verify-badge">✓ QAHE Institution ID 120875</span>
                <span className="verify-badge">✓ ICCAC Registered Ins AS0501586 P</span>
              </div>
            </div>

            <div className="verify-cta">
              <Link
                href="/admissions"
                className="btn btn-gold"
                onClick={(e) => handleLink(e, 'admissions')}
              >
                Apply for Admission ↗
              </Link>
              <a
                href="mailto:admin@aui.university?subject=Verification%20of%20AUI%20Accreditation%20and%20Credentials"
                className="btn btn-outline"
                style={{ borderColor: 'rgba(255,255,255,0.4)', color: '#fff' }}
              >
                Hubungi Sekretariat Mutu
              </a>
            </div>
          </div>
        </div>
      </section>

      {/* LIGHTBOX / ZOOM MODAL */}
      {selectedCert && (
        <div
          className="accred-modal-backdrop"
          onClick={() => setSelectedCert(null)}
          role="dialog"
          aria-modal="true"
          aria-label={selectedCert.title}
        >
          <div
            className="accred-modal-content"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="accred-modal-header">
              <div className="modal-title-area">
                <span className={`accred-chip chip-${selectedCert.category}`}>
                  {selectedCert.categoryLabel}
                </span>
                <h3>{selectedCert.title}</h3>
                <p className="modal-org">{selectedCert.organization}</p>
              </div>

              <div className="modal-actions-top">
                <button
                  type="button"
                  className="modal-zoom-btn"
                  onClick={toggleZoom}
                  title="Ubah perbesaran gambar (1x / 1.5x / 2x)"
                >
                  Zoom {zoomLevel}x
                </button>
                <a
                  href={selectedCert.image}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="modal-newtab-btn"
                  title="Buka gambar resolusi penuh di tab baru"
                >
                  Buka Asli ↗
                </a>
                <button
                  type="button"
                  className="modal-close-btn"
                  onClick={() => setSelectedCert(null)}
                  aria-label="Tutup pratinjau"
                >
                  ✕
                </button>
              </div>
            </div>

            {/* Modal Body: Split view of large image & details */}
            <div className="accred-modal-body">
              <div className="accred-modal-viewer">
                <div
                  className="accred-zoom-container"
                  style={{
                    transform: `scale(${zoomLevel})`,
                    transformOrigin: 'top center',
                    transition: 'transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)',
                  }}
                >
                  <img
                    src={selectedCert.image}
                    alt={selectedCert.title}
                    className="accred-modal-img"
                  />
                </div>
              </div>

              <div className="accred-modal-sidebar">
                <div className="sidebar-sec">
                  <h4>Nomor Registrasi / Identifikasi</h4>
                  <div className="sidebar-val-pill">{selectedCert.regNumber}</div>
                </div>

                <div className="sidebar-sec">
                  <h4>Status Validitas Mutu</h4>
                  <div className="sidebar-val-status">{selectedCert.validity}</div>
                </div>

                <div className="sidebar-sec">
                  <h4>Deskripsi Dokumen</h4>
                  <p className="sidebar-desc">{selectedCert.description}</p>
                </div>

                <div className="sidebar-sec">
                  <h4>Poin Utama &amp; Penilaian</h4>
                  <ul className="sidebar-highlights">
                    {selectedCert.highlights.map((h, i) => (
                      <li key={i}>
                        <span className="check">✓</span>
                        <span>{h}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="sidebar-cta">
                  <a
                    href={selectedCert.image}
                    download
                    className="btn btn-outline-brand"
                    style={{ width: '100%', textAlign: 'center', justifyContent: 'center' }}
                  >
                    Unduh Sertifikat (WebP) ↓
                  </a>
                  <button
                    type="button"
                    className="btn btn-gold"
                    style={{ width: '100%', marginTop: '10px', textAlign: 'center', justifyContent: 'center' }}
                    onClick={() => {
                      setSelectedCert(null);
                      if (onNavigate) {
                        onNavigate('admissions');
                        window.scrollTo({ top: 0, behavior: 'instant' });
                      } else {
                        window.location.href = '/admissions';
                      }
                    }}
                  >
                    Daftar Sekarang ↗
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
