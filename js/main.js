/* =========================================================
   L'Atelier Berlingot — Interactions
   - Menu mobile
   - Année footer
   - Bouton "retour en haut"
   - Apparition au scroll (reveal)
   - Fermeture du menu au clic sur un lien
   - Soumission du formulaire (envoi via envoi.php, repli client mail)
   ========================================================= */
(function () {
    "use strict";

    /* ---------- Menu mobile ---------- */
    var navToggle = document.querySelector(".nav-toggle");
    var mainNav = document.getElementById("main-nav");

    function closeNav() {
        if (!navToggle || !mainNav) return;
        navToggle.setAttribute("aria-expanded", "false");
        mainNav.classList.remove("is-open");
    }

    if (navToggle && mainNav) {
        navToggle.addEventListener("click", function () {
            var open = mainNav.classList.toggle("is-open");
            navToggle.setAttribute("aria-expanded", open ? "true" : "false");
        });

        // Ferme le menu quand on clique sur un lien
        mainNav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", closeNav);
        });
    }

    // Ferme le menu si on clique en dehors
    document.addEventListener("click", function (e) {
        if (!mainNav || !mainNav.classList.contains("is-open")) return;
        if (!mainNav.contains(e.target) && e.target !== navToggle && !navToggle.contains(e.target)) {
            closeNav();
        }
    });

    // Ferme le menu avec Échap
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeNav();
    });

    /* ---------- Année footer ---------- */
    var yearEl = document.getElementById("year");
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    /* ---------- Reveal au scroll ---------- */
    var revealTargets = document.querySelectorAll(
        ".card, .peda-item, .info-block, .faq-item, .hours-card, .contact-form, .section-head"
    );
    revealTargets.forEach(function (el) { el.classList.add("reveal"); });

    if ("IntersectionObserver" in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

        revealTargets.forEach(function (el) { io.observe(el); });
    } else {
        revealTargets.forEach(function (el) { el.classList.add("is-visible"); });
    }

    /* ---------- Formulaire de contact (envoi via envoi.php) ---------- */
    var form = document.querySelector(".contact-form");
    if (form) {
        var note = form.querySelector(".form-note");

        // Message de retour après une soumission sans JavaScript (redirection envoi.php).
        var statut = new URLSearchParams(location.search).get("statut");
        if (statut === "ok" || statut === "erreur") {
            if (note) {
                note.textContent = statut === "ok"
                    ? "Merci ! Votre message a bien été envoyé, nous vous répondrons vite."
                    : "L'envoi a échoué. Vous pouvez nous écrire directement à eaje.atelierberlingot@gmail.com.";
                note.classList.toggle("error", statut === "erreur");
            }
            history.replaceState(null, "", location.pathname);
        }

        function afficheNote(texte, erreur) {
            if (!note) return;
            note.textContent = texte;
            note.classList.toggle("error", !!erreur);
        }

        // Repli si le serveur ne peut pas envoyer l'email (ex. preview sans PHP) :
        // on propose l'ouverture du client mail pré-rempli.
        function repliMailto() {
            var name = form.querySelector("#cf-name").value.trim();
            var email = form.querySelector("#cf-email").value.trim();
            var msg = form.querySelector("#cf-msg").value.trim();
            var subject = encodeURIComponent("Demande depuis le site — " + name);
            var body = encodeURIComponent(
                msg + "\n\n--\n" + name +
                (form.querySelector("#cf-phone").value.trim() ? "\nTél : " + form.querySelector("#cf-phone").value.trim() : "") +
                "\n" + email
            );
            afficheNote("Envoi direct impossible : votre logiciel de messagerie va s'ouvrir.", true);
            window.location.href = "mailto:eaje.atelierberlingot@gmail.com?subject=" + subject + "&body=" + body;
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            // Vérification minimale côté client.
            var name = form.querySelector("#cf-name");
            var email = form.querySelector("#cf-email");
            var msg = form.querySelector("#cf-msg");
            var emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());

            if (!name.value.trim() || !emailValid || !msg.value.trim()) {
                afficheNote("Merci de renseigner votre nom, un email valide et un message.", true);
                return;
            }

            afficheNote("Envoi en cours…", false);

            fetch(form.action, {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: new FormData(form)
            })
                .then(function (res) {
                    if (!res.ok) throw new Error("HTTP " + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (data.ok) {
                        launchConfetti();
                        afficheNote(data.message, false);
                        form.reset();
                    } else {
                        afficheNote(data.message, true);
                    }
                })
                .catch(function () {
                    // Serveur sans PHP (preview) ou erreur réseau : repli client mail.
                    repliMailto();
                });
        });
    }

    /* ---------- Confettis (soumission formulaire) ---------- */
    // Couleurs pastel de la charte graphique.
    var confettiColors = ["#ff8a5b", "#6ec6a8", "#ffd166", "#f4a6c0", "#b39ddb", "#e8704f"];

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    }

    function launchConfetti() {
        if (prefersReducedMotion()) return; // respecte le confort visuel
        var count = 44;
        var originX = window.innerWidth / 2;
        var originY = window.innerHeight / 2;
        var fragment = document.createDocumentFragment();

        for (var i = 0; i < count; i++) {
            var piece = document.createElement("span");
            piece.className = "confetti";
            piece.style.background = confettiColors[i % confettiColors.length];
            piece.style.left = originX + "px";
            piece.style.top = originY + "px";
            // Trajectoires aléatoires : vers le haut puis retombée.
            var angle = Math.random() * Math.PI * 2;
            var distance = 120 + Math.random() * 220;
            var dx = Math.cos(angle) * distance;
            var dy = Math.sin(angle) * distance + 200; // gravité vers le bas
            var rot = (Math.random() > 0.5 ? 1 : -1) * (360 + Math.random() * 540);
            piece.style.setProperty("--dx", dx + "px");
            piece.style.setProperty("--dy", dy + "px");
            piece.style.setProperty("--rot", rot + "deg");
            piece.style.width = (6 + Math.random() * 8) + "px";
            piece.style.height = (8 + Math.random() * 10) + "px";
            fragment.appendChild(piece);
        }
        document.body.appendChild(fragment);
        // Nettoyage après la fin de l'animation (2,5s définies en CSS).
        setTimeout(function () {
            var pieces = document.querySelectorAll(".confetti");
            pieces.forEach(function (p) { p.remove(); });
        }, 2600);
    }

    /* ---------- Parallax des étoiles du hero à la souris ---------- */
    // Déplacement léger (max ~12px) du wrapper .float-stars selon la position
    // de la souris. Désactivé si motion réduite ou appareil tactile (pas de souris).
    /* ---------- Parallax du décor du hero à la souris ---------- */
    // Déplacement léger de tous les éléments décoratifs (.hero-shapes > *,
    // + .float-stars) selon la position de la souris. Chaque couche a sa propre
    // profondeur (data-parallax) : 1 = lointain (peu de mouvement), 3 = proche.
    // Désactivé si motion réduite ou appareil tactile (pas de souris).
    var hero = document.querySelector(".hero");
    var isTouchDevice = window.matchMedia && window.matchMedia("(hover: none)").matches;

    if (hero && !prefersReducedMotion() && !isTouchDevice) {
        // Cible tous les enfants directs de .hero-shapes + le wrapper .float-stars.
        var parallaxItems = hero.querySelectorAll(".hero-shapes > *");
        // Affecte une profondeur à chaque type d'élément pour un effet de couches.
        var depthByClass = {
            "blob": 1,      // très lointain, bouge peu
            "cloud": 2,     // moyen
            "sun": 2,
            "candy": 3,     // proche, bouge davantage
            "float-stars": 3
        };

        hero.addEventListener("mousemove", function (e) {
            var rect = hero.getBoundingClientRect();
            // Position relative −0.5 à +0.5 sur chaque axe.
            var rx = (e.clientX - rect.left) / rect.width - 0.5;
            var ry = (e.clientY - rect.top) / rect.height - 0.5;
            parallaxItems.forEach(function (item) {
                var depth = depthByClass[item.className.split(" ")[0]] || 2;
                var intensity = depth * 14; // 14px par unité de profondeur
                item.style.setProperty("--px", (rx * intensity) + "px");
                item.style.setProperty("--py", (ry * intensity) + "px");
            });
            var stars = hero.querySelector(".float-stars");
            if (stars) {
                stars.style.setProperty("--px", (rx * 42) + "px");
                stars.style.setProperty("--py", (ry * 42) + "px");
            }
        });
        hero.addEventListener("mouseleave", function () {
            parallaxItems.forEach(function (item) {
                item.style.setProperty("--px", "");
                item.style.setProperty("--py", "");
            });
            var stars = hero.querySelector(".float-stars");
            if (stars) {
                stars.style.setProperty("--px", "");
                stars.style.setProperty("--py", "");
            }
        });
    }

    /* ---------- Accès clavier : menu anchor offset ---------- */
    // Ajuste le scroll pour compenser le header sticky lors de l'ancre.
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener("click", function (e) {
            var id = anchor.getAttribute("href");
            if (id === "#" || id.length < 2) return;
            var target = document.querySelector(id);
            if (!target) return;
            e.preventDefault();
            var headerH = document.querySelector(".site-header");
            var offset = headerH ? headerH.offsetHeight + 8 : 0;
            var top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: top, behavior: "smooth" });
            history.pushState(null, "", id);
        });
    });
})();
