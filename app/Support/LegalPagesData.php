<?php

namespace App\Support;

class LegalPagesData
{
    /**
     * @return array<string, mixed>
     */
    public static function privacy(): array
    {
        return [
            'key' => 'privacy',
            'title' => 'Privacy Policy',
            'subtitle' => 'Personal Data Protection Notice (PDPA 2010 Compliance)',
            'eyebrow' => 'Institutional Governance & Data Protection',
            'lede' => 'ASEAN University International (AUI), operated under ASEAN International Education SDN. BHD., is firmly committed to safeguarding the personal data, privacy, and confidentiality of our prospective students, enrolled scholars, alumni, faculty, and website visitors in strict adherence to the Personal Data Protection Act 2010 (Malaysia) and global privacy standards.',
            'heroImage' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=1800&q=70',
            'chips' => [
                ['label' => 'Statutory Basis: PDPA 2010 (Act 709, Malaysia)'],
                ['label' => 'Last Revised: October 2026'],
                ['label' => 'Data Protection Officer: dpo@aui.university'],
            ],
            'toc' => [
                ['id' => 'sec-1', 'title' => '1. Statutory Scope & Applicability'],
                ['id' => 'sec-2', 'title' => '2. Categories of Personal Data Collected'],
                ['id' => 'sec-3', 'title' => '3. Purpose of Processing Personal Data'],
                ['id' => 'sec-4', 'title' => '4. Disclosure & Cross-Border Transfers'],
                ['id' => 'sec-5', 'title' => '5. Security Safeguards & Measures'],
                ['id' => 'sec-6', 'title' => '6. Retention Periods & Disposal'],
                ['id' => 'sec-7', 'title' => '7. Your Statutory Rights'],
                ['id' => 'sec-8', 'title' => '8. Inquiries & DPO Contact'],
            ],
            'clauses' => [
                [
                    'id' => 'sec-1',
                    'title' => '1. Statutory Scope & Applicability',
                    'paragraphs' => [
                        'This Privacy Policy applies to all personal data collected, stored, processed, and managed by ASEAN University International (“AUI”, “the University”, “we”, “our”, or “us”) through our institutional websites, student information system (SIAKAD), learning management systems (LMS), mobile applications, admission enquiry portals, and on-campus administrative registers.',
                        'By providing personal data to AUI or using any of our educational services, you acknowledge that you have read, understood, and consented to the collection, recording, holding, disclosure, and processing of your personal data in accordance with the terms of this Policy.',
                    ],
                ],
                [
                    'id' => 'sec-2',
                    'title' => '2. Categories of Personal Data Collected',
                    'paragraphs' => [
                        'In the ordinary course of our academic and administrative governance, AUI collects personal data directly from data subjects during admissions, enrolment, fee assessments, and curricular participation. This data comprises:',
                    ],
                    'boxes' => [
                        [
                            'title' => 'Personal Identifiers',
                            'description' => 'Full legal name, national identity card (MyKad/NRIC) or passport number, nationality, date of birth, gender, residential address, personal email, and telephone numbers.',
                        ],
                        [
                            'title' => 'Academic & Prior Qualifications',
                            'description' => 'Secondary school certificates, diploma/degree transcripts, grade point averages, curriculum vitae, language proficiency scores (IELTS/TOEFL), and professional reference letters.',
                        ],
                        [
                            'title' => 'Financial & Billing Records',
                            'description' => 'Tuition fee receipts, bank account details for authorized disbursements, scholarship awards, sponsorship correspondence, and payment gateway transaction references.',
                        ],
                        [
                            'title' => 'Technical & Telemetry Data',
                            'description' => 'IP addresses, browser characteristics, login timestamps on SIAKAD / LMS, access audit logs, device operating system parameters, and session tracking cookies.',
                        ],
                    ],
                ],
                [
                    'id' => 'sec-3',
                    'title' => '3. Purpose of Processing Personal Data',
                    'paragraphs' => [
                        'The processing of your personal data is necessary for the performance of our educational contract with you, compliance with statutory requirements under the Ministry of Higher Education (MOHE) and Malaysian Qualifications Agency (MQA), and advancing legitimate institutional research:',
                    ],
                    'list' => [
                        'Processing prospective student applications, eligibility assessments, credential verifications, and matriculation onboarding.',
                        'Managing academic progress, course registration, attendance registers, examinations, grade reporting, and conferring degree credentials.',
                        'Administering tuition invoicing, scholarship allocations, bursary programs, and fee reconciliation with authorized banking channels.',
                        'Maintaining regulatory records mandated by MOHE, MQA, immigration departments (for international student visas), and statutory audit panels.',
                        'Delivering critical student notices, timetable announcements, academic advising alerts, emergency campus broadcasts, and alumni relations.',
                    ],
                ],
                [
                    'id' => 'sec-4',
                    'title' => '4. Disclosure & Cross-Border Data Transfers',
                    'paragraphs' => [
                        'AUI does not sell, rent, or lease personal data to external commercial marketers. Personal data is disclosed solely to authorized recipients on a strict need-to-know basis:',
                        'Authorized regulatory authorities, including MOHE, MQA, Department of Higher Education (JPT), and partner universities with reciprocal credit-transfer agreements.',
                        'Vetted technology infrastructure and payment service providers operating under strict confidentiality covenants and ISO/IEC 27001 data security standards.',
                        'Where cross-border data transfer is required for international accreditation, research partnerships, or cloud database redundancy, AUI ensures recipients provide an equivalent or higher standard of personal data protection.',
                    ],
                ],
                [
                    'id' => 'sec-5',
                    'title' => '5. Security Safeguards & Technical Measures',
                    'paragraphs' => [
                        'The University enforces multi-layered defense-in-depth security architectures to shield personal data against accidental destruction, unauthorized alteration, unlawful disclosure, or illicit access:',
                        'All digital data transmissions across public networks are encrypted using TLS 1.3 / HTTPS. At-rest databases employ AES-256 cryptographic standards.',
                        'Strict role-based access control (RBAC), multi-factor authentication (MFA) for administrative staff, automated session timeouts, and continuous intrusion detection logging.',
                        'Periodic third-party vulnerability assessments, penetration testing, and annual compliance audits by independent cybersecurity assessors.',
                    ],
                ],
                [
                    'id' => 'sec-6',
                    'title' => '6. Retention Periods & Data Disposal',
                    'paragraphs' => [
                        'Personal data is preserved only for as long as necessary to fulfill the academic, administrative, legal, and regulatory purposes for which it was collected:',
                        'Enrolled student academic transcripts, graduation registers, and degree awards are preserved in our permanent institutional archive for lifelong academic credential verification.',
                        'Admissions enquiry and unmatriculated applicant records are systematically archived or expunged after two (2) academic recruitment cycles.',
                        'Financial transaction records and billing receipts are retained for seven (7) statutory years in compliance with national taxation and audit frameworks.',
                    ],
                ],
                [
                    'id' => 'sec-7',
                    'title' => '7. Your Statutory Rights as a Data Subject',
                    'paragraphs' => [
                        'Under the Personal Data Protection Act 2010 (PDPA), data subjects maintain statutory entitlements regarding their personal data held by AUI:',
                        'Right of Access: Request a verified copy of your personal data records held within our systems.',
                        'Right of Correction: Request rectification of inaccurate, incomplete, misleading, or outdated personal data.',
                        'Right to Withdraw Consent: Subject to contractual and statutory retention obligations, withdraw consent to non-essential processing.',
                        'Right to Prevent Processing for Direct Marketing: Object to receiving promotional newsletters and non-academic institutional broadcasts.',
                    ],
                ],
                [
                    'id' => 'sec-8',
                    'title' => '8. Inquiries, Complaints & Data Protection Officer Contact',
                    'paragraphs' => [
                        'For any inquiries, requests to exercise your data subject rights, or formal privacy concerns, please contact the AUI Data Protection Office in writing:',
                    ],
                    'contactCard' => [
                        'title' => 'Office of Data Protection & Regulatory Compliance',
                        'lines' => [
                            'ASEAN University International (AUI)',
                            'Operated by ASEAN International Education SDN. BHD.',
                            'Address: F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia',
                            'Email: dpo@aui.university / compliance@aui.university',
                            'Direct Telephone: +60 165 680 906',
                            'Office Hours: Monday – Friday, 09:00 – 17:00 (GMT+8)',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function terms(): array
    {
        return [
            'key' => 'terms',
            'title' => 'Terms of Use',
            'subtitle' => 'University Digital Platforms, Enrolment Agreement & Code of Conduct',
            'eyebrow' => 'Institutional Regulations & Student Agreement',
            'lede' => 'These Terms of Use delineate the legal agreement, operational regulations, and academic honor codes governing your access to ASEAN University International (“AUI”) digital portals, courseware, and student services under ASEAN International Education SDN. BHD.',
            'heroImage' => 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=1800&q=70',
            'chips' => [
                ['label' => 'Governing Law: Malaysia & International Standards'],
                ['label' => 'Effective Date: Academic Year 2026 / 2027'],
                ['label' => 'Legal Counsel: legal@aui.university'],
            ],
            'toc' => [
                ['id' => 'sec-1', 'title' => '1. Acceptance & Statutory Framework'],
                ['id' => 'sec-2', 'title' => '2. User Credentials & Account Integrity'],
                ['id' => 'sec-3', 'title' => '3. Academic Integrity & Honor Code'],
                ['id' => 'sec-4', 'title' => '4. Intellectual Property & Course Materials'],
                ['id' => 'sec-5', 'title' => '5. Tuition Fees, Billing & Refund Policies'],
                ['id' => 'sec-6', 'title' => '6. Acceptable Use of Digital Infrastructure'],
                ['id' => 'sec-7', 'title' => '7. Limitation of Liability & Warranties'],
                ['id' => 'sec-8', 'title' => '8. Amendments, Jurisdiction & Contact'],
            ],
            'clauses' => [
                [
                    'id' => 'sec-1',
                    'title' => '1. Acceptance & Statutory Framework',
                    'paragraphs' => [
                        'By accessing our public website, logging into the student information system (SIAKAD), engaging with our learning management system (LMS), or submitting an admission application, you confirm that you have read, understood, and agreed to be legally bound by these Terms of Use and all affiliated university statutes.',
                        'If you do not agree with any provision of these Terms, you must discontinue your use of our platforms immediately. Enrolled students are subject to additional binding obligations documented in the AUI Student Handbook and Matriculation Agreement.',
                    ],
                ],
                [
                    'id' => 'sec-2',
                    'title' => '2. User Credentials & Account Integrity',
                    'paragraphs' => [
                        'Authorized students, faculty, and administrative staff are issued unique system credentials (NIM/NIDN/Username and secure password) for accessing authenticated institutional services.',
                    ],
                    'boxes' => [
                        [
                            'title' => 'Confidentiality of Credentials',
                            'description' => 'You are exclusively responsible for maintaining the confidentiality of your username and password. Sharing accounts or delegating login rights to third parties is strictly prohibited.',
                        ],
                        [
                            'title' => 'Notification of Unauthorized Access',
                            'description' => 'You must notify the AUI IT Helpdesk immediately at it.support@aui.university upon suspecting any compromise, unauthorized access, or credential theft.',
                        ],
                        [
                            'title' => 'Identity Verification',
                            'description' => 'The University reserves the right to enforce multi-factor authentication (MFA) and biometric verification during digital examinations and high-security transactions.',
                        ],
                        [
                            'title' => 'Account Revocation',
                            'description' => 'AUI reserves the authority to suspend or terminate accounts that breach academic codes, fail fee settlements, or exhibit anomalous network activity.',
                        ],
                    ],
                ],
                [
                    'id' => 'sec-3',
                    'title' => '3. Academic Integrity & Honor Code',
                    'paragraphs' => [
                        'ASEAN University International upholds uncompromising standards of academic honesty, intellectual rigor, and scholarly ethics:',
                        'Plagiarism, unauthorized collaboration, assignment commercialization, impersonation during assessments, or utilizing unauthorized generative AI tools during proctored examinations constitutes severe academic misconduct.',
                        'Misconduct cases are adjudicated by the University Disciplinary Board, carrying sanctions up to course failure, suspension, or formal expulsion with revocation of awarded credits.',
                    ],
                ],
                [
                    'id' => 'sec-4',
                    'title' => '4. Intellectual Property & Course Materials',
                    'paragraphs' => [
                        'All intellectual property rights in course curricula, lecture slides, recorded webinars, syllabi, software repositories, and research databases produced by AUI remain the exclusive property of ASEAN International Education SDN. BHD. and its faculty authors.',
                        'Students are granted a non-exclusive, non-transferable, revocable personal license to download and review materials solely for individual educational purposes. Recording lectures or redistributing course assets onto public repositories or commercial notes services without written permission is strictly prohibited.',
                    ],
                ],
                [
                    'id' => 'sec-5',
                    'title' => '5. Tuition Fees, Billing & Refund Policies',
                    'paragraphs' => [
                        'Tuition fees, enrollment dues, and graduation period charges are calculated according to the schedule published by the Office of the Bursar for each academic intake:',
                        'All fee payments must be settled prior to the semester course addition deadline (KRS validation) via approved banking channels or institutional payment gateways.',
                        'Refunds in the event of formal course withdrawal are governed strictly by the AUI Fee Refund Schedule. Administrative registration fees and visa processing surcharges are non-refundable.',
                    ],
                ],
                [
                    'id' => 'sec-6',
                    'title' => '6. Acceptable Use of Digital Infrastructure',
                    'paragraphs' => [
                        'Users agree to utilize university networks, portals, and servers solely for lawful academic and administrative endeavors. Prohibited activities include:',
                    ],
                    'list' => [
                        'Deploying automated scrapers, denial-of-service tools, or packet sniffers against university digital services.',
                        'Attempting to bypass access controls, exploit software vulnerabilities, or reverse-engineer proprietary portal code.',
                        'Transmitting defamatory, harassing, obscene, discriminatory, or unlawful material across institutional chat and discussion forums.',
                        'Engaging in commercial advertising, cryptocurrency mining, or unauthorized proxy routing using university compute resources.',
                    ],
                ],
                [
                    'id' => 'sec-7',
                    'title' => '7. Limitation of Liability & Warranties',
                    'paragraphs' => [
                        'AUI digital platforms are provided on an “as is” and “as available” basis. While the University endeavors to maintain uninterrupted service and data accuracy, we do not guarantee that portals will be completely error-free or free from scheduled maintenance outages.',
                        'To the maximum extent permitted by Malaysian law, AUI disclaims liability for any indirect, incidental, or consequential damages resulting from platform downtime, loss of unsaved assignments, or third-party network interruptions.',
                    ],
                ],
                [
                    'id' => 'sec-8',
                    'title' => '8. Amendments, Jurisdiction & Contact',
                    'paragraphs' => [
                        'The University reserves the right to revise these Terms of Use periodically. Substantive amendments will be notified through student portal banners and email broadcasts. Continued use of services post-amendment constitutes acceptance of modified terms.',
                        'These Terms are governed by and construed in accordance with the laws of Malaysia. Any disputes arising shall be subject to the exclusive jurisdiction of the courts of Malaysia.',
                    ],
                    'contactCard' => [
                        'title' => 'Office of the Legal Counsel & University Secretariat',
                        'lines' => [
                            'ASEAN University International (AUI)',
                            'ASEAN International Education SDN. BHD.',
                            'Address: F-2-23, Jalan BG 3B/1 Bangi Gateway 3B, Seksyen 15, 43650 Bandar Baru Bangi, Selangor, Malaysia',
                            'Email: legal@aui.university / secretariat@aui.university',
                            'Telephone: +60 165 680 906',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accessibility(): array
    {
        return [
            'key' => 'accessibility',
            'title' => 'Accessibility Statement',
            'subtitle' => 'Commitment to Barrier-Free Academic Learning for All',
            'eyebrow' => 'Inclusive Digital Higher Education',
            'lede' => 'ASEAN University International is committed to ensuring digital accessibility for people with disabilities. We continuously improve the user experience for everyone and apply the relevant accessibility standards in accordance with the Web Content Accessibility Guidelines (WCAG 2.1, Level AA).',
            'heroImage' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1800&q=70',
            'chips' => [
                ['label' => 'Standard Target: WCAG 2.1 Level AA'],
                ['label' => 'Review Cycle: Annual Audit 2026'],
                ['label' => 'Support Desk: accessibility@aui.university'],
            ],
            'toc' => [
                ['id' => 'sec-1', 'title' => '1. Conformance Status & Standards'],
                ['id' => 'sec-2', 'title' => '2. Accessibility Measures Enforced'],
                ['id' => 'sec-3', 'title' => '3. Multimodal Academic Accommodations'],
                ['id' => 'sec-4', 'title' => '4. Technical Specifications & Stack'],
                ['id' => 'sec-5', 'title' => '5. Assistive Technology Compatibility'],
                ['id' => 'sec-6', 'title' => '6. Known Limitations & Roadmap'],
                ['id' => 'sec-7', 'title' => '7. Assessment & Audit Methodology'],
                ['id' => 'sec-8', 'title' => '8. Feedback, Support & Contact'],
            ],
            'clauses' => [
                [
                    'id' => 'sec-1',
                    'title' => '1. Conformance Status & Standards',
                    'paragraphs' => [
                        'The Web Content Accessibility Guidelines (WCAG) defines requirements for designers and developers to improve accessibility for individuals with visual, auditory, motor, or cognitive disabilities. It specifies three levels of conformance: Level A, Level AA, and Level AAA.',
                        'ASEAN University International digital platforms—including our landing page, SIAKAD academic records dashboard, and LMS courseware—are actively maintained to conform with WCAG 2.1 Level AA benchmarks.',
                    ],
                ],
                [
                    'id' => 'sec-2',
                    'title' => '2. Accessibility Measures Enforced Across Platforms',
                    'paragraphs' => [
                        'AUI integrates accessibility into institutional procurement, UX design, and software engineering workflows:',
                    ],
                    'boxes' => [
                        [
                            'title' => 'Visual Accessibility',
                            'description' => 'Text color contrast meets or exceeds the 4.5:1 ratio for normal body copy and 3:1 for large headers. Layouts support zoom up to 200% without loss of content or broken horizontal scrolling.',
                        ],
                        [
                            'title' => 'Auditory Accommodations',
                            'description' => 'Video lectures, symposium webinars, and convocation broadcasts are accompanied by synchronized English subtitles, closed captions, and downloadable text transcripts.',
                        ],
                        [
                            'title' => 'Keyboard & Motor Navigability',
                            'description' => 'All interactive controls, modals, tables, and forms are fully navigable via standard keyboard inputs with visible high-contrast focus indicators.',
                        ],
                        [
                            'title' => 'Cognitive & Screen Reader Support',
                            'description' => 'Semantic HTML5 landmarks (header, nav, main, aside, footer), explicit ARIA attributes, structured heading hierarchies, and descriptive alt text on all instructional imagery.',
                        ],
                    ],
                ],
                [
                    'id' => 'sec-3',
                    'title' => '3. Multimodal Academic Accommodations',
                    'paragraphs' => [
                        'The AUI Student Disability Support Unit coordinates personalized academic accommodations for matriculated scholars:',
                        'Extended time allowances (up to 50% additional duration) for proctored online examinations and quizzes.',
                        'Provision of alternative accessible formats for core textbooks and learning modules, including screen-reader-optimized PDF, EPUB, and audio recordings.',
                        'Assistive software licensing support, including screen magnification utilities, speech-to-text input tools, and specialized dyslexic reading typography options.',
                    ],
                ],
                [
                    'id' => 'sec-4',
                    'title' => '4. Technical Specifications & Architecture',
                    'paragraphs' => [
                        'Accessibility across AUI systems relies on standardized web technologies working in synergy with assistive browsers and screen readers:',
                        'HTML5, WAI-ARIA 1.2, CSS3 Flexbox/Grid, and modern JavaScript (ES6+).',
                        'These technologies are tested against standard operating systems including Windows (NVDA, JAWS), macOS/iOS (VoiceOver), and Android (TalkBack).',
                    ],
                ],
                [
                    'id' => 'sec-5',
                    'title' => '5. Assistive Technology Compatibility',
                    'paragraphs' => [
                        'Our digital campus portals are engineered for high compatibility with modern web browsers (Chrome, Edge, Firefox, Safari) and specialized assistive devices:',
                        'Screen readers seamlessly pronounce button states, expandable accordions, tab selections, and form validation errors through live ARIA announcements.',
                        'Form elements feature explicit associated `<label>` tags, input constraints, and human-readable guidance text to prevent submission errors.',
                    ],
                ],
                [
                    'id' => 'sec-6',
                    'title' => '6. Known Limitations & Remediation Roadmap',
                    'paragraphs' => [
                        'Despite our best efforts to ensure full accessibility across all university archives, some legacy documents and third-party tools may exhibit temporary constraints:',
                        'Historical scanned research theses and archived PDF documents produced prior to 2024 may lack OCR text layers. Remediation upon student request is fulfilled within 5 business days.',
                        'Third-party interactive campus maps and geospatial plugins may require table-based alternate summaries, which are provided alongside the visualization.',
                    ],
                ],
                [
                    'id' => 'sec-7',
                    'title' => '7. Assessment & Audit Methodology',
                    'paragraphs' => [
                        'AUI assesses the accessibility of our digital properties through ongoing internal automated auditing (Lighthouse, axe-core) combined with annual manual evaluations performed by assistive technology users.',
                    ],
                ],
                [
                    'id' => 'sec-8',
                    'title' => '8. Feedback, Accommodations & Contact',
                    'paragraphs' => [
                        'We welcome your feedback on the accessibility of ASEAN University International services. If you encounter an accessibility barrier or require an academic accommodation, please contact us:',
                    ],
                    'contactCard' => [
                        'title' => 'AUI Accessibility & Inclusive Education Desk',
                        'lines' => [
                            'ASEAN University International (AUI)',
                            'Student Disability Support Unit',
                            'Email: accessibility@aui.university / disability.support@aui.university',
                            'Telephone: +60 165 680 906',
                            'Response Time: Within two (2) business days',
                        ],
                    ],
                ],
            ],
        ];
    }
}
