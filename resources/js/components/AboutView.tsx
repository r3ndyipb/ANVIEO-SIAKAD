

import React, { useEffect, useRef } from 'react';
import Link from './Link';

interface AboutViewProps {
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

export default function AboutView({ onNavigate }: AboutViewProps) {
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

  return (
    <main className="view is-on" id="view-about" ref={containerRef}>
      {/* 1. PAGE HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1800&q=70"
          alt="ASEAN University International Campus"
        />
        <div className="wrap">
          <div className="eyebrow">About ASEAN University International</div>
          <h1>Asean University International</h1>
          <div className="sub">With Unbelievable Impact</div>
          <p className="lede">
            At ASEAN University International (AUI), we foster excellent education that is engaging
            and enthusiastically presented, with an emphasis on disseminating practical and pertinent knowledge.
          </p>
          <div className="ahero-stats rv">
            <div className="s">
              <div className="s-num">
                <span data-count="3000">0</span>
                <span className="sym">+</span>
              </div>
              <div className="s-label">Students · 5+ Countries</div>
            </div>
            <div className="s">
              <div className="s-num">
                <span data-count="5">0</span>
              </div>
              <div className="s-label">Academic Faculties</div>
            </div>
            <div className="s">
              <div className="s-num">
                <span data-count="20">0</span>
                <span className="sym">+</span>
              </div>
              <div className="s-label">Research Centers</div>
            </div>
            <div className="s">
              <div className="s-num">
                <span data-count="100">0</span>
                <span className="sym">+</span>
              </div>
              <div className="s-label">Industry Partnerships</div>
            </div>
            <div className="s">
              <div className="s-num">
                <span data-count="90">0</span>
                <span className="sym">%</span>
              </div>
              <div className="s-label">Employability ≤ 6 Months</div>
            </div>
          </div>
        </div>
      </section>

      {/* 2. MISSION & VISION */}
      <section className="sec">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">01 — Our Purpose</div>
            <h2>Mission &amp; Vision</h2>
          </div>
          <div className="mv-grid">
            <div className="mv-card rv">
              <div className="num">01</div>
              <h3>Mission</h3>
              <p>
                Our goal is to provide students with the opportunity to delve into a vast world of
                information taught by professionals and graduate with essential skills for the latest
                industries.
              </p>
              <ul>
                <li>Deliver engaging and enthusiastically presented education</li>
                <li>Disseminate practical and pertinent knowledge across all programmes</li>
                <li>Ensure students have the resources to succeed in their studies</li>
                <li>Make an unbelievably positive impact on academic and professional growth</li>
              </ul>
            </div>
            <div className="mv-card rv">
              <div className="num">02</div>
              <h3>Vision</h3>
              <p>
                To be a global university that opens up great opportunities for academic and professional
                development, nurturing effective global leaders through transformative education and
                multidisciplinary research.
              </p>
              <ul>
                <li>Nurture effective global leaders</li>
                <li>Impact society through multidisciplinary research</li>
                <li>Transform lives for the better</li>
                <li>Build a powerful, connected network across the globe</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* 3. STATS BAND */}
      <section className="stats-band">
        <div className="wrap">
          <div className="sband rv">
            <div className="row">
              <b><span data-count="30000">0</span></b>
            </div>
            <span>students — nearly 30,000<br />across the globe</span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b><span data-count="20000">0</span></b>
            </div>
            <span>alumni in the AUI<br />connected network</span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b><span data-count="20">0</span></b>
              <span className="sym" data-sym>+</span>
            </div>
            <span>research centers across<br />5 academic faculties</span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b><span data-count="100">0</span></b>
              <span className="sym" data-sym>+</span>
            </div>
            <span>industry partnerships<br />worldwide</span>
          </div>
          <div className="sband rv">
            <div className="row">
              <b><span data-count="90">0</span></b>
              <span className="sym" data-sym>%</span>
            </div>
            <span>graduate employability<br />rate within 6 months</span>
          </div>
        </div>
      </section>

      {/* 4. OUR JOURNEY (TIMELINE) */}
      <section className="sec hist" id="journey">
        <div className="wrap">
          <div className="hist-intro rv">
            <div className="eyebrow">02 — Our Journey</div>
            <h2>From Yales to ASEAN University International</h2>
            <p>
              Perjalanan lebih dari dua dekade — dari lembaga pendidikan kecil hingga universitas internasional
              dengan jaringan global.
            </p>
          </div>

          <div className="tl">
            {/* 2001–2002 : LEFT */}
            <div className="tl-row left rv">
              <div className="tl-card">
                <div className="yr">2001 – 2002</div>
                <span className="tag">Foundation</span>
                <h4>Yales, Board of Education Management</h4>
                <p>
                  Yales, Board of Education Management was established, running a few courses such as
                  Management, Business Law, and TOEFL under the supervision of Dr. Suhendar.
                </p>
              </div>
              <div className="tl-dot"><i></i></div>
              <div className="tl-empty"></div>
            </div>

            {/* 2003–2004 : RIGHT */}
            <div className="tl-row right rv">
              <div className="tl-empty"></div>
              <div className="tl-dot"><i></i></div>
              <div className="tl-card">
                <div className="yr">2003 – 2004</div>
                <span className="tag">Collaboration</span>
                <h4>Expanded Collaboration with Singapore</h4>
                <p>
                  Expanded collaboration with Explomo Technical Services (Singapore), promoting Corporate
                  Law and International Hospitality with &quot;Akademi Perhotelan Kartika&quot;.
                </p>
              </div>
            </div>

            {/* 2005–2010 : LEFT */}
            <div className="tl-row left rv">
              <div className="tl-card">
                <div className="yr">2005 – 2010</div>
                <span className="tag">Growth</span>
                <h4>Akademi Yayasan Taruma Negara</h4>
                <p>
                  Yales Foundation expanded into &quot;Akademi Yayasan Taruma Negara&quot;, offering Executive
                  Diplomas in Management and Secretarial studies.
                </p>
              </div>
              <div className="tl-dot"><i></i></div>
              <div className="tl-empty"></div>
            </div>

            {/* 2011 : RIGHT (HIGHLIGHT) */}
            <div className="tl-row right hl rv">
              <div className="tl-empty"></div>
              <div className="tl-dot"><i></i></div>
              <div className="tl-card">
                <div className="yr">2011</div>
                <span className="tag">Milestone</span>
                <h4>ASEAN University Officially Founded</h4>
                <p>
                  ASEAN University was officially founded in Labuan, Malaysia, as a significant milestone
                  in regional education standards.
                </p>
              </div>
            </div>

            {/* 2019–Present : LEFT (CURRENT) */}
            <div className="tl-row left now rv">
              <div className="tl-card">
                <div className="yr">2019 – Present</div>
                <span className="tag">Global Expansion</span>
                <h4>Rebranded as ASEAN University International</h4>
                <p>
                  Rebranded as ASEAN University International in Kuala Lumpur. Partnering with global
                  institutions like Al-Khalifa Business School and ALFA University College.
                </p>
              </div>
              <div className="tl-dot"><i></i></div>
              <div className="tl-empty"></div>
            </div>
          </div>
        </div>
      </section>

      {/* 5. CORE VALUES */}
      <section className="sec" id="values">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">03 — Core Values</div>
            <h2>Empat pilar yang membentuk AUI</h2>
          </div>
          <div className="val-grid rv">
            <div className="vcard">
              <div className="ic">◉</div>
              <h3>Accessibility</h3>
              <p>Every programme can be studied from anywhere in the world — online, or at your own pace.</p>
            </div>
            <div className="vcard">
              <div className="ic">✦</div>
              <h3>Quality</h3>
              <p>Whichever study method you choose, you&apos;ll experience the same high‑quality, accredited education programme of study.</p>
            </div>
            <div className="vcard">
              <div className="ic">◎</div>
              <h3>Community</h3>
              <p>A powerful, connected network right across the globe — nearly 30,000 students and 20,000 alumni.</p>
            </div>
            <div className="vcard">
              <div className="ic">◐</div>
              <h3>Impact</h3>
              <p>Making an unbelievably positive impact on academic and professional growth — 90% employability within 6 months.</p>
            </div>
          </div>
        </div>
      </section>

      {/* 6. FACULTIES */}
      <section
        className="sec"
        style={{
          background: '#fff',
          borderTop: '1px solid var(--line-soft)',
          borderBottom: '1px solid var(--line-soft)',
        }}
      >
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">04 — Academic Faculties</div>
            <h2>5 Faculties · 20+ Research Centers</h2>
            <p>
              Lima fakultas akademik yang menaungi seluruh programme AUI, didukung oleh lebih dari 20 pusat
              riset dan 100+ kemitraan industri.
            </p>
          </div>
          <div className="fac-grid">
            <article className="fac rv">
              <figure>
                <img
                  loading="lazy"
                  src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=70"
                  alt="Business and Management"
                />
              </figure>
              <div className="body">
                <div className="cnt">Faculty 01</div>
                <h3>Business &amp; Management</h3>
                <p>
                  Programme bisnis dengan penekanan pada leadership, global mindset, dan kesiapan industri terkini.
                </p>
                <Link href="/academic" className="link-gold">Explore →</Link>
              </div>
            </article>

            <article className="fac rv">
              <figure>
                <img
                  loading="lazy"
                  src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=800&q=70"
                  alt="Technology and Computing"
                />
              </figure>
              <div className="body">
                <div className="cnt">Faculty 02</div>
                <h3>Technology &amp; Computing</h3>
                <p>
                  Komputasi, rekayasa perangkat lunak, dan kecerdasan buatan untuk menghadapi tantangan digital global.
                </p>
                <Link href="/academic" className="link-gold">Explore →</Link>
              </div>
            </article>

            <article className="fac rv">
              <figure>
                <img
                  loading="lazy"
                  src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=800&q=70"
                  alt="Health Sciences"
                />
              </figure>
              <div className="body">
                <div className="cnt">Faculty 03</div>
                <h3>Health Sciences</h3>
                <p>
                  Pendidikan kesehatan berbasis praktik dan empati, mempersiapkan lulusan untuk industri kesehatan masa kini.
                </p>
                <Link href="/academic" className="link-gold">Explore →</Link>
              </div>
            </article>

            <article className="fac rv">
              <figure>
                <img
                  loading="lazy"
                  src="https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=800&q=70"
                  alt="Arts and Humanities"
                />
              </figure>
              <div className="body">
                <div className="cnt">Faculty 04</div>
                <h3>Arts &amp; Humanities</h3>
                <p>
                  Kreativitas, budaya, dan ekspresi lintas zaman — membangun pemahaman mendalam tentang manusia dan masyarakat.
                </p>
                <Link href="/academic" className="link-gold">Explore →</Link>
              </div>
            </article>

            <article className="fac rv">
              <figure>
                <img
                  loading="lazy"
                  src="https://images.unsplash.com/photo-1466611653911-95081537e5b7?auto=format&fit=crop&w=800&q=70"
                  alt="Environmental Studies"
                />
              </figure>
              <div className="body">
                <div className="cnt">Faculty 05</div>
                <h3>Environmental Studies</h3>
                <p>
                  Memahami dan mengatasi tantangan lingkungan menuju masa depan yang berkelanjutan.
                </p>
                <Link href="/academic" className="link-gold">Explore →</Link>
              </div>
            </article>

            <article
              className="fac rv"
              style={{
                background: 'var(--brand)',
                color: '#fff',
                border: 0,
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'center',
                padding: '48px 36px',
              }}
            >
              <h3 style={{ color: '#fff', fontSize: '24px', marginBottom: '12px' }}>
                20+ Research Centers
              </h3>
              <p style={{ color: 'rgba(255,255,255,.78)', fontSize: '14px', flex: 1, marginBottom: '24px' }}>
                Setiap fakultas didukung oleh pusat riset yang menjembatani akademik dan industri di tingkat global.
              </p>
              <Link href="/academic" className="btn btn-gold" style={{ alignSelf: 'flex-start' }}>
                Learn More →
              </Link>
            </article>
          </div>
        </div>
      </section>

      {/* 7. LEADERSHIP */}
      <section className="sec" id="leadership">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">05 — Leadership</div>
            <h2>Dipimpin oleh professionals</h2>
            <p>
              Tim pimpinan AUI yang berpengalaman dalam pendidikan tinggi, riset, dan kemitraan industri global.
            </p>
          </div>
          <div className="lead-grid">
            <div className="lead rv">
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=200&q=70"
                alt="Chancellor"
              />
              <b>Sample Chancellor</b>
              <span>Chancellor</span>
              <em>Global Education Strategy</em>
            </div>
            <div className="lead rv">
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=200&q=70"
                alt="Vice-Chancellor"
              />
              <b>Sample Vice-Chancellor</b>
              <span>Vice-Chancellor</span>
              <em>Academic &amp; Research</em>
            </div>
            <div className="lead rv">
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=70"
                alt="Dean of Faculties"
              />
              <b>Sample Dean</b>
              <span>Dean of Faculties</span>
              <em>Programme Quality</em>
            </div>
            <div className="lead rv">
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=200&q=70"
                alt="Director of Admissions"
              />
              <b>Sample Director</b>
              <span>Director of Admissions</span>
              <em>Student Recruitment</em>
            </div>
          </div>
        </div>
      </section>

      {/* 8. QUOTE */}
      <section className="quote-band">
        <div className="wrap">
          <blockquote>
            &quot;AUI ensures that you have the resources to succeed in your studies along the way, making an
            unbelievably positive impact on your academic and professional growth.&quot;
          </blockquote>
          <cite>— ASEAN University International</cite>
        </div>
      </section>

      {/* 9. CAMPUS LIFE */}
      <section className="sec">
        <div className="wrap">
          <div className="life-split">
            <div className="rv">
              <div className="eyebrow">06 — Life at AUI</div>
              <h2>More Than a Programme</h2>
              <p>
                Be part of a powerful, connected network right across the globe — a learning experience
                that&apos;s both personalised and flexible, from your first enquiry to your career after graduation.
              </p>
              <p>
                With nearly 30,000 students and 20,000 alumni, we offer a part of a powerful, connected
                network right across the globe.
              </p>
              <Link href="/academic" className="btn btn-line">Discover Campus Life →</Link>
            </div>
            <div className="life-mosaic rv">
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=700&q=70"
                alt="Students Collaboration"
              />
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=500&q=70"
                alt="Community Spirit"
              />
              <img
                loading="lazy"
                src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=500&q=70"
                alt="Networking and Learning"
              />
            </div>
          </div>
        </div>
      </section>

      {/* 10. PARTNERS */}
      <section className="sec" style={{ paddingTop: 0 }}>
        <div className="wrap">
          <div className="sec-head rv" style={{ marginBottom: '36px' }}>
            <div className="eyebrow">07 — Partnerships</div>
            <h2>100+ industry partnerships worldwide</h2>
            <p>Partner institusi global AUI.</p>
          </div>
          <div className="partners rv">
            <div>Al-Khalifa Business School</div>
            <div>ALFA University College</div>
            <div>Explomo Singapore</div>
            <div>Global Academic Consortium</div>
            <div>ASEAN EduConnect</div>
            <div>International Research Forum</div>
          </div>
        </div>
      </section>

      {/* 11. CTA VISIT */}
      <section className="visit">
        <div className="wrap">
          <div>
            <h3>Ready to join AUI?</h3>
            <p>Tuition from MYR 35,000 · Scholarship Available · Study from anywhere in the world.</p>
          </div>
          <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
            <Link href="/#tuition" className="btn btn-brand">
              Apply now
            </Link>
            <Link href="/#contact" className="btn btn-ghost">
              Contact Admissions
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
