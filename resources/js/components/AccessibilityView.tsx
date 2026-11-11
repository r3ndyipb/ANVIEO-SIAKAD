

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

interface AccessibilityViewProps {
  onNavigate?: (view: AppView) => void;
}

export default function AccessibilityView({ onNavigate }: AccessibilityViewProps) {
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
    <main className="view is-on" id="view-accessibility" ref={containerRef}>
      {/* 1. HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1800&q=70"
          alt="ASEAN University International Digital Accessibility"
        />
        <div className="wrap">
          <div className="eyebrow">Inclusive Digital Higher Education</div>
          <h1>Accessibility Statement</h1>
          <div className="sub">Commitment to Barrier-Free Academic Learning for All</div>
          <p className="lede">
            ASEAN University International is committed to ensuring digital accessibility for people
            with disabilities. We continuously improve the user experience for everyone and apply the
            relevant accessibility standards in accordance with the Web Content Accessibility
            Guidelines (WCAG 2.1, Level AA).
          </p>

          <div className="contact-hero-chips rv">
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Standard Target: WCAG 2.1 Level AA
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Review Cycle: Annual Audit 2026
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              Support Desk: accessibility@aui.university
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
                  <a href="#a-sec-1" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-1'); }}>
                    1. Our Institutional Commitment
                  </a>
                  <a href="#a-sec-2" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-2'); }}>
                    2. Conformance Standards (WCAG 2.1 AA)
                  </a>
                  <a href="#a-sec-3" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-3'); }}>
                    3. Key Accessibility Features Implemented
                  </a>
                  <a href="#a-sec-4" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-4'); }}>
                    4. Assistive Technology Compatibility
                  </a>
                  <a href="#a-sec-5" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-5'); }}>
                    5. Student Disability Accommodations
                  </a>
                  <a href="#a-sec-6" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-6'); }}>
                    6. Technical Specifications &amp; Browsers
                  </a>
                  <a href="#a-sec-7" onClick={(e) => { e.preventDefault(); scrollToSection('a-sec-7'); }}>
                    7. Reporting Barriers &amp; Feedback SLA
                  </a>
                </nav>

                <div className="legal-toc-help">
                  <strong>Accessibility Assistance?</strong>
                  <p>Our Digital Accessibility Team reviews and assists with any assistive barriers.</p>
                  <a href="mailto:accessibility@aui.university" className="link-gold">
                    ✉ accessibility@aui.university
                  </a>
                </div>
              </div>
            </aside>

            {/* Right: Comprehensive Content */}
            <article className="legal-article rv">
              {/* SECTION 1 */}
              <section className="legal-clause" id="a-sec-1">
                <h2>1. Our Institutional Commitment</h2>
                <p>
                  Higher education is a fundamental driver of personal elevation, scientific advancement,
                  and socio-economic empowerment across the ASEAN region and the world. <b>ASEAN University
                  International (AUI)</b> believes that online education, digital libraries, and student
                  management portals must be fully usable, navigable, and intelligible by every learner,
                  regardless of cognitive, visual, auditory, or motor abilities.
                </p>
                <p>
                  We are actively implementing universal design principles across our web portal, Learning
                  Management Systems, and virtual classroom interfaces to cultivate an inclusive,
                  dignified, and barrier-free educational community.
                </p>
              </section>

              {/* SECTION 2 */}
              <section className="legal-clause" id="a-sec-2">
                <h2>2. Conformance Standards (WCAG 2.1 Level AA)</h2>
                <p>
                  To provide a uniform standard across all digital learning assets, AUI benchmarks its digital
                  ecosystem against the <b>Web Content Accessibility Guidelines (WCAG) 2.1 Level AA</b>,
                  published by the World Wide Web Consortium (W3C) Web Accessibility Initiative (WAI).
                </p>
                <p>
                  These international guidelines define four core principles of web accessibility:
                </p>
                <div className="legal-box-grid">
                  <div className="legal-box">
                    <h4>1. Perceivable</h4>
                    <p>
                      Information and user interface components must be presentable to users in ways they can
                      perceive (e.g. text alternatives, high contrast, captions).
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>2. Operable</h4>
                    <p>
                      User interface components and navigation must be operable (e.g. keyboard accessible,
                      sufficient reading time, clear headings).
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>3. Understandable</h4>
                    <p>
                      Information and the operation of the user interface must be understandable (e.g.
                      predictable navigation, input assistance, readable typography).
                    </p>
                  </div>
                  <div className="legal-box">
                    <h4>4. Robust</h4>
                    <p>
                      Content must be robust enough that it can be interpreted reliably by a wide variety of
                      user agents, including current assistive technologies.
                    </p>
                  </div>
                </div>
              </section>

              {/* SECTION 3 */}
              <section className="legal-clause" id="a-sec-3">
                <h2>3. Key Accessibility Features Implemented</h2>
                <p>
                  AUI has integrated specific technical features across its public web portal and student
                  dashboards:
                </p>
                <ul>
                  <li>
                    <b>High Contrast Visual Aesthetics:</b> Color contrasts between text and backgrounds
                    strictly maintain or exceed the 4.5:1 ratio for body copy and 3:1 for large display titles,
                    pairing our deep navy (<code>#0c1c2c</code>) and gold accents with clean, high-legibility
                    backdrops.
                  </li>
                  <li>
                    <b>Full Keyboard Navigability:</b> All interactive buttons, forms, navigation dropdowns,
                    and links can be navigated using the standard <code>Tab</code>, <code>Shift + Tab</code>,
                    and <code>Enter / Space</code> keys, accompanied by high-visibility focus indicator rings.
                  </li>
                  <li>
                    <b>Semantic HTML5 Hierarchy:</b> Pages utilize native landmark elements (<code>&lt;header&gt;</code>,
                    <code>&lt;nav&gt;</code>, <code>&lt;main&gt;</code>, <code>&lt;section&gt;</code>,
                    <code>&lt;article&gt;</code>, <code>&lt;footer&gt;</code>) with a single, logical
                    <code>&lt;h1&gt;</code> structure per page to ensure seamless screen reader traversal.
                  </li>
                  <li>
                    <b>Alternative Text for Visual Media:</b> All instructional diagrams, campus photography,
                    and institutional seals feature descriptive <code>alt</code> text attributes for non-sighted
                    learners.
                  </li>
                  <li>
                    <b>Responsive Layouts &amp; Zoom Support:</b> The portal supports browser zoom up to 200%
                    without clipping content, horizontal scrollbars, or loss of functionality.
                  </li>
                  <li>
                    <b>Accessible Form Controls:</b> Every form field features explicit <code>&lt;label&gt;</code>{' '}
                    associations, intuitive placeholder guidance, and clear error notifications.
                  </li>
                </ul>
              </section>

              {/* SECTION 4 */}
              <section className="legal-clause" id="a-sec-4">
                <h2>4. Assistive Technology Compatibility</h2>
                <p>
                  The AUI web ecosystem is designed and tested for compatibility with widely adopted
                  assistive technologies:
                </p>
                <ul>
                  <li>
                    <b>Screen Readers:</b> NVDA (NonVisual Desktop Access) on Windows, JAWS on Windows,
                    VoiceOver on macOS / iOS, and TalkBack on Android.
                  </li>
                  <li>
                    <b>Screen Magnification:</b> ZoomText, Windows Magnifier, and macOS Zoom utilities.
                  </li>
                  <li>
                    <b>Speech Recognition Software:</b> Dragon NaturallySpeaking and native OS dictation
                    suites.
                  </li>
                  <li>
                    <b>Alternative Input Devices:</b> Single-switch input hardware, head wands, and eye-gaze
                    trackers.
                  </li>
                </ul>
              </section>

              {/* SECTION 5 */}
              <section className="legal-clause" id="a-sec-5">
                <h2>5. Student Disability Accommodations &amp; Learning Support</h2>
                <p>
                  Enrolled students requiring academic accommodations for examinations, thesis defenses, or
                  instructional media can request personalized support through the Office of Student Affairs:
                </p>
                <div className="table-wrap">
                  <table className="ttable">
                    <thead>
                      <tr>
                        <th>Accommodation Category</th>
                        <th>Available Academic Support Services</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>
                          <b>Examination &amp; Assessment Time</b>
                        </td>
                        <td>
                          Time-and-a-half (1.5x) or double-time (2.0x) examination windows on online portals,
                          with scheduled rest intervals.
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <b>Alternative Format Courseware</b>
                        </td>
                        <td>
                          Curriculum modules delivered in screen-reader friendly accessible PDFs, plain text, or
                          large-print digital formats.
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <b>Multimedia &amp; Lecture Captions</b>
                        </td>
                        <td>
                          Synchronous live captioning or verbatim written lecture transcripts for recorded audio
                          and video lectures.
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <b>Dedicated Academic Advisor</b>
                        </td>
                        <td>
                          1-on-1 coordination with faculty professors to ensure course requirements align with
                          student physical accessibility needs.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>

              {/* SECTION 6 */}
              <section className="legal-clause" id="a-sec-6">
                <h2>6. Technical Specifications &amp; Recommended Browsers</h2>
                <p>
                  Accessibility of the ASEAN University International portal relies on modern web
                  technologies to work seamlessly with web browsers and assistive tools:
                </p>
                <ul>
                  <li>HTML5 Semantic Markup</li>
                  <li>WAI-ARIA (Accessible Rich Internet Applications) 1.2 Attributes</li>
                  <li>CSS3 Flexbox and CSS Grid</li>
                  <li>Modern JavaScript (ECMAScript 2022+)</li>
                </ul>
                <p>
                  For the best accessible experience, we recommend using the latest stable versions of{' '}
                  <b>Google Chrome, Mozilla Firefox, Apple Safari,</b> or <b>Microsoft Edge</b>.
                </p>
              </section>

              {/* SECTION 7 */}
              <section className="legal-clause" id="a-sec-7">
                <h2>7. Reporting Barriers &amp; Feedback Response SLA</h2>
                <p>
                  We welcome your feedback on the accessibility of the AUI digital campus. If you encounter any
                  accessibility barrier, unreadable content, or experience difficulty submitting an application,
                  please notify our Digital Accessibility Coordinator:
                </p>

                <div className="legal-dpo-card">
                  <h4>Digital Accessibility Coordination Desk</h4>
                  <p>
                    <b>ASEAN International Education SDN. BHD.</b>
                    <br />
                    F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15,
                    <br />
                    43650 Bandar Baru Bangi, Selangor Darul Ehsan, Malaysia
                  </p>
                  <p>
                    <b>Email:</b>{' '}
                    <a href="mailto:accessibility@aui.university" className="link-gold">
                      accessibility@aui.university
                    </a>{' '}
                    / <a href="mailto:admin@aui.university">admin@aui.university</a>
                    <br />
                    <b>Telephone / WhatsApp:</b> <a href="tel:+60165680906">+60 165 680 906</a>
                  </p>
                  <p style={{ marginTop: '8px', fontSize: '13px', color: '#666' }}>
                    <b>Service Level Agreement (SLA):</b> We investigate and respond to all accessibility
                    barrier reports within <b>two (2) business days</b>, providing an accessible alternative or
                    technical remediation.
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
          <h2>Need Immediate Academic Assistance?</h2>
          <p>
            Our admissions counselors and student advisory desk are available to support your academic application.
          </p>
          <div className="cband-actions">
            <Link
              href="/contact"
              onClick={(e) => handleView(e, 'contact')}
              className="btn btn-gold"
            >
              Contact Student Support &rarr;
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
