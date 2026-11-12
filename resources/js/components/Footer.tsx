
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

interface FooterProps {
  onNavigate?: (view: AppView) => void;
}

export default function Footer({ onNavigate }: FooterProps) {
  const handleView = (e: React.MouseEvent, view: AppView) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  return (
    <footer className="ftr">
      <div className="wrap">
        <div className="ftr-main">
          <div className="ftr-brand">
            <Link
              href="/"
              className="logo"
              aria-label="AUI Home"
              onClick={(e) => handleView(e, 'home')}
            >
              <img
                src="/img/logo-footer.png"
                alt="ASEAN University International logo"
                width={250}
                height={88}
                style={{ height: '88px', width: 'auto', display: 'block' }}
              />
            </Link>
            <p>
              ASEAN University International is committed to transformative, multidisciplinary higher
              education that empowers leaders and impacts global society.
            </p>
            <div className="soc" aria-label="Social links">
              <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook">f</a>
              <a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a>
              <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" aria-label="YouTube">▶</a>
              <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram">ig</a>
            </div>
          </div>
          <div>
            <h4>Programmes</h4>
            <ul>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Master of Business</Link>
              </li>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Bachelor of CS</Link>
              </li>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Doctor of Philosophy</Link>
              </li>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Health Sciences</Link>
              </li>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Executive Education</Link>
              </li>
              <li>
                <Link href="/academic" onClick={(e) => handleView(e, 'academic')}>All Programmes →</Link>
              </li>
            </ul>
          </div>
          <div>
            <h4>Quick Links</h4>
            <ul>
              <li><Link href="/about" onClick={(e) => handleView(e, 'about')}>About AUI</Link></li>
              <li><Link href="/structure" onClick={(e) => handleView(e, 'structure')}>Structure</Link></li>
              <li><Link href="/admissions" onClick={(e) => handleView(e, 'admissions')}>Admissions</Link></li>
              <li><Link href="/academic" onClick={(e) => handleView(e, 'academic')}>Academic</Link></li>
              <li><Link href="/accreditation" onClick={(e) => handleView(e, 'accreditation')}>Accreditation</Link></li>
              <li><a href="#news">News &amp; Articles</a></li>
              <li><Link href="/payment-information" onClick={(e) => handleView(e, 'payment')}>Payment &amp; Bank Details</Link></li>
              <li><Link href="/contact" onClick={(e) => handleView(e, 'contact')}>Contact Information</Link></li>
              <li><a href="#network">AUI Network</a></li>
              <li><a href="#tuition">Scholarship Available</a></li>
              <li><a href="#anywhere">Study From Anywhere</a></li>
              <li><a href="/login" style={{ color: 'var(--gold-soft)', fontWeight: 600 }}>LOGIN PORTAL &rarr;</a></li>
            </ul>
          </div>
          <div>
            <h4>Contact Us</h4>
            <ul>
              <li>✉ <a href="mailto:admin@aui.university">admin@aui.university</a></li>
              <li>☎ <a href="tel:+60165680906">+60 165 680 906</a></li>
              <li>◎ <a href="/login">Portal AUI (Students)</a></li>
              <li style={{ marginTop: '8px' }}>
                F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia
              </li>
              <li style={{ marginTop: '8px' }}>
                <Link href="/contact" onClick={(e) => handleView(e, 'contact')} className="link-gold" style={{ fontWeight: 600 }}>
                  View Campus Map &amp; Directions &rarr;
                </Link>
              </li>
            </ul>
          </div>
        </div>
        <div className="ftr-btm">
          <span className="footer-copyright">&copy; {new Date().getFullYear()} ASEAN University International. All Rights Reserved.</span>
          <ul>
            <li>
              <Link href="/privacy" onClick={(e) => handleView(e, 'privacy')}>
                Privacy Policy
              </Link>
            </li>
            <li>
              <Link href="/terms" onClick={(e) => handleView(e, 'terms')}>
                Terms of Use
              </Link>
            </li>
            <li>
              <Link href="/accessibility" onClick={(e) => handleView(e, 'accessibility')}>
                Accessibility Statement
              </Link>
            </li>
          </ul>
        </div>
      </div>
    </footer>
  );
}
