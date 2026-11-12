
import React, { useState, useEffect, useRef } from 'react';
import Link from './Link';
import HeroSlideshow from './HeroSlideshow';

interface HomeViewProps {
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
  portalData?: any;
}

interface NewsItem {
  id: string;
  title: string;
  category: string;
  categoryKey: 'academic' | 'campus' | 'research' | 'admissions' | 'partnerships';
  date: string;
  readTime: string;
  tag: string;
  image: string;
  excerpt: string;
}

const NEWS_ARTICLES: NewsItem[] = [
  {
    id: 'n1',
    title: 'AUI Ranked Among Top ASEAN Emerging Universities for Multidisciplinary Research',
    category: 'Academic',
    categoryKey: 'academic',
    date: '24 Oct 2026',
    readTime: '4 min read',
    tag: 'Recognition',
    image: '/img/news-ranking.jpg',
    excerpt: 'ASEAN University International achieves benchmark recognition for high-impact open research and flexible cross-border academic excellence across Southeast Asia.'
  },
  {
    id: 'n2',
    title: 'Global Commencement 2026: Celebrating 1,200+ Leaders Graduating Across 45 Nations',
    category: 'Campus Life',
    categoryKey: 'campus',
    date: '18 Oct 2026',
    readTime: '5 min read',
    tag: 'Graduation',
    image: '/img/89376r98172e09812r.jpeg',
    excerpt: 'AUI honours its global graduating cohort in a historic hybrid celebration uniting scholars, ambassadors, and faculty from around the world.'
  },
  {
    id: 'n3',
    title: 'Breakthrough Multidisciplinary Climate & Health Research Hub Launched in Southeast Asia',
    category: 'Research',
    categoryKey: 'research',
    date: '12 Oct 2026',
    readTime: '3 min read',
    tag: 'Innovation',
    image: '/img/news-research.jpg',
    excerpt: 'New inter-faculty research grant accelerates indigenous sustainability studies and health technology partnerships across developing ASEAN economies.'
  },
  {
    id: 'n4',
    title: 'New International Industry Partnerships: Direct Career Tracks in AI & FinTech',
    category: 'Partnerships',
    categoryKey: 'partnerships',
    date: '05 Oct 2026',
    readTime: '4 min read',
    tag: 'Careers',
    image: '/img/program-business.jpg',
    excerpt: 'AUI collaborates with premier regional tech enterprises to offer direct executive mentorship and guaranteed capstone internships for Master & Bachelor scholars.'
  },
  {
    id: 'n5',
    title: '2027 ASEAN Excellence Scholarship Applications Now Open for International Students',
    category: 'Admissions',
    categoryKey: 'admissions',
    date: '28 Sep 2026',
    readTime: '3 min read',
    tag: 'Scholarship',
    image: '/img/campus-life.jpg',
    excerpt: 'Merit-based and need-aware tuition relief funding expanded for prospective students enrolling in our flexible on-campus and remote degree programmes.'
  },
  {
    id: 'n6',
    title: 'Modern Healthcare Informatics Programme Receives Prestigious International Accreditation',
    category: 'Academic',
    categoryKey: 'academic',
    date: '15 Sep 2026',
    readTime: '5 min read',
    tag: 'Accreditation',
    image: '/img/program-health.jpg',
    excerpt: 'The Faculty of Medicine & Allied Health Sciences secures global standard curriculum accreditation, providing graduates with recognized practice pathways.'
  }
];

const NEWS_CATEGORIES = [
  { id: 'all', label: 'All Updates' },
  { id: 'academic', label: 'Academic & Research' },
  { id: 'campus', label: 'Campus Life' },
  { id: 'admissions', label: 'Admissions & Scholarships' },
  { id: 'partnerships', label: 'Partnerships' },
];

export default function HomeView({ onNavigate, portalData }: HomeViewProps) {
  const [activeAnn, setActiveAnn] = useState(0);
  const [progFilter, setProgFilter] = useState('all');
  const testiTrackRef = useRef<HTMLDivElement>(null);
  const homeRef = useRef<HTMLDivElement>(null);

  // Derive dynamic portal metrics from SIAKAD
  const metrics = portalData?.metrics || [];
  const getMetricValue = (key: string, fallback: number) => {
    const found = metrics.find((m: any) => m.key === key);
    if (found && typeof found.value === 'number' && found.value >= 0) {
      return found.value;
    }
    return fallback;
  };

  const studentCount = getMetricValue('students', portalData?.studentTotal ?? 3000);
  const facultyCount = portalData?.unitRows?.length || 8;
  const programCount = getMetricValue('programs', 24);
  const lecturerCount = getMetricValue('lecturers', 12);
  const courseCount = getMetricValue('courses', 26);

  // News running marquee states
  const [activeNewsFilter, setActiveNewsFilter] = useState('all');
  const [isMarqueePaused, setIsMarqueePaused] = useState(false);

  // Filtered news articles
  const filteredNews = NEWS_ARTICLES.filter((item) => {
    if (activeNewsFilter === 'all') return true;
    if (activeNewsFilter === 'academic') return item.categoryKey === 'academic' || item.categoryKey === 'research';
    return item.categoryKey === activeNewsFilter;
  });

  // Ensure minimum 6 cards per set so marquee track always covers wide screens seamlessly
  const displayNews = filteredNews.length < 5
    ? [...filteredNews, ...filteredNews, ...filteredNews].slice(0, 6)
    : filteredNews;

  // Announcement ticker
  useEffect(() => {
    const timer = setInterval(() => {
      setActiveAnn((prev) => (prev + 1) % 4);
    }, 4500);
    return () => clearInterval(timer);
  }, []);

  // Number count-up effect & reveal
  useEffect(() => {
    if (!homeRef.current) return;
    const countElements = homeRef.current.querySelectorAll<HTMLElement>('[data-count]');
    
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

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const targetEl = entry.target as HTMLElement;
          const row = targetEl.closest('.row') || targetEl.parentElement;
          const sym = row ? row.querySelector<HTMLElement>('[data-sym]') : null;
          countUp(targetEl, sym);
          observer.unobserve(targetEl);
        }
      });
    }, { threshold: 0.3 });

    countElements.forEach((el) => {
      observer.observe(el);
      const rect = el.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        const row = el.closest('.row') || el.parentElement;
        const sym = row ? row.querySelector<HTMLElement>('[data-sym]') : null;
        countUp(el, sym);
      }
    });

    // Scroll reveal observer
    const rvElements = homeRef.current.querySelectorAll<HTMLElement>('.rv:not(.in)');
    const rvObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          rvObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    rvElements.forEach((el) => rvObserver.observe(el));

    return () => {
      observer.disconnect();
      rvObserver.disconnect();
    };
  }, [studentCount, facultyCount, programCount, lecturerCount, courseCount]);

  const handleTestiScroll = (dir: 'prev' | 'next') => {
    if (!testiTrackRef.current) return;
    const tr = testiTrackRef.current;
    const step = Math.min(tr.clientWidth * 0.85, 446);
    tr.scrollBy({ left: dir === 'next' ? step : -step, behavior: 'smooth' });
  };

  return (
    <main className="view is-on" id="view-home" ref={homeRef}>
{/*  HERO  */}
        <section className="hero">
            <HeroSlideshow />
            <div className="wrap">
                <div className="hero-grid">
                    <div className="hero-content">
                        <div className="eyebrow" style={{ color: "var(--gold-soft)" }}>ASEAN University International · Study From
                            Anywhere</div>
                        <h1>
                            Transformative Education,<br />
                            <em>Unbelievable Impact</em>
                        </h1>
                        <p className="lede">Learn how our transformative education and multidisciplinary research have
                            nurtured effective global leaders, impacted society and transformed lives for the better.
                        </p>
                        <div className="hero-btns">
                            <a href="#tuition" className="btn btn-gold">Apply now</a>
                            <a href="#anywhere" className="btn btn-ghost">Study From Anywhere</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {/*  ===== STATS (Berbasis Data Portal SIAKAD) =====  */}
        <div className="stats" id="portal-stats">
            <div className="wrap">
                <div className="stat">
                    <div className="row">
                        <b><span data-count={studentCount}>0</span></b>
                        <span className="sym" data-sym>+</span>
                    </div>
                    <span className="lbl">students enrolled from<br />5+ countries</span>
                </div>
                <div className="stat">
                    <div className="row">
                        <b><span data-count={facultyCount}>0</span></b>
                    </div>
                    <span className="lbl">academic faculties and<br />{programCount}+ study programmes</span>
                </div>
                <div className="stat">
                    <div className="row">
                        <b><span data-count={lecturerCount}>0</span></b>
                        <span className="sym" data-sym>+</span>
                    </div>
                    <span className="lbl">academic lecturers &amp;<br />faculty members</span>
                </div>
                <div className="stat">
                    <div className="row">
                        <b><span data-count={courseCount}>0</span></b>
                        <span className="sym" data-sym>+</span>
                    </div>
                    <span className="lbl">accredited courses &amp;<br />curriculum subjects</span>
                </div>
            </div>
        </div>

        {/*  PENGUMUMAN  */}
        <div className="ann">
            <div className="wrap">
                <span className="ann-tag">Announcement</span>
                <div className="ann-body" id="annBody">
                    <div className="ann-item is-on"><b>Scholarship Available</b> — for Bachelor, Master and Doctor
                        programmes.</div>
                    <div className="ann-item"><b>Study From Anywhere In The World</b> — online, or at your own pace with or
                        without attending classes.</div>
                    <div className="ann-item"><b>Admissions</b> — admin@aui.university · +60 165 680 906 · portal-aui</div>
                </div>
                <div className="ann-dots" id="annDots">
                    {[0, 1, 2, 3].map((idx) => (
                      <button
                        key={idx}
                        type="button"
                        aria-label={`Announcement ${idx + 1}`}
                        className={activeAnn === idx ? "is-on" : ""}
                        onClick={() => setActiveAnn(idx)}
                      />
                    ))}
                  </div>
            </div>
        </div>

        {/*  NILAI UNGGUL  */}
        <section className="sec">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow">01 — Why AUI</div>
                    <h2>Four pillars behind the AUI learning experience</h2>
                </div>
                <div className="values rv">
                    <div className="value">
                        <div className="ic">◉</div>
                        <h3>Study From Anywhere</h3>
                        <p>Every programme we offer can be studied from anywhere in the world — online, or at your own
                            pace.</p>
                        <a href="#anywhere" className="link-gold">Selengkapnya →</a>
                    </div>
                    <div className="value">
                        <div className="ic">✦</div>
                        <h3>Accredited Quality</h3>
                        <p>Whichever study method you choose, you'll experience the same high‑quality, accredited
                            education programme of study.</p>
                        <a href="#" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }} className="link-gold">Selengkapnya →</a>
                    </div>
                    <div className="value">
                        <div className="ic">◎</div>
                        <h3>Global Network</h3>
                        <p>Nearly 30,000 students and 20,000 alumni — a powerful, connected network right across the
                            globe.</p>
                        <a href="#network" className="link-gold">Selengkapnya →</a>
                    </div>
                    <div className="value">
                        <div className="ic">◐</div>
                        <h3>Career Impact</h3>
                        <p>90% graduate employability rate within 6 months, supported by 100+ industry partnerships
                            worldwide.</p>
                        <a href="#network" className="link-gold">Selengkapnya →</a>
                    </div>
                </div>
            </div>
        </section>

        {/*  STUDY FROM ANYWHERE  */}
        <section className="sec" id="anywhere" style={{ paddingTop: "0" }}>
            <div className="wrap">
                <div className="split">
                    <div className="rv">
                        <div className="eyebrow">02 — Flexible Learning</div>
                        <h2>Study From Anywhere In The World.</h2>
                        <p>Every programme we offer can be studied from anywhere in the world – choose to study online,
                            or at your own pace with or without attending classes.</p>
                        <div className="modes">
                            <div className="mode"><i>◉</i>
                                <div><b>Online</b><span>Study from anywhere in the world.</span></div>
                            </div>
                            <div className="mode"><i>◷</i>
                                <div><b>At your own pace</b><span>With or without attending classes.</span></div>
                            </div>
                            <div className="mode"><i>✦</i>
                                <div><b>Same accredited quality</b><span>Whichever study method you choose, you'll
                                        experience the same high‑quality, accredited education programme of
                                        study.</span></div>
                            </div>
                        </div>
                        <p>With nearly 30,000 students and 20,000 alumni, we offer a part of a powerful, connected
                            network right across the globe and a learning experience that's both personalised and
                            flexible.</p>
                        <a href="#" className="btn btn-line" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Explore Academic Programmes →</a>
                    </div>
                    <figure className="rv">
                        <img loading="lazy"
                            src="/img/depan-asen-university-international.webp"
                            alt="Gedung Kampus Utama ASEAN University International - Study from anywhere" />
                        <div className="tagfloat">Nearly 30,000 students &amp; 20,000 alumni worldwide</div>
                    </figure>
                </div>
            </div>
        </section>

        {/*  ===== 03 — ACADEMIC (FIXED: kartu bersih, tanpa badge melayang, gambar andal) =====  */}
        <section className="sec" id="programmes" style={{ paddingTop: "0" }}>
            <div className="wrap">
                <div className="prog-layout">
                    <div className="prog-intro rv">
                        <div className="eyebrow">03 — Academic</div>
                        <h2>Find Your Path</h2>
                        <p>Choose your level of study — Bachelor, Master, or Doctor — and the study method that fits
                            your life. Every AUI programme can be studied from anywhere in the world.</p>
                        <a href="#" className="btn btn-line" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>View All Programmes →</a>
                        <div className="prog-note">
                            <b>8 Faculties</b>
                            20+ research centers · 3 programme levels (S1, S2, S3) · online &amp; own‑pace study methods
                        </div>
                    </div>
                    <div>
                        <div className="tabs" id="progTabs">
              <button type="button" className={`tab ${progFilter === "all" ? "is-on" : ""}`} onClick={() => setProgFilter("all")}>All Programmes</button>
              <button type="button" className={`tab ${progFilter === "bachelor" ? "is-on" : ""}`} onClick={() => setProgFilter("bachelor")}>Bachelor (S1)</button>
              <button type="button" className={`tab ${progFilter === "master" ? "is-on" : ""}`} onClick={() => setProgFilter("master")}>Master (S2)</button>
              <button type="button" className={`tab ${progFilter === "doctor" ? "is-on" : ""}`} onClick={() => setProgFilter("doctor")}>Doctor (S3)</button>
            </div>
                        <div className="prog-grid" id="progGrid">

                            <article className="card rv" data-lvl="s1">
                                <figure>
                                    <img loading="lazy"
                                        src="/img/program-arts.jpg"
                                        alt="Bachelor degree programmes" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Undergraduate (S1)</span>
                                    <h3><a href="#" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Bachelor Degree · 3.5–4 Years</a></h3>
                                    <div className="fee-line">MYR 35,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <p>Kurikulum sarjana modern yang dirancang bersama industri dan mitra riset global — fleksibel dan berkualitas internasional.</p>
                                    <a href="#" className="link-gold" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now →</a>
                                </div>
                            </article>

                            <article className="card rv" data-lvl="s2">
                                <figure>
                                    <img loading="lazy"
                                        src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=800&q=70"
                                        alt="Master programme class" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Graduate (S2)</span>
                                    <h3><a href="#" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Master Degree · 1.5–2 Years</a></h3>
                                    <div className="fee-line">MYR 45,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <p>Pendidikan pascasarjana bertaraf tinggi dan terakreditasi internasional untuk akselerasi karier eksekutif dan profesional.</p>
                                    <a href="#" className="link-gold" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now →</a>
                                </div>
                            </article>

                            <article className="card rv" data-lvl="doc">
                                <figure>
                                    <img loading="lazy"
                                        src="https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=800&q=70"
                                        alt="Doctoral research" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Doctoral (S3)</span>
                                    <h3><a href="#" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Ph.D / Doctorate · 3–5 Years</a></h3>
                                    <div className="fee-line">MYR 65,000</div>
                                    <div className="sch">Scholarship Available</div>
                                    <p>Program doktoral dengan penekanan pada riset multidisiplin terapan dan dampak nyata bagi kemajuan ilmu pengetahuan.</p>
                                    <a href="#" className="link-gold" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now →</a>
                                </div>
                            </article>

                            <article className="card rv" data-lvl="mode">
                                <figure>
                                    <img loading="lazy"
                                        src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=800&q=70"
                                        alt="Online study" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Study Mode</span>
                                    <h3>Online</h3>
                                    <p>Every programme we offer can be studied from anywhere in the world.</p>
                                    <a href="#anywhere" className="link-gold">Selengkapnya →</a>
                                </div>
                            </article>

                            <article className="card rv" data-lvl="mode">
                                <figure>
                                    <img loading="lazy"
                                        src="https://images.unsplash.com/photo-1501504905252-473c47e087f8?auto=format&fit=crop&w=800&q=70"
                                        alt="Study at your own pace" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Study Mode</span>
                                    <h3>At Your Own Pace</h3>
                                    <p>Study online, or at your own pace with or without attending classes.</p>
                                    <a href="#anywhere" className="link-gold">Selengkapnya →</a>
                                </div>
                            </article>

                            <article className="card rv" data-lvl="mode">
                                <figure>
                                    <img loading="lazy"
                                        src="https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=70"
                                        alt="Industry partnership" />
                                </figure>
                                <div className="body">
                                    <span className="lvl">Research &amp; Industry</span>
                                    <h3>8 Faculties · 20+ Centers</h3>
                                    <p>20+ research centers and 100+ industry partnerships worldwide support every
                                        programme.</p>
                                    <a href="#network" className="link-gold">Selengkapnya →</a>
                                </div>
                            </article>

                        </div>
                    </div>
                </div>
            </div>
        </section>

        {/*  TUITION FEES  */}
        <section className="sec tuition" id="tuition">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow" style={{ color: "var(--gold-soft)" }}>04 — Admissions · Payment Information</div>
                    <h2>Tuition Fees and Special Offer</h2>
                    <p>Choose your level of study. Every programme comes with scholarship opportunities.</p>
                </div>
                <div className="fees">
                    <article className="fee rv">
                        <span className="lvl">Undergraduate</span>
                        <h3>Bachelor</h3>
                        <span className="cur">Tuition Fee</span>
                        <div className="price"><small>MYR</small> 35<small>,000</small></div>
                        <span className="badge">Scholarship Available</span>
                        <a href="#" className="btn btn-brand" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now</a>
                    </article>
                    <article className="fee rv">
                        <span className="lvl">Postgraduate</span>
                        <h3>Master</h3>
                        <span className="cur">Tuition Fee</span>
                        <div className="price"><small>MYR</small> 45<small>,000</small></div>
                        <span className="badge">Scholarship Available</span>
                        <a href="#" className="btn btn-brand" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now</a>
                    </article>
                    <article className="fee rv">
                        <span className="lvl">Doctoral</span>
                        <h3>Doctor</h3>
                        <span className="cur">Tuition Fee</span>
                        <div className="price"><small>MYR</small> 65<small>,000</small></div>
                        <span className="badge">Scholarship Available</span>
                        <a href="#" className="btn btn-brand" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply Now</a>
                    </article>
                </div>
            </div>
        </section>

        {/*  ===== 05 — THE AUI NETWORK (FIXED: simbol + dan % muncul setelah count-up) =====  */}
        <section className="sec achieve" id="network">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow">05 — The AUI Network</div>
                    <h2>A powerful, connected network across the globe</h2>
                    <p>Joining ASEAN University opens up great opportunities for your academic and professional
                        development.</p>
                </div>
                <div className="achieve-grid rv">
                    <div className="ach">
                        <div className="row">
                            <b><span data-count="30000">0</span></b>
                        </div>
                        <span>students — nearly 30,000 across the globe</span>
                    </div>
                    <div className="ach">
                        <div className="row">
                            <b><span data-count="20000">0</span></b>
                        </div>
                        <span>alumni in the AUI connected network</span>
                    </div>
                    <div className="ach">
                        <div className="row">
                            <b><span data-count="20">0</span></b>
                            <span className="sym" data-sym>+</span>
                        </div>
                        <span>research centers across 8 academic faculties</span>
                    </div>
                    <div className="ach">
                        <div className="row">
                            <b><span data-count="100">0</span></b>
                            <span className="sym" data-sym>+</span>
                        </div>
                        <span>industry partnerships worldwide</span>
                    </div>
                </div>
                <div className="accred rv">
                    <div>High-quality accredited education</div>
                    <div>Online · At your own pace</div>
                    <div>Students from 5+ countries</div>
                    <div>90% employability within 6 months</div>
                </div>
            </div>
        </section>

        {/*  ABOUT AUI  */}
        <section className="sec" id="about">
            <div className="wrap">
                <div className="about">
                    <div className="rv">
                        <div className="eyebrow">06 — About</div>
                        <h2>Asean University International</h2>
                        <div className="sub">With Unbelievable Impact</div>
                        <p>At ASEAN University International (AUI), we foster excellent education that is engaging and
                            enthusiastically presented, with an emphasis on disseminating practical and pertinent
                            knowledge. Our goal is to provide students with the opportunity to delve into a vast world
                            of information taught by professionals and graduate with essential skills for the latest
                            industries.</p>
                        <Link
                            href="/about"
                            className="link-gold"
                            style={{ marginTop: "10px" }}
                            onClick={(e) => {
                                if (onNavigate) {
                                    e.preventDefault();
                                    onNavigate('about');
                                    window.scrollTo({ top: 0, behavior: 'instant' });
                                }
                            }}
                        >
                            More about AUI →
                        </Link>
                    </div>
                    <div className="about-figure rv">
                        <img loading="lazy"
                            src="/img/Kompleks%20Kampus%20Bata%20dengan%20Langit%20Putih.png"
                            alt="AUI campus institution" />
                        <img loading="lazy"
                            src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=500&q=70"
                            alt="Professional lecturer" />
                        <img loading="lazy"
                            src="https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&w=500&q=70"
                            alt="Students in class" />
                    </div>
                </div>
            </div>
        </section>

        {/*  ===== 07 — STUDENTS (REDESIGNED) =====  */}
        <section className="portal" id="students">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow">07 — Students</div>
                    <h2>Everything you need, in one place</h2>
                    <p>Akses portal mahasiswa, informasi pembayaran, dan bantuan admissions — atau cari langsung di
                        bawah.</p>
                </div>
                <div className="svc-grid rv">
                    <div className="svc">
                        <div className="ic">◎</div>
                        <h3>portal-aui</h3>
                        <p>Masuk ke student portal untuk mengakses layanan akademik, jadwal, dan informasi pribadi Anda
                            sebagai mahasiswa AUI.</p>
                        <a href="/login" className="btn btn-brand">Enter Portal &rarr;</a>
                    </div>
                    <div className="svc">
                        <div className="ic">💳</div>
                        <h3>Payment Information</h3>
                        <p>Informasi lengkap mengenai metode pembayaran tuition fee, jadwal pembayaran, dan pertanyaan
                            umum seputar biaya kuliah.</p>
                        <a href="#tuition" className="btn btn-line">View Payment Info</a>
                    </div>
                    <div className="svc">
                        <div className="ic">✉</div>
                        <h3>Contact Support</h3>
                        <p>Hubungi tim admissions AUI melalui email admin@aui.university atau telepon +60 165 680 906
                            untuk bantuan lebih lanjut.</p>
                        <a href="#contact" className="btn btn-line">Contact Us</a>
                    </div>
                </div>
                <form className="searchbar rv" onSubmit={(e) => e.preventDefault()} suppressHydrationWarning>
                    <input type="search"
                        placeholder="Students…  search programme, scholarship, payment, or student services"
                        aria-label="Search for students" suppressHydrationWarning />
                    <button type="submit">SEARCH</button>
                </form>
            </div>
        </section>

        {/*  08 — COMMUNITY  */}
        <section className="sec">
            <div className="wrap">
                <div className="life-split rv">
                    <div>
                        <div className="eyebrow">08 — Community</div>
                        <h2>More Than a Programme — A Global Scholarly Family</h2>
                        <p>Be part of a powerful, connected network right across the globe — a learning experience that's both personalised and flexible, from your first enquiry to your career after graduation.</p>
                        <p>Whether engaging in collaborative international research symposia, networking with regional enterprise leaders, or participating in peer-led discussion roundtables, AUI connects you with ambitious minds across Southeast Asia and worldwide.</p>
                        <p style={{ marginTop: "24px" }}>
                            <a href="#network" className="btn btn-brand">Discover the AUI Network →</a>
                        </p>
                    </div>
                    <div className="life-mosaic">
                        <img loading="lazy"
                            src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=70"
                            alt="AUI global community" />
                        <img loading="lazy"
                            src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=600&q=70"
                            alt="Students collaboration" />
                        <img loading="lazy"
                            src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=600&q=70"
                            alt="Admissions support" />
                    </div>
                </div>
            </div>
        </section>

        {/*  08.5 — ACADEMIC MILESTONE & STUDENT SUCCESS  */}
        <section className="sec milestone-sec" id="graduation-achievement">
            <div className="wrap">
                {/* Section Header */}
                <div className="milestone-head rv">
                    <div className="eyebrow">Academic Milestone &amp; Student Success</div>
                    <h2>Graduation Achievement: Empowering Tomorrow&apos;s Leaders</h2>
                    <p>
                        Setiap upacara wisuda di ASEAN University International menandai keberhasilan transformatif mahasiswa dari berbagai negara ASEAN dan dunia. Lulusan kami dibekali kompetensi kepemimpinan praktis, wawasan global, dan gelar terakreditasi internasional yang diakui dunia industri.
                    </p>
                </div>

                {/* 2-Column Split: Visual & Pillars */}
                <div className="milestone-split rv">
                    {/* Left Column: Authentic Graduation Photo Card */}
                    <div className="milestone-photo-card">
                        <div className="milestone-img-wrap">
                            <img
                                src="/img/89376r98172e09812r.jpeg"
                                alt="ASEAN University International Graduation Achievement & Convocation"
                                className="milestone-img"
                                loading="lazy"
                            />
                            <div className="milestone-photo-tag">
                                <span className="dot"></span>
                                Official Convocation Milestone
                            </div>
                        </div>
                        <div className="milestone-photo-caption">
                            <div className="caption-icon">🎓</div>
                            <div className="caption-text">
                                <strong>Global Alumni &amp; Convocation Celebration</strong>
                                <span>Mencetak generasi pemimpin inovatif, profesional unggul, dan cendekiawan berintegritas tinggi</span>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Narrative, Pillars & CTA */}
                    <div className="milestone-story">
                        <h3>Mempersiapkan Pemimpin Masa Depan dengan Wawasan Global</h3>
                        <p className="lead-p">
                            Kurikulum terapan AUI dirancang komprehensif untuk memastikan setiap mahasiswa memperoleh keahlian praktis yang langsung relevan dengan dinamika industri global, didukung dosen pakar internasional dan keleluasaan belajar kelas dunia.
                        </p>

                        <div className="milestone-pillars">
                            <div className="milestone-pillar-item">
                                <div className="pillar-bullet">✦</div>
                                <div>
                                    <strong>Internationally Accredited Degrees</strong>
                                    <span>Gelar akademik terakreditasi internasional yang diakui dunia industri dan kualifikasi global.</span>
                                </div>
                            </div>
                            <div className="milestone-pillar-item">
                                <div className="pillar-bullet">✦</div>
                                <div>
                                    <strong>Accelerated Career Readiness</strong>
                                    <span>Program mentoring intensif, proyek praktikum riil, dan persiapan kompetensi profesional terdepan.</span>
                                </div>
                            </div>
                            <div className="milestone-pillar-item">
                                <div className="pillar-bullet">✦</div>
                                <div>
                                    <strong>Connected Regional Alumni Network</strong>
                                    <span>Akses langsung ke jaringan alumni di lebih dari 5 negara ASEAN serta kemitraan korporasi multinasional.</span>
                                </div>
                            </div>
                        </div>

                        <div className="milestone-actions">
                            <Link
                                href="/admissions"
                                onClick={(e) => {
                                    if (onNavigate) {
                                        e.preventDefault();
                                        onNavigate('admissions');
                                        window.scrollTo({ top: 0, behavior: 'instant' });
                                    }
                                }}
                                className="btn btn-gold"
                            >
                                Apply for Next Intake &rarr;
                            </Link>
                            <Link
                                href="/academic"
                                onClick={(e) => {
                                    if (onNavigate) {
                                        e.preventDefault();
                                        onNavigate('academic');
                                        window.scrollTo({ top: 0, behavior: 'instant' });
                                    }
                                }}
                                className="btn btn-line"
                            >
                                Explore Programmes &rarr;
                            </Link>
                        </div>
                    </div>
                </div>

                {/* 4-Column Balanced Key Metrics */}
                <div className="milestone-stats-grid rv">
                    <div className="mstat-card">
                        <div className="mstat-num">90%+</div>
                        <div className="mstat-title">Employability Rate</div>
                        <div className="mstat-desc">Lulusan terserap kerja &le; 6 bulan atau studi lanjut</div>
                    </div>
                    <div className="mstat-card">
                        <div className="mstat-num">3,000+</div>
                        <div className="mstat-title">Global Graduates</div>
                        <div className="mstat-desc">Alumni berkarier di Asia Tenggara dan internasional</div>
                    </div>
                    <div className="mstat-card">
                        <div className="mstat-num">5+</div>
                        <div className="mstat-title">Member Countries</div>
                        <div className="mstat-desc">Jaringan representasi mahasiswa lintas batas negara</div>
                    </div>
                    <div className="mstat-card">
                        <div className="mstat-num">100+</div>
                        <div className="mstat-title">Industry Partners</div>
                        <div className="mstat-desc">Kerjasama korporasi untuk magang dan rekrutmen karier</div>
                    </div>
                </div>
            </div>
        </section>

        {/*  09 — NEWS & ARTICLES UNLIMITED RUNNING MARQUEE  */}
        <section 
            className="sec news-sec" 
            id="news"
        >
            <div className="wrap">
                {/* SECTION HEADER & CONTROLS */}
                <div className="news-header-wrap rv">
                    <div>
                        <div className="eyebrow">09 — News &amp; Articles</div>
                        <h2 style={{ fontFamily: "var(--serif)", fontSize: "clamp(30px, 3.4vw, 42px)", fontWeight: 600, color: "var(--brand)", lineHeight: 1.15 }}>
                            Insights, Breakthroughs &amp; Campus Stories
                        </h2>
                        {/* CATEGORY FILTER TABS */}
                        <div className="news-filters">
                            {NEWS_CATEGORIES.map((cat) => (
                                <button
                                    key={cat.id}
                                    type="button"
                                    className={`news-filter-btn ${activeNewsFilter === cat.id ? 'is-active' : ''}`}
                                    onClick={() => setActiveNewsFilter(cat.id)}
                                >
                                    {cat.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="news-nav-controls">
                        <button
                            type="button"
                            className={`news-status-pill ${isMarqueePaused ? 'is-paused' : ''}`}
                            onClick={() => setIsMarqueePaused(!isMarqueePaused)}
                            title="Toggle autoplay"
                        >
                            <span className="pulse-dot"></span>
                            <span>{isMarqueePaused ? '▶ Resume Motion' : '⏸ Running (Hover to Pause)'}</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* CONTINUOUS UNLIMITED RUNNING MARQUEE VIEWPORT */}
            <div 
                className="news-marquee-viewport rv"
                onMouseEnter={() => setIsMarqueePaused(true)}
                onMouseLeave={() => setIsMarqueePaused(false)}
            >
                <div className={`news-marquee-track ${isMarqueePaused ? 'is-paused' : ''}`}>
                    {/* Primary Group */}
                    <div className="news-marquee-group">
                        {displayNews.map((item, idx) => (
                            <div className="news-card-wrapper" key={`g1-${item.id}-${idx}`}>
                                <article className="news-card">
                                    <figure>
                                        <img loading="lazy" src={item.image} alt={item.title} />
                                        <span className="news-card-tag">{item.tag}</span>
                                    </figure>
                                    <div className="body">
                                        <div className="meta">
                                            <span className="category">{item.category}</span>
                                            <span>{item.date} · {item.readTime}</span>
                                        </div>
                                        <h3>{item.title}</h3>
                                        <p>{item.excerpt}</p>
                                        <div className="card-footer">
                                            <a href="#contact" className="link-gold">Read Full Article &rarr;</a>
                                            <span style={{ fontSize: "11px", color: "var(--muted)", fontWeight: 600 }}>AUI Press</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        ))}
                    </div>

                    {/* Twin Group for Mathematical 0-Jerk Seamless Infinite Loop */}
                    <div className="news-marquee-group" aria-hidden="true">
                        {displayNews.map((item, idx) => (
                            <div className="news-card-wrapper" key={`g2-${item.id}-${idx}`}>
                                <article className="news-card">
                                    <figure>
                                        <img loading="lazy" src={item.image} alt={item.title} />
                                        <span className="news-card-tag">{item.tag}</span>
                                    </figure>
                                    <div className="body">
                                        <div className="meta">
                                            <span className="category">{item.category}</span>
                                            <span>{item.date} · {item.readTime}</span>
                                        </div>
                                        <h3>{item.title}</h3>
                                        <p>{item.excerpt}</p>
                                        <div className="card-footer">
                                            <a href="#contact" className="link-gold">Read Full Article &rarr;</a>
                                            <span style={{ fontSize: "11px", color: "var(--muted)", fontWeight: 600 }}>AUI Press</span>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="wrap">
                <div className="news-hint-bar rv">
                    <span>⚡ Seamless continuous stream — hover or touch any story to pause and read.</span>
                    <a href="#contact" className="link-gold" style={{ fontSize: "12.5px" }}>
                        View Media &amp; Press Archive &rarr;
                    </a>
                </div>
            </div>
        </section>

        {/*  10 — TESTIMONI  */}
        <section className="sec testi">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow">10 — Voices</div>
                    <h2>Why learners choose AUI</h2>
                    <p>Kartu di bawah ini adalah contoh tampilan — ganti dengan testimoni mahasiswa, dosen, dan alumni
                        AUI yang sebenarnya.</p>
                </div>
                <div className="testi-track" id="testiTrack" ref={testiTrackRef}>
                    <div className="tcard rv">
                        <blockquote>"I can study my Master from my own country, at my own pace, and still receive the
                            same accredited education."</blockquote>
                        <div className="who">
                            <img loading="lazy"
                                src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=200&q=70"
                                alt="Sample student" />
                            <div><b>Sample Student</b><span>Master · Online study</span><br /><span className="ph">Contoh
                                    tampilan</span></div>
                        </div>
                    </div>
                    <div className="tcard rv">
                        <blockquote>"The flexible study method let me keep working while completing my Bachelor — the
                            learning experience is personalised and flexible."</blockquote>
                        <div className="who">
                            <img loading="lazy"
                                src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=200&q=70"
                                alt="Sample alumnus" />
                            <div><b>Sample Alumnus</b><span>Bachelor · Own pace</span><br /><span className="ph">Contoh
                                    tampilan</span></div>
                        </div>
                    </div>
                    <div className="tcard rv">
                        <blockquote>"Practical and pertinent knowledge, taught by professionals — that is exactly what
                            AUI delivers in every class."</blockquote>
                        <div className="who">
                            <img loading="lazy"
                                src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=200&q=70"
                                alt="Sample lecturer" />
                            <div><b>Sample Lecturer</b><span>Faculty · Research center</span><br /><span className="ph">Contoh
                                    tampilan</span></div>
                        </div>
                    </div>
                </div>
                <div className="testi-nav">
              <button type="button" id="tPrev" aria-label="Previous testimonial" onClick={() => handleTestiScroll("prev")}>&larr;</button>
              <button type="button" id="tNext" aria-label="Next testimonial" onClick={() => handleTestiScroll("next")}>&rarr;</button>
            </div>
            </div>
        </section>

        {/*  11 — MITRA  */}
        <section className="sec" style={{ paddingTop: "80px" }}>
            <div className="wrap">
                <div className="sec-head rv" style={{ marginBottom: "36px" }}>
                    <div className="eyebrow">11 — Partnerships</div>
                    <h2>100+ industry partnerships worldwide</h2>
                    <p>Nama logo di bawah ini hanya placeholder — ganti dengan logo partner AUI yang sebenarnya.</p>
                </div>
                <div className="partners rv">
                    <div>Partner 01</div>
                    <div>Partner 02</div>
                    <div>Partner 03</div>
                    <div>Partner 04</div>
                    <div>Partner 05</div>
                    <div>Partner 06</div>
                </div>
            </div>
        </section>

        {/*  CTA  */}
        <section className="visit">
            <div className="wrap">
                <div>
                    <h3>Start Your Application Today</h3>
                    <p>Tuition from MYR 35,000 · Scholarship Available · Study from anywhere in the world.</p>
                </div>
                <div style={{ display: "flex", gap: "12px", flexWrap: "wrap" }}>
                    <a href="#" className="btn btn-brand" onClick={(e) => { if (onNavigate) { e.preventDefault(); onNavigate("detail"); window.scrollTo({top:0, behavior: "instant"}); } }}>Apply now</a>
                    <a href="#contact" className="btn btn-ghost">Contact Admissions</a>
                </div>
            </div>
        </section>

        {/*  12 — CONTACT  */}
        <section className="sec" id="contact">
            <div className="wrap">
                <div className="sec-head rv">
                    <div className="eyebrow">12 — Contact</div>
                    <h2>Talk to the AUI admissions team</h2>
                </div>
                <div className="contact-grid">
                    <div className="cinfo rv">
                        <div className="crow">
                            <div className="ic">✉</div>
                            <div><b>Email</b><a href="mailto:admin@aui.university">admin@aui.university</a></div>
                        </div>
                        <div className="crow">
                            <div className="ic">☎</div>
                            <div><b>Phone / WhatsApp</b><a href="tel:+60165680906">+60 165 680 906</a></div>
                        </div>
                        <div className="crow">
                            <div className="ic">◎</div>
                            <div><b>Student Portal</b><a href="/login">Portal AUI (Students)</a></div>
                        </div>
                        <div className="crow">
                            <div className="ic">⌂</div>
                            <div><b>Address</b><span>F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar
                                    Baru Bangi, Selangor, Malaysia</span></div>
                        </div>
                    </div>
                    <form className="cform rv" onSubmit={(e) => e.preventDefault()} suppressHydrationWarning>
                        <h3>Enquiry Form</h3>
                        <div><label htmlFor="fn">Full name</label><input id="fn" name="fullName" type="text" placeholder="Your name" autoComplete="name" suppressHydrationWarning /></div>
                        <div><label htmlFor="em">Email</label><input id="em" name="email" type="email" placeholder="you@email.com" autoComplete="email" suppressHydrationWarning /></div>
                        <div><label htmlFor="pg">Programme of interest</label>
                            <select id="pg" name="programme" suppressHydrationWarning>
                                <option>Bachelor — MYR 35,000</option>
                                <option>Master — MYR 45,000</option>
                                <option>Doctor — MYR 65,000</option>
                            </select>
                        </div>
                        <div><label htmlFor="sm">Preferred study method</label>
                            <select id="sm" name="studyMethod" suppressHydrationWarning>
                                <option>Online</option>
                                <option>At my own pace</option>
                                <option>With attending classes</option>
                            </select>
                        </div>
                        <div><label htmlFor="ms">Message</label><textarea id="ms" name="message"
                                placeholder="Tell us about your study plans…" suppressHydrationWarning></textarea></div>
                        <button className="btn btn-gold" type="submit">Send Enquiry</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
  );
}
