(() => {
    "use strict";

    const $ = window.jQuery;
    const config = window.SACCIIslandSettings || {};

    function initialiseLogo() {
        const control = document.querySelector("[data-sacci-logo-control]");
        if (!control || !window.wp?.media) {
            return;
        }

        const idInput = control.querySelector("[data-sacci-logo-id]");
        const preview = control.querySelector("[data-sacci-logo-preview]");
        const remove = control.querySelector("[data-sacci-remove-logo]");

        control.querySelector("[data-sacci-select-logo]")?.addEventListener("click", () => {
            const frame = wp.media({
                title: config.mediaTitle || "Choose logo",
                button: { text: config.mediaButton || "Use this logo" },
                multiple: false,
                library: { type: "image" }
            });

            frame.on("select", () => {
                const attachment = frame.state().get("selection").first().toJSON();
                const src = attachment.sizes?.medium?.url || attachment.url;

                idInput.value = attachment.id || "";
                preview.innerHTML = `<img src="${src}" alt="">`;
                document.querySelector("[data-preview-logo]")?.setAttribute("src", src);
                remove.hidden = false;
            });

            frame.open();
        });

        remove?.addEventListener("click", () => {
            idInput.value = "";
            preview.innerHTML = `<img src="${config.defaultLogo || ""}" alt="">`;
            document.querySelector("[data-preview-logo]")?.setAttribute("src", config.defaultLogo || "");
            remove.hidden = true;
        });
    }

    function initialisePreview() {
        const nameInput = document.querySelector('input[name="settings[brand_name]"]');
        const taglineInput = document.querySelector('input[name="settings[brand_tagline]"]');
        const namePreview = document.querySelector("[data-preview-name]");
        const taglinePreview = document.querySelector("[data-preview-tagline]");
        const preview = document.querySelector("[data-sacci-live-preview]");

        nameInput?.addEventListener("input", () => {
            if (namePreview) {
                namePreview.textContent = nameInput.value || "Parish Administration";
            }
        });

        taglineInput?.addEventListener("input", () => {
            if (taglinePreview) {
                taglinePreview.textContent = taglineInput.value || "Parish Administration";
            }
        });

        document.querySelectorAll("[data-preview-colour]").forEach((input) => {
            input.addEventListener("input", () => {
                input.parentElement?.querySelector("code")?.replaceChildren(input.value);

                if (!preview) {
                    return;
                }

                const key = input.dataset.previewColour;
                const variables = {
                    primary: "--preview-primary",
                    primary_deep: "--preview-deep",
                    accent: "--preview-accent",
                    surface: "--preview-surface",
                    card: "--preview-card",
                    text: "--preview-text"
                };

                if (variables[key]) {
                    preview.style.setProperty(variables[key], input.value);
                }
            });
        });

        const radius = document.querySelector("[data-radius-range]");
        const radiusOutput = document.querySelector("[data-radius-output]");
        radius?.addEventListener("input", () => {
            if (radiusOutput) {
                radiusOutput.textContent = `${radius.value}px`;
            }
        });

        const sidebar = document.querySelector("[data-sidebar-range]");
        const sidebarOutput = document.querySelector("[data-sidebar-output]");
        sidebar?.addEventListener("input", () => {
            if (sidebarOutput) {
                sidebarOutput.textContent = `${sidebar.value}px`;
            }
        });
    }

    function initialiseMenuBuilder() {
        if (!$) {
            return;
        }

        const builder = $("[data-sacci-menu-builder]");
        const orderInput = document.querySelector("[data-sacci-menu-order]");

        if (!builder.length || !orderInput) {
            return;
        }

        const updateOrder = () => {
            const slugs = Array.from(
                document.querySelectorAll("[data-sacci-menu-builder] [data-menu-slug]")
            ).map((item) => item.dataset.menuSlug);

            orderInput.value = slugs.join(",");
        };

        builder.sortable({
            handle: ".sacci-island-drag",
            placeholder: "sacci-island-menu-placeholder",
            forcePlaceholderSize: true,
            tolerance: "pointer",
            update: updateOrder
        });

        updateOrder();
    }

    function initialiseReset() {
        document.querySelector("[data-sacci-confirm-reset]")?.addEventListener("click", (event) => {
            if (!window.confirm("Reset the Island Admin design and access settings?")) {
                event.preventDefault();
            }
        });
    }

    function initialiseRoleCount() {
        const head = document.querySelector(".sacci-island-access-row--head");
        const table = document.querySelector(".sacci-island-access-table");
        const rbacHead = document.querySelector(".sacci-island-rbac-row--head");
        const rbacTable = document.querySelector(".sacci-island-rbac-matrix");

        if (!head || !table) {
            if (rbacHead && rbacTable) {
                const count = Math.max(1, rbacHead.children.length - 1);
                rbacTable.style.setProperty("--sacci-role-count", count);
            }

            return;
        }

        const count = Math.max(1, head.children.length - 1);
        table.style.setProperty("--sacci-role-count", count);

        if (rbacHead && rbacTable) {
            const rbacCount = Math.max(1, rbacHead.children.length - 1);
            rbacTable.style.setProperty("--sacci-role-count", rbacCount);
        }
    }

    function initialiseRbacMatrix() {
        const matrix = document.querySelector("[data-sacci-rbac-matrix]");

        if (!matrix) {
            return;
        }

        const roleSearch = document.querySelector("[data-sacci-role-search]");
        const permissionSearch = document.querySelector("[data-sacci-permission-search]");
        const moduleFilter = document.querySelector("[data-sacci-module-filter]");

        const applyFilters = () => {
            const roleQuery = String(roleSearch?.value || "").trim().toLowerCase();
            const permissionQuery = String(permissionSearch?.value || "").trim().toLowerCase();
            const moduleValue = String(moduleFilter?.value || "");

            matrix.querySelectorAll("[data-role-column], [data-role-cell]").forEach((cell) => {
                const role = String(cell.dataset.roleColumn || cell.dataset.roleCell || "");
                const label = String(cell.textContent || role).toLowerCase();
                cell.hidden = Boolean(roleQuery && !label.includes(roleQuery) && !role.includes(roleQuery));
            });

            matrix.querySelectorAll(".sacci-island-rbac-row:not(.sacci-island-rbac-row--head)").forEach((row) => {
                const rowModule = String(row.dataset.module || "");
                const label = String(row.dataset.permissionLabel || row.textContent || "").toLowerCase();
                const moduleMatches = !moduleValue || rowModule === moduleValue;
                const permissionMatches = !permissionQuery || label.includes(permissionQuery);
                row.classList.toggle("is-hidden", !moduleMatches || !permissionMatches);
            });

            matrix.querySelectorAll(".sacci-island-rbac-module").forEach((module) => {
                const moduleName = String(module.dataset.module || "");
                const visibleRows = matrix.querySelectorAll(`.sacci-island-rbac-row[data-module="${CSS.escape(moduleName)}"]:not(.is-hidden)`);
                module.classList.toggle("is-hidden", !visibleRows.length || Boolean(moduleValue && moduleName !== moduleValue));
            });
        };

        roleSearch?.addEventListener("input", applyFilters);
        permissionSearch?.addEventListener("input", applyFilters);
        moduleFilter?.addEventListener("change", applyFilters);

        matrix.querySelectorAll("[data-sacci-toggle-role]").forEach((button) => {
            button.addEventListener("click", () => {
                const role = button.dataset.sacciToggleRole;
                const cells = Array.from(matrix.querySelectorAll(`[data-role-cell="${CSS.escape(role)}"] input:not(:disabled)`))
                    .filter((input) => !input.closest(".sacci-island-rbac-row")?.classList.contains("is-hidden"));
                const shouldCheck = cells.some((input) => !input.checked);

                cells.forEach((input) => {
                    input.checked = shouldCheck;
                });
            });
        });

        matrix.querySelectorAll("[data-sacci-toggle-module]").forEach((button) => {
            button.addEventListener("click", () => {
                const moduleName = button.dataset.sacciToggleModule;
                const cells = Array.from(matrix.querySelectorAll(`.sacci-island-rbac-row[data-module="${CSS.escape(moduleName)}"] input:not(:disabled)`));
                const shouldCheck = cells.some((input) => !input.checked);

                cells.forEach((input) => {
                    input.checked = shouldCheck;
                });
            });
        });

        document.querySelector("[data-sacci-check-visible]")?.addEventListener("click", () => {
            matrix.querySelectorAll(".sacci-island-rbac-row:not(.is-hidden) input:not(:disabled)").forEach((input) => {
                input.checked = true;
            });
        });

        document.querySelector("[data-sacci-clear-visible]")?.addEventListener("click", () => {
            matrix.querySelectorAll(".sacci-island-rbac-row:not(.is-hidden) input:not(:disabled)").forEach((input) => {
                input.checked = false;
            });
        });

        document.querySelector("[data-sacci-clone-role]")?.addEventListener("click", () => {
            const from = document.querySelector("[data-sacci-clone-from]")?.value;
            const to = document.querySelector("[data-sacci-clone-to]")?.value;

            if (!from || !to || from === to) {
                return;
            }

            const source = Array.from(matrix.querySelectorAll(`[data-role-cell="${CSS.escape(from)}"] input`));
            const target = Array.from(matrix.querySelectorAll(`[data-role-cell="${CSS.escape(to)}"] input:not(:disabled)`));

            target.forEach((input, index) => {
                if (source[index]) {
                    input.checked = source[index].checked;
                }
            });
        });
    }

    function initialise() {
        initialiseLogo();
        initialisePreview();
        initialiseMenuBuilder();
        initialiseReset();
        initialiseRoleCount();
        initialiseRbacMatrix();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initialise);
    } else {
        initialise();
    }
})();
