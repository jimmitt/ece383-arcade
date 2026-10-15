(() => {
    "use strict";
    const games = Array.from(document.querySelectorAll(".game-link"));
    if (games.length === 0) return;
    const artwork = document.getElementById("artwork");
    const image = document.getElementById("game-art");
    const status = document.getElementById("selection-status");
    const description = document.getElementById("game-description");
    let selected = 0;
    let launching = false;

    function select(index, focus = true) {
        selected = (index + games.length) % games.length;
        games.forEach((game, i) => {
            const current = i === selected;
            game.classList.toggle("is-selected", current);
            game.tabIndex = current ? 0 : -1;
            if (current) game.setAttribute("aria-current", "true");
            else game.removeAttribute("aria-current");
        });
        const game = games[selected];
        const source = game.dataset.image;
        image.hidden = !source;
        artwork.dataset.empty = source ? "false" : "true";
        image.alt = `${game.dataset.title} artwork`;
        if (source) {
            if (image.getAttribute("src") !== source) image.setAttribute("src", source);
        } else image.removeAttribute("src");
        description.textContent = game.dataset.description;
        status.textContent = `${game.dataset.title} · Ready to play`;
        if (focus) {
            game.focus({ preventScroll: true });
            game.scrollIntoView({ block: "nearest", inline: "nearest" });
        }
    }

    image.addEventListener("error", () => {
        image.hidden = true;
        artwork.dataset.empty = "true";
    });
    games.forEach((game, index) => {
        game.addEventListener("focus", () => {
            if (!launching && selected !== index) select(index, false);
        });
        game.addEventListener("pointermove", (event) => {
            // A stationary mouse must not override joystick focus after scrolling.
            if ((event.movementX || event.movementY) && !launching && selected !== index) select(index);
        });
        game.addEventListener("click", (event) => {
            if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            if (launching) { event.preventDefault(); return; }
            select(index, false);
            launching = true;
            status.textContent = `Loading ${game.dataset.title}…`;
            // Real links follow fpgaloader.php; no second programming endpoint.
        });
    });
    document.addEventListener("keydown", (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey) return;
        const key = event.key.toLowerCase();
        const previous = key === "arrowup" || key === "arrowleft";
        const next = key === "arrowdown" || key === "arrowright";
        // Due A='a', B='b', Select=Space, Start=Enter, for either player.
        const activate = ["a", "b", " ", "enter"].includes(key);
        if (!previous && !next && !activate) return;
        event.preventDefault();
        if (launching) return;
        if (activate) {
            if (!event.repeat) games[selected].click();
        } else select(selected + (next ? 1 : -1));
    });
    // A bfcache return must not leave the menu locked in its loading state.
    window.addEventListener("pageshow", () => {
        launching = false;
        select(selected, false);
    });
    select(0, false);
})();
