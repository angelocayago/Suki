document.addEventListener("DOMContentLoaded", () => {
    const root = document.body;

    function refreshIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    refreshIcons();

    /* =====================================================
       DARK MODE
    ====================================================== */

    const themeToggle = document.getElementById("landingThemeToggle");

    const preferredTheme =
        localStorage.getItem("suki-landing-theme") ||
        (window.matchMedia?.("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light");

    function setTheme(theme) {
        root.dataset.theme = theme;

        if (themeToggle) {
            const dark = theme === "dark";

            themeToggle.setAttribute(
                "aria-pressed",
                dark ? "true" : "false"
            );

            themeToggle.innerHTML = dark
                ? '<i data-lucide="sun"></i>'
                : '<i data-lucide="moon"></i>';
        }

        refreshIcons();
    }

    setTheme(preferredTheme);

    themeToggle?.addEventListener("click", () => {
        const next =
            root.dataset.theme === "dark"
                ? "light"
                : "dark";

        localStorage.setItem(
            "suki-landing-theme",
            next
        );

        setTheme(next);
    });


    /* =====================================================
       HERO CAROUSEL
    ====================================================== */

    const heroImage =
        document.getElementById("heroImage");

    const heroEyebrow =
        document.getElementById("heroEyebrow");

    const heroHeading =
        document.getElementById("heroHeading");

    const heroDescription =
        document.getElementById("heroDescription");

    const heroCurrent =
        document.getElementById("heroCurrent");

    const heroToggle =
        document.getElementById("heroToggle");

    const heroPrevious =
        document.querySelector(".hero-prev");

    const heroNext =
        document.querySelector(".hero-next");

    const heroDots = Array.from(
        document.querySelectorAll(".hero-dot")
    );

    const HERO_DELAY = 7000;

    let currentHero = 0;
    let heroTimer = null;
    let heroPaused = false;
    let heroChanging = false;

    heroDots.forEach((dot) => {
        if (!dot.dataset.image) return;

        const image = new Image();
        image.src = dot.dataset.image;
    });

    function updateHeroToggle() {
        if (!heroToggle) return;

        heroToggle.innerHTML = heroPaused
            ? '<i data-lucide="play"></i>'
            : '<i data-lucide="pause"></i>';

        heroToggle.setAttribute(
            "aria-label",
            heroPaused
                ? "Play carousel"
                : "Pause carousel"
        );

        heroToggle.setAttribute(
            "aria-pressed",
            heroPaused ? "true" : "false"
        );

        refreshIcons();
    }

    function showHero(index) {
        if (
            !heroImage ||
            !heroDots.length ||
            heroChanging
        ) {
            return;
        }

        const normalized =
            (index + heroDots.length) %
            heroDots.length;

        const selected =
            heroDots[normalized];

        const source =
            selected.dataset.image;

        if (!source) return;

        heroChanging = true;

        heroDots.forEach((dot, dotIndex) => {
            const active =
                dotIndex === normalized;

            dot.classList.toggle(
                "active",
                active
            );

            dot.setAttribute(
                "aria-current",
                active ? "true" : "false"
            );
        });

        heroImage.classList.remove("hero-animate");
        // Force the browser to treat the next class addition as a fresh
        // animation, including when a previously viewed slide is revisited.
        void heroImage.offsetWidth;
        heroImage.style.opacity = "0.35";
        heroImage.style.transform = "scale(1.02)";

        if (heroEyebrow) {
            heroEyebrow.style.opacity = "0";
        }

        if (heroHeading) {
            heroHeading.style.opacity = "0";
        }

        if (heroDescription) {
            heroDescription.style.opacity = "0";
        }

        window.setTimeout(() => {
            heroImage.src = source;

            if (heroEyebrow) {
                heroEyebrow.textContent =
                    selected.dataset.eyebrow || "";
            }

            if (heroHeading) {
                heroHeading.textContent =
                    selected.dataset.heading || "";
            }

            if (heroDescription) {
                heroDescription.textContent =
                    selected.dataset.description || "";
            }

            if (heroCurrent) {
                heroCurrent.textContent =
                    String(normalized + 1)
                        .padStart(2, "0");
            }

            const finish = () => {
                heroImage.style.opacity = "1";
                heroImage.style.transform = "";
                heroImage.classList.add("hero-animate");

                [heroEyebrow, heroHeading, heroDescription]
                    .filter(Boolean)
                    .forEach((element) => {
                        element.style.opacity = "1";
                    });

                heroChanging = false;
            };

            if (heroImage.complete) {
                finish();
            } else {
                heroImage.onload = finish;
                heroImage.onerror = finish;
            }
        }, 180);

        currentHero = normalized;
    }

    function stopHeroTimer() {
        if (!heroTimer) return;

        clearInterval(heroTimer);
        heroTimer = null;
    }

    function startHeroTimer() {
        stopHeroTimer();

        if (
            heroPaused ||
            heroDots.length <= 1
        ) {
            return;
        }

        heroTimer = setInterval(() => {
            showHero(currentHero + 1);
        }, HERO_DELAY);
    }

    function manualHero(index) {
        showHero(index);

        if (!heroPaused) {
            startHeroTimer();
        }
    }

    heroDots.forEach((dot, index) => {
        dot.addEventListener(
            "click",
            () => manualHero(index)
        );
    });

    heroPrevious?.addEventListener(
        "click",
        () => manualHero(currentHero - 1)
    );

    heroNext?.addEventListener(
        "click",
        () => manualHero(currentHero + 1)
    );

    heroToggle?.addEventListener(
        "click",
        () => {
            heroPaused = !heroPaused;

            updateHeroToggle();

            if (heroPaused) {
                stopHeroTimer();
            } else {
                startHeroTimer();
            }
        }
    );

    document.addEventListener(
        "visibilitychange",
        () => {
            if (document.hidden) {
                stopHeroTimer();
            } else {
                startHeroTimer();
            }
        }
    );

    updateHeroToggle();
    heroImage?.classList.add("hero-animate");
    startHeroTimer();


    /* =====================================================
       GENERIC HORIZONTAL SLIDER
    ====================================================== */

    function setupSlider(
        slider,
        previousButton,
        nextButton,
        itemSelector,
        multiplier = 3
    ) {
        if (!slider) return;

        function step() {
            const first =
                slider.querySelector(
                    itemSelector
                );

            if (!first) {
                return Math.max(
                    280,
                    slider.clientWidth * 0.75
                );
            }

            const styles =
                getComputedStyle(slider);

            const gap =
                parseFloat(styles.gap) || 0;

            return (
                first.getBoundingClientRect().width +
                gap
            ) * multiplier;
        }

        previousButton?.addEventListener(
            "click",
            () => {
                slider.scrollBy({
                    left: -step(),
                    behavior: "smooth"
                });
            }
        );

        nextButton?.addEventListener(
            "click",
            () => {
                slider.scrollBy({
                    left: step(),
                    behavior: "smooth"
                });
            }
        );

        let dragging = false;
        let moved = false;
        let suppressClickUntil = 0;
        let startX = 0;
        let startScroll = 0;

        slider.addEventListener(
            "pointerdown",
            (event) => {
                if (
                    event.pointerType !== "mouse" ||
                    event.button !== 0
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        "a, button, input, select, textarea, label"
                    )
                ) {
                    suppressClickUntil = 0;
                    return;
                }

                dragging = true;
                moved = false;
                startX = event.clientX;
                startScroll = slider.scrollLeft;

                slider.setPointerCapture?.(
                    event.pointerId
                );
            }
        );

        slider.addEventListener(
            "pointermove",
            (event) => {
                if (!dragging) return;

                const delta =
                    event.clientX - startX;

                if (Math.abs(delta) > 5) {
                    moved = true;
                }

                slider.scrollLeft =
                    startScroll - delta;
            }
        );

        const stopDragging = () => {
            if (dragging && moved) {
                suppressClickUntil = performance.now() + 250;
            }

            dragging = false;
        };

        slider.addEventListener(
            "pointerup",
            stopDragging
        );

        slider.addEventListener(
            "pointercancel",
            stopDragging
        );

        slider.addEventListener("click", (event) => {
            const link = event.target.closest("a");

            if (
                !link ||
                !slider.contains(link)
            ) {
                return;
            }

            if (performance.now() > suppressClickUntil) {
                suppressClickUntil = 0;
                return;
            }

            event.preventDefault();
            suppressClickUntil = 0;
        });
    }


    /* Categories */

    setupSlider(
        document.querySelector(
            ".category-slider"
        ),
        document.querySelector(
            ".category-prev"
        ),
        document.querySelector(
            ".category-next"
        ),
        ".category-item",
        4
    );


    /* Featured products */

    setupSlider(
        document.querySelector(
            ".product-grid"
        ),
        document.querySelector(
            ".product-prev"
        ),
        document.querySelector(
            ".product-next"
        ),
        ".product-card",
        3
    );


    /* =====================================================
       INTERNAL ANCHORS
    ====================================================== */

    document
        .querySelectorAll('a[href^="#"]')
        .forEach((anchor) => {
            anchor.addEventListener(
                "click",
                (event) => {
                    const targetId =
                        anchor.getAttribute("href");

                    if (
                        !targetId ||
                        targetId === "#"
                    ) {
                        return;
                    }

                    const target =
                        document.querySelector(
                            targetId
                        );

                    if (!target) return;

                    event.preventDefault();

                    target.scrollIntoView({
                        behavior: "smooth",
                        block: "start"
                    });
                }
            );
        });


    // Public catalog and product links use their normal destinations.
    // Cart, wishlist and checkout authorization is enforced by their routes.

    refreshIcons();
});
