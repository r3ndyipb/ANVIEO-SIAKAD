

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

interface TermsViewProps {
  onNavigate?: (view: AppView) => void;
}

export default function TermsView({ onNavigate }: TermsViewProps) {
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
    <main className="view is-on" id="view-terms" ref={containerRef}>
      {/* 1. HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=1800&q=70"
          alt="ASEAN University International Academic & Legal Terms"
        />
        <div className="wrap">
          <div className="eyebrow">Institutional Regulations &amp; Student Agreement</div>
          <h1>Terms of Use</h1>
          <div className="sub">University Digital Platforms, Enrolment Agreement &amp; Code of Conduct</div>
          <p className="lede">
            These Terms of Use delineate the legal agreement, operational regulations, and academic
            honor codes governing your access to ASEAN University International (“AUI”) digital
            portals, courseware, and student services under ASEAN International Education SDN. BHD.
          </p>

          <div className="contact-hero-chips rv">
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Governing Law: Malaysia &amp; International Standards
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Effective Date: Academic Year 2026 / 2027
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Legal Counsel: legal@aui.university
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
                  <a href="#t-sec-1" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-1'); }}>
                    1. Acceptance of Terms &amp; Eligibility
                  </a>
                  <a href="#t-sec-2" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-2'); }}>
                    2. User Accounts &amp; Portal Security
                  </a>
                  <a href="#t-sec-3" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-3'); }}>
                    3. Intellectual Property &amp; Courseware
                  </a>
                  <a href="#t-sec-4" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-4'); }}>
                    4. Academic Integrity &amp; Conduct
                  </a>
                  <a href="#t-sec-5" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-5'); }}>
                    5. Tuition Fees, Billing &amp; Refunds
                  </a>
                  <a href="#t-sec-6" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-6'); }}>
                    6. Distance Learning &amp; Service Uptime
                  </a>
                  <a href="#t-sec-7" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-7'); }}>
                    7. Degree Conferral &amp; Accreditations
                  </a>
                  <a href="#t-sec-8" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-8'); }}>
                    8. Disclaimers &amp; Limitation of Liability
                  </a>
                  <a href="#t-sec-9" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-9'); }}>
                    9. Suspension &amp; Termination of Status
                  </a>
                  <a href="#t-sec-10" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-10'); }}>
                    10. Governing Law &amp; Jurisdiction
                  </a>
                  <a href="#t-sec-11" onClick={(e) => { e.preventDefault(); scrollToSection('t-sec-11'); }}>
                    11. Modifications &amp; Legal Inquiries
                  </a>
                </nav>

                <div className="legal-toc-help">
                  <strong>Need Academic Counsel?</strong>
                  <p>Inquiries regarding matriculation contracts or student handbook regulations.</p>
                  <a href="mailto:legal@aui.university" className="link-gold">
                    ✉ legal@aui.university
                  </a>
                </div>
              </div>
            </aside>

            {/* Right: Comprehensive Legal Text */}
            <article className="legal-article rv">
              {/* SECTION 1 */}
              <section className="legal-clause" id="t-sec-1">
                <h2>1. Acceptance of Terms &amp; Eligibility</h2>
                <p>
                  By accessing, browsing, registering for, or utilizing any website, digital portal
                  (including <code>portal-aui</code> and our Learning Management System), or academic
                  service owned and operated by <b>ASEAN International Education SDN. BHD.</b> (“AUI”, “we”,
                  “us”, or “our”), you acknowledge that you have read, understood, and irrevocably agree to
                  be bound by these Terms of Use and our associated{' '}
                  <Link
                    href="/privacy"
                    onClick={(e) => handleView(e, 'privacy')}
                    className="link-gold"
                  >
                    Privacy Policy
                  </Link>
                  .
                </p>
                <p>
                  To be eligible for enrollment in AUI higher education programs (Bachelor, Master,
                  Doctoral), applicants must possess the requisite secondary education or degree
                  credentials recognized by their national ministry of education or equivalent, and be at
                  least 18 years of age (or possess certified consent from a legal guardian).
                </p>
              </section>

              {/* SECTION 2 */}
              <section className="legal-clause" id="t-sec-2">
                <h2>2. User Accounts, Student Credentials &amp; Portal Security</h2>
                <p>
                  Enrolled scholars are granted unique, non-transferable access credentials to the AUI
                  Student Portal (<code>portal-aui</code>). You are strictly responsible for maintaining
                  the confidentiality of your login credentials and password.
                </p>
                <ul>
                  <li>
                    <b>Prohibition of Account Sharing:</b> Sharing credentials, student ID numbers, or exam
                    passkeys with any third party, tutoring service, or automated bot is strictly prohibited
                    and constitutes grounds for immediate academic suspension.
                  </li>
                  <li>
                    <b>Notification Obligation:</b> You agree to notify the University Registrar immediately at{' '}
                    <b>admin@aui.university</b> if you suspect unauthorized access or any security breach
                    concerning your student account.
                  </li>
                  <li>
                    <b>Accuracy of Profile Data:</b> Students must keep their registered contact details,
                    residential address, and passport/visa information accurate and current throughout their
                    matriculation.
                  </li>
                </ul>
              </section>

              {/* SECTION 3 */}
              <section className="legal-clause" id="t-sec-3">
                <h2>3. Intellectual Property Rights &amp; Courseware Ownership</h2>
                <p>
                  All educational content published across AUI digital platforms—including lecture videos,
                  syllabi, academic journal articles, slide decks, assignments, examinations, software
                  code, trademarks, logos, and branding elements—remains the exclusive intellectual
                  property of <b>ASEAN International Education SDN. BHD.</b> and its contributing faculty.
                </p>
                <div className="legal-box-grid">
                  <div className="legal-box">
                    <h4>Personal Educational License</h4>
                    <p>
                      AUI grants students a limited, revocable, non-exclusive, non-transferable license to
                      view and download curriculum resources solely for personal educational use.
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>Commercial Restrictions</h4>
                    <p>
                      Reproduction, distribution, public broadcasting, resale, or uploading of course
                      materials to commercial study hubs without written authorization is illegal.
                    </p>
                  </div>
                </div>
              </section>

              {/* SECTION 4 */}
              <section className="legal-clause" id="t-sec-4">
                <h2>4. Academic Integrity &amp; Student Code of Conduct</h2>
                <p>
                  Academic honesty is the cornerstone of ASEAN University International. Every student is
                  held to rigorous international standards of academic integrity:
                </p>
                <ul>
                  <li>
                    <b>Zero Tolerance for Plagiarism:</b> All submitted dissertations, essays, research
                    papers, and project deliverables must be the student&apos;s original intellectual work.
                    Unattributed usage of another author&apos;s text or unapproved artificial intelligence
                    generation is subject to disciplinary review and automatic zero score.
                  </li>
                  <li>
                    <b>Proctoring &amp; Examination Ethics:</b> During online or hybrid examinations, students
                    must adhere to established proctoring protocols, identity checks, and prohibited aid
                    guidelines.
                  </li>
                  <li>
                    <b>Respectful Community Conduct:</b> Harassment, hate speech, abusive language, or
                    disruptive behavior directed at faculty, staff, or fellow students across online forums
                    or physical campus facilities will result in immediate disciplinary tribunal review.
                  </li>
                </ul>
              </section>

              {/* SECTION 5 */}
              <section className="legal-clause" id="t-sec-5">
                <h2>5. Tuition Fees, Billing, Official Invoices &amp; Refund Policy</h2>
                <p>
                  Tuition rates and program fee structures are officially determined by the University Board
                  of Directors:
                </p>
                <ul>
                  <li>
                    <b>Official Payment Channels:</b> All tuition payments must be remitted strictly to AUI&apos;s
                    three designated corporate bank accounts (CIMB Islamic Bank Malaysia, BNI Indonesia, Bank
                    Mandiri Indonesia). AUI is not liable for funds sent to unverified third-party accounts.
                  </li>
                  <li>
                    <b>Application &amp; Registration Fees:</b> Initial application evaluation and registration
                    fees are non-refundable administrative costs incurred for credential assessment.
                  </li>
                  <li>
                    <b>Tuition Refund Schedule:</b>
                    <div className="table-wrap" style={{ marginTop: '12px' }}>
                      <table className="ttable">
                        <thead>
                          <tr>
                            <th>Withdrawal Notice Timeline</th>
                            <th>Refund Eligibility</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>Prior to official semester commencement date</td>
                            <td>80% of net semester tuition refunded</td>
                          </tr>
                          <tr>
                            <td>Within 14 calendar days of semester start</td>
                            <td>50% of net semester tuition refunded</td>
                          </tr>
                          <tr>
                            <td>After 14 calendar days of semester start</td>
                            <td>0% (No refund granted; fees forfeit)</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </li>
                  <li>
                    <b>Currency &amp; Bank Charges:</b> Students are responsible for all intermediary banking
                    commissions, telegraphic transfer fees, and currency conversion fluctuations.
                  </li>
                </ul>
              </section>

              {/* SECTION 6 */}
              <section className="legal-clause" id="t-sec-6">
                <h2>6. Distance Learning Delivery &amp; System Service Availability</h2>
                <p>
                  While AUI endeavors to maintain 99.5% uptime for its virtual learning infrastructure, we do
                  not warrant that digital platforms will operate entirely uninterrupted or error-free.
                  Scheduled maintenance, system upgrades, and global network interruptions may temporarily
                  affect portal access.
                </p>
                <p>
                  In the event of an unscheduled system outage during a scheduled online examination, the
                  Examinations Board will provide an equitable retake or extension window to all affected
                  candidates.
                </p>
              </section>

              {/* SECTION 7 */}
              <section className="legal-clause" id="t-sec-7">
                <h2>7. Degree Conferral, Academic Credentials &amp; Accreditations</h2>
                <p>
                  Degrees (Bachelor, Master, Doctor) are conferred solely upon candidates who have satisfied
                  all curriculum requirements, achieved minimum cumulative grade point averages (CGPA),
                  successfully defended capstone projects or doctoral dissertations, and settled all financial
                  obligations with the Bursary Office.
                </p>
                <p>
                  AUI maintains institutional accreditation and recognition through recognized bodies
                  including the European Association for Higher Education Advancement (EAHEA), International
                  Association for Quality Assurance in Pre-Tertiary &amp; Higher Education (QAHE), and
                  International Commission for Central Authority of Credentialing (ICCAC). Students are
                  advised that professional licensure requirements vary by jurisdiction and should consult
                  their respective national licensing boards.
                </p>
              </section>

              {/* SECTION 8 */}
              <section className="legal-clause" id="t-sec-8">
                <h2>8. Disclaimers &amp; Limitation of Liability</h2>
                <p>
                  To the maximum extent permitted under applicable law, AUI provides its websites, portals,
                  and digital course materials on an “as-is” and “as-available” basis without warranties of
                  any kind, express or implied.
                </p>
                <p>
                  Under no circumstances shall ASEAN International Education SDN. BHD., its directors,
                  officers, professors, or staff be liable for any indirect, punitive, incidental, special, or
                  consequential damages resulting from portal downtime, data loss, or reliance upon website
                  content. In any event, the university&apos;s aggregate liability shall not exceed the total
                  tuition fees paid by the student for the specific academic term in question.
                </p>
              </section>

              {/* SECTION 9 */}
              <section className="legal-clause" id="t-sec-9">
                <h2>9. Suspension &amp; Termination of Student Status</h2>
                <p>
                  The University reserves the right to suspend or terminate a student&apos;s matriculation, portal
                  access, and examination rights in the event of:
                </p>
                <ol>
                  <li>Substantial breach of the Academic Integrity or Student Code of Conduct.</li>
                  <li>Failure to remit required tuition fees within the published grace periods.</li>
                  <li>Submission of fraudulent, altered, or forged educational credentials or identity cards.</li>
                  <li>Violation of Malaysian immigration laws or visa revocation.</li>
                </ol>
              </section>

              {/* SECTION 10 */}
              <section className="legal-clause" id="t-sec-10">
                <h2>10. Governing Law &amp; Dispute Resolution Jurisdiction</h2>
                <p>
                  These Terms of Use and any student educational agreements entered into with AUI shall be
                  governed by and construed in accordance with the <b>Laws of Malaysia</b>.
                </p>
                <p>
                  Any dispute, controversy, or claim arising out of or relating to these Terms or student
                  matriculation shall first be subject to amicable conciliation with the University Senate.
                  Failing mutual agreement within 30 days, disputes shall be submitted to the exclusive
                  jurisdiction of the competent <b>Courts of Selangor Darul Ehsan, Malaysia</b>.
                </p>
              </section>

              {/* SECTION 11 */}
              <section className="legal-clause" id="t-sec-11">
                <h2>11. Modifications &amp; Legal Inquiries</h2>
                <p>
                  AUI reserves the right to revise these Terms of Use periodically to reflect updates in
                  academic regulations, technology systems, or statutory requirements. Continued usage of
                  university portals after updates constitutes acceptance of the revised Terms.
                </p>

                <div className="legal-dpo-card">
                  <h4>Office of Legal Counsel &amp; Secretariat</h4>
                  <p>
                    <b>ASEAN International Education SDN. BHD.</b>
                    <br />
                    F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15,
                    <br />
                    43650 Bandar Baru Bangi, Selangor Darul Ehsan, Malaysia
                  </p>
                  <p>
                    <b>Legal Inquiries:</b>{' '}
                    <a href="mailto:legal@aui.university" className="link-gold">
                      legal@aui.university
                    </a>{' '}
                    / <a href="mailto:admin@aui.university">admin@aui.university</a>
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
          <h2>Need Further Clarification on University Policies?</h2>
          <p>
            Our Admissions and Registrar teams are available to discuss enrollment requirements and course policies.
          </p>
          <div className="cband-actions">
            <Link
              href="/contact"
              onClick={(e) => handleView(e, 'contact')}
              className="btn btn-gold"
            >
              Contact Admissions Office &rarr;
            </Link>
            <Link
              href="/privacy"
              onClick={(e) => handleView(e, 'privacy')}
              className="btn btn-line"
            >
              Read Privacy Policy
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
