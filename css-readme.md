# uwuweb CSS Elements Reference

## CSS Variables

### Colors
- `--bg-primary`: Main background color
- `--bg-secondary`: Card backgrounds, secondary elements
- `--bg-tertiary`: Form inputs, elevated elements
- `--accent-primary`: Primary buttons, links, focus states
- `--accent-secondary`: Secondary actions, highlights
- `--accent-tertiary`: Tertiary elements, decorative accents
- `--accent-warning`: Warnings, notifications
- `--text-primary`: Main text color
- `--text-secondary`: Secondary text color
- `--text-disabled`: Disabled text color

### Typography
- `--font-primary`: Primary font family
- `--font-size-xs`: Extra small text (12px)
- `--font-size-sm`: Small text (14px)
- `--font-size-md`: Medium text (16px)
- `--font-size-lg`: Large text (20px)
- `--font-size-xl`: Extra large text (24px)
- `--font-size-xxl`: Extra-extra large text (32px)
- `--font-weight-normal`: Normal font weight (400)
- `--font-weight-medium`: Medium font weight (500)
- `--font-weight-bold`: Bold font weight (700)

### Spacing
- `--space-xs`: Extra small spacing (4px)
- `--space-sm`: Small spacing (8px)
- `--space-md`: Medium spacing (16px)
- `--space-lg`: Large spacing (24px)
- `--space-xl`: Extra large spacing (32px)
- `--space-xxl`: Extra-extra large spacing (48px)

### Component Variables
- `--card-radius`: Border radius for cards
- `--card-shadow`: Shadow for cards
- `--card-padding`: Padding inside cards
- `--button-radius`: Border radius for buttons
- `--button-shadow`: Shadow for buttons
- `--button-shadow-hover`: Shadow for buttons on hover
- `--button-shadow-active`: Shadow for buttons when active
- `--transition-fast`: Fast transition speed (150ms)
- `--transition-normal`: Normal transition speed (250ms)
- `--transition-slow`: Slow transition speed (350ms)

## Core Components

### Layout
- `.dashboard-grid`: Grid layout for dashboard cards

### Cards
- `.card`: Basic card component
- `.card-entrance`: Animation for card entrance

### Buttons
- `.btn`: Basic button
- `.btn-primary`: Primary action button
- `.btn-secondary`: Secondary action button

### Forms
- `.form-group`: Container for form elements
- `.form-input`: Input fields
- `.form-label`: Labels for form elements

### Tables
- `.data-table`: Table for displaying data
- `.data-table th`: Table headers
- `.data-table td`: Table cells

### Navigation
- `.navbar`: Top navigation bar
- `.navbar-logo`: Logo in navigation
- `.navbar-menu`: Navigation menu
- `.navbar-link`: Navigation links
- `.navbar-toggle`: Mobile navigation toggle

### Role Indicators
- `.profile-admin`: Admin profile indicator
- `.profile-teacher`: Teacher profile indicator
- `.profile-student`: Student profile indicator
- `.profile-parent`: Parent profile indicator

## State Indicators

### Status
- `.status-success`: Success status
- `.status-warning`: Warning status
- `.status-error`: Error status
- `.status-info`: Information status

### Form States
- `.is-valid`: Valid form input
- `.is-invalid`: Invalid form input
- `.feedback-text`: Feedback text for forms
- `.feedback-invalid`: Invalid feedback text

### Attendance Status
- `.attendance-status`: Attendance status container
- `.status-present`: Present status
- `.status-absent`: Absent status
- `.status-late`: Late status

### Grade Display
- `.grade`: Grade display container
- `.grade-high`: High grade
- `.grade-medium`: Medium grade
- `.grade-low`: Low grade

## Utility Classes

### Margin Utilities
- `.mt-*`: Margin top (xs, sm, md, lg, xl)
- `.mb-*`: Margin bottom (xs, sm, md, lg, xl)
- `.ml-*`: Margin left (xs, sm, md, lg, xl)
- `.mr-*`: Margin right (xs, sm, md, lg, xl)

### Display Utilities
- `.d-flex`: Display flex
- `.d-grid`: Display grid
- `.d-none`: Display none

### Flex Utilities
- `.flex-row`: Flex direction row
- `.flex-column`: Flex direction column
- `.items-center`: Align items center
- `.justify-between`: Justify content space between
- `.justify-center`: Justify content center
- `.gap-*`: Gap (sm, md, lg)

### Text Utilities
- `.text-center`: Text align center
- `.text-right`: Text align right
- `.text-primary`: Text color primary
- `.text-secondary`: Text color secondary
- `.text-disabled`: Text color disabled

## Animations
- `.page-transition`: Fade in transition for pages
- `@keyframes fadeIn`: Fade in animation
- `@keyframes slideUp`: Slide up animation