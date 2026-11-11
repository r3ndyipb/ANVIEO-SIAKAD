
import React from 'react';
import Link from './Link';
import { AppView } from './Header';

interface UtilityBarProps {
  onNavigate?: (view: AppView) => void;
}

export default function UtilityBar({ onNavigate }: UtilityBarProps) {
  const handleView = (e: React.MouseEvent, view: AppView) => {
    if (onNavigate) {
      e.preventDefault();
      onNavigate(view);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };

  return (
    <div className="util">
      <div className="wrap">
        <ul>
          <li>
            <a href="mailto:admin@aui.university">✉ admin@aui.university</a>
          </li>
          <li className="sep">|</li>
          <li>
            <a href="tel:+60165680906">☎ +60 165 680 906</a>
          </li>
          <li className="sep">|</li>
          <li>
            <a href="/login">Portal AUI (Students)</a>
          </li>
        </ul>
        <ul>
          <li>
            <Link href="/payment-information" onClick={(e) => handleView(e, 'payment')}>
              Payment Information
            </Link>
          </li>
          <li>
            <Link href="/contact" onClick={(e) => handleView(e, 'contact')}>
              Contact
            </Link>
          </li>
          <li>
            <a href="#">EN</a>
          </li>
        </ul>
      </div>
    </div>
  );
}
