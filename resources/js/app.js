import { Chart, registerables } from "chart.js";

Chart.register(...registerables);
Chart.defaults.font.family =
    "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

const pad = (n) => String(n).padStart(2, "0");

export function formatDuration(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds));
    return `${Math.floor(s / 3600)}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}`;
}

function readTheme() {
    try {
        return localStorage.getItem("theme") || "system";
    } catch (e) {
        return "system";
    }
}

const systemDark = window.matchMedia("(prefers-color-scheme: dark)");

function applyTheme(mode) {
    const dark = mode === "dark" || (mode === "system" && systemDark.matches);
    document.documentElement.classList.toggle("dark", dark);
    window.dispatchEvent(new CustomEvent("theme-changed"));
}

systemDark.addEventListener("change", () => readTheme() === "system" && applyTheme("system"));

/** Re-apply after wire:navigate swaps the page. */
document.addEventListener("livewire:navigated", () => applyTheme(readTheme()));

const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

document.addEventListener("alpine:init", () => {
    /**
     * Ticking timer. `startedAt` is an epoch ms timestamp from the server; `offset` corrects
     * client clock skew so every tab shows the same elapsed time.
     */
    window.Alpine.data("timer", (startedAt = null, serverNow = Date.now()) => ({
        startedAt,
        offset: Date.now() - serverNow,
        elapsed: "0:00:00",
        handle: null,
        baseTitle: document.title,
        init() {
            this.tick();
            this.handle = setInterval(() => this.tick(), 1000);
        },
        destroy() {
            clearInterval(this.handle);
            document.title = this.baseTitle;
        },
        tick() {
            if (!this.startedAt) {
                this.elapsed = "0:00:00";
                return;
            }
            this.elapsed = formatDuration(
                (Date.now() - this.offset - this.startedAt) / 1000,
            );
            document.title = `${this.elapsed} · ${this.baseTitle}`;
        },
    }));

    /** Chart.js wrapper; the element is re-keyed by Livewire whenever its config changes. */
    /** Chart.js wrapper; re-keyed by Livewire when data changes and rebuilt when the theme changes. */
    window.Alpine.data("chart", (source) => {
        // Kept outside Alpine's reactive state: proxying a Chart instance breaks its internals.
        let chart = null;

        const build = (canvas) => {
            const config = structuredClone(source);
            const ink = cssVar("--color-muted");
            const grid = cssVar("--color-line-soft");
            const surface = cssVar("--color-surface");

            Chart.defaults.color = ink;
            config.options ??= {};
            config.options.plugins ??= {};
            config.options.plugins.tooltip = {
                callbacks: { label: (ctx) => `${ctx.dataset.label || ctx.label || ""}: ${formatDuration(ctx.raw ?? 0)}` },
            };

            if (config.type === "bar") {
                const sets = config.data.datasets;
                const maxHours =
                    Math.max(0, ...config.data.labels.map((_, i) => sets.reduce((sum, d) => sum + (d.data[i] || 0), 0))) / 3600;
                const step = [0.25, 0.5, 1, 2, 4, 6, 12, 24, 48, 96].find((s) => maxHours / s <= 8) ?? 200;
                config.options.scales.y.ticks = { stepSize: step * 3600, callback: (v) => `${Math.round((v / 3600) * 100) / 100}h` };
                config.options.scales.y.grid = { color: grid };
                config.options.scales.y.border = { color: grid };
                config.options.scales.x.border = { color: grid };
            } else {
                config.data.datasets.forEach((d) => (d.borderColor = surface));
            }

            chart = new Chart(canvas, config);
        };

        return {
            init() {
                build(this.$refs.canvas);
                this.onTheme = () => {
                    chart?.destroy();
                    build(this.$refs.canvas);
                };
                window.addEventListener("theme-changed", this.onTheme);
            },
            destroy() {
                window.removeEventListener("theme-changed", this.onTheme);
                chart?.stop();
                chart?.destroy();
                chart = null;
            },
        };
    });

    /** Searchable dropdown shell shared by the project / tag / member pickers. */
    window.Alpine.data("dropdown", () => ({
        open: false,
        search: "",
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.search = "";
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() {
            this.open = false;
        },
        matches(text) {
            return text
                .toLowerCase()
                .includes(this.search.trim().toLowerCase());
        },
    }));

    /** Workspace projects/tags shared by every picker on the page (sent once, refreshed on change). */
    window.Alpine.store("catalog", {
        projects: [],
        tags: [],
        canCreateProject: false,
        canCreateTag: false,
    });

    /** Project (+ task) picker. Emits `project-picked` with { projectId, taskId }. */
    window.Alpine.data("projectPicker", () => ({
        open: false,
        search: "",
        expanded: {},
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.search = "";
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        close() {
            this.open = false;
        },
        q() {
            return this.search.trim().toLowerCase();
        },
        projectMatches(p) {
            const q = this.q();
            return (
                !q ||
                p.name.toLowerCase().includes(q) ||
                (p.client || "").toLowerCase().includes(q) ||
                p.tasks.some((t) => t.name.toLowerCase().includes(q))
            );
        },
        tasksFor(p) {
            const q = this.q();
            if (
                !q ||
                p.name.toLowerCase().includes(q) ||
                (p.client || "").toLowerCase().includes(q)
            ) {
                return this.expanded[p.id] ? p.tasks : [];
            }
            return p.tasks.filter((t) => t.name.toLowerCase().includes(q));
        },
        get groups() {
            const projects = this.$store.catalog.projects.filter((p) =>
                this.projectMatches(p),
            );
            const groups = [];
            const favorites = projects.filter((p) => p.favorite);
            if (favorites.length)
                groups.push({
                    label: "Favorites",
                    key: "fav",
                    projects: favorites,
                });
            const byClient = new Map();
            projects.forEach((p) => {
                const key = p.client || "";
                if (!byClient.has(key)) byClient.set(key, []);
                byClient.get(key).push(p);
            });
            byClient.forEach((list, client) =>
                groups.push({
                    label: client || "No client",
                    key: `c-${client}`,
                    projects: list,
                }),
            );
            return groups;
        },
        pick(projectId, taskId = null) {
            this.open = false;
            this.$dispatch("project-picked", { projectId, taskId });
        },
        create(origin) {
            this.open = false;
            this.$dispatch("open-project-form", {
                name: this.search.trim(),
                origin,
            });
        },
    }));

    /** Multi tag picker. Emits `tags-picked` with { ids } when closed with changes. */
    window.Alpine.data("tagPicker", (initial = []) => ({
        open: false,
        search: "",
        selected: [...initial],
        initial: [...initial],
        toggle() {
            this.open ? this.close() : this.openPanel();
        },
        openPanel() {
            this.open = true;
            this.search = "";
            this.initial = [...this.selected];
            this.$nextTick(() => this.$refs.search?.focus());
        },
        close() {
            if (!this.open) return;
            this.open = false;
            const changed =
                this.selected.length !== this.initial.length ||
                this.selected.some((id) => !this.initial.includes(id));
            if (changed)
                this.$dispatch("tags-picked", { ids: [...this.selected] });
        },
        isSelected(id) {
            return this.selected.includes(id);
        },
        flip(id) {
            this.selected = this.isSelected(id)
                ? this.selected.filter((x) => x !== id)
                : [...this.selected, id];
        },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            return this.$store.catalog.tags.filter(
                (t) => !q || t.name.toLowerCase().includes(q),
            );
        },
        get canCreate() {
            const q = this.search.trim().toLowerCase();
            return (
                this.$store.catalog.canCreateTag &&
                q !== "" &&
                !this.$store.catalog.tags.some(
                    (t) => t.name.toLowerCase() === q,
                )
            );
        },
        get names() {
            return this.$store.catalog.tags
                .filter((t) => this.selected.includes(t.id))
                .map((t) => t.name);
        },
        async create() {
            const id = await this.$wire.createTag(this.search.trim());
            if (id) {
                this.selected = [...this.selected, id];
                this.search = "";
            }
        },
    }));

    /** Light / dark / system theme, persisted per browser. */
    window.Alpine.store("theme", {
        mode: readTheme(),
        set(mode) {
            this.mode = mode;
            try {
                localStorage.setItem("theme", mode);
            } catch (e) {}
            applyTheme(mode);
        },
    });

    window.Alpine.store("toasts", {
        items: [],
        push(message, type = "success") {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.remove(id), 3500);
        },
        remove(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });
});

window.addEventListener("catalog-updated", (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    Object.assign(window.Alpine.store("catalog"), detail.catalog);
});

window.addEventListener("notify", (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store("toasts").push(
        detail.message,
        detail.type ?? "success",
    );
});
