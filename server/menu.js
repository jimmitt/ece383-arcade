(() => {
    "use strict";
    const games = Array.from(document.querySelectorAll(".game-link"));
    if (games.length === 0) return;
    const artwork = document.getElementById("artwork");
    const image = document.getElementById("game-art");
    const status = document.getElementById("selection-status");
    const description = document.getElementById("game-description");
    const dialog = document.getElementById("load-dialog");
    const title = document.getElementById("load-title");
    const message = document.getElementById("load-message");
    const symbol = document.getElementById("load-symbol");
    const hint = document.getElementById("continue-hint");
    const continueButton = document.getElementById("continue-button");
    const heldKeys = new Set();
    let selected = 0;
    let phase = "menu";

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

    function finish() {
        if (phase !== "result") return;
        phase = "menu";
        dialog.close();
        select(selected);
    }

    async function launch(index) {
        if (phase !== "menu") return;
        select(index, false);
        phase = "loading";
        const game = games[selected];
        dialog.dataset.state = "loading";
        symbol.textContent = "";
        title.textContent = `Loading ${game.dataset.title}`;
        message.textContent = "Please wait while the FPGA is loaded.";
        hint.hidden = true;
        continueButton.hidden = true;
        dialog.showModal();
        let success = false;
        try {
            const response = await fetch(game.href, {
                method: "POST",
                headers: { "Accept": "application/json" },
                cache: "no-store"
            });
            const result = await response.json();
            success = response.ok && result.success === true;
            if (!success) throw new Error("Programming failed");
            message.textContent = "Use the silver button to switch the display source to the FPGA.";
        } catch {
            message.textContent = "The FPGA was not loaded successfully. Press any key to return to the menu and try again.";
        }
        phase = "result";
        dialog.dataset.state = success ? "success" : "failure";
        symbol.textContent = success ? "✓" : "!";
        title.textContent = success ? "FPGA loaded" : "Loading unsuccessful";
        hint.hidden = false;
        continueButton.hidden = false;
        continueButton.focus({ preventScroll: true });
    }

    image.addEventListener("error", () => {
        image.hidden = true;
        artwork.dataset.empty = "true";
    });
    continueButton.addEventListener("click", finish);
    dialog.addEventListener("cancel", (event) => { event.preventDefault(); finish(); });
    games.forEach((game, index) => {
        game.addEventListener("focus", () => {
            if (phase === "menu" && selected !== index) select(index, false);
        });
        game.addEventListener("pointermove", (event) => {
            // A stationary mouse must not override joystick focus after scrolling.
            if ((event.movementX || event.movementY) && phase === "menu" && selected !== index) select(index);
        });
        game.addEventListener("click", (event) => {
            if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
            event.preventDefault();
            launch(index);
        });
    });
    document.addEventListener("keyup", (event) => heldKeys.delete(event.code || event.key));
    window.addEventListener("blur", () => heldKeys.clear());
    document.addEventListener("keydown", (event) => {
        const id = event.code || event.key;
        const repeated = event.repeat || heldKeys.has(id);
        heldKeys.add(id);
        if (event.altKey || event.ctrlKey || event.metaKey) return;
        if (phase !== "menu") {
            event.preventDefault();
            if (phase === "result" && !repeated) finish();
            return;
        }
        const key = event.key.toLowerCase();
        const previous = key === "arrowup" || key === "arrowleft";
        const next = key === "arrowdown" || key === "arrowright";
        // Due A='a', B='b', Select=Space, Start=Enter, for either player.
        const activate = ["a", "b", " ", "enter"].includes(key);
        if (!previous && !next && !activate) return;
        event.preventDefault();
        if (activate) {
            if (!repeated) launch(selected);
        } else select(selected + (next ? 1 : -1));
    });
    select(0, false);
})();
