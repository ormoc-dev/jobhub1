# Employee Dashboard – UI Color Design & Layout

Layout matches the reference dashboard (employer-style): **Site Overview** KPIs, **Daily Application Trends**, **Site Application Status** (donut), **Application Funnel**, **Site Job Match Summary** (monthly chart), plus Quick Actions & Recent Applications.

---

## Section labels

| Section | Label | Icon |
|--------|--------|------|
| KPI cards | **System Overview** | `fa-th-large` |
| User stats row | **User Statistics** | `fa-user-circle` |
| Charts block | **Job & Application Metrics** | `fa-chart-bar` |
| Donut chart | **Site Application Status** | `fa-tasks` |
| Monthly chart | **Site Job Match Summary** | `fa-chart-pie` |

---

## UI color palette

| Role | Variable | Hex | Usage |
|------|----------|-----|--------|
| **Background** | `--dash-bg` | `#f1f5f9` | Main content area |
| **Surface** | `--dash-surface` | `#ffffff` | Cards, panels |
| **Border** | `--dash-border` | `#e2e8f0` | Card borders, dividers |
| **Text** | `--dash-text` | `#0f172a` | Headings, body |
| **Text muted** | `--dash-text-muted` | `#64748b` | Secondary text |
| **Primary** | `--dash-primary` | `#4f46e5` | Section titles, CTAs |
| **Primary light** | `--dash-primary-light` | `#818cf8` | Icons, hover |
| **Primary dark** | `--dash-primary-dark` | `#3730a3` | Button hover |
| **Accent** | `--dash-accent` | `#0d9488` | Progress, highlights |
| **Success** | `--dash-success` | `#059669` | Progress bar end |
| **Warning** | `--dash-warning` | `#d97706` | Alerts |
| **Info** | `--dash-info` | `#0284c7` | Info states |
| **Danger** | `--dash-danger` | `#dc2626` | Errors, rejected |

### KPI stat card gradients (base: employee's own data)

| Card | Gradient | Description |
|------|----------|-------------|
| My Applications This Week | `#3b82f6` → `#1e40af` (blue) | Applications I sent this week |
| My Applications This Month | `#8b5cf6` → `#6d28d9` (purple) | My applications this month |
| My Acceptance Rate | `#ec4899` → `#be185d` (pink) | X of my applications accepted |
| My Avg per Job | `#f59e0b` → `#d97706` (orange) | My applications per job applied |

---

## UI layout

```
┌─────────────────────────────────────────────────────────────────┐
│  Welcome back, [Name]!         [Browse Jobs] [Profile Status]    │
└─────────────────────────────────────────────────────────────────┘

┌─ System Overview (base: employee) ──────────────────────────────┐
│  [My Apps This Week] [My Apps This Month] [My Acceptance] [Avg] │  ← 4 KPI cards
└─────────────────────────────────────────────────────────────────┘

┌─ User Statistics ───────────────────────────────────────────────┐
│  [Profile Completion] [Saved Jobs] [Active Jobs]                │  ← 3 user stat cards
└─────────────────────────────────────────────────────────────────┘

┌─ Job & Application Metrics ─────────────────────────────────────┐
│  Daily Application Trends    │  Site Application Status (donut) │
│  Application Funnel (line)   │  Site Job Match Summary (line)   │
└─────────────────────────────────────────────────────────────────┘

┌─ Quick Actions ─────────────────┐  ┌─ Recent Applications ───────┐
│  Browse | Profile | Apps | Saved│  │  List + View All            │
└─────────────────────────────────┘  └─────────────────────────────┘
```

### Layout rules

- **System Overview**: Four KPI cards (blue, purple, pink, orange) — basis: employee's own activity. "My Applications This Week", "My Applications This Month", "My Acceptance Rate", "My Avg per Job". Subtitle: "Based on your activity as an employee".
- **User Statistics**: Three cards — Profile Completion %, Saved Jobs, Active Jobs (with Update Profile / View Saved / Browse Jobs).
- **Job & Application Metrics**: Four chart cards — Daily Trends (bar), Site Application Status (donut), Application Funnel (line), Site Job Match Summary (line).
- **Charts**: Chart.js — bar (daily), doughnut (response rate), line (funnel), line (monthly). Chart containers use `chart-container` (height 200px).
- **Buttons**: `btn-dash-primary` (blue gradient), `btn-dash-outline` (green border).
- **Responsive**: Stat cards and chart rows stack on small screens.

### Files

- **Markup**: `employee/dashboard.php`
- **Styles**: `employee/css/dashboard.css`
- **Global**: `assets/style.css` (base + employee-layout)
