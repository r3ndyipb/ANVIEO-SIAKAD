

import React, { useState, useEffect, useRef } from 'react';
import Link from './Link';

interface PaymentViewProps {
  onNavigate?: (
    view:
      | 'home'
      | 'detail'
      | 'about'
      | 'structure'
      | 'admissions'
      | 'academic'
      | 'accreditation'
      | 'payment'
  ) => void;
}

export default function PaymentView({ onNavigate }: PaymentViewProps) {
  const [copiedId, setCopiedId] = useState<string | null>(null);
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

  const handleCopy = (id: string, text: string) => {
    const cleanText = text.replace(/[-\s]/g, '');
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard
        .writeText(cleanText)
        .then(() => {
          setCopiedId(id);
          setTimeout(() => setCopiedId(null), 2500);
        })
        .catch(() => fallbackCopy(id, cleanText));
    } else {
      fallbackCopy(id, cleanText);
    }
  };

  const fallbackCopy = (id: string, text: string) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try {
      document.execCommand('copy');
      setCopiedId(id);
      setTimeout(() => setCopiedId(null), 2500);
    } catch (err) {
      console.error('Copy failed', err);
    }
    document.body.removeChild(ta);
  };

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
      | 'payment'
  ) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  return (
    <main className="view is-on" id="view-payment" ref={containerRef}>
      {/* Skip to Content Anchor */}
      <div id="content" tabIndex={-1} style={{ outline: 'none' }} />

      {/* HERO SECTION */}
      <section className="ahero">
        <img
          className="ahero-img"
          src="https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=1800&q=70"
          alt="Payment information"
        />
        <div className="wrap">
          <div className="eyebrow">Payment Information · Official Bank Transfer</div>
          <h1>Payment Information</h1>
          <div className="sub">Direct Institutional Bank Transfer &amp; Tuition Fee Guide</div>
          <p className="lede">
            Silakan gunakan rincian rekening bank resmi di bawah ini untuk menyelesaikan transaksi pembayaran tuition
            fee, biaya registrasi, atau formulir pendaftaran ASEAN University International (AUI).
          </p>
        </div>
      </section>

      {/* QUICK INFO STRIP */}
      <div className="qinfo">
        <div className="wrap">
          <div className="qi">
            <b>3 Bank Options</b>
            <span>CIMB (Malaysia) · BNI · Mandiri (Indonesia)</span>
          </div>
          <div className="qi">
            <b>MYR &amp; IDR Accounts</b>
            <span>Rekening resmi berbadan hukum di Malaysia &amp; Indonesia</span>
          </div>
          <div className="qi">
            <b>One-Click Copy</b>
            <span>Salin nomor rekening dengan mudah dan akurat</span>
          </div>
        </div>
      </div>

      {/* ============================================================
          01 — BANK ACCOUNTS
          ============================================================ */}
      <section className="sec" id="banks">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">01 — Official Bank Transfer</div>
            <h2>Rekening Tujuan Pembayaran Resmi</h2>
            <p>
              Transfer dapat dilakukan melalui ATM, Mobile Banking, Internet Banking, atau Telegraphic Transfer (TT /
              Wire Transfer) internasional ke salah satu rekening di bawah ini:
            </p>
          </div>

          <div className="banks">
            {/* CIMB BANK MALAYSIA */}
            <article className="bank cimb rv">
              <div className="bank-inner">
                <div className="bank-brand">
                  <div className="icon">C</div>
                  <div>
                    <h3>CIMB</h3>
                    <span className="region">Malaysia (MYR)</span>
                  </div>
                </div>

                <div className="bank-details">
                  <div className="fields">
                    <div className="field">
                      <span className="label">Bank Name</span>
                      <span className="value bank-name-text">
                        CIMB Bank Berhad (Malaysia)
                      </span>
                    </div>
                    <div className="field">
                      <span className="label">Account Holder / Penerima</span>
                      <span className="holder">Asian International Education SDN BHD</span>
                    </div>
                  </div>

                  <div className="field">
                    <span className="label">Account Number / Nomor Rekening</span>
                    <span className="value acct" id="acct-cimb">
                      8605260456
                    </span>
                  </div>

                  <div className="bank-card-actions">
                    <button
                      type="button"
                      className={`copy-btn ${copiedId === 'cimb' ? 'copied' : ''}`}
                      onClick={() => handleCopy('cimb', '8605260456')}
                      aria-label="Salin nomor rekening CIMB Bank"
                    >
                      <span className="ic">{copiedId === 'cimb' ? '✓' : '⧉'}</span>
                      {copiedId === 'cimb' ? 'Copied to Clipboard!' : 'Copy Account Number'}
                    </button>
                    <span className="currency-pill">Currency: MYR (Malaysian Ringgit)</span>
                  </div>
                </div>
              </div>
            </article>

            {/* BNI INDONESIA */}
            <article className="bank bni rv">
              <div className="bank-inner">
                <div className="bank-brand">
                  <div className="icon">B</div>
                  <div>
                    <h3>BNI</h3>
                    <span className="region">Indonesia (IDR)</span>
                  </div>
                </div>

                <div className="bank-details">
                  <div className="fields">
                    <div className="field">
                      <span className="label">Bank Name</span>
                      <span className="value bank-name-text">
                        Bank Negara Indonesia (BNI)
                      </span>
                    </div>
                    <div className="field">
                      <span className="label">Account Holder / Penerima</span>
                      <span className="holder">Asian University International</span>
                    </div>
                  </div>

                  <div className="field">
                    <span className="label">Account Number / Nomor Rekening</span>
                    <span className="value acct" id="acct-bni">
                      8899-6644-04
                    </span>
                  </div>

                  <div className="bank-card-actions">
                    <button
                      type="button"
                      className={`copy-btn ${copiedId === 'bni' ? 'copied' : ''}`}
                      onClick={() => handleCopy('bni', '8899664404')}
                      aria-label="Salin nomor rekening BNI"
                    >
                      <span className="ic">{copiedId === 'bni' ? '✓' : '⧉'}</span>
                      {copiedId === 'bni' ? 'Copied to Clipboard!' : 'Copy Account Number'}
                    </button>
                    <span className="currency-pill">Currency: IDR (Indonesian Rupiah)</span>
                  </div>
                </div>
              </div>
            </article>

            {/* MANDIRI INDONESIA */}
            <article className="bank mandiri rv">
              <div className="bank-inner">
                <div className="bank-brand">
                  <div className="icon">M</div>
                  <div>
                    <h3>Mandiri</h3>
                    <span className="region">Indonesia (IDR)</span>
                  </div>
                </div>

                <div className="bank-details">
                  <div className="fields">
                    <div className="field">
                      <span className="label">Bank Name</span>
                      <span className="value bank-name-text">
                        Bank Mandiri (Persero) Tbk
                      </span>
                    </div>
                    <div className="field">
                      <span className="label">Account Holder / Penerima</span>
                      <span className="holder">Asian University International</span>
                    </div>
                  </div>

                  <div className="field">
                    <span className="label">Account Number / Nomor Rekening</span>
                    <span className="value acct" id="acct-mandiri">
                      133-00-20248480
                    </span>
                  </div>

                  <div className="bank-card-actions">
                    <button
                      type="button"
                      className={`copy-btn ${copiedId === 'mandiri' ? 'copied' : ''}`}
                      onClick={() => handleCopy('mandiri', '1330020248480')}
                      aria-label="Salin nomor rekening Bank Mandiri"
                    >
                      <span className="ic">{copiedId === 'mandiri' ? '✓' : '⧉'}</span>
                      {copiedId === 'mandiri' ? 'Copied to Clipboard!' : 'Copy Account Number'}
                    </button>
                    <span className="currency-pill">Currency: IDR (Indonesian Rupiah)</span>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* ============================================================
          02 — TUITION FEES & SPECIAL OFFER
          ============================================================ */}
      <section className="sec tuition-sec" id="tuition">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">02 — Tuition Fees</div>
            <h2>Tuition Fees and Special Offer</h2>
            <p>
              Transfer jumlah sesuai jenjang program studi yang Anda pilih ke salah satu rekening resmi di atas.
              Tersedia beasiswa dan skema cicilan untuk seluruh tingkatan.
            </p>
          </div>

          <div className="fees">
            {/* Bachelor */}
            <article className="fee rv">
              <span className="lvl">Undergraduate Track</span>
              <h3>Bachelor</h3>
              <span className="cur">Tuition Fee</span>
              <div className="price">
                <small>MYR</small> 35<small>,000</small>
              </div>
              <span className="badge">Scholarship Available</span>
              <div className="fee-actions">
                <Link
                  href="/admissions"
                  className="btn btn-brand"
                  onClick={(e) => handleLink(e, 'admissions')}
                >
                  Apply Now ↗
                </Link>
              </div>
            </article>

            {/* Master */}
            <article className="fee rv">
              <span className="lvl">Postgraduate Track</span>
              <h3>Master</h3>
              <span className="cur">Tuition Fee</span>
              <div className="price">
                <small>MYR</small> 45<small>,000</small>
              </div>
              <span className="badge">Scholarship Available</span>
              <div className="fee-actions">
                <Link
                  href="/admissions"
                  className="btn btn-brand"
                  onClick={(e) => handleLink(e, 'admissions')}
                >
                  Apply Now ↗
                </Link>
              </div>
            </article>

            {/* Doctor */}
            <article className="fee rv">
              <span className="lvl">Doctoral Track</span>
              <h3>Doctor</h3>
              <span className="cur">Tuition Fee</span>
              <div className="price">
                <small>MYR</small> 65<small>,000</small>
              </div>
              <span className="badge">Scholarship Available</span>
              <div className="fee-actions">
                <Link
                  href="/admissions"
                  className="btn btn-brand"
                  onClick={(e) => handleLink(e, 'admissions')}
                >
                  Apply Now ↗
                </Link>
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* ============================================================
          03 — PAYMENT STEPS
          ============================================================ */}
      <section className="sec" id="steps">
        <div className="wrap">
          <div className="sec-head rv">
            <div className="eyebrow">03 — How to Pay</div>
            <h2>Langkah-Langkah Pembayaran</h2>
            <p>Ikuti empat langkah sederhana untuk menyelesaikan proses transfer tuition fee Anda:</p>
          </div>

          <div className="steps">
            <div className="step rv">
              <div className="n">01</div>
              <h4>Pilih Rekening</h4>
              <p>
                Pilih salah satu dari 3 bank resmi yang tersedia (CIMB Malaysia, BNI, atau Mandiri) sesuai lokasi dan
                mata uang transfer Anda.
              </p>
            </div>

            <div className="step rv">
              <div className="n">02</div>
              <h4>Salin Nomor Rekening</h4>
              <p>
                Gunakan tombol &quot;Copy&quot; pada kartu rekening di atas untuk menghindari kesalahan ketik nomor
                rekening tujuan.
              </p>
            </div>

            <div className="step rv">
              <div className="n">03</div>
              <h4>Transfer Sesuai Jumlah</h4>
              <p>
                Lakukan transfer sesuai nominal tagihan atau jenjang studi Anda (Bachelor MYR 35,000 · Master MYR 45,000
                · Doctor MYR 65,000).
              </p>
            </div>

            <div className="step rv">
              <div className="n">04</div>
              <h4>Kirim Bukti Transfer</h4>
              <p>
                Kirimkan resi / slip transfer ke email <b>admin@aui.university</b> atau melalui WhatsApp resmi{' '}
                <b>+60 165 680 906</b> untuk verifikasi.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
          NOTES & VISA SOLVENCY
          ============================================================ */}
      <section className="sec-notes" style={{ padding: '0 0 104px' }}>
        <div className="wrap">
          <div className="notes">
            <div className="note gold rv">
              <div className="icon">💡</div>
              <h4>Catatan Penting Keamanan Pembayaran</h4>
              <p>
                Pastikan Anda hanya mentransfer ke rekening resmi atas nama <b>Asian International Education SDN BHD</b>{' '}
                (CIMB Malaysia) atau <b>Asian University International</b> (BNI &amp; Mandiri Indonesia). AUI tidak pernah
                menggunakan rekening pribadi individu.
              </p>
              <ul>
                <li>Selalu cocokkan nama penerima di layar ATM/m-Banking sebelum menekan konfirmasi</li>
                <li>Simpan bukti transfer hingga Surat Penerimaan (Letter of Acceptance) resmi diterbitkan</li>
                <li>Hubungi admissions jika transfer mengalami kendala atau membutuhkan invoice resmi</li>
              </ul>
            </div>

            <div className="note blue rv">
              <div className="icon">🛡</div>
              <h4>Financial Solvency for Student Visa</h4>
              <p>
                Immigration departments across the ASEAN zone uniformly demand verified proof of liquidity via a formal
                Bank Statement. This security fund must comfortably cover tuition fees plus living overheads for at least{' '}
                <b>1 full calendar academic year</b>.
              </p>
              <ul>
                <li>Dana harus mengendap (mature) pada rekening sebelum proses pengajuan visa pelajar dimulai</li>
                <li>Rekening koran / Bank Statement wajib menunjukkan saldo likuid yang memenuhi syarat</li>
                <li>Sekretariat AUI menyediakan surat pengantar resmi (Visa Support Letter) setelah verifikasi</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      {/* ============================================================
          CALL TO ACTION BANNER
          ============================================================ */}
      <section className="visit">
        <div className="wrap">
          <div>
            <h3>Need help with your payment?</h3>
            <p>
              Tim Admissions &amp; Finance AUI siap membantu konfirmasi transaksi, invoice institusi, atau panduan
              transfer valuta asing.
            </p>
          </div>
          <div style={{ display: 'flex', gap: '14px', flexWrap: 'wrap' }}>
            <a href="mailto:admin@aui.university?subject=Payment%20Inquiry" className="btn btn-brand">
              Contact Admissions ✉
            </a>
            <a href="tel:+60165680906" className="btn btn-ghost">
              WhatsApp +60 165 680 906 ☎
            </a>
          </div>
        </div>
      </section>

      {/* ============================================================
          CONTACT BAND
          ============================================================ */}
      <section className="cband" id="contact">
        <div className="wrap">
          <div className="cband-grid rv">
            <div className="cband-item">
              <div className="ic">✉</div>
              <div>
                <b>Email</b>
                <a href="mailto:admin@aui.university">admin@aui.university</a>
              </div>
            </div>
            <div className="cband-item">
              <div className="ic">☎</div>
              <div>
                <b>Phone / WhatsApp</b>
                <a href="tel:+60165680906">+60 165 680 906</a>
              </div>
            </div>
            <div className="cband-item">
              <div className="ic">◎</div>
              <div>
                <b>Student Portal</b>
                <a href="#students">portal-aui</a>
              </div>
            </div>
            <div className="cband-item">
              <div className="ic">⌂</div>
              <div>
                <b>Address</b>
                <span>F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia</span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
