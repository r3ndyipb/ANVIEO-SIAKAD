
import React, { useState, useEffect, useRef, useCallback } from 'react';

export const HERO_IMAGES = [
  {
    src: '/img/Perayaan%20Wisuda%20di%20Bawah%20Menara%20Kota.png',
    alt: 'Perayaan Wisuda di Bawah Menara Kota - ASEAN University International',
    label: 'Perayaan Wisuda',
  },
  {
    src: '/img/Halaman%20Kampus%20Merah%20Putih%20Dikelilingi%20Pepohonan.png',
    alt: 'Halaman Kampus Merah Putih Dikelilingi Pepohonan - AUI Campus',
    label: 'Halaman Kampus',
  },
  {
    src: '/img/Kompleks%20Kampus%20Bata%20dengan%20Langit%20Putih.png',
    alt: 'Kompleks Kampus Bata dengan Langit Putih - AUI Academic Complex',
    label: 'Kompleks Kampus',
  },
];

const COLS = 12;
const ROWS = 7;
const TOTAL_TILES = COLS * ROWS;

export default function HeroSlideshow() {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [outgoingIndex, setOutgoingIndex] = useState(0);
  const [incomingIndex, setIncomingIndex] = useState(0);
  const [isTransitioning, setIsTransitioning] = useState(false);
  const [isFadingOut, setIsFadingOut] = useState(false);
  const [tileDelays, setTileDelays] = useState<number[]>(() =>
    Array.from({ length: TOTAL_TILES }, () => Math.floor(Math.random() * 650))
  );

  const transitionTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const autoPlayTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const triggerSlideTransition = useCallback(
    (targetIndex: number) => {
      if (isTransitioning || targetIndex === currentIndex) return;

      // 1. Set outgoing (current) and incoming (target)
      setOutgoingIndex(currentIndex);
      setIncomingIndex(targetIndex);
      setIsTransitioning(true);
      setIsFadingOut(false);

      // 2. Generate new randomized delays for all tiles (0ms to 650ms)
      const newDelays = Array.from({ length: TOTAL_TILES }, () =>
        Math.floor(Math.random() * 650)
      );
      setTileDelays(newDelays);

      // 3. Trigger fadeout on next frame
      requestAnimationFrame(() => {
        setTimeout(() => {
          setIsFadingOut(true);
        }, 30);
      });

      // 4. Clean up after all tiles have vanished (650ms max delay + 450ms transition + 50ms buffer)
      if (transitionTimerRef.current) clearTimeout(transitionTimerRef.current);
      transitionTimerRef.current = setTimeout(() => {
        setCurrentIndex(targetIndex);
        setOutgoingIndex(targetIndex);
        setIsTransitioning(false);
        setIsFadingOut(false);
      }, 1150);
    },
    [currentIndex, isTransitioning]
  );

  const nextSlide = useCallback(() => {
    const nextIdx = (currentIndex + 1) % HERO_IMAGES.length;
    triggerSlideTransition(nextIdx);
  }, [currentIndex, triggerSlideTransition]);

  // Preload all slideshow images on mount for instant switching
  useEffect(() => {
    HERO_IMAGES.forEach((img) => {
      if (typeof window !== 'undefined') {
        const preloader = new Image();
        preloader.src = img.src;
      }
    });
  }, []);

  // Auto-play interval every 6.5 seconds
  useEffect(() => {
    if (autoPlayTimerRef.current) clearInterval(autoPlayTimerRef.current);

    autoPlayTimerRef.current = setInterval(() => {
      if (!isTransitioning) {
        nextSlide();
      }
    }, 6500);

    return () => {
      if (autoPlayTimerRef.current) clearInterval(autoPlayTimerRef.current);
      if (transitionTimerRef.current) clearTimeout(transitionTimerRef.current);
    };
  }, [nextSlide, isTransitioning]);

  return (
    <div
      className="hero-slideshow-container"
      aria-label="Hero Background Slideshow"
    >
      {/* 1. Underlying image (incoming slide revealed beneath the disappearing tiles) */}
      <div
        className="hero-underlay-image"
        style={{
          backgroundImage: `url(${HERO_IMAGES[incomingIndex].src})`,
        }}
        role="img"
        aria-label={HERO_IMAGES[incomingIndex].alt}
      />

      {/* 2. Grid of square tiles showing outgoing image that randomly fade out */}
      <div
        className={`hero-tile-grid ${isTransitioning ? 'is-animating' : ''}`}
        aria-hidden="true"
        style={{
          display: isTransitioning ? 'block' : 'none',
        }}
      >
        {Array.from({ length: TOTAL_TILES }).map((_, i) => {
          const col = i % COLS;
          const row = Math.floor(i / COLS);
          const widthPercent = 100 / COLS;
          const heightPercent = 100 / ROWS;
          const leftPercent = col * widthPercent;
          const topPercent = row * heightPercent;
          const bgPosX = (col * 100) / (COLS - 1);
          const bgPosY = (row * 100) / (ROWS - 1);
          const delay = tileDelays[i] || 0;

          return (
            <div
              key={i}
              className={`hero-tile ${isFadingOut ? 'tile-hidden' : ''}`}
              style={{
                width: `${widthPercent + 0.1}%`, // slight 0.1% overlap to prevent subpixel seams
                height: `${heightPercent + 0.1}%`,
                left: `${leftPercent}%`,
                top: `${topPercent}%`,
                backgroundImage: `url(${HERO_IMAGES[outgoingIndex].src})`,
                backgroundSize: `${COLS * 100}% ${ROWS * 100}%`,
                backgroundPosition: `${bgPosX}% ${bgPosY}%`,
                transitionDelay: isFadingOut ? `${delay}ms` : '0ms',
              }}
            />
          );
        })}
      </div>

      {/* 3. Dark gradient overlay ensuring text contrast */}
      <div className="hero-gradient-overlay" />
    </div>
  );
}
