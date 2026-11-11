import React from 'react';
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

interface AcademicDetailViewProps {
  onNavigate?: (view: AppView) => void;
}

export default function AcademicDetailView({ onNavigate }: AcademicDetailViewProps) {
  const handleNav = (e: React.MouseEvent, view: AppView) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  return (
    <main className="view is-on" id="view-detail">
        <section className="phero">
            <img className="phero-img"
                src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=1800&q=70"
                alt="Master programme study" />
            <div className="wrap">
                <div className="eyebrow">Postgraduate Programme · ASEAN University International</div>
                <h1>Master Degree</h1>
                <div className="sub">
                    <span>Tuition: <b>MYR 45,000</b></span>
                    <span>Study mode: <b>Online · At your own pace</b></span>
                    <span>Accredited programme of study</span>
                    <span>Scholarship: <b>Available</b></span>
                </div>
            </div>
        </section>

        <div className="wrap">
            <div className="pdetail">

                <aside className="side">
                    <div className="side-box">
                        <h3>Quick Info</h3>
                        <dl>
                            <div>
                                <dt>Programme level</dt>
                                <dd>Master (Postgraduate · S2)</dd>
                            </div>
                            <div>
                                <dt>Tuition fee</dt>
                                <dd className="price-tag">MYR 45,000</dd>
                            </div>
                            <div>
                                <dt>Scholarship</dt>
                                <dd>Available (up to 50%)</dd>
                            </div>
                            <div>
                                <dt>Study method</dt>
                                <dd>Online / own pace</dd>
                            </div>
                            <div>
                                <dt>Location</dt>
                                <dd>Study from anywhere in the world</dd>
                            </div>
                            <div>
                                <dt>Faculties</dt>
                                <dd>8 academic faculties · 20+ research centers</dd>
                            </div>
                            <div>
                                <dt>Admissions</dt>
                                <dd>admin@aui.university<br />+60 165 680 906</dd>
                            </div>
                        </dl>
                        <a href="#tuition" className="btn btn-gold" onClick={(e) => handleNav(e, 'admissions')}>Apply now</a>
                        <a href="/contact" className="btn btn-line" onClick={(e) => handleNav(e, 'contact')}>Ask Admissions</a>
                        <a href="/payment-information" className="btn btn-line" onClick={(e) => handleNav(e, 'payment')}>Payment Information</a>
                    </div>
                    <div className="side-box" style={{ background: "var(--brand)", color: "#fff", border: "0" }}>
                        <h3 style={{ color: "#fff", borderColor: "rgba(255,255,255,.2)" }}>Scholarship Available</h3>
                        <p style={{ fontSize: "13.5px", color: "rgba(255,255,255,.8)", margin: "0" }}>Every AUI programme — Bachelor,
                            Master, and Doctor — offers scholarship opportunities. Contact admissions for the current
                            special offer.</p>
                    </div>
                </aside>

                <div className="pmain">

                    <section className="rv">
                        <h2>Programme Overview</h2>
                        <p>The Master programme at ASEAN University International (AUI) can be studied from anywhere in
                            the world – choose to study online, or at your own pace with or without attending classes.
                        </p>
                        <p>Whichever study method you choose, you'll experience the same high‑quality, accredited
                            education programme of study. AUI fosters excellent education that is engaging and
                            enthusiastically presented, with an emphasis on disseminating practical and pertinent
                            knowledge, taught by professionals — so you graduate with essential skills for the latest
                            industries.</p>
                    </section>

                    <section className="rv">
                        <h2>Programme Snapshot</h2>
                        <table className="kur">
                            <tbody>
                                <tr>
                                    <th>Award</th>
                                    <td>Master Degree (Postgraduate)</td>
                                </tr>
                                <tr>
                                    <th>Duration</th>
                                    <td>1.5 – 2 Years (Flexible / Own pace)</td>
                                </tr>
                                <tr>
                                    <th>Credits</th>
                                    <td>42 – 48 Credit Hours</td>
                                </tr>
                                <tr>
                                    <th>Delivery</th>
                                    <td>100% Online · Hybrid · Self-Paced</td>
                                </tr>
                                <tr>
                                    <th>Accreditation</th>
                                    <td>Internationally Accredited &amp; Recognized</td>
                                </tr>
                                <tr>
                                    <th>Intakes</th>
                                    <td>January, May, and September (Rolling Admissions)</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section className="rv">
                        <h2>Curriculum Highlights</h2>
                        <div className="grid-2">
                            <div className="box">
                                <h3>Core Modules</h3>
                                <ul>
                                    <li>Strategic Leadership &amp; Innovation</li>
                                    <li>Advanced Research Methodology &amp; Analytics</li>
                                    <li>Global Corporate Governance &amp; Ethics</li>
                                    <li>Cross-Cultural Management &amp; Negotiation</li>
                                </ul>
                            </div>
                            <div className="box">
                                <h3>Specialisation Tracks</h3>
                                <ul>
                                    <li>Digital Transformation &amp; AI Integration</li>
                                    <li>Financial Engineering &amp; Global Capital</li>
                                    <li>Sustainable Development &amp; ESG Frameworks</li>
                                    <li>Applied Master Capstone Project / Thesis</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section className="rv">
                        <h2>Compare Programmes</h2>
                        <div className="cards-3">
                            <article className="card">
                                <div className="body">
                                    <span className="lvl">Undergraduate</span>
                                    <h3>Bachelor</h3>
                                    <div className="price">MYR 35,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <a href="/academic" className="link-gold" onClick={(e) => handleNav(e, 'academic')}>Explore Programme →</a>
                                </div>
                            </article>
                            <article className="card" style={{ borderColor: "var(--gold)", boxShadow: "0 8px 24px rgba(201,150,46,0.15)" }}>
                                <div className="body">
                                    <span className="lvl">Postgraduate · Current Page</span>
                                    <h3>Master</h3>
                                    <div className="price">MYR 45,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <a href="/admissions" className="link-gold" onClick={(e) => handleNav(e, 'admissions')}>Apply Now →</a>
                                </div>
                            </article>
                            <article className="card">
                                <div className="body">
                                    <span className="lvl">Doctoral</span>
                                    <h3>Doctor / Ph.D</h3>
                                    <div className="price">MYR 65,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <a href="/academic" className="link-gold" onClick={(e) => handleNav(e, 'academic')}>Explore Programme →</a>
                                </div>
                            </article>
                        </div>
                        <p style={{ marginTop: "20px" }}>For payment methods and instalment information, visit <Link
                                href="/payment-information" onClick={(e) => handleNav(e, 'payment')} style={{ color: "var(--gold)", fontWeight: "700" }}>Payment Information</Link>.</p>
                    </section>

                    <section className="rv">
                        <h2>Frequently Asked Questions</h2>
                        <details className="faq">
                            <summary>Can I study the Master programme from my own country?</summary>
                            <p>Yes. Every programme AUI offers can be studied from anywhere in the world — choose to
                                study online, or at your own pace with or without attending classes.</p>
                        </details>
                        <details className="faq">
                            <summary>Is the education accredited if I study online?</summary>
                            <p>Whichever study method you choose, you'll experience the same high‑quality, accredited
                                education programme of study.</p>
                        </details>
                        <details className="faq">
                            <summary>Are scholarships available for the Master programme?</summary>
                            <p>Yes. Scholarship is available for the Master programme (and also for Bachelor and
                                Doctor). Contact admissions at admin@aui.university for the current special offer.</p>
                        </details>
                        <details className="faq">
                            <summary>How large is the AUI student community?</summary>
                            <p>AUI has nearly 30,000 students and 20,000 alumni, with 3,000+ students enrolled from 5+
                                countries, 8 academic faculties, 20+ research centers, and 100+ industry partnerships
                                worldwide.</p>
                        </details>
                        <details className="faq">
                            <summary>How do I contact admissions?</summary>
                            <p>Email admin@aui.university, call +60 165 680 906, or use the student portal (portal-aui).
                                Our office is at F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru
                                Bangi, Selangor, Malaysia.</p>
                        </details>
                    </section>

                    <section className="rv">
                        <div className="pcta">
                            <div>
                                <h3>Ready to start your Master programme?</h3>
                                <p>Tuition MYR 45,000 · Scholarship Available · Study from anywhere in the world.</p>
                            </div>
                            <div style={{ display: "flex", gap: "12px", flexWrap: "wrap" }}>
                                <a href="/admissions" className="btn btn-gold" onClick={(e) => handleNav(e, 'admissions')}>Apply now</a>
                                <a href="/" className="btn btn-ghost" onClick={(e) => handleNav(e, 'home')}>← Back to Home</a>
                            </div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>
  );
}
