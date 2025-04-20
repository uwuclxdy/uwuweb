# uwuweb CSS Documentation

This document provides a structured overview of the CSS assets used in the uwuweb Grade Management System and Šolski center Celje design system. It is organized so that an AI system can parse component definitions, design tokens, and role-specific layouts effectively.

## CSS Files Inventory

The uwuweb project contains the following CSS files:

- **assets/css/style.css** - Main stylesheet with global variables, reset styles, and common components
- **assets/css/advanced-components.css** - Role-specific layouts and specialized components
- **assets/css/login.css** - Login page specific styles
- **assets/css/parent-attendance.css** - Parent attendance view styles
- **assets/css/parent-grades.css** - Parent grades view styles
- **assets/css/student-attendance.css** - Student attendance view styles
- **assets/css/student-grades.css** - Student grades view styles
- **assets/css/student-justification.css** - Student absence justification page styles
- **assets/css/teacher-attendance.css** - Teacher attendance management styles
- **assets/css/teacher-gradebook.css** - Teacher gradebook styles
- **assets/css/teacher-justifications.css** - Teacher justification review styles

---

## 1. Design Tokens & Variables

**File Reference:** Core theme variables in `style.css` and Šolski center variables.

- **Color Palette** (`--sc-primary`, `--sc-gray`, extended shades `--sc-primary-100` through `--sc-primary-900`, `--sc-gray-100` through `--sc-gray-900`). citeturn0file1
- **Semantic Colors** (`--accent-primary`, `--accent-secondary`, `--accent-tertiary`, `--accent-success`, `--accent-warning`, `--accent-error`, `--accent-info`). citeturn0file1
- **Backgrounds** (`--bg-base`, `--bg-surface`, `--bg-primary`, `--bg-secondary`, `--bg-tertiary`, `--bg-elevated`). citeturn0file1
- **Typography** (`--font-heading`, `--font-primary`, scale `--font-size-xs` to `--font-size-xxxl`, line-heights, letter-spacing). citeturn0file1
- **Spacing Scale** (`--space-3xs` to `--space-3xl`). citeturn0file1
- **Border Radius** (`--radius-sm` to `--radius-full`, `--card-radius`). citeturn0file1
- **Shadows** (`--shadow-xs` to `--shadow-xl`, `--card-shadow`). citeturn0file1
- **Transitions & Easing** (`--transition-fast`, `--transition-normal`, `--transition-slow`, `--ease-*`). citeturn0file1

---

## 2. Core Components

**File Reference:** Advanced components and core framework in `advanced-components.css` and `style.css`.

### 2.1 Card (`.card`, `.card-compact`, `.card-expanded`)
- Container with background, radius, shadow.
- States: `.is-selected`, `.is-disabled`.
- Usage: `<div class="card [variant] [state]">...</div>`.

### 2.2 Button (`.btn`, variants `.btn-primary`, `.btn-secondary`, `.btn-tertiary`, `.btn-error`)
- Base styling, padding, border-radius, shadow.
- States: `.is-loading` (spinner), `.is-disabled`.
- Usage: `<button class="btn btn-primary">Label</button>`.

### 2.3 Form Elements (`.form-input`, `.form-select`, `.form-textarea`)
- Variants for inputs, selects, textareas.
- Validation states: `.has-error`, `.has-success`.
- Usage: `<input class="form-input has-error">`.

### 2.4 Table (`.data-table`, `.data-table-responsive`)
- Responsive tables with sticky headers.
- Sorting: `.is-sortable`, `.is-sorted`.

### 2.5 Navigation (`.navbar`, `.sidebar`, `.mobile-nav`)
- Stateful: `.is-active`, `.is-expanded`.
- Tab container: `.tab-container`, `.tab-button`, `.tab-content`.

### 2.6 Alert / Notification (`.alert`, variants `.alert-success`, `.alert-warning`, `.alert-error`)
- Dismissible: `.is-dismissible`.

### 2.7 Modal (`.modal-overlay`, `.modal`, `.is-active`)
- Overlay with focus isolation.

### 2.8 Calendar & Dates
- Calendar grid: `.calendar`, `.calendar-day`, states `.today`, `.is-selected`, `.outside-month`.
- Indicators: `.day-indicator`, status colors.
- Detail views: `.calendar-event`, event types.

### 2.9 Charts
- Containers: `.chart-container`, `.bar-chart`, `.line-chart`, `.donut-chart`.
- Legend: `.chart-legend`, `.legend-item`.
- Interactive states: `.is-interactive`.

---

## 3. Role-Specific Layouts

**File References:** Role styles in `admin`, `teacher-*`, `student-*`, `parent-*` CSS.

### 3.1 Administrator
- Grid dashboard: `.admin-dashboard`.
- User management: `.user-management`, `.filter-bar`, `.search-group`.
- Analytics: `.metric-card`, `.metric-value`, `.metric-change`.
- Timeline: `.term-timeline`, `.timeline-item`, states `.active`, hover.

### 3.2 Teacher
- Gradebook: `.gradebook-container`, `.gradebook-table`, `.grade-input`, inline editing states.
- Attendance: `.attendance-calendar`, day indicators.
- Justifications: `.justifications-container`, modals, status classes. citeturn0file2

### 3.3 Student
- Grade overview: `.grade-overview`, `.subject-card`, progress bar `.progress-fill`, grade classification colors.
- Attendance: `.attendance-summary`, `.attendance-item`, status classes. citeturn0file7

### 3.4 Parent
- Multi-student selector: `.student-selector`, `.student-option`.
- Consolidated grade view: `.consolidated-grades`, badges, notification badge.
- Attendance: `.attendance-container`, summary cards. citeturn0file9

---

## 4. File Upload Components

**File Reference:** Core in `advanced-components.css`.

- Upload area: `.file-upload`, drag & drop states.
- Uploaded file list: `.uploaded-file`, `.file-icon`, progress bars.

---

## 5. Utility & Helper Classes

- **Resets & Base**: `*`, `html`, `body`, `h1–h6`, `p`, `a`, `img` resets. citeturn0file1
- **Layout Helpers**: `.container`, `.dashboard-grid`, responsive media queries.
- **Typography Helpers**: text align, truncation, ellipsis.
- **Visibility & Animation**: `.is-active`, `.is-loading`, keyframe animations like `highlightBackground`.

---

### Parsing Instructions for AI

1. **Token Extraction:** Map all `--variable` definitions into a key–value object for theme lookup.
2. **Component Registry:** Build a registry of class selectors and their descriptions under sections (Core, Role-specific).
3. **State Recognition:** Associate state classes (e.g., `.is-disabled`, `.active`, `.modal-active`) with toggles in UI logic.
4. **Variant Resolution:** Link base component with variant modifiers (e.g., `.btn` + `.btn-primary`).
5. **Responsive Breakpoints:** Note media query ranges (`max-width: 768px`) for mobile adjustments.
6. **Hierarchy & Nesting:** Preserve nesting contexts (e.g., `.navbar .navbar-menu.active`).

---
