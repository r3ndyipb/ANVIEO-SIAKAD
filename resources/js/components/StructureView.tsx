

import React, { useState, useMemo } from 'react';
import {
  STRUCTURE_MEMBERS,
  STRUCTURE_TIERS,
  LeaderMember,
} from '../data/structureData';

interface StructureViewProps {
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

export default function StructureView({ onNavigate: _onNavigate }: StructureViewProps) {
  const [activeTab, setActiveTab] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedLeader, setSelectedLeader] = useState<LeaderMember | null>(null);

  // Filter members based on active category tab & search query
  const filteredMembers = useMemo(() => {
    return STRUCTURE_MEMBERS.filter((m) => {
      const matchesTab = activeTab === 'all' || m.category === activeTab;
      const matchesSearch =
        searchQuery.trim() === '' ||
        m.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        m.role.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (m.facultyOrDivision &&
          m.facultyOrDivision.toLowerCase().includes(searchQuery.toLowerCase()));
      return matchesTab && matchesSearch;
    });
  }, [activeTab, searchQuery]);

  // Tab counts
  const counts = useMemo(() => {
    const c: Record<string, number> = { all: STRUCTURE_MEMBERS.length };
    STRUCTURE_TIERS.forEach((tier) => {
      c[tier.key] = STRUCTURE_MEMBERS.filter((m) => m.category === tier.key).length;
    });
    return c;
  }, []);

  return (
    <main className="view is-on" id="view-structure">
      {/* Skip to Content Anchor */}
      <div id="content" tabIndex={-1} style={{ outline: 'none' }} />

      {/* 1. PAGE HERO */}
      <section className="struct-hero">
        <img
          src="/img/bawah-asean-university-international.webp"
          alt="ASEAN University International Campus & Leadership Background"
          className="struct-hero-bg"
        />
        <div className="wrap">
          <div className="eyebrow">ASEAN UNIVERSITY INTERNATIONAL</div>
          <h1>University Leadership &amp; Governance Structure</h1>
          <p className="struct-lede">
            Struktur kepemimpinan, Dewan Pembina, Kanselir, Direksi, dan Dewan Dekan Fakultas
            ASEAN University International (AUI) di bawah naungan Asian International Education SDN BHD.
          </p>

          {/* Quick Stat Band */}
          <div className="struct-stat-band">
            <div className="struct-stat-item">
              <span className="struct-stat-num">32</span>
              <span className="struct-stat-label">Distinguished Leaders</span>
            </div>
            <div className="struct-stat-item">
              <span className="struct-stat-num">5</span>
              <span className="struct-stat-label">Governance Boards</span>
            </div>
            <div className="struct-stat-item">
              <span className="struct-stat-num">12</span>
              <span className="struct-stat-label">Academic Faculties</span>
            </div>
            <div className="struct-stat-item">
              <span className="struct-stat-num">5+</span>
              <span className="struct-stat-label">ASEAN Countries</span>
            </div>
          </div>
        </div>
      </section>

      {/* 2. FILTER & SEARCH CONTROLS */}
      <section className="struct-controls-bar">
        <div className="wrap">
          <div className="struct-controls-flex">
            {/* Filter Tabs */}
            <div className="struct-tabs" role="tablist" aria-label="Leadership Categories">
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'all'}
                className={`struct-tab ${activeTab === 'all' ? 'is-active' : ''}`}
                onClick={() => setActiveTab('all')}
              >
                All Members
                <span className="struct-tab-badge">{counts.all}</span>
              </button>
              {STRUCTURE_TIERS.map((tier) => (
                <button
                  key={tier.key}
                  type="button"
                  role="tab"
                  aria-selected={activeTab === tier.key}
                  className={`struct-tab ${activeTab === tier.key ? 'is-active' : ''}`}
                  onClick={() => setActiveTab(tier.key)}
                >
                  {tier.title}
                  <span className="struct-tab-badge">{counts[tier.key]}</span>
                </button>
              ))}
            </div>

            {/* Search Input */}
            <div className="struct-search-box">
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search leader by name, role, or faculty..."
                aria-label="Filter structure"
              />
              {searchQuery && (
                <button
                  type="button"
                  className="struct-search-clear"
                  onClick={() => setSearchQuery('')}
                  aria-label="Clear search"
                >
                  ✕
                </button>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* 3. MEMBERS DISPLAY */}
      <section className="struct-content-sec">
        <div className="wrap">
          {filteredMembers.length === 0 ? (
            <div className="struct-empty">
              <div className="struct-empty-icon">🔍</div>
              <h3>No leaders found</h3>
              <p>No results match your search term &quot;{searchQuery}&quot;. Please try another keyword.</p>
              <button
                type="button"
                className="btn btn-brand"
                onClick={() => {
                  setSearchQuery('');
                  setActiveTab('all');
                }}
              >
                Reset Filter
              </button>
            </div>
          ) : activeTab === 'all' && searchQuery.trim() === '' ? (
            // Render Grouped by Tier when viewing ALL without active search
            STRUCTURE_TIERS.map((tier, idx) => {
              const membersInTier = STRUCTURE_MEMBERS.filter((m) => m.category === tier.key);
              if (membersInTier.length === 0) return null;

              return (
                <div key={tier.key} className="struct-tier-group" id={tier.key}>
                  <div className="struct-tier-header">
                    <div className="struct-tier-eyebrow">
                      0{idx + 1} — {tier.badge}
                    </div>
                    <div className="struct-tier-title-row">
                      <h2>{tier.title}</h2>
                      <span className="struct-tier-count">
                        {membersInTier.length} Member{membersInTier.length > 1 ? 's' : ''}
                      </span>
                    </div>
                    <p className="struct-tier-desc">{tier.description}</p>
                  </div>

                  <div className="struct-grid">
                    {membersInTier.map((leader) => (
                      <LeaderCard
                        key={leader.id}
                        leader={leader}
                        onClick={() => setSelectedLeader(leader)}
                      />
                    ))}
                  </div>
                </div>
              );
            })
          ) : (
            // Render Flat Grid for filtered results
            <div className="struct-filtered-view">
              <div className="struct-filtered-header">
                <h3>
                  Showing {filteredMembers.length} {filteredMembers.length === 1 ? 'member' : 'members'}
                  {searchQuery ? ` matching "${searchQuery}"` : ''}
                </h3>
              </div>
              <div className="struct-grid">
                {filteredMembers.map((leader) => (
                  <LeaderCard
                    key={leader.id}
                    leader={leader}
                    onClick={() => setSelectedLeader(leader)}
                  />
                ))}
              </div>
            </div>
          )}
        </div>
      </section>

      {/* 3.5 INSTITUTIONAL CAMPUS & HEADQUARTERS CARD */}
      <section className="sec" style={{ background: '#fbf9f4', paddingTop: '40px', paddingBottom: '60px' }}>
        <div className="wrap">
          <div className="struct-campus-card">
            <div className="struct-campus-img-wrap">
              <img
                src="/img/bawah-asean-university-international.webp"
                alt="ASEAN University International Academic Facilities and Administration"
                className="struct-campus-img"
                loading="lazy"
              />
            </div>
            <div className="struct-campus-info">
              <div className="eyebrow" style={{ color: 'var(--gold)' }}>University Administration</div>
              <h3 style={{ fontFamily: 'var(--serif)', fontSize: '24px', color: 'var(--brand)', marginBottom: '12px' }}>
                ASEAN International Education SDN. BHD.
              </h3>
              <p style={{ color: 'var(--muted)', fontSize: '15px', lineHeight: '1.6', marginBottom: '18px' }}>
                Pusat tata kelola institusi, koordinasi dekan fakultas, dan sekretariat internasional ASEAN University International beroperasi di bawah naungan ASEAN International Education SDN BHD di Bangi Gateway, Selangor, Malaysia.
              </p>
              <div className="struct-campus-meta" style={{ display: 'grid', gap: '8px', fontSize: '13.5px', color: 'var(--brand)', fontWeight: 600 }}>
                <span>📍 F-2-23, Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia</span>
                <span>✉ admin@aui.university • ☎ +60 165 680 906</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 4. MODAL DETAIL (Interactive dialog when clicking on leader) */}
      {selectedLeader && (
        <div
          className="struct-modal-backdrop"
          onClick={() => setSelectedLeader(null)}
          role="dialog"
          aria-modal="true"
          aria-labelledby="modal-leader-name"
        >
          <div className="struct-modal-card" onClick={(e) => e.stopPropagation()}>
            <button
              type="button"
              className="struct-modal-close"
              onClick={() => setSelectedLeader(null)}
              aria-label="Close details"
            >
              ✕
            </button>
            <div className="struct-modal-body">
              <div className="struct-modal-photo-wrap">
                <img
                  src={selectedLeader.image}
                  alt={selectedLeader.name}
                  className="struct-modal-photo"
                  onError={(e) => {
                    (e.currentTarget as HTMLImageElement).src =
                      'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=400&q=70';
                  }}
                />
                <span className="struct-modal-badge">{selectedLeader.categoryLabel}</span>
              </div>
              <div className="struct-modal-info">
                <div className="struct-modal-tag">ASEAN UNIVERSITY INTERNATIONAL</div>
                <h3 id="modal-leader-name">{selectedLeader.name}</h3>
                <div className="struct-modal-role">{selectedLeader.role}</div>
                {selectedLeader.facultyOrDivision && (
                  <div className="struct-modal-division">
                    <strong>Division / Faculty:</strong> {selectedLeader.facultyOrDivision}
                  </div>
                )}
                <div className="struct-modal-text">
                  Anggota terhormat pimpinan akademik dan tata kelola ASEAN University International,
                  berkomitmen untuk memajukan pendidikan tinggi berkualitas global, riset berdaya guna,
                  serta integrasi institusi lintas negara ASEAN.
                </div>
                <div className="struct-modal-actions">
                  <a href="mailto:admin@aui.university" className="btn btn-gold">
                    Contact via Secretariat
                  </a>
                  <button
                    type="button"
                    className="btn btn-line"
                    onClick={() => setSelectedLeader(null)}
                  >
                    Close
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}

// Single Leader Card Component
function LeaderCard({
  leader,
  onClick,
}: {
  leader: LeaderMember;
  onClick: () => void;
}) {
  return (
    <article
      className={`struct-card ${leader.isHead ? 'is-head' : ''}`}
      onClick={onClick}
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          onClick();
        }
      }}
      role="button"
      aria-label={`View details for ${leader.name}`}
    >
      <div className="struct-card-img-wrap">
        <img
          src={leader.image}
          alt={leader.name}
          loading="lazy"
          className="struct-card-img"
          onError={(e) => {
            // Graceful fallback if image has load issues
            (e.currentTarget as HTMLImageElement).src =
              'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=300&q=70';
          }}
        />
        {leader.isHead && <span className="struct-card-head-badge">LEAD</span>}
      </div>

      <div className="struct-card-content">
        <div className="struct-card-category">{leader.categoryLabel}</div>
        <h3 className="struct-card-name" title={leader.name}>
          {leader.name}
        </h3>
        <p className="struct-card-role">{leader.role}</p>
        {leader.facultyOrDivision && (
          <span className="struct-card-div">{leader.facultyOrDivision}</span>
        )}
      </div>

      <div className="struct-card-footer">
        <span className="struct-card-action">View Profile &rarr;</span>
      </div>
    </article>
  );
}
