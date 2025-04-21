# uwuweb CSS Elements Reference (Updated)

## CSS Variables

### Colors
- `--bg-primary`: Main background color (#121212)
- `--bg-secondary`: Card backgrounds, secondary elements (#1e1e1e)
- `--bg-tertiary`: Form inputs, elevated elements (#2a2a2a)
- `--accent-primary`: Primary buttons, links, focus states (#00457c)
- `--accent-secondary`: Secondary actions, highlights (#95a0a9)
- `--accent-tertiary`: Tertiary elements, decorative accents (#2d7db3)
- `--accent-warning`: Warnings, notifications (#e67e22)
- `--text-primary`: Main text color (rgba(255, 255, 255, 0.92))
- `--text-secondary`: Secondary text color (rgba(255, 255, 255, 0.7))
- `--text-disabled`: Disabled text color (rgba(255, 255, 255, 0.45))

### Typography
- `--font-primary`: Primary font family ('Utsaah Bold', 'Segoe UI', 'Arial', sans-serif)
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
- `--card-radius`: Border radius for cards (18px)
- `--card-shadow`: Shadow for cards
- `--card-padding`: Padding inside cards
- `--button-radius`: Border radius for buttons (10px)
- `--button-shadow`: Shadow for buttons
- `--button-shadow-hover`: Shadow for buttons on hover
- `--button-shadow-active`: Shadow for buttons when active
- `--transition-fast`: Fast transition speed (180ms cubic-bezier)
- `--transition-normal`: Normal transition speed (250ms cubic-bezier)
- `--transition-slow`: Slow transition speed (350ms cubic-bezier)

## Core Components

### Layout
- `.container`: Container with responsive padding
- `.dashboard-grid`: Grid layout for dashboard cards
- `.section`: Vertical section with bottom margin
- `.row`: Flex row container with wrap
- `.col`: Basic column (various responsive variants available)

### Cards
- `.card`: Enhanced card component with hover effects
- `.card::before`: Top border accent on hover
- `.card__title`: Card title with accent color
- `.card__content`: Card content area
- `.card__footer`: Optional footer with top border
- `.card-entrance`: Animation for card entrance

### Buttons
- `.btn`: Basic button with ripple effect
- `.btn-primary`: Primary action button with gradient
- `.btn-secondary`: Secondary action button
- `.btn-sm`: Small button variant
- `.btn-lg`: Large button variant
- `.btn-icon`: Space for button icon

### Forms
- `.form-group`: Container for form elements
- `.form-input`: Enhanced input fields with focus effects
- `.form-label`: Labels with focus state
- `.form-select`: Custom select dropdowns

### Tables
- `.data-table`: Enhanced table for displaying data
- `.data-table th`: Sticky table headers
- `.data-table td`: Table cells with hover effect
- `.table-responsive`: Container for responsive tables

### Navigation
- `.navbar`: Enhanced top navigation bar
- `.navbar-logo`: Logo in navigation
- `.navbar-menu`: Navigation menu with mobile states
- `.navbar-link`: Navigation links with underline effect
- `.navbar-toggle`: Improved mobile navigation toggle

### Role Indicators
- `.profile-admin`: Admin profile indicator
- `.profile-teacher`: Teacher profile indicator
- `.profile-student`: Student profile indicator
- `.profile-parent`: Parent profile indicator
- `.role-badge`: Badge for role display
- `.role-admin`, `.role-teacher`, `.role-student`: Role-specific badges

## State Indicators

### Status
- `.status-success`: Success status with icon
- `.status-warning`: Warning status with icon
- `.status-error`: Error status with icon
- `.status-info`: Information status with icon

### Form States
- `.is-valid`: Valid form input
- `.is-invalid`: Invalid form input
- `.feedback-text`: Feedback text for forms
- `.feedback-invalid`: Invalid feedback text

### Attendance Status
- `.attendance-status`: Improved attendance status container
- `.status-present`: Present status
- `.status-absent`: Absent status
- `.status-late`: Late status

### Grade Display
- `.grade`: Enhanced grade display container
- `.grade-high`: High grade with background
- `.grade-medium`: Medium grade with background
- `.grade-low`: Low grade with background

## New Components

### Alerts
- `.alert`: Enhanced alert component with icons
- `.alert.status-success`: Success alert
- `.alert.status-warning`: Warning alert
- `.alert.status-error`: Error alert
- `.alert.status-info`: Info alert

### Modals
- `.modal`: Modal container with animation
- `.modal-overlay`: Background overlay with blur
- `.modal-container`: Content container
- `.modal-header`: Modal header section
- `.modal-body`: Modal content area
- `.modal-footer`: Modal action area
- `.btn-close`: Close button

### Badges
- `.badge`: Base badge component
- `.badge-primary`: Primary color badge
- `.badge-secondary`: Secondary color badge
- `.badge-success`: Success badge
- `.badge-warning`: Warning badge
- `.badge-error`: Error badge

## Animations
- `.page-transition`: Fade in transition for pages
- `.card-entrance`: Slide up animation for cards
- `.pulse`: Pulsing animation effect
- `@keyframes fadeIn`: Fade in animation
- `@keyframes slideUp`: Slide up animation
- `@keyframes pulse`: Pulse animation
- `@keyframes modalSlideIn`: Modal entrance animation

## Utility Classes

### Spacing Utilities
- `.mt-*`: Margin top (0, xs, sm, md, lg, xl)
- `.mb-*`: Margin bottom (0, xs, sm, md, lg, xl)
- `.ml-*`: Margin left (0, xs, sm, md, lg, xl)
- `.mr-*`: Margin right (0, xs, sm, md, lg, xl)
- `.m-0`: No margin
- `.p-0`: No padding
- `.p-*`: Padding (xs, sm, md, lg, xl)

### Display Utilities
- `.d-flex`: Display flex
- `.d-grid`: Display grid
- `.d-none`: Display none
- `.d-block`: Display block
- `.d-inline-block`: Display inline-block
- `.d-md-none`, `.d-md-block`: Responsive display variants
- `.d-lg-none`, `.d-lg-block`: Responsive display variants

### Flex Utilities
- `.flex-row`: Flex direction row
- `.flex-column`: Flex direction column
- `.flex-wrap`: Flex wrap
- `.flex-nowrap`: Flex nowrap
- `.items-center`: Align items center
- `.items-start`: Align items flex-start
- `.items-end`: Align items flex-end
- `.justify-between`: Justify content space between
- `.justify-center`: Justify content center
- `.justify-start`: Justify content flex-start
- `.justify-end`: Justify content flex-end
- `.gap-*`: Gap (xs, sm, md, lg)

### Text Utilities
- `.text-center`: Text align center
- `.text-right`: Text align right
- `.text-left`: Text align left
- `.text-primary`: Text color primary
- `.text-secondary`: Text color secondary
- `.text-disabled`: Text color disabled
- `.text-accent`: Text with accent color
- `.text-sm`, `.text-md`, `.text-lg`: Text size utilities
- `.text-bold`: Bold text

### Visual Utilities
- `.rounded`: Add border radius
- `.shadow-sm`: Small shadow
- `.shadow`: Medium shadow
- `.shadow-lg`: Large shadow
- `.opacity-75`: 75% opacity
- `.opacity-50`: 50% opacity

## Responsive Features

### Mobile Navigation
- Hamburger menu with animations
- Full-screen overlay navigation for mobile devices
- Optimized mobile touch targets (44px minimum)

### Responsive Grid
- Collapsing multi-column layouts on small screens
- Responsive typography scaling
- Stack elements on mobile with `.row-md`

### Responsive Utility Classes
- `.col-md-*`: Medium screen column widths
- `.col-lg-*`: Large screen column widths
- Responsive display utilities for different breakpoints

### Media Queries
- Small mobile (max-width: 375px)
- Mobile (max-width: 767px)
- Tablet (min-width: 768px)
- Desktop (min-width: 1024px)
- Large desktop (min-width: 1440px)

## Special Features
- Dark mode toggle button
- Custom scrollbar styling
- Print-specific styles
- Link hover effects with transitions
- Card hover effects with top border accent
- Button ripple effects