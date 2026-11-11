

import React, { useEffect, useRef } from 'react';
import Link from './Link';

interface AdmissionsViewProps {
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

export default function AdmissionsView({ onNavigate }: AdmissionsViewProps) {
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

  return (
    <main className="view is-on" id="view-admissions" ref={containerRef}>
      {/* Skip to Content Anchor */}
      <div id="content" tabIndex={-1} style={{ outline: 'none' }} />

      {/* HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1800&q=70"
          alt="AUI admissions"
        />
        <div className="wrap">
          <div className="eyebrow">Admissions · Programme Criteria</div>
          <h1>Programmatic Criteria</h1>
          <div className="sub">Program-Specific Admission Manual</div>
          <p className="lede">
            Granular entry requirements, track-specific benchmarks, and milestones from Undergraduate to Doctorate levels.
          </p>
        </div>
      </section>

      {/* QUICK STATS */}
      <div className="qstrip">
        <div className="wrap">
          <div className="qs">
            <b>3 Tracks</b>
            <span>Undergraduate · Master · Doctoral</span>
          </div>
          <div className="qs">
            <b>Study From Anywhere</b>
            <span>Online or at your own pace</span>
          </div>
          <div className="qs">
            <b>Scholarship Available</b>
            <span>All programme levels</span>
          </div>
        </div>
      </div>

      {/* ============================================================
           01 — PROGRAMME TRACKS
           ============================================================ */}
      <section className="sec" id="tracks">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">01 — Admission Tracks</div>
            <h2>Programme-specific requirements</h2>
            <p>
              Choose your track below to view granular entry requirements, academic benchmarks, and required deliverables.
            </p>
          </div>

          <div className="track-grid">
            {/* ===== UNDERGRADUATE ===== */}
            <div className="track rv" id="track-ug">
              <div className="track-top">
                <div className="track-info">
                  <div className="num">Track 01</div>
                  <h3>Undergraduate Track</h3>
                  <div className="prog-name">International Bachelor Program (S1)</div>
                  <div className="dur">
                    <i>⏱</i> Average Duration: 3.5 – 4 Years
                  </div>
                  <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <a href="#contact" className="btn btn-gold">
                      Apply Now
                    </a>
                    <span className="btn btn-line">Tuition — MYR 35,000</span>
                  </div>
                </div>
                <div className="track-visual">
                  <img
                    loading="lazy"
                    src="/img/campus-life.jpg"
                    alt="Undergraduate students - ASEAN University International"
                  />
                </div>
              </div>
              <div className="track-body">
                <h4>
                  Academic &amp; Language Benchmarks
                  <em>Entry requirements</em>
                </h4>
                <div className="req-grid">
                  <div className="req-block">
                    <h5>Academic Requirements</h5>
                    <ul>
                      <li>High School Diploma: National, IB Diploma (min. 32 pts), or A-Levels (min. BBB)</li>
                      <li>GPA Threshold: Minimum equivalent of 3.00/4.00 or an 80% aggregate raw score</li>
                      <li>Standardized Aptitude: SAT (1250+) or ACT (26+) heavily preferred for premium tiers</li>
                    </ul>
                  </div>
                  <div className="req-block">
                    <h5>English Proficiency</h5>
                    <ul>
                      <li>IELTS ≥ 6.0 overall band</li>
                      <li>TOEFL iBT ≥ 80</li>
                    </ul>
                  </div>
                </div>
                <div className="dossier">
                  <h5>Required Portfolio Dossier</h5>
                  <ol>
                    <li>
                      <div>
                        <b>Certified Grade Transcripts</b>
                        <span>Officially translated copies covering the final 3 structural school years.</span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Personal Statement Essay</b>
                        <span>
                          A 500-word statement highlighting leadership experiences, community involvement, and your core
                          motivations for chosen majors.
                        </span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Institutional Endorsement</b>
                        <span>
                          1 mandatory Recommendation Letter signed by your High School Principal, Academic Counselor, or
                          Core Class Teacher.
                        </span>
                      </div>
                    </li>
                  </ol>
                </div>
              </div>
            </div>

            {/* ===== GRADUATE ===== */}
            <div className="track rv" id="track-pg">
              <div className="track-top">
                <div className="track-info">
                  <div className="num">Track 02</div>
                  <h3>Graduate Track</h3>
                  <div className="prog-name">International Master Program (S2)</div>
                  <div className="dur">
                    <i>⏱</i> Average Duration: 1.5 – 2 Years
                  </div>
                  <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <a href="#contact" className="btn btn-gold">
                      Apply Now
                    </a>
                    <span className="btn btn-line">Tuition — MYR 45,000</span>
                  </div>
                </div>
                <div className="track-visual">
                  <img
                    loading="lazy"
                    src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=900&q=70"
                    alt="Master students"
                  />
                </div>
              </div>
              <div className="track-body">
                <h4>
                  Academic &amp; Language Benchmarks
                  <em>Entry requirements</em>
                </h4>
                <div className="req-grid">
                  <div className="req-block">
                    <h5>Academic Requirements</h5>
                    <ul>
                      <li>
                        Academic Credential: Accredited Bachelor&apos;s Degree (B.A., B.Sc., B.Eng.) from a recognized
                        global institution
                      </li>
                      <li>
                        GPA Threshold: Minimum cumulative GPA of 3.00 on a 4.00 scale (or equivalent class standing)
                      </li>
                      <li>Graduate Testing: GRE or GMAT scores may be mandated for STEM and Business fields</li>
                    </ul>
                  </div>
                  <div className="req-block">
                    <h5>English Proficiency</h5>
                    <ul>
                      <li>IELTS ≥ 6.5 overall band</li>
                      <li>TOEFL iBT ≥ 85</li>
                    </ul>
                  </div>
                </div>
                <div className="dossier">
                  <h5>Required Portfolio Dossier</h5>
                  <ol>
                    <li>
                      <div>
                        <b>Professional Curriculum Vitae (CV)</b>
                        <span>
                          Highlights structural industry practice, past internships, published work, or academic group
                          projects.
                        </span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Statement of Purpose (SoP)</b>
                        <span>
                          A detailed 750-to-1000 word essay specifying your academic focus, career objectives, and
                          research alignment.
                        </span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Academic Testimonials</b>
                        <span>
                          2 formal reference letters submitted directly by senior university professors or corporate
                          managers.
                        </span>
                      </div>
                    </li>
                  </ol>
                </div>
              </div>
            </div>

            {/* ===== POSTGRADUATE RESEARCH ===== */}
            <div className="track rv" id="track-dr">
              <div className="track-top">
                <div className="track-info">
                  <div className="num">Track 03</div>
                  <h3>Postgraduate Research Track</h3>
                  <div className="prog-name">Doctoral / Ph.D. Program (S3)</div>
                  <div className="dur">
                    <i>⏱</i> Average Duration: 3 – 5 Years
                  </div>
                  <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <a href="#contact" className="btn btn-gold">
                      Apply Now
                    </a>
                    <span className="btn btn-line">Tuition — MYR 65,000</span>
                  </div>
                </div>
                <div className="track-visual">
                  <img
                    loading="lazy"
                    src="https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=900&q=70"
                    alt="Doctoral research"
                  />
                </div>
              </div>
              <div className="track-body">
                <h4>
                  Academic &amp; Language Benchmarks
                  <em>Entry requirements</em>
                </h4>
                <div className="req-grid">
                  <div className="req-block">
                    <h5>Academic Requirements</h5>
                    <ul>
                      <li>
                        Academic Credential: Validated Master&apos;s degree (M.Sc., M.A., or M.Res.) showing high
                        academic alignment
                      </li>
                      <li>
                        GPA Threshold: Top-tier academic standings, typically requiring a minimum Master&apos;s GPA of
                        3.50/4.00
                      </li>
                      <li>
                        Prior Publications: A strong asset, and often mandatory — proof of Scopus-indexed or internationally
                        peer-reviewed publications
                      </li>
                    </ul>
                  </div>
                  <div className="req-block">
                    <h5>English Proficiency</h5>
                    <ul>
                      <li>IELTS Overall Band ≥ 6.5 – 7.0</li>
                      <li>TOEFL iBT ≥ 90 – 100</li>
                    </ul>
                  </div>
                </div>
                <div className="dossier">
                  <h5>Required Portfolio Dossier</h5>
                  <ol>
                    <li>
                      <div>
                        <b>Full Research Proposal</b>
                        <span>
                          A 1,500 to 3,000-word methodology mapping — background, research questions, literature review,
                          and proposed scientific contributions.
                        </span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Supervisor Invitation Letter</b>
                        <span>
                          Formal confirmation from a tenured faculty member or lab head who agrees to oversee your project
                          before official system submission.
                        </span>
                      </div>
                    </li>
                    <li>
                      <div>
                        <b>Testimonials</b>
                        <span>
                          3 highly credentialed academic evaluations from full professors or your prior S2 research
                          promoters.
                        </span>
                      </div>
                    </li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
           02 — IDENTITY DOCUMENTS
           ============================================================ */}
      <section
        className="sec"
        style={{
          background: '#fff',
          borderTop: '1px solid var(--line-soft)',
          borderBottom: '1px solid var(--line-soft)',
        }}
        id="docs"
      >
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">02 — International Programs</div>
            <h2>Mandatory Identity Documents</h2>
            <p>Admission criteria, expanded academic prerequisites, and student visa pathways for ASEAN Universities.</p>
          </div>
          <div className="docs-grid">
            <div className="doc-card rv">
              <div className="dnum">01</div>
              <h3>National ID Card</h3>
              <p>
                Mainly utilized during the initial account creation on university portals to validate nationality and home
                country background.
              </p>
              <ul>
                <li>High-resolution color scan, fully legible, with zero cropped margins</li>
                <li>
                  Must be officially translated into English by a Certified Sworn Translator if the original format is
                  non-bilingual
                </li>
              </ul>
            </div>
            <div className="doc-card rv">
              <div className="dnum">02</div>
              <h3>International Passport</h3>
              <p>
                The most critical legal document required for issuing the Official Letter of Acceptance (LoA) and
                processing visa sponsorship.
              </p>
              <ul>
                <li>
                  <b>Validity Policy:</b> Minimum of 6 to 18 months remaining validity prior to entry
                </li>
                <li>
                  Specific destination authorities (e.g., Malaysia&apos;s EMGS) mandate a comprehensive scan of{' '}
                  <b>every single page</b>, including blank ones
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
           03 — VISA PROCESS
           ============================================================ */}
      <section className="sec visa" id="visa">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">03 — Visa Pathway</div>
            <h2>Step-by-Step Student Visa Process</h2>
            <p>Comprehensive guide to admission and immigration — five phases from offer letter to student pass endorsement.</p>
          </div>
          <div className="phases rv">
            <div className="phase">
              <div className="pnum">1</div>
              <h4>Receiving Official LoA &amp; Sponsorship Approval</h4>
              <p>
                Upon successfully clearing admissions and securing your first-semester tuition payment, the university
                dispatches your Unconditional LoA alongside official Visa Approval Letters (VAL) or legal institutional
                sponsorships.
              </p>
            </div>
            <div className="phase">
              <div className="pnum">2</div>
              <h4>Visa Application Submission</h4>
              <p>
                Depending on the host country, you either finalize a direct cloud-based digital process (e.g.,
                Singapore&apos;s ICA e-Service) or submit physical files to the corresponding Embassy/Consulate General in
                your home nation to obtain a Single Entry Visa (SEV).
              </p>
            </div>
            <div className="phase">
              <div className="pnum">3</div>
              <h4>Compulsory Medical Surveillance</h4>
              <p>
                Undergoing rigorous diagnostic tests (blood testing, chest X-rays) at accredited panel clinics
                pre-departure. Many regions require a secondary post-arrival routing check at the university medical center
                within 7 days of landing.
              </p>
            </div>
            <div className="phase">
              <div className="pnum">4</div>
              <h4>Border Immigration Clearance</h4>
              <p>
                Present yourself at designated Special International Student counters at the arrival airport. Ready your
                physical Passport, LoA, VAL/IPA documents, and return ticket itinerary to secure a temporary entry
                endorsement stamp.
              </p>
            </div>
            <div className="phase">
              <div className="pnum">5</div>
              <h4>Student Pass Conversion &amp; Endorsement</h4>
              <p>
                Surrender your passport to the university&apos;s International Student Office. They coordinate directly with
                domestic Immigration Headquarters to secure a multi-entry Student Pass sticker/card, typically valid for 1
                year and renewable annually.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
           04 — DASHBOARD TABLE
           ============================================================ */}
      <section className="sec" id="dashboard">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">04 — Requirements Dashboard</div>
            <h2>Global Admission Criteria &amp; Academic Requirements</h2>
            <p>Perbandingan komprehensif persyaratan masuk untuk tiga jenjang programme AUI.</p>
          </div>
          <div className="dash rv">
            <table className="dash-tbl">
              <thead>
                <tr>
                  <th>Evaluation Metric</th>
                  <th>Bachelor (S1)</th>
                  <th>Master (S2)</th>
                  <th>Doctoral / Ph.D (S3)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Primary Identity Documentation</td>
                  <td colSpan={3} style={{ textAlign: 'center', fontStyle: 'italic' }}>
                    Color Scan of National ID Card &amp; Passport Book with a minimum validity threshold of 12 to 18 months
                    out from departure date.
                  </td>
                </tr>
                <tr>
                  <td>Strategic Selection Focus</td>
                  <td>Cumulative High School Performance, Core Aptitude Profiles, &amp; Extracurricular Leadership traits.</td>
                  <td>
                    Undergraduate GPA Metrics, Linear Academic Background, &amp; Structural Professional/Field Experience.
                  </td>
                  <td>Peer-Reviewed Novelty of Research, Empirical Feasibility, &amp; Verified Faculty Supervisor Alignment.</td>
                </tr>
                <tr>
                  <td>English Language Standards</td>
                  <td>
                    <b style={{ color: 'var(--brand)' }}>IELTS ≥ 6.0</b> or{' '}
                    <b style={{ color: 'var(--brand)' }}>TOEFL iBT ≥ 80</b>
                  </td>
                  <td>
                    <b style={{ color: 'var(--brand)' }}>IELTS ≥ 6.5</b> or{' '}
                    <b style={{ color: 'var(--brand)' }}>TOEFL iBT ≥ 85</b>
                  </td>
                  <td>
                    <b style={{ color: 'var(--brand)' }}>IELTS ≥ 6.5–7.0</b> or{' '}
                    <b style={{ color: 'var(--brand)' }}>TOEFL iBT ≥ 90–100</b>
                  </td>
                </tr>
                <tr>
                  <td>Expanded Academic Prerequisites</td>
                  <td>
                    <ul>
                      <li>High School Diploma or Equivalent — Min. 80% or GPA 3.0/4.0</li>
                      <li>IB Diploma (Min. 32–36 pts) or A-Levels (Min. BBB/ABB)</li>
                      <li>SAT score (Min. 1250–1350) if applicable</li>
                    </ul>
                  </td>
                  <td>
                    <ul>
                      <li>Accredited Bachelor&apos;s Degree (B.A./B.Sc.)</li>
                      <li>Minimum GPA of 3.00 on a 4.00 Scale</li>
                      <li>Official Subject-Specific Transcripts</li>
                      <li>GMAT/GRE for Business/Engineering</li>
                    </ul>
                  </td>
                  <td>
                    <ul>
                      <li>Validated Master&apos;s Degree (M.A./M.Sc./M.Res.)</li>
                      <li>Minimum GPA of 3.50 on a 4.00 Scale</li>
                      <li>Undergraduate + Graduate Comprehensive Transcripts</li>
                      <li>Mandatory matching background in prior major</li>
                    </ul>
                  </td>
                </tr>
                <tr>
                  <td>Required Deliverables</td>
                  <td>High School Transcripts, Personal Statement Essay, &amp; Extracurricular Portfolio.</td>
                  <td>Comprehensive Academic CV, Statement of Purpose (SoP), &amp; Preliminary Study Essay.</td>
                  <td>
                    Research Proposal (1,500–3,000 words), Scopus/Indexed Peer-Review Publications, &amp; Supervisor
                    Invitation Letter.
                  </td>
                </tr>
                <tr>
                  <td>Institutional Endorsements</td>
                  <td>1–2 Letters from High School Principal or Subject Teachers.</td>
                  <td>2 Letters from Undergraduate Professors or Professional Supervisors.</td>
                  <td>2–3 Academic Testimonials from Tenured Professors or S2 Research Promoters.</td>
                </tr>
                <tr>
                  <td>Sovereign Visa Demands</td>
                  <td colSpan={3} style={{ textAlign: 'center', fontStyle: 'italic' }}>
                    Official Unconditional University LoA + Host Ministry Legal Approval Voucher + Comprehensive Disease-Free
                    Health Examination + Minimum Liquid Funds Bank Statement (Proof of Financial Solvency).
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* ============================================================
           SOLVENCY NOTE
           ============================================================ */}
      <section style={{ padding: '0 0 104px' }}>
        <div className="wrap">
          <div className="solvency rv">
            <div className="icon">💡</div>
            <div>
              <h3>Critical Solvency Note Regarding Visa Clearances</h3>
              <p>
                Immigration departments across the ASEAN zone uniformly demand verified proof of liquidity via a formal
                Bank Statement. This security fund must comfortably cover tuition fees plus cost of living overheads for at
                least <b style={{ color: 'var(--brand)' }}>1 full calendar academic year</b>. Ensure these funds are
                deposited and matured well before launching formal visa applications.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="visit">
        <div className="wrap">
          <div>
            <h3>Ready to apply?</h3>
            <p>Scholarship Available · Study from anywhere in the world · Tuition from MYR 35,000</p>
          </div>
          <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
            <a href="#tracks" className="btn btn-brand">
              Choose Your Track
            </a>
            <Link href="/contact" className="btn btn-ghost">
              Contact Admissions
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
