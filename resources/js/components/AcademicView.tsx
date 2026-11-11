

import React, { useState, useEffect, useRef } from 'react';
import Link from './Link';

interface AcademicViewProps {
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

interface CourseLevel {
  badge: 'S1' | 'S2' | 'S3';
  level: string;
  degree: string;
  courses: string[];
}

interface FacultyCatalog {
  id: string;
  name: string;
  shortName: string;
  description: string;
  levels: CourseLevel[];
}

const CATALOG_DATA: FacultyCatalog[] = [
  {
    id: 'fbe',
    name: 'Faculty of Business and Economics',
    shortName: 'Business & Economics',
    description: 'Developing visionary business leaders, economists, and entrepreneurs for the global market.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Principles of Management', 'Microeconomics', 'Business Communication', 'Financial Accounting'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Strategic Management', 'Advanced Macroeconomics', 'Corporate Finance', 'International Business Strategy'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Advanced Research Methodology', 'Economic Theory Seminar', 'Global Markets Analysis', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'fet',
    name: 'Faculty of Engineering and Technology',
    shortName: 'Engineering & Tech',
    description: 'Driving technological advancements, engineering solutions, and digital innovation.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Introduction to Programming', 'Calculus I', 'Physics for Engineers', 'Data Structures'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Artificial Intelligence', 'Advanced Data Analytics', 'Cybersecurity', 'Cloud Computing Architectures'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Advanced Algorithms', 'Research in Systems Engineering', 'Machine Learning Theory', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'fsh',
    name: 'Faculty of Social Sciences and Humanities',
    shortName: 'Social Sciences & Hum.',
    description: 'Understanding human society, culture, and interpersonal dynamics in a changing world.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Introduction to Sociology', 'Psychology Basics', 'History of Civilizations', 'Human Geography'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Sociological Theory', 'Advanced Research Methods', 'Social Policy Analysis', 'Globalization Studies'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Contemporary Social Theory', 'Advanced Qualitative Research', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'flw',
    name: 'Faculty of Law',
    shortName: 'Law',
    description: 'Upholding justice, legal frameworks, and international regulatory standards.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Introduction to Law', 'Civil Law', 'Criminal Law', 'Constitutional Law'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['International Business Law', 'Comparative Constitutional Law', 'Human Rights Law', 'Advanced Legal Research'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Philosophy of Law', 'Comparative Legal Systems', 'Advanced Legal Thesis'],
      },
    ],
  },
  {
    id: 'fhu',
    name: 'Faculty of Humaniora',
    shortName: 'Humaniora',
    description: 'Exploring philosophy, arts, literature, and human history to enrich cultural perspectives.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Philosophy of Life', 'Literature and Culture', 'Art History', 'Languages'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Comparative Literature', 'Cultural Studies', 'Philosophy of Language', 'Advanced Aesthetics'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Theoretical Frameworks in Humanities', 'Interdisciplinary Research', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'fhs',
    name: 'Faculty of Health Sciences',
    shortName: 'Health Sciences',
    description: 'Advancing public wellness, medical care, and health-related research innovations.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Anatomy and Physiology', 'Public Health Basics', 'Nutrition Science', 'Introduction to Healthcare'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Epidemiology', 'Healthcare Policy and Management', 'Advanced Nursing Practice', 'Biostatistics'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Clinical Research Methods', 'Advanced Health Sciences Theory', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'fis',
    name: 'Faculty of Islamic Studies',
    shortName: 'Islamic Studies',
    description: 'Deepening the academic study of Islamic jurisprudence, history, and civilization.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Islamic Jurisprudence (Fiqh)', 'Quranic Studies', 'Islamic History', 'Hadith Studies'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Contemporary Islamic Finance', 'Islamic Philosophy', 'Advanced Fiqh Studies', 'Islamic Education'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['Critical Studies in Islamic Texts', 'Islamic Thought and Modernity', 'Dissertation Research'],
      },
    ],
  },
  {
    id: 'fps',
    name: 'Faculty of Politics and Social Sciences',
    shortName: 'Politics & Soc. Sciences',
    description: 'Analyzing political systems, governance, public policy, and international relations.',
    levels: [
      {
        badge: 'S1',
        degree: 'Bachelor Degree',
        level: 'Undergraduate',
        courses: ['Political Science Basics', 'Public Administration', 'International Relations', 'Public Policy'],
      },
      {
        badge: 'S2',
        degree: 'Master Degree',
        level: 'Graduate',
        courses: ['Global Governance', 'Advanced Public Policy', 'Conflict Resolution', 'Political Economy'],
      },
      {
        badge: 'S3',
        degree: 'Doctoral Degree',
        level: 'Ph.D / Doctorate',
        courses: ['International Relations Theory', 'Advanced Policy Analysis', 'Dissertation Research'],
      },
    ],
  },
];

const FACULTIES_GRID = [
  {
    id: 'fbe',
    name: 'Faculty of Business and Economics',
    img: 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=700&q=70',
    desc: 'Developing visionary business leaders, economists, and entrepreneurs for the global market.',
  },
  {
    id: 'fet',
    name: 'Faculty of Engineering and Technology',
    img: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=700&q=70',
    desc: 'Driving technological advancements, engineering solutions, and digital innovation.',
  },
  {
    id: 'fsh',
    name: 'Faculty of Social Sciences and Humanities',
    img: 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=700&q=70',
    desc: 'Understanding human society, culture, and interpersonal dynamics in a changing world.',
  },
  {
    id: 'flw',
    name: 'Faculty of Law',
    img: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=700&q=70',
    desc: 'Upholding justice, legal frameworks, and international regulatory standards.',
  },
  {
    id: 'fhu',
    name: 'Faculty of Humaniora',
    img: 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=700&q=70',
    desc: 'Exploring philosophy, arts, literature, and human history to enrich cultural perspectives.',
  },
  {
    id: 'fhs',
    name: 'Faculty of Health Sciences',
    img: 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=700&q=70',
    desc: 'Advancing public wellness, medical care, and health-related research innovations.',
  },
  {
    id: 'fis',
    name: 'Faculty of Islamic Studies',
    img: 'https://images.unsplash.com/photo-1585036156171-384164a8c675?auto=format&fit=crop&w=700&q=70',
    desc: 'Deepening the academic study of Islamic jurisprudence, history, and civilization.',
  },
  {
    id: 'fps',
    name: 'Faculty of Politics and Social Sciences',
    img: 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=700&q=70',
    desc: 'Analyzing political systems, governance, public policy, and international relations.',
  },
];

export default function AcademicView({ onNavigate }: AcademicViewProps) {
  const [activeTab, setActiveTab] = useState<string>('fbe');
  const containerRef = useRef<HTMLDivElement>(null);

  // Number count-up effect & scroll reveal observer
  useEffect(() => {
    if (!containerRef.current) return;

    const countElements = containerRef.current.querySelectorAll<HTMLElement>('[data-count]');
    const countUp = (el: HTMLElement, sym: HTMLElement | null) => {
      const target = +(el.getAttribute('data-count') || 0);
      const dur = 1400;
      const t0 = performance.now();

      const tick = (t: number) => {
        const p = Math.min((t - t0) / dur, 1);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('en-US');
        if (p < 1) {
          requestAnimationFrame(tick);
        } else if (sym) {
          setTimeout(() => sym.classList.add('on'), 120);
        }
      };
      requestAnimationFrame(tick);
    };

    const countObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const targetEl = entry.target as HTMLElement;
            const parent = targetEl.closest('.row, .s, .sband, .s-num') || targetEl.parentElement;
            const sym = parent ? parent.querySelector<HTMLElement>('[data-sym], .sym') : null;
            countUp(targetEl, sym);
            countObserver.unobserve(targetEl);
          }
        });
      },
      { threshold: 0.25 }
    );

    countElements.forEach((el) => countObserver.observe(el));

    // Scroll reveal observer
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
      countObserver.disconnect();
      rvObserver.disconnect();
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

  const handleSelectFaculty = (facId: string) => {
    setActiveTab(facId);
    const catalogEl = document.getElementById('catalog');
    if (catalogEl) {
      catalogEl.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const currentFaculty = CATALOG_DATA.find((f) => f.id === activeTab) || CATALOG_DATA[0];

  return (
    <main className="view is-on" id="view-academic" ref={containerRef}>
      {/* Skip to Content Anchor */}
      <div id="content" tabIndex={-1} style={{ outline: 'none' }} />

      {/* ============================================================
           HERO
           ============================================================ */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="/img/Halaman%20Kampus%20Merah%20Putih%20Dikelilingi%20Pepohonan.png"
          alt="Halaman Kampus Merah Putih Dikelilingi Pepohonan - ASEAN University International"
        />
        <div className="wrap">
          <div className="eyebrow">Academic · Faculties &amp; Divisions</div>
          <h1>
            Our Faculties &amp;
            <br />
            Academic Divisions
          </h1>
          <p className="lede">
            Explore our comprehensive range of faculties supporting various undergraduate, graduate, and honorary degree programs.
          </p>
          <div className="ahero-stats">
            <div>
              <b>
                <span data-count="8">0</span>
              </b>
              <span>Academic Faculties</span>
            </div>
            <div>
              <b>
                <span data-count="24">0</span>
              </b>
              <span>Programme Levels</span>
            </div>
            <div>
              <b>
                <span data-count="20">0</span>+
              </b>
              <span>Research Centers</span>
            </div>
            <div>
              <b>3</b>
              <span>Degree Levels · S1 · S2 · S3</span>
            </div>
          </div>
        </div>
      </section>

      {/* QUICK STRIP */}
      <div className="qstrip">
        <div className="wrap">
          <div className="qs">
            <b>Undergraduate (S1)</b>
            <span>Bachelor Degree · 3.5–4 Years</span>
          </div>
          <div className="qs">
            <b>Graduate (S2)</b>
            <span>Master Degree · 1.5–2 Years</span>
          </div>
          <div className="qs">
            <b>Doctoral (S3)</b>
            <span>Ph.D / Doctorate · 3–5 Years</span>
          </div>
          <div className="qs">
            <b>Scholarship</b>
            <span>Available for All Levels</span>
          </div>
        </div>
      </div>

      {/* ============================================================
           01 — FACULTIES GRID (8 cards)
           ============================================================ */}
      <section className="sec" id="faculties">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">01 — Faculties</div>
            <h2>Eight academic faculties</h2>
            <p>
              Setiap fakultas menaungi programme S1, S2, dan S3 dengan kurikulum yang dirancang bersama industri dan mitra
              riset global.
            </p>
          </div>
          <div className="fac-grid">
            {FACULTIES_GRID.map((fac) => (
              <article key={fac.id} className="fac rv">
                <figure>
                  <img loading="lazy" src={fac.img} alt={fac.name} />
                </figure>
                <div className="body">
                  <h3>{fac.name}</h3>
                  <p>{fac.desc}</p>
                  <button
                    type="button"
                    className="btn btn-brand"
                    onClick={() => handleSelectFaculty(fac.id)}
                  >
                    View Curriculum →
                  </button>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* ============================================================
           02 — COURSE CATALOG (tabs)
           ============================================================ */}
      <section className="sec catalog" id="catalog">
        <div className="wrap">
          <div className="cat-intro rv">
            <div className="eyebrow">02 — Course Catalog</div>
            <h2>Academic Course Catalog</h2>
            <p>Pilih fakultas untuk melihat kurikulum programme Bachelor, Master, dan Doctoral.</p>
          </div>

          {/* TAB BUTTONS */}
          <div className="ftabs rv" id="ftabs" role="tablist">
            {CATALOG_DATA.map((fac) => (
              <button
                key={fac.id}
                type="button"
                role="tab"
                aria-selected={activeTab === fac.id}
                className={`ftab ${activeTab === fac.id ? 'on' : ''}`}
                onClick={() => setActiveTab(fac.id)}
              >
                {fac.shortName}
              </button>
            ))}
          </div>

          {/* ACTIVE FACULTY PANEL */}
          <div className="fpanel on" id={`fac-${currentFaculty.id}`}>
            <div className="sec-head" style={{ marginBottom: '36px' }}>
              <div className="eyebrow">{currentFaculty.name}</div>
              <h2 style={{ fontSize: 'clamp(24px,2.8vw,34px)' }}>{currentFaculty.shortName}</h2>
              <p>{currentFaculty.description}</p>
            </div>
            <div className="lv-grid">
              {currentFaculty.levels.map((lvl) => (
                <div key={lvl.badge} className="lv-card">
                  <header>
                    <div className={`badge ${lvl.badge.toLowerCase()}`}>{lvl.badge}</div>
                    <div>
                      <span>{lvl.degree}</span>
                      <h4>{lvl.level}</h4>
                    </div>
                  </header>
                  <div className="divider" />
                  <ul>
                    {lvl.courses.map((course, idx) => (
                      <li key={idx}>{course}</li>
                    ))}
                  </ul>
                  <footer>
                    <Link
                      href="/admissions"
                      className="btn btn-brand"
                      onClick={(e) => handleLink(e, 'admissions')}
                    >
                      Apply Now
                    </Link>
                  </footer>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
           STATS BAND
           ============================================================ */}
      <section className="stats-band">
        <div className="wrap">
          <div className="sband rv">
            <div className="row">
              <b>
                <span data-count="3000">0</span>
              </b>
              <span className="sym" data-sym>
                +
              </span>
            </div>
            <span>
              students from
              <br />
              5+ countries
            </span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b>
                <span data-count="8">0</span>
              </b>
            </div>
            <span>
              academic faculties
              <br />
              across 3 levels
            </span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b>
                <span data-count="20">0</span>
              </b>
              <span className="sym" data-sym>
                +
              </span>
            </div>
            <span>
              research centers
              <br />
              worldwide
            </span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b>
                <span data-count="100">0</span>
              </b>
              <span className="sym" data-sym>
                +
              </span>
            </div>
            <span>
              industry partnerships
              <br />
              globally
            </span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b>
                <span data-count="90">0</span>
              </b>
              <span className="sym" data-sym>
                %
              </span>
            </div>
            <span>
              employability rate
              <br />
              within 6 months
            </span>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="visit">
        <div className="wrap">
          <div>
            <h3>Ready to start your journey?</h3>
            <p>Scholarship Available · Study from anywhere in the world · Tuition from MYR 35,000</p>
          </div>
          <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
            <Link
              href="/admissions"
              className="btn btn-brand"
              onClick={(e) => handleLink(e, 'admissions')}
            >
              Apply now
            </Link>
            <Link href="/contact" className="btn btn-ghost">
              Contact Admissions
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
