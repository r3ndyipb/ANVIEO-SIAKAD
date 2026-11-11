import React from 'react';

export default function Link({
    href,
    onClick,
    children,
    ...props
}: React.AnchorHTMLAttributes<HTMLAnchorElement> & { href: string; children?: React.ReactNode }) {
    const handleClick = (e: React.MouseEvent<HTMLAnchorElement>) => {
        if (onClick) {
            onClick(e);
        }
        if (e.defaultPrevented) return;

        // Ignore modified clicks (Ctrl, Cmd, Shift, Alt) or right/middle click
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
            return;
        }

        // If it is an external link, anchor-only (#id), or explicit target, leave it to native browser
        if (!href || href.startsWith('http') || href.startsWith('//') || href.startsWith('mailto:') || href.startsWith('tel:') || props.target) {
            return;
        }

        // Do not intercept non-SPA routes like /login, /logout, /fakultas, /prodi
        if (href.startsWith('/login') || href.startsWith('/logout') || href.startsWith('/fakultas') || href.startsWith('/prodi')) {
            return;
        }

        // Intercept internal SPA views: /, /about, /structure, /admissions, /academic, /accreditation, /payment-information, /contact, /privacy, /terms, /accessibility
        e.preventDefault();
        window.history.pushState({}, '', href);
        window.dispatchEvent(new PopStateEvent('popstate'));
        window.scrollTo({ top: 0, behavior: 'instant' });
    };

    return (
        <a href={href} onClick={handleClick} {...props}>
            {children}
        </a>
    );
}
