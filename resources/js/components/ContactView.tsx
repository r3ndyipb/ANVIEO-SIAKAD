

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

interface ContactViewProps {
  onNavigate?: (view: AppView) => void;
}

export default function ContactView({ onNavigate }: ContactViewProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [formSubmitted, setFormSubmitted] = useState(false);
  const [formData, setFormData] = useState({
    fullName: '',
    email: '',
    phone: '',
    country: '',
    programme: 'Bachelor — MYR 35,000',
    studyMode: 'Online (Flexible Distance Learning)',
    consultType: 'Online Video Consultation (Zoom)',
    message: '',
  });

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

  const handleInputChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.fullName || !formData.email) return;
    setFormSubmitted(true);
  };

  const handleResetForm = () => {
    setFormData({
      fullName: '',
      email: '',
      phone: '',
      country: '',
      programme: 'Bachelor — MYR 35,000',
      studyMode: 'Online (Flexible Distance Learning)',
      consultType: 'Online Video Consultation (Zoom)',
      message: '',
    });
    setFormSubmitted(false);
  };

  const handleView = (e: React.MouseEvent, view: AppView) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  const GOOGLE_MAPS_SHORT_LINK = 'https://maps.app.goo.gl/4FWdMa4oNG3x3BGi7';
  const GOOGLE_MAPS_EMBED_SRC =
    'https://maps.google.com/maps?q=2.9289376,101.7647136&hl=en&z=16&output=embed';

  return (
    <main className="view is-on" id="view-contact" ref={containerRef}>
      {/* 1. HERO */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="/img/depan-asen-university-international.webp"
          alt="ASEAN University International Campus & Headquarters"
        />
        <div className="wrap">
          <div className="eyebrow">Contact &amp; Global Secretariat</div>
          <h1>Contact Information</h1>
          <div className="sub">Connect with ASEAN University International</div>
          <p className="lede">
            Our admissions counselors, faculty secretariats, and international student advisory
            teams are ready to assist with your academic enrollment, credit transfers, and
            campus visits in Malaysia.
          </p>

          <div className="contact-hero-chips rv">
            <span className="hero-chip">
              <span className="chip-dot"></span>
              📍 Bangi Gateway, Selangor, Malaysia
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              ⏱ Response SLA: ≤ 24 Hours
            </span>
            <span className="hero-chip">
              <span className="chip-dot"></span>
              🌐 Online &amp; In-Person Consultations
            </span>
          </div>
        </div>
      </section>

      {/* 2. QUICK CONTACT CARDS */}
      <section className="qinfo">
        <div className="wrap">
          <div className="qi">
            <div className="qi-ic">📍</div>
            <div className="qi-t">Campus Location</div>
            <div className="qi-d">
              F-2-23, Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia
            </div>
          </div>
          <div className="qi">
            <div className="qi-ic">☎</div>
            <div className="qi-t">Phone &amp; WhatsApp</div>
            <div className="qi-d">
              <a href="tel:+60165680906" className="link-hover">
                +60 165 680 906
              </a>
              <br />
              <span style={{ fontSize: '12px', color: '#888' }}>Direct admissions hotline</span>
            </div>
          </div>
          <div className="qi">
            <div className="qi-ic">✉</div>
            <div className="qi-t">Official Email Desk</div>
            <div className="qi-d">
              <a href="mailto:admin@aui.university" className="link-hover">
                admin@aui.university
              </a>
              <br />
              <a href="mailto:finance@aui.university" className="link-hover" style={{ fontSize: '12px' }}>
                finance@aui.university
              </a>
            </div>
          </div>
          <div className="qi">
            <div className="qi-ic">🕒</div>
            <div className="qi-t">Operating Hours</div>
            <div className="qi-d">
              Mon – Fri: 08:30 – 17:30 (MYT)
              <br />
              Sat: 09:00 – 13:00 (MYT)
            </div>
          </div>
        </div>
      </section>

      {/* 3. SHOWCASE: INTERACTIVE MAP & DIRECTIONS */}
      <section className="sec map-section" id="campus-map">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">Interactive Campus Location</div>
            <h2>Visit Our Malaysia Campus &amp; Administrative Headquarters</h2>
            <p className="lead">
              Conveniently located at Bangi Gateway in Bandar Baru Bangi, Selangor — just 25 minutes
              from Kuala Lumpur International Airport (KLIA) and directly accessible via the PLUS
              North-South Expressway.
            </p>
          </div>

          <div className="map-showcase-card rv">
            {/* Top Bar of Map Card */}
            <div className="map-card-bar">
              <div className="map-card-title">
                <span className="live-dot"></span>
                <strong>Asean University International</strong> — Bangi Gateway Complex
              </div>
              <div className="map-card-coords">
                <span>GPS: 2.9289° N, 101.7647° E</span>
                <span className="sep">•</span>
                <span>Selangor, Malaysia</span>
              </div>
            </div>

            {/* Main Interactive Map Frame + Overlay Panel */}
            <div className="map-grid-container">
              {/* Left/Main: Responsive Embedded Map */}
              <div className="map-iframe-container">
                <iframe
                  title="Asean University International Google Maps Location"
                  src={GOOGLE_MAPS_EMBED_SRC}
                  className="map-iframe"
                  loading="lazy"
                  allowFullScreen
                  referrerPolicy="no-referrer-when-downgrade"
                ></iframe>
              </div>

              {/* Right: Rich Campus Card with Direct Links */}
              <div className="map-info-panel">
                <div className="map-panel-header">
                  <div className="map-badge">Official Campus</div>
                  <h3>Asean International Education SDN. BHD</h3>
                  <div className="map-sub">Asean University International (AUI)</div>
                </div>

                <div className="map-panel-address">
                  <div className="pin-icon">📍</div>
                  <div>
                    <strong>Full Postal Address:</strong>
                    <p>
                      F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15,
                      <br />
                      43650 Bandar Baru Bangi, Selangor Darul Ehsan, Malaysia
                    </p>
                  </div>
                </div>

                <div className="map-panel-actions">
                  <a
                    href={GOOGLE_MAPS_SHORT_LINK}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-gold btn-block map-btn-primary"
                    id="btn-open-google-maps"
                  >
                    <span>Buka di Google Maps</span>
                    <span className="arrow-icon">↗</span>
                  </a>

                  <a
                    href={GOOGLE_MAPS_SHORT_LINK}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-line btn-block map-btn-secondary"
                  >
                    <span>🚗 Petunjuk Arah (Directions)</span>
                  </a>
                </div>

                <div className="map-panel-transit">
                  <h4>Getting Here:</h4>
                  <ul>
                    <li>
                      <b>✈️ By Air:</b> ~25 mins from KLIA 1 &amp; KLIA 2 via ELITE / PLUS Expressway.
                    </li>
                    <li>
                      <b>🚆 By Rail:</b> KTM Komuter UKM or Bangi Station (~7 mins by taxi/Grab).
                    </li>
                    <li>
                      <b>🚗 By Car:</b> Exit 212 (Bangi Interchange) from the North-South Expressway.
                    </li>
                    <li>
                      <b>🅿️ Parking:</b> Ample covered visitor parking available inside Bangi Gateway.
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 4. DEPARTMENT DIRECTORY & ENQUIRY FORM */}
      <section className="sec" style={{ background: '#fbf9f4' }}>
        <div className="wrap">
          <div className="contact-main-grid">
            {/* Left: Department Contact Directory */}
            <div className="dept-directory rv">
              <div className="eyebrow" style={{ color: 'var(--gold)' }}>
                Department Directory
              </div>
              <h2 style={{ fontSize: '32px', marginBottom: '24px' }}>
                Direct Department Contacts
              </h2>
              <p style={{ color: 'var(--muted)', fontSize: '15px', marginBottom: '32px' }}>
                Need targeted assistance? Reach out directly to the specialized division overseeing
                your academic or administrative inquiry.
              </p>

              <div className="dept-cards-stack">
                <div className="dept-card">
                  <div className="dept-icon">🎓</div>
                  <div className="dept-content">
                    <h4>Admissions &amp; Enrollment Office</h4>
                    <p>
                      Program prerequisites, international student admission, credit transfers, and
                      academic track selection.
                    </p>
                    <div className="dept-links">
                      <a href="mailto:admin@aui.university">✉ admin@aui.university</a>
                      <span className="sep">•</span>
                      <a href="tel:+60165680906">☎ +60 165 680 906</a>
                    </div>
                  </div>
                </div>

                <div className="dept-card">
                  <div className="dept-icon">💳</div>
                  <div className="dept-content">
                    <h4>Bursary &amp; Tuition Payment Services</h4>
                    <p>
                      Tuition fees verification, wire transfers, payment receipts, student invoicing,
                      and payment plan inquiries.
                    </p>
                    <div className="dept-links">
                      <a href="mailto:finance@aui.university">✉ finance@aui.university</a>
                      <span className="sep">•</span>
                      <Link
                        href="/payment-information"
                        onClick={(e) => handleView(e, 'payment')}
                        className="link-gold"
                      >
                        View Official Bank Accounts &rarr;
                      </Link>
                    </div>
                  </div>
                </div>

                <div className="dept-card">
                  <div className="dept-icon">📜</div>
                  <div className="dept-content">
                    <h4>Academic Affairs &amp; Registrar</h4>
                    <p>
                      Official transcripts, graduation eligibility, student ID verification, and
                      academic status certifications.
                    </p>
                    <div className="dept-links">
                      <a href="mailto:admin@aui.university">✉ registrar@aui.university</a>
                      <span className="sep">•</span>
                      <a href="#students">Student Portal Access</a>
                    </div>
                  </div>
                </div>

                <div className="dept-card">
                  <div className="dept-icon">🏛️</div>
                  <div className="dept-content">
                    <h4>Corporate Governance &amp; MoUs</h4>
                    <p>
                      ASEAN university partnerships, international collaboration agreements, and
                      institutional accreditation inquiries.
                    </p>
                    <div className="dept-links">
                      <a href="mailto:admin@aui.university">✉ secretariat@aui.university</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Right: Interactive Consultation / Enquiry Form */}
            <div className="enquiry-form-container rv">
              <div className="form-card">
                <div className="form-header">
                  <div className="form-badge">Fast Response Desk</div>
                  <h3>Submit an Online Inquiry</h3>
                  <p>
                    Fill out the form below and an AUI Academic Advisor will review your details and
                    reply within 24 hours.
                  </p>
                </div>

                {formSubmitted ? (
                  <div className="form-success-alert">
                    <div className="success-icon">✓</div>
                    <h4>Inquiry Received Successfully!</h4>
                    <p>
                      Thank you, <b>{formData.fullName}</b>. Our academic admissions office has
                      logged your inquiry for the <b>{formData.programme}</b>.
                    </p>
                    <p style={{ fontSize: '13.5px', color: '#666' }}>
                      A verification copy and response will be sent to <b>{formData.email}</b>{' '}
                      within 24 business hours. You may also contact our WhatsApp hotline for urgent
                      matters.
                    </p>
                    <button
                      type="button"
                      className="btn btn-gold"
                      onClick={handleResetForm}
                      style={{ marginTop: '16px' }}
                    >
                      Submit Another Inquiry
                    </button>
                  </div>
                ) : (
                  <form className="cform-modern" onSubmit={handleSubmit}>
                    <div className="form-row-2">
                      <div className="form-group">
                        <label htmlFor="fullName">
                          Full Name <span className="req">*</span>
                        </label>
                        <input
                          id="fullName"
                          name="fullName"
                          type="text"
                          required
                          placeholder="e.g. Alexander Tan"
                          value={formData.fullName}
                          onChange={handleInputChange}
                        />
                      </div>
                      <div className="form-group">
                        <label htmlFor="email">
                          Email Address <span className="req">*</span>
                        </label>
                        <input
                          id="email"
                          name="email"
                          type="email"
                          required
                          placeholder="e.g. alex@example.com"
                          value={formData.email}
                          onChange={handleInputChange}
                        />
                      </div>
                    </div>

                    <div className="form-row-2">
                      <div className="form-group">
                        <label htmlFor="phone">Phone / WhatsApp Number</label>
                        <input
                          id="phone"
                          name="phone"
                          type="tel"
                          placeholder="e.g. +60 12 345 6789"
                          value={formData.phone}
                          onChange={handleInputChange}
                        />
                      </div>
                      <div className="form-group">
                        <label htmlFor="country">Country of Residence</label>
                        <input
                          id="country"
                          name="country"
                          type="text"
                          placeholder="e.g. Indonesia, Malaysia, Singapore"
                          value={formData.country}
                          onChange={handleInputChange}
                        />
                      </div>
                    </div>

                    <div className="form-group">
                      <label htmlFor="programme">Programme of Interest</label>
                      <select
                        id="programme"
                        name="programme"
                        value={formData.programme}
                        onChange={handleInputChange}
                      >
                        <option value="Bachelor — MYR 35,000">Bachelor Degree (MYR 35,000)</option>
                        <option value="Master — MYR 45,000">Master Degree (MYR 45,000)</option>
                        <option value="Doctor — MYR 65,000">Doctoral / Ph.D. (MYR 65,000)</option>
                        <option value="General Inquiry">General / Other Inquiries</option>
                      </select>
                    </div>

                    <div className="form-row-2">
                      <div className="form-group">
                        <label htmlFor="studyMode">Preferred Study Mode</label>
                        <select
                          id="studyMode"
                          name="studyMode"
                          value={formData.studyMode}
                          onChange={handleInputChange}
                        >
                          <option value="Online (Flexible Distance Learning)">
                            100% Online (Flexible Distance Learning)
                          </option>
                          <option value="At My Own Pace (Independent Study)">
                            At My Own Pace (Self-Paced Research)
                          </option>
                          <option value="Blended / Campus Seminars">
                            Blended / Attending Campus Seminars
                          </option>
                        </select>
                      </div>

                      <div className="form-group">
                        <label htmlFor="consultType">Consultation Format</label>
                        <select
                          id="consultType"
                          name="consultType"
                          value={formData.consultType}
                          onChange={handleInputChange}
                        >
                          <option value="Online Video Consultation (Zoom)">
                            Online Video Call (Zoom / Google Meet)
                          </option>
                          <option value="In-Person Campus Visit (Bangi)">
                            In-Person Campus Visit (Bangi, Malaysia)
                          </option>
                          <option value="Email Communication">Email Correspondence Only</option>
                        </select>
                      </div>
                    </div>

                    <div className="form-group">
                      <label htmlFor="message">Your Message or Questions</label>
                      <textarea
                        id="message"
                        name="message"
                        rows={4}
                        placeholder="Tell us about your background, desired intake, or questions regarding tuition and credit recognition..."
                        value={formData.message}
                        onChange={handleInputChange}
                      ></textarea>
                    </div>

                    <button type="submit" className="btn btn-gold btn-block form-submit-btn">
                      Send Inquiry &amp; Request Consultation &rarr;
                    </button>

                    <div className="form-privacy-note">
                      By submitting, you agree to our{' '}
                      <Link
                        href="/privacy"
                        onClick={(e) => handleView(e, 'privacy')}
                        className="link-gold"
                      >
                        Privacy Policy
                      </Link>
                      . We will never share your personal data with unauthorized third parties.
                    </div>
                  </form>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 5. CAMPUS VISITATION FAQ */}
      <section className="sec" style={{ background: '#fff' }}>
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">Frequently Asked Questions</div>
            <h2>Campus Visits &amp; Direct Inquiries</h2>
          </div>

          <div className="contact-faq-grid rv">
            <div className="faq-item">
              <h4>Can I visit the AUI campus in Malaysia without an appointment?</h4>
              <p>
                While walk-ins are welcomed during standard business hours (Mon–Fri 08:30–17:30), we
                strongly recommend booking an appointment via our inquiry form or WhatsApp hotline
                so our admissions counselors can prepare personalized degree evaluation documents.
              </p>
            </div>

            <div className="faq-item">
              <h4>How fast will I receive a reply to my email inquiry?</h4>
              <p>
                Our standard service level agreement (SLA) guarantees an official reply within 24
                business hours. For immediate assistance regarding application deadlines or payment
                verifications, contact us via WhatsApp at +60 165 680 906.
              </p>
            </div>

            <div className="faq-item">
              <h4>How do I schedule an online consultation via Zoom?</h4>
              <p>
                Select “Online Video Call (Zoom)” in our inquiry form above. Our admissions team will
                email you a calendar booking link with slots accommodated to your time zone across
                ASEAN, Europe, or the Americas.
              </p>
            </div>

            <div className="faq-item">
              <h4>Where should I send my official certified academic transcripts?</h4>
              <p>
                Digital scanned copies may be sent directly to <b>admin@aui.university</b> for
                preliminary credit evaluation. Hard copies can be couriered to our registered campus
                office at Bangi Gateway, Bandar Baru Bangi, Selangor, Malaysia.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* 6. BOTTOM BANNER */}
      <section className="cband" id="contact-footer-band">
        <div className="wrap">
          <h2>Ready to Begin Your International Academic Journey?</h2>
          <p>
            Join more than 3,000 students across 5+ countries pursuing accredited higher education
            at ASEAN University International.
          </p>
          <div className="cband-actions">
            <Link
              href="/admissions"
              onClick={(e) => handleView(e, 'admissions')}
              className="btn btn-gold"
            >
              Apply for Admission &rarr;
            </Link>
            <a
              href={GOOGLE_MAPS_SHORT_LINK}
              target="_blank"
              rel="noopener noreferrer"
              className="btn btn-line"
            >
              Open Campus Map in Google Maps ↗
            </a>
          </div>
        </div>
      </section>
    </main>
  );
}
