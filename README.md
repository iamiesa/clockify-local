# TimeTrack

A fast, responsive time tracker for individuals and teams: start/stop timers, organise work into
projects, clients and tags, manage workspaces and members, and analyse time with dashboards and
reports. Works on desktop and mobile, in light and dark themes.

Built with **Laravel 13**, **Livewire 4**, **Alpine.js**, **Tailwind CSS 4** and **Chart.js**.

## Features

| Area | What you get |
|---|---|
| **Time tracker** | Start/stop timer (one running timer per user, live in the tab title), manual entries, inline editing of description, project/task, tags, billable flag, start/end time, date and duration; continue, duplicate, bulk delete; entries grouped by week and day with totals |
| **Projects** | Colours, clients, public/private access, billable defaults, hourly rates, time estimates with progress, tasks, project members with per-project rates and managers, notes, favourites, archive → delete |
| **Clients & tags** | Create, rename, archive/restore, delete, search |
| **Team** | Invite by email (shareable invite links), roles (owner/admin/member), member hourly rates, deactivate/remove, groups |
| **Workspaces** | Multiple workspaces per user, switching, settings (currency, default rate, billable by default, required project, member permissions) |
| **Dashboard** | Total time and amount, top project/client, daily stacked chart by project or billability, project split, most tracked activities, team activity |
| **Reports** | Summary (group + sub-group by project, client, user, tag, task, date, description), Detailed (paginated entries), Weekly (rows × days); filters for team, groups, clients, projects, tags, billable, description; decimal or h:mm:ss; CSV export |
| **Profile** | Name, email, timezone, week start, 12/24-hour time, avatar colour, password |

Durations are stored in seconds and money in cents; timestamps are stored in UTC and displayed in
each user's timezone. The effective hourly rate is resolved in this order:
**project member rate → project rate → workspace member rate → workspace default**, and snapshotted
on each entry when it is saved.

## Architecture

```
app/
├── Actions/            Business logic as single-purpose invokable classes
│                       (TimerStart, TimerStop, TimeEntrySave, HourlyRateResolve, MemberInvite, …)
├── Reports/            ReportBuilder: the one place that filters, scopes and aggregates time
├── Livewire/           One full-page component per screen (Tracker, Dashboard, Reports, Projects, Team, …)
│   └── Concerns/       Shared workspace / date-range behaviour
├── Models/             Workspace, Membership (pivot), Project, Task, Client, Tag, TimeEntry, UserGroup, User
├── Support/            Duration, ClockTime, Money, DateRange, Timezone helpers
└── Http/Middleware/    EnsureCurrentWorkspace
resources/views/        Blade layouts, components (pickers, modal, icons) and Livewire views
resources/js/app.js     Alpine components: live timer, project/tag pickers, charts, theme, toasts
```

- Every domain row is scoped by `workspace_id`; actions verify that projects, tasks and tags belong
  to the workspace and that the actor may touch the entry.
- Members only see their own data in reports unless the workspace allows team reports.
- Project and tag pickers render from one shared Alpine store, so long entry lists stay light.

## Local development

Requirements: PHP 8.3+, Composer, Node 20+. SQLite is used by default.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # demo data
composer run dev                # app on http://localhost:8000 + Vite
```

Demo login: **demo@example.com** / **password** (workspace "Demo Company", five members,
eight weeks of entries).

## Tests

```bash
php artisan test --compact                 # unit + feature tests (SQLite in memory)
php artisan test --compact tests/Browser   # real-browser tests (needs: npx playwright install chromium)
DEPLOYED_URL=https://your-app.onrender.com php artisan test tests/Browser/Deployed   # smoke-test a deployment
```

The suite also passes against PostgreSQL:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=timetrack_test \
DB_USERNAME=postgres DB_PASSWORD=secret php artisan test --compact
```

## Deploying (free): Render + Neon

The repository includes a `Dockerfile` (PHP 8.4 + nginx) and a Render Blueprint (`render.yaml`).
On boot the container runs migrations and caches config, routes and views automatically.

1. **Database — [Neon](https://neon.tech)** (free Postgres): create a project and copy the
   connection string. It looks like
   `postgresql://user:password@ep-xxxx.region.aws.neon.tech/neondb?sslmode=require`.
2. **App — [Render](https://render.com)**: *New → Blueprint*, connect this GitHub repository and
   apply. When prompted, set:
   - `DB_URL` — the Neon connection string
   - `APP_URL` — your Render URL, e.g. `https://timetrack.onrender.com`
3. Wait for the first deploy, open the URL and **Sign up**. The first account becomes the owner of
   its own workspace; invite others from **Team**.

`APP_KEY` is generated by Render. Keep it unchanged: changing it logs everyone out and invalidates
encrypted data.

Notes on the free tiers: Render's free web service sleeps after a period of inactivity, so the first
request afterwards takes up to a minute. Check Render's and Neon's current plan limits before relying
on them.

**Custom domain:** add it under the Render service's *Settings → Custom Domains*, create the DNS
record it shows at your registrar, then update `APP_URL`.

### Alternative: MySQL

The test suite also passes on MySQL 8.4 and TiDB v8.5 (MySQL-compatible, e.g. TiDB Cloud's free
tier). To use MySQL instead of Postgres, change these variables in Render:

| Variable | Value |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_URL` | `mysql://user:password@host:port/database` |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` (for hosts whose TLS certificate is from a public CA, such as TiDB Cloud) |

Hosts that sign connections with their own CA (e.g. Aiven) need that CA file added to the image
and `MYSQL_ATTR_SSL_CA` pointed at it.

### Environment variables

| Variable | Production value |
|---|---|
| `APP_NAME` | `TimeTrack` (shown in the UI and page titles) |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | generated; `base64:…` or a bare 32-byte base64 key |
| `APP_URL` | public HTTPS URL |
| `DB_CONNECTION` / `DB_URL` | `pgsql` / Neon connection string |
| `SESSION_DRIVER`, `CACHE_STORE` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `sync` |

Email delivery is not configured; invitation links are shown in the Team screen to copy and share.

## Licence

MIT. See [LICENSE](LICENSE). Third-party assets are listed in
[THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

TimeTrack is an independent project and is not affiliated with or endorsed by any other
time-tracking product or company.
