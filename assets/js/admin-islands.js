(() => {
    "use strict";

    function initialise() {
        const body = document.body;
        const menuWrap = document.getElementById("adminmenuwrap");
        const toggleNode = document.getElementById(
            "wp-admin-bar-sacci-sidebar-toggle"
        );
        const toggleLink = toggleNode?.querySelector(".ab-item");
        const searchNode = document.getElementById(
            "wp-admin-bar-sacci-admin-search"
        );
        const searchLink = searchNode?.querySelector(".ab-item");
        const config = window.SACCIIslandAdmin || {};

        if (
            !body ||
            !menuWrap ||
            !toggleLink ||
            menuWrap.dataset.sacciConnectedReady === "true"
        ) {
            return;
        }

        menuWrap.dataset.sacciConnectedReady = "true";
        document.documentElement.style.colorScheme = "light";
        body.style.colorScheme = "light";

        const backdrop = document.createElement("button");
        backdrop.type = "button";
        backdrop.className = "sacci-sidebar-backdrop";
        backdrop.setAttribute(
            "aria-label",
            config.closeLabel || "Close navigation"
        );
        document.body.appendChild(backdrop);

        function isSidebarOpen() {
            return body.classList.contains("sacci-sidebar-open");
        }

        function persistSidebar(open) {
            const state = open ? "open" : "closed";

            try {
                window.localStorage.setItem(
                    config.storageKey || "sacci_connected_sidebar_state",
                    state
                );
            } catch (error) {
                // Storage can be unavailable in hardened browsers.
            }

            document.cookie =
                `sacci_connected_sidebar=${state}; path=/; max-age=31536000; SameSite=Lax`;
        }

        function setSidebar(open, persist = true) {
            body.classList.toggle("sacci-sidebar-open", open);

            if (persist) {
                persistSidebar(open);
            }

            toggleLink.setAttribute("aria-expanded", open ? "true" : "false");
            toggleLink.setAttribute(
                "aria-label",
                open
                    ? (config.closeLabel || "Close navigation")
                    : (config.openLabel || "Open navigation")
            );
        }

        try {
            const saved = window.localStorage.getItem(
                config.storageKey || "sacci_connected_sidebar_state"
            );

            if (saved === "open" || saved === "closed") {
                setSidebar(saved === "open", false);
            }
        } catch (error) {
            // Use the server-rendered cookie state.
        }

        toggleLink.addEventListener("click", (event) => {
            event.preventDefault();
            setSidebar(!isSidebarOpen());
        });

        backdrop.addEventListener("click", () => {
            setSidebar(false);
        });

        document.addEventListener("click", (event) => {
            if (
                window.innerWidth > 960 ||
                !isSidebarOpen() ||
                menuWrap.contains(event.target) ||
                toggleNode.contains(event.target)
            ) {
                return;
            }

            setSidebar(false);
        });

        initialiseSubmenus();
        initialiseCommandSearch(searchLink);
    }

    function initialiseSubmenus() {
        const menu = document.getElementById("adminmenu");

        if (!menu || menu.dataset.sacciAccordionReady === "true") {
            return;
        }

        menu.dataset.sacciAccordionReady = "true";

        const items = Array.from(
            menu.querySelectorAll("li.wp-has-submenu")
        ).filter((item) => item.querySelector(":scope > .wp-submenu"));

        function setSubmenuHeight(item) {
            const submenu = item.querySelector(":scope > .wp-submenu");

            if (!submenu) {
                return;
            }

            submenu.style.setProperty(
                "--sacci-submenu-height",
                `${submenu.scrollHeight + 20}px`
            );
        }

        function closeItem(item) {
            const button = item.querySelector(":scope > .sacci-submenu-toggle");

            item.classList.remove("sacci-submenu-open");
            button?.setAttribute("aria-expanded", "false");
        }

        function openItem(item) {
            items.forEach((candidate) => {
                if (candidate !== item) {
                    closeItem(candidate);
                }
            });

            setSubmenuHeight(item);
            item.classList.add("sacci-submenu-open");
            item
                .querySelector(":scope > .sacci-submenu-toggle")
                ?.setAttribute("aria-expanded", "true");
        }

        function toggleItem(item) {
            if (item.classList.contains("sacci-submenu-open")) {
                closeItem(item);
                return;
            }

            openItem(item);
        }

        items.forEach((item) => {
            const link = item.querySelector(":scope > a.menu-top");
            const submenu = item.querySelector(":scope > .wp-submenu");

            if (!link || !submenu) {
                return;
            }

            const label = getMenuLabel(link);
            const button = document.createElement("button");

            button.type = "button";
            button.className = "sacci-submenu-toggle";
            button.setAttribute("aria-expanded", "false");
            button.setAttribute("aria-label", `Toggle ${label} submenu`);
            button.innerHTML = `
                <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                    <path d="m6 9 6 6 6-6"></path>
                </svg>
            `;

            link.insertAdjacentElement("afterend", button);
            setSubmenuHeight(item);

            button.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleItem(item);
            });
        });

        const current = items.find((item) =>
            item.classList.contains("wp-has-current-submenu") ||
            item.querySelector(":scope > .wp-submenu li.current")
        );

        items.forEach(closeItem);

        if (current) {
            openItem(current);
        }

        window.addEventListener("resize", () => {
            items.forEach(setSubmenuHeight);
        });
    }

    function getMenuLabel(link) {
        const name = link.querySelector(".wp-menu-name");
        return String(name?.textContent || link.textContent || "section").trim();
    }

    function initialiseCommandSearch(searchLink) {
        const links = Array.from(
            document.querySelectorAll(
                "#adminmenu a[href]:not([href='#'])"
            )
        ).map((link) => ({
            href: link.href,
            label: String(link.textContent || "").trim()
        })).filter((item, index, all) =>
            item.label &&
            all.findIndex((candidate) =>
                candidate.href === item.href
            ) === index
        );

        const overlay = document.createElement("div");
        overlay.className = "sacci-admin-command-overlay";
        overlay.innerHTML = `
            <section
                class="sacci-admin-command"
                role="dialog"
                aria-modal="true"
                aria-label="Search administration"
            >
                <div class="sacci-admin-command__input">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input
                        type="search"
                        placeholder="Search parish administration"
                        autocomplete="off"
                    >
                </div>
                <div class="sacci-admin-command__results"></div>
            </section>
        `;

        document.body.appendChild(overlay);

        const input = overlay.querySelector("input");
        const results = overlay.querySelector(
            ".sacci-admin-command__results"
        );

        function render(query = "") {
            const normalized = query.trim().toLowerCase();
            const matches = links.filter((item) =>
                !normalized ||
                item.label.toLowerCase().includes(normalized)
            ).slice(0, 18);

            results.replaceChildren();

            matches.forEach((item, index) => {
                const anchor = document.createElement("a");
                const icon = document.createElement("span");
                const label = document.createElement("span");

                anchor.href = item.href;
                anchor.className = index === 0 ? "is-selected" : "";
                icon.className = "dashicons dashicons-arrow-right-alt2";
                icon.setAttribute("aria-hidden", "true");
                label.textContent = item.label;

                anchor.append(icon, label);
                results.appendChild(anchor);
            });

            if (!matches.length) {
                const empty = document.createElement("p");
                empty.className = "sacci-admin-command__empty";
                empty.textContent = "No administration page matched your search.";
                results.appendChild(empty);
            }
        }

        function open() {
            render("");
            overlay.classList.add("is-open");
            window.setTimeout(() => input?.focus(), 20);
        }

        function close() {
            overlay.classList.remove("is-open");

            if (input) {
                input.value = "";
            }
        }

        searchLink?.addEventListener("click", (event) => {
            event.preventDefault();
            open();
        });

        input?.addEventListener("input", () => {
            render(input.value);
        });

        input?.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                close();
                return;
            }

            if (event.key === "Enter") {
                const selected = results.querySelector(
                    "a.is-selected, a"
                );

                if (selected) {
                    window.location.href = selected.href;
                }
            }
        });

        overlay.addEventListener("click", (event) => {
            if (event.target === overlay) {
                close();
            }
        });

        document.addEventListener("keydown", (event) => {
            const target = event.target;
            const editable =
                target instanceof HTMLElement &&
                (
                    target.isContentEditable ||
                    /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)
                );

            if (
                event.ctrlKey &&
                !event.altKey &&
                !event.metaKey &&
                event.key.toLowerCase() === "k" &&
                !editable
            ) {
                event.preventDefault();
                open();
            }

            if (
                event.key === "Escape" &&
                overlay.classList.contains("is-open")
            ) {
                close();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise);
    } else {
        initialise();
    }
})();
