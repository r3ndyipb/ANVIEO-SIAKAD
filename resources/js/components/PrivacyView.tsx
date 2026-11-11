

import React, { useEffect, useRef } from 'react';
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

interface PrivacyViewProps {
  onNavigate?: (view: AppView) => void;
}

export default function PrivacyView({ onNavigate }: PrivacyViewProps) {
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

  const handleView = (e: React.MouseEvent, view: AppView) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  const scrollToSection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  return (
    <main className="view is-on" id="view-privacy" ref={containerRef}>
      {/* 1. HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=1800&q=70"
          alt="ASEAN University International Legal & Compliance"
        />
        <div className="wrap">
          <div className="eyebrow">Institutional Governance &amp; Data Protection</div>
          <h1>Privacy Policy</h1>
          <div className="sub">Personal Data Protection Notice (PDPA 2010 Compliance)</div>
          <p className="lede">
            ASEAN University International (AUI), operated under ASEAN International Education SDN.
            BHD., is firmly committed to safeguarding the personal data, privacy, and confidentiality
            of our prospective students, enrolled scholars, alumni, faculty, and website visitors in
            strict adherence to the Personal Data Protection Act 2010 (Malaysia) and global privacy
            standards.
          </p>

          <div className="contact-hero-chips rv">
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Statutory Basis: PDPA 2010 (Act 709, Malaysia)
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Last Revised: October 2026
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Data Protection Officer: dpo@aui.university
            </span>
          </div>
        </div>
      </section>

      {/* 2. MAIN CONTENT LAYOUT */}
      <section className="sec" style={{ background: '#fff' }}>
        <div className="wrap">
          <div className="legal-layout">
            {/* Left: Sticky Table of Contents */}
            <aside className="legal-sidebar rv">
              <div className="legal-toc-card">
                <div className="legal-toc-title">Table of Contents</div>
                <nav className="legal-toc-nav">
                  <a href="#sec-1" onClick={(e) => { e.preventDefault(); scrollToSection('sec-1'); }}>
                    1. Institutional Scope &amp; Controller
                  </a>
                  <a href="#sec-2" onClick={(e) => { e.preventDefault(); scrollToSection('sec-2'); }}>
                    2. Categories of Personal Data Collected
                  </a>
                  <a href="#sec-3" onClick={(e) => { e.preventDefault(); scrollToSection('sec-3'); }}>
                    3. Sources &amp; Collection Methods
                  </a>
                  <a href="#sec-4" onClick={(e) => { e.preventDefault(); scrollToSection('sec-4'); }}>
                    4. Purposes of Data Processing
                  </a>
                  <a href="#sec-5" onClick={(e) => { e.preventDefault(); scrollToSection('sec-5'); }}>
                    5. Legal Grounds for Processing
                  </a>
                  <a href="#sec-6" onClick={(e) => { e.preventDefault(); scrollToSection('sec-6'); }}>
                    6. Disclosure to Third Parties &amp; Authorities
                  </a>
                  <a href="#sec-7" onClick={(e) => { e.preventDefault(); scrollToSection('sec-7'); }}>
                    7. International Data Transfers
                  </a>
                  <a href="#sec-8" onClick={(e) => { e.preventDefault(); scrollToSection('sec-8'); }}>
                    8. Data Security &amp; Encryption Standards
                  </a>
                  <a href="#sec-9" onClick={(e) => { e.preventDefault(); scrollToSection('sec-9'); }}>
                    9. Retention &amp; Archival Schedule
                  </a>
                  <a href="#sec-10" onClick={(e) => { e.preventDefault(); scrollToSection('sec-10'); }}>
                    10. Your Statutory Rights as Data Subject
                  </a>
                  <a href="#sec-11" onClick={(e) => { e.preventDefault(); scrollToSection('sec-11'); }}>
                    11. Cookies &amp; Digital Tracking Technologies
                  </a>
                  <a href="#sec-12" onClick={(e) => { e.preventDefault(); scrollToSection('sec-12'); }}>
                    12. Contact Data Protection Officer (DPO)
                  </a>
                </nav>

                <div className="legal-toc-help">
                  <strong>Need Privacy Support?</strong>
                  <p>Our Data Protection Office is available for any access or correction inquiries.</p>
                  <a href="mailto:dpo@aui.university" className="link-gold">
                    ✉ dpo@aui.university
                  </a>
                </div>
              </div>
            </aside>

            {/* Right: Comprehensive Legal Text */}
            <article className="legal-article rv">
              {/* SECTION 1 */}
              <section className="legal-clause" id="sec-1">
                <h2>1. Institutional Scope &amp; Data Controller</h2>
                <p>
                  This Privacy Policy (“Policy”) governs all personal data processing activities
                  conducted by <b>ASEAN International Education SDN. BHD.</b> (Registration No.
                  202301032890), operating as <b>ASEAN University International (“AUI”)</b>, having its
                  registered office at <i>F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650
                  Bandar Baru Bangi, Selangor, Malaysia</i>.
                </p>
                <p>
                  For the purposes of the <b>Personal Data Protection Act 2010 of Malaysia (“PDPA 2010”)</b>
                  and applicable international data protection standards (including the EU General Data
                  Protection Regulation where applicable to European applicants), ASEAN International
                  Education SDN. BHD. acts as the Data User / Data Controller responsible for the
                  protection of personal data collected through our official websites, student
                  management portal (<code>portal-aui</code>), learning management systems (LMS), and
                  physical admissions offices.
                </p>
              </section>

              {/* SECTION 2 */}
              <section className="legal-clause" id="sec-2">
                <h2>2. Categories of Personal Data Collected</h2>
                <p>
                  In order to facilitate university admissions, program enrollment, academic instruction,
                  immigration reporting, and credential issuance, AUI collects and processes various
                  categories of personal data, including but not limited to:
                </p>
                <ul>
                  <li>
                    <b>Identity &amp; Biographical Data:</b> Full legal name, date of birth, gender,
                    nationality, national identity card (MyKad/KTP) number, and international passport
                    details.
                  </li>
                  <li>
                    <b>Contact Details:</b> Residential postal address, active email address, mobile/WhatsApp
                    telephone number, and emergency contact details.
                  </li>
                  <li>
                    <b>Academic History &amp; Credentials:</b> Prior high school, diploma, or university
                    degree certificates, academic transcripts, credit transfer evaluation portfolios,
                    standardized language test scores (IELTS, TOEFL), and curriculum vitae (CV).
                  </li>
                  <li>
                    <b>Immigration &amp; Residency Data:</b> For international applicants requiring student
                    visas in Malaysia or partner ASEAN member states, student pass applications, visa
                    approval letters (VAL), and Education Malaysia Global Services (EMGS) documentation.
                  </li>
                  <li>
                    <b>Financial &amp; Billing Information:</b> Bank account holder details, telegraphic
                    transfer receipts, proof of tuition payment, invoice records, and scholarship award
                    records. (Note: AUI does not store raw credit card CVV numbers).
                  </li>
                  <li>
                    <b>Digital Portal &amp; Technical Usage Data:</b> IP address, device telemetry, browser
                    type, session duration, portal access timestamps, course module completion logs, and
                    examination submission metadata.
                  </li>
                </ul>
              </section>

              {/* SECTION 3 */}
              <section className="legal-clause" id="sec-3">
                <h2>3. Sources &amp; Collection Methods</h2>
                <p>Personal data is obtained through lawful and transparent channels, specifically:</p>
                <ol>
                  <li>
                    <b>Direct Submission:</b> When you complete our online admissions form, submit
                    inquiries via our contact page, upload identity documents, or correspond with our
                    secretariat via email or WhatsApp.
                  </li>
                  <li>
                    <b>Academic Institutions &amp; Partners:</b> From secondary schools, prior universities,
                    or institutional partners submitting credit recognition transcripts upon your formal
                    request.
                  </li>
                  <li>
                    <b>Automated Telemetry:</b> Through session cookies and server access logs when you
                    interact with our digital portals and web platforms.
                  </li>
                </ol>
              </section>

              {/* SECTION 4 */}
              <section className="legal-clause" id="sec-4">
                <h2>4. Purposes of Data Processing</h2>
                <p>
                  AUI processes personal data strictly for legitimate educational, academic, and
                  administrative purposes, which include:
                </p>
                <ul>
                  <li>Evaluating admission qualifications and issuing official Offer Letters.</li>
                  <li>Enrolling students into Bachelor, Master, or Doctoral degree curricula.</li>
                  <li>Administering online examinations, dissertations, and academic grading records.</li>
                  <li>
                    Facilitating Education Malaysia Global Services (EMGS) and Malaysian Immigration
                    Department student visa endorsements.
                  </li>
                  <li>Verifying tuition payments, issuing official tax invoices and receipts.</li>
                  <li>
                    Issuing official degree scrolls, diplomas, academic transcripts, and graduate verification
                    credentials to future employers and credential evaluation agencies.
                  </li>
                  <li>
                    Complying with statutory audits from higher education quality assurance bodies
                    (e.g., EAHEA, QAHE, ICCAC).
                  </li>
                </ul>
              </section>

              {/* SECTION 5 */}
              <section className="legal-clause" id="sec-5">
                <h2>5. Legal Grounds for Processing</h2>
                <p>
                  Our processing operations are anchored on recognized lawful grounds under Section 6 of the
                  PDPA 2010 and international regulations:
                </p>
                <div className="legal-box-grid">
                  <div className="legal-box">
                    <h4>Contractual Necessity</h4>
                    <p>
                      Processing is necessary to fulfill the educational agreement entered into between the
                      enrolled student and ASEAN University International.
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>Explicit Consent</h4>
                    <p>
                      Provided by the applicant or student during application submission, document
                      uploading, and online portal onboarding.
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>Legal &amp; Statutory Compliance</h4>
                    <p>
                      Adhering to mandatory Malaysian immigration, tax reporting, and educational regulatory
                      oversight laws.
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>Legitimate Educational Interests</h4>
                    <p>
                      Maintaining institutional academic standards, preventing plagiarism, and ensuring
                      uninterrupted portal security.
                    </p>
                  </div>
                </div>
              </section>

              {/* SECTION 6 */}
              <section className="legal-clause" id="sec-6">
                <h2>6. Disclosure to Third Parties &amp; Authorities</h2>
                <p>
                  AUI strictly adheres to confidentiality protocols. We never sell, rent, or lease student
                  personal data to marketing brokers. Disclosures are limited solely to authorized entities:
                </p>
                <ul>
                  <li>
                    <b>Governmental &amp; Immigration Authorities:</b> Ministry of Higher Education Malaysia,
                    Immigration Department of Malaysia, and EMGS for visa and regulatory compliance.
                  </li>
                  <li>
                    <b>Accreditation &amp; Quality Councils:</b> International bodies certifying AUI degree
                    frameworks (e.g., European Association for Higher Education Advancement, QAHE, ICCAC).
                  </li>
                  <li>
                    <b>Authorized Banking Institutions:</b> CIMB Islamic Bank Malaysia, Bank Negara
                    Indonesia (BNI), and Bank Mandiri for direct telegraphic payment reconciliation.
                  </li>
                  <li>
                    <b>Verified Technology Infrastructure Providers:</b> Secure cloud hosting and Learning
                    Management System (LMS) providers bound by strict Data Processing Agreements (DPAs).
                  </li>
                </ul>
              </section>

              {/* SECTION 7 */}
              <section className="legal-clause" id="sec-7">
                <h2>7. International Data Transfers</h2>
                <p>
                  As an international institution serving students across Southeast Asia and globally,
                  personal data may be accessed by our academic faculty, thesis supervisors, or external
                  examiners located in Malaysia, Indonesia, Singapore, and European partner centers.
                </p>
                <p>
                  Where cross-border data transfer occurs, AUI ensures appropriate contractual and technical
                  safeguards are enacted to ensure that the recipient country provides an equivalent standard
                  of data protection to that prescribed under Malaysia&apos;s PDPA 2010.
                </p>
              </section>

              {/* SECTION 8 */}
              <section className="legal-clause" id="sec-8">
                <h2>8. Data Security &amp; Encryption Standards</h2>
                <p>
                  AUI employs defense-in-depth security architectures to protect electronic and hardcopy
                  personal records:
                </p>
                <ul>
                  <li>
                    <b>Transport Encryption:</b> All web and portal traffic is mandated over HTTPS with
                    Transport Layer Security (TLS 1.3) utilizing strong cryptographic cipher suites.
                  </li>
                  <li>
                    <b>Rest Encryption:</b> Student records and financial databases are encrypted at rest
                    utilizing industry-standard Advanced Encryption Standard (AES-256).
                  </li>
                  <li>
                    <b>Access Governance:</b> Strict Role-Based Access Control (RBAC) and Multi-Factor
                    Authentication (MFA) ensure that only authorized administrative staff have visibility into
                    sensitive records.
                  </li>
                  <li>
                    <b>Security Audits:</b> Periodic vulnerability assessments and penetration testing on
                    student-facing portals.
                  </li>
                </ul>
              </section>

              {/* SECTION 9 */}
              <section className="legal-clause" id="sec-9">
                <h2>9. Retention &amp; Archival Schedule</h2>
                <p>
                  Personal data is retained only for the duration necessary to satisfy academic, legal, and
                  accreditation mandates:
                </p>
                <div className="table-wrap">
                  <table className="ttable">
                    <thead>
                      <tr>
                        <th>Record Category</th>
                        <th>Retention Period</th>
                        <th>Statutory / Institutional Rationale</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>
                          <b>Permanent Academic Transcripts &amp; Degree Records</b>
                        </td>
                        <td>Indefinite (Permanent)</td>
                        <td>Lifelong degree verification and graduate credential authentication.</td>
                      </tr>
                      <tr>
                        <td>
                          <b>Tuition Payment &amp; Billing Vouchers</b>
                        </td>
                        <td>7 Years</td>
                        <td>Malaysian Inland Revenue Board (LHDN) and statutory financial audit compliance.</td>
                      </tr>
                      <tr>
                        <td>
                          <b>Unsuccessful Applicant Files</b>
                        </td>
                        <td>12 Months</td>
                        <td>Re-evaluation requests or dispute resolution, after which files are securely shredded.</td>
                      </tr>
                      <tr>
                        <td>
                          <b>Student Pass &amp; Immigration Files</b>
                        </td>
                        <td>5 Years Post-Graduation</td>
                        <td>Malaysian Immigration Department record-keeping guidelines.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>

              {/* SECTION 10 */}
              <section className="legal-clause" id="sec-10">
                <h2>10. Your Statutory Rights as Data Subject</h2>
                <p>Under the Malaysian PDPA 2010, students and website users enjoy distinct legal rights:</p>
                <ul>
                  <li>
                    <b>Right of Access:</b> You are entitled to request a copy of personal data maintained in
                    our university records.
                  </li>
                  <li>
                    <b>Right of Correction:</b> You have the right to request correction of inaccurate,
                    incomplete, or out-of-date personal data.
                  </li>
                  <li>
                    <b>Right to Withdraw Consent:</b> You may withdraw consent for optional communications or
                    marketing notices at any time without impacting your academic standing.
                  </li>
                  <li>
                    <b>Right to Prevent Damage or Distress:</b> Request that we halt processing that is
                    unwarranted and causes substantial damage or distress.
                  </li>
                </ul>
                <p>
                  To exercise these rights, submit a written Data Subject Request to{' '}
                  <b>dpo@aui.university</b> accompanied by valid identity verification.
                </p>
              </section>

              {/* SECTION 11 */}
              <section className="legal-clause" id="sec-11">
                <h2>11. Cookies &amp; Digital Tracking Technologies</h2>
                <p>
                  Our portals utilize cookies to maintain session states, authenticate users on{' '}
                  <code>portal-aui</code>, remember user preferences, and analyze anonymized site traffic
                  patterns. You can configure your browser to decline optional cookies; however, essential
                  session cookies are necessary for secure portal login.
                </p>
              </section>

              {/* SECTION 12 */}
              <section className="legal-clause" id="sec-12">
                <h2>12. Contact Data Protection Officer (DPO)</h2>
                <p>
                  If you have inquiries, feedback, or complaints regarding how ASEAN University International
                  manages your personal data, please contact our designated Data Protection Officer:
                </p>

                <div className="legal-dpo-card">
                  <h4>Data Protection Office</h4>
                  <p>
                    <b>ASEAN International Education SDN. BHD.</b>
                    <br />
                    F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15,
                    <br />
                    43650 Bandar Baru Bangi, Selangor Darul Ehsan, Malaysia
                  </p>
                  <p>
                    <b>Email:</b>{' '}
                    <a href="mailto:dpo@aui.university" className="link-gold">
                      dpo@aui.university
                    </a>{' '}
                    / <a href="mailto:admin@aui.university">admin@aui.university</a>
                    <br />
                    <b>Telephone:</b> <a href="tel:+60165680906">+60 165 680 906</a>
                  </p>
                </div>
              </section>
            </article>
          </div>
        </div>
      </section>

      {/* 3. CONTACT BAND */}
      <section className="cband">
        <div className="wrap">
          <h2>Have Questions Regarding Student Governance?</h2>
          <p>
            Connect with our university secretariat or review our admissions policies and institutional terms.
          </p>
          <div className="cband-actions">
            <Link
              href="/contact"
              onClick={(e) => handleView(e, 'contact')}
              className="btn btn-gold"
            >
              Contact University Secretariat &rarr;
            </Link>
            <Link
              href="/terms"
              onClick={(e) => handleView(e, 'terms')}
              className="btn btn-line"
            >
              Read Terms of Use
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
