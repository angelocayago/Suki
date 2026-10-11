document.addEventListener("DOMContentLoaded", () => {

    /* =====================================================
       LUCIDE ICONS
    ====================================================== */

    if (window.lucide) {
        window.lucide.createIcons();
    }


    /* =====================================================
       HERO CAROUSEL
       - automatic
       - clickable dots
       - loops 1 → 2 → 3 → 1
       - resumes after manual click
    ====================================================== */

    const heroImage = document.getElementById("heroImage");

    const heroDots = Array.from(
        document.querySelectorAll(".hero-dot")
    );

    const HERO_DELAY = 5000;

    let currentHero = 0;
    let heroTimer = null;
    let heroChanging = false;


    /* Preload slides */

    heroDots.forEach((dot) => {

        const source = dot.dataset.image;

        if (!source) {
            return;
        }

        const image = new Image();

        image.src = source;

    });


    function showHero(index) {

        if (
            !heroImage ||
            heroDots.length === 0 ||
            heroChanging
        ) {
            return;
        }


        if (index >= heroDots.length) {
            index = 0;
        }

        if (index < 0) {
            index = heroDots.length - 1;
        }


        const selectedDot = heroDots[index];
        const nextImage = selectedDot.dataset.image;


        if (!nextImage) {
            return;
        }


        heroChanging = true;


        /* Update dot */

        heroDots.forEach((dot, dotIndex) => {

            const active = dotIndex === index;

            dot.classList.toggle(
                "active",
                active
            );

            dot.setAttribute(
                "aria-current",
                active ? "true" : "false"
            );

        });


        /* Fade current image */

        heroImage.style.opacity = "0.45";

        heroImage.style.transform =
            "scale(1.012)";


        window.setTimeout(() => {

            heroImage.src = nextImage;


            const finishTransition = () => {

                heroImage.style.opacity = "1";

                heroImage.style.transform =
                    "scale(1)";

                heroChanging = false;

            };


            if (heroImage.complete) {

                finishTransition();

            } else {

                heroImage.onload =
                    finishTransition;

                heroImage.onerror =
                    finishTransition;

            }

        }, 180);


        currentHero = index;

    }


    function nextHero() {

        const nextIndex =
            (currentHero + 1) %
            heroDots.length;

        showHero(nextIndex);

    }


    function stopHeroTimer() {

        if (!heroTimer) {
            return;
        }

        window.clearInterval(heroTimer);

        heroTimer = null;

    }


    function startHeroTimer() {

        stopHeroTimer();


        if (heroDots.length <= 1) {
            return;
        }


        heroTimer = window.setInterval(
            nextHero,
            HERO_DELAY
        );

    }


    heroDots.forEach((dot, index) => {

        dot.addEventListener(
            "click",
            () => {

                showHero(index);

                /*
                 * Restart timer after manual click.
                 * User gets another full 5 seconds
                 * before auto-slide continues.
                 */

                startHeroTimer();

            }
        );

    });


    /*
     * Don't keep advancing while
     * user is on another browser tab.
     */

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


    startHeroTimer();



    /* =====================================================
       CATEGORY SLIDER
       - arrows
       - mouse drag
       - touch swipe
       - progress indicator
       - responsive
    ====================================================== */

    const categorySlider =
        document.querySelector(
            ".category-slider"
        );

    const previousButton =
        document.querySelector(
            ".category-prev"
        );

    const nextButton =
        document.querySelector(
            ".category-next"
        );

    const progressTrack =
        document.querySelector(
            ".category-progress"
        );

    const progressBar =
        document.querySelector(
            ".category-progress-bar"
        );


    if (categorySlider) {


        /* ---------------------------------------------
           Calculate scroll distance
        --------------------------------------------- */

        function getCategoryStep() {

            const firstItem =
                categorySlider.querySelector(
                    ".category-item"
                );


            if (!firstItem) {

                return Math.max(
                    300,
                    categorySlider.clientWidth *
                        0.7
                );

            }


            const styles =
                window.getComputedStyle(
                    categorySlider
                );


            const gap =
                parseFloat(styles.gap) || 0;


            const itemWidth =
                firstItem.getBoundingClientRect()
                    .width;


            /*
             * Scroll roughly 4 items
             * on desktop/tablet.
             */

            return (itemWidth + gap) * 4;

        }



        /* ---------------------------------------------
           Scroll arrows
        --------------------------------------------- */

        previousButton?.addEventListener(
            "click",
            () => {

                categorySlider.scrollBy({

                    left: -getCategoryStep(),
                    behavior: "smooth"

                });

            }
        );


        nextButton?.addEventListener(
            "click",
            () => {

                categorySlider.scrollBy({

                    left: getCategoryStep(),
                    behavior: "smooth"

                });

            }
        );



        /* ---------------------------------------------
           Arrow state + progress bar
        --------------------------------------------- */

        function updateCategoryUI() {

            const maximumScroll =
                categorySlider.scrollWidth -
                categorySlider.clientWidth;


            /*
             * No scrolling needed.
             */

            if (maximumScroll <= 2) {

                if (previousButton) {

                    previousButton.disabled = true;

                }

                if (nextButton) {

                    nextButton.disabled = true;

                }

                if (progressBar) {

                    progressBar.style.width =
                        "100%";

                    progressBar.style.transform =
                        "translateX(0px)";

                }

                return;

            }


            const currentScroll =
                Math.max(
                    0,
                    categorySlider.scrollLeft
                );


            const atStart =
                currentScroll <= 3;


            const atEnd =
                currentScroll >=
                maximumScroll - 3;


            if (previousButton) {

                previousButton.disabled =
                    atStart;

            }


            if (nextButton) {

                nextButton.disabled =
                    atEnd;

            }



            /* Progress */

            if (
                progressTrack &&
                progressBar
            ) {

                const visibleRatio =
                    categorySlider.clientWidth /
                    categorySlider.scrollWidth;


                const barWidth =
                    Math.max(
                        52,
                        progressTrack.clientWidth *
                            visibleRatio
                    );


                const availableTravel =
                    progressTrack.clientWidth -
                    barWidth;


                const percentage =
                    Math.min(
                        1,
                        currentScroll /
                            maximumScroll
                    );


                progressBar.style.width =
                    `${barWidth}px`;


                progressBar.style.transform =
                    `translateX(${
                        availableTravel *
                        percentage
                    }px)`;

            }

        }



        categorySlider.addEventListener(
            "scroll",
            updateCategoryUI,
            {
                passive: true
            }
        );


        window.addEventListener(
            "resize",
            updateCategoryUI
        );



        /* ---------------------------------------------
           Desktop drag
        --------------------------------------------- */

        let dragging = false;
        let dragged = false;

        let startX = 0;
        let startingScroll = 0;


        categorySlider.addEventListener(
            "pointerdown",
            (event) => {

                /*
                 * Touch already has native swipe.
                 * Custom drag is mainly for mouse.
                 */

                if (
                    event.pointerType !== "mouse" ||
                    event.button !== 0
                ) {
                    return;
                }


                dragging = true;
                dragged = false;

                startX = event.clientX;

                startingScroll =
                    categorySlider.scrollLeft;


                categorySlider.style.scrollBehavior =
                    "auto";


                categorySlider.setPointerCapture?.(
                    event.pointerId
                );

            }
        );


        categorySlider.addEventListener(
            "pointermove",
            (event) => {

                if (!dragging) {
                    return;
                }


                const distance =
                    event.clientX -
                    startX;


                if (
                    Math.abs(distance) >
                    5
                ) {

                    dragged = true;

                }


                categorySlider.scrollLeft =
                    startingScroll -
                    distance * 1.1;

            }
        );


        function finishDrag(event) {

            if (!dragging) {
                return;
            }


            dragging = false;


            categorySlider.style.scrollBehavior =
                "smooth";


            if (
                event &&
                categorySlider.hasPointerCapture?.(
                    event.pointerId
                )
            ) {

                categorySlider.releasePointerCapture(
                    event.pointerId
                );

            }

        }


        categorySlider.addEventListener(
            "pointerup",
            finishDrag
        );


        categorySlider.addEventListener(
            "pointercancel",
            finishDrag
        );


        categorySlider.addEventListener(
            "lostpointercapture",
            () => {

                dragging = false;

                categorySlider.style.scrollBehavior =
                    "smooth";

            }
        );



        /*
         * Prevent link opening if
         * user was dragging the slider.
         */

        categorySlider
            .querySelectorAll(
                ".category-item"
            )
            .forEach((item) => {

                item.addEventListener(
                    "click",
                    (event) => {

                        if (!dragged) {
                            return;
                        }

                        event.preventDefault();

                        dragged = false;

                    }
                );

            });



        /* ---------------------------------------------
           Initial category state
        --------------------------------------------- */

        window.requestAnimationFrame(
            updateCategoryUI
        );

    }



    /* =====================================================
       WISHLIST VISUAL STATE
       Landing-page preview only.
    ====================================================== */

    document
        .querySelectorAll(
            ".wishlist-btn"
        )
        .forEach((button) => {

            button.setAttribute(
                "aria-pressed",
                "false"
            );


            button.addEventListener(
                "click",
                (event) => {

                    event.preventDefault();

                    const active =
                        button.classList.toggle(
                            "active"
                        );


                    button.setAttribute(
                        "aria-pressed",
                        active
                            ? "true"
                            : "false"
                    );


                    const icon =
                        button.querySelector(
                            "svg"
                        );


                    if (icon) {

                        icon.style.fill =
                            active
                                ? "currentColor"
                                : "none";

                    }


                    button.style.color =
                        active
                            ? "var(--green)"
                            : "";

                }
            );

        });



    /* =====================================================
       INTERNAL LANDING ANCHORS
       Smooth but free scrolling.
    ====================================================== */

    document
        .querySelectorAll(
            'a[href^="#"]'
        )
        .forEach((anchor) => {

            anchor.addEventListener(
                "click",
                (event) => {

                    const targetId =
                        anchor.getAttribute(
                            "href"
                        );


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


                    if (!target) {
                        return;
                    }


                    event.preventDefault();


                    target.scrollIntoView({

                        behavior: "smooth",
                        block: "start"

                    });

                }
            );

        });

});
/* =====================================================
   LANDING AUTH GUARD
   Require login/register before buyer actions
===================================================== */

document.addEventListener("DOMContentLoaded", () => {

    const protectedLinks = document.querySelectorAll(
        `
        .hero-btn,
        .editorial-item,
        .category-item,
        .cart-btn,
        .section-link,
        .cta-btn,
        .footer-links a
        `
    );


    protectedLinks.forEach(link => {


        link.addEventListener(
            "click",
            function(event){


                const href =
                    this.getAttribute("href");


                /*
                    Allow internal landing navigation
                    like #categories
                */

                if(
                    href &&
                    href.startsWith("#")
                ){
                    return;
                }



                /*
                    Redirect guest to login
                */

                event.preventDefault();


                window.location.href =
                    "{{ route('login') }}";


            }
        );


    });

});