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
            menuWrap.dataset.sacciConnectedReady === "true"
        ) {
            return;
        }

        menuWrap.dataset.sacciConnectedReady = "true";
        document.documentElement.style.colorScheme = "light";
        body.style.colorScheme = "light";

        if (toggleLink) {
            initialiseSidebar({
                body,
                menuWrap,
                toggleNode,
                toggleLink,
                config
            });
        }

        initialiseSubmenus();
        initialiseAdminBarMenus();
        initialiseCommandSearch(searchLink);
    }

    function initialiseSidebar({
        body,
        menuWrap,
        toggleNode,
        toggleLink,
        config
    }) {
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
                toggleNode?.contains(event.target)
            ) {
                return;
            }

            setSidebar(false);
        });
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
            const link = item.querySelector(":scope > a.menu-top");

            item.classList.remove("sacci-submenu-open");
            button?.setAttribute("aria-expanded", "false");
            link?.setAttribute("aria-expanded", "false");
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
            item
                .querySelector(":scope > a.menu-top")
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
            const submenuId = submenu.id ||
                `sacci-submenu-${items.indexOf(item) + 1}`;

            submenu.id = submenuId;
            link.setAttribute("aria-haspopup", "true");
            link.setAttribute("aria-controls", submenuId);
            link.setAttribute("aria-expanded", "false");
            button.type = "button";
            button.className = "sacci-submenu-toggle";
            button.setAttribute("aria-expanded", "false");
            button.setAttribute("aria-controls", submenuId);
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
                event.stopImmediatePropagation();
                toggleItem(item);
            });

            link.addEventListener("keydown", (event) => {
                if (event.key !== "ArrowDown") {
                    return;
                }

                event.preventDefault();
                openItem(item);
                submenu.querySelector("a[href]")?.focus();
            });
        });

        /*
         * WordPress and some vendor plugins register their own menu click
         * handlers before this script runs. Intercept parent links in the
         * capture phase so a row such as Plugins always toggles its accordion
         * and can never race another handler into navigating to plugins.php.
         * The real destination remains the first link inside the submenu.
         */
        menu.addEventListener("click", (event) => {
            const target = event.target;
            const link = target instanceof Element
                ? target.closest("li.wp-has-submenu > a.menu-top")
                : null;
            const item = link?.parentElement;

            if (!link || !item || !items.includes(item)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            toggleItem(item);
        }, true);

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

        menu.addEventListener("keydown", (event) => {
            if (event.key !== "Escape") {
                return;
            }

            const openItemNode = event.target.closest(
                "li.sacci-submenu-open"
            );

            if (!openItemNode) {
                return;
            }

            closeItem(openItemNode);
            openItemNode.querySelector(":scope > a.menu-top")?.focus();
        });
    }

    function getMenuLabel(link) {
        const name = link.querySelector(".wp-menu-name");
        return String(name?.textContent || link.textContent || "section").trim();
    }

    function initialiseAdminBarMenus() {
        const adminBar = document.getElementById("wpadminbar");

        if (!adminBar || adminBar.dataset.sacciMenusReady === "true") {
            return;
        }

        adminBar.dataset.sacciMenusReady = "true";

        const menus = Array.from(
            adminBar.querySelectorAll(
                ".ab-top-menu > li.menupop"
            )
        ).filter((item) =>
            item.querySelector(":scope > .ab-item") &&
            item.querySelector(":scope > .ab-sub-wrapper")
        );

        function closeMenu(item, restoreFocus = false) {
            const trigger = item.querySelector(":scope > .ab-item");

            item.classList.remove("sacci-adminbar-menu-open", "hover");
            trigger?.setAttribute("aria-expanded", "false");

            if (restoreFocus) {
                trigger?.focus();
            }
        }

        function openMenu(item) {
            menus.forEach((candidate) => {
                if (candidate !== item) {
                    closeMenu(candidate);
                }
            });

            item.classList.add("sacci-adminbar-menu-open");
            item
                .querySelector(":scope > .ab-item")
                ?.setAttribute("aria-expanded", "true");
        }

        menus.forEach((item) => {
            const trigger = item.querySelector(":scope > .ab-item");
            const submenu = item.querySelector(":scope > .ab-sub-wrapper");

            trigger.setAttribute("aria-haspopup", "true");
            trigger.setAttribute("aria-expanded", "false");

            trigger.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (item.classList.contains("sacci-adminbar-menu-open")) {
                    closeMenu(item);
                    return;
                }

                openMenu(item);
            }, true);

            trigger.addEventListener("keydown", (event) => {
                if (event.key !== "ArrowDown") {
                    return;
                }

                event.preventDefault();
                openMenu(item);
                submenu.querySelector("a[href]")?.focus();
            });

            submenu.addEventListener("click", (event) => {
                event.stopPropagation();
            });
        });

        document.addEventListener("click", () => {
            menus.forEach((item) => closeMenu(item));
        });

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape") {
                return;
            }

            const openMenuItem = menus.find((item) =>
                item.classList.contains("sacci-adminbar-menu-open")
            );

            if (openMenuItem) {
                closeMenu(openMenuItem, true);
            }
        });
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
