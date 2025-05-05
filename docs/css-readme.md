# uwuweb CSS Documentation

## Overview

This stylesheet provides a comprehensive design system for the uwuweb Grade Management System. It's built with a dark theme and includes responsive components, utility classes, and role-based styling for all user types (admin, teacher, student, parent).

## Table of Contents

1. [Design System Variables](#design-system-variables)
2. [Base Elements](#base-elements)
3. [Layout System](#layout-system)
4. [UI Components](#ui-components)
5. [Role-specific Styling](#role-specific-styling)
6. [Status & State Indicators](#status--indicators)
7. [Animations](#animations)
8. [Utility Classes]()
   - [Spacing Utilities](#spacing-utilities)
   - [Display Utilities](#display-utilities)
   - [Flex Utilities](#flex-utilities)
   - [Text Utilities](#text-utilities)
   - [Visual Utilities](#visual-utilities)
9. [Additional UI Elements]()
   - [Alerts](#alerts)
   - [Modals](#modals)
   - [Badges](#badges)
   - [Dark Mode Toggle]()
10. [Responsive Design](#responsive-design)
11. [Accessibility](#accessibility)
12. [Print Styles](#print-styles)

## Design System Variables

The stylesheet uses CSS variables for consistent theming. Key variable categories include:

### Colors

- **Backgrounds**: `--bg-primary` (dark charcoal), `--bg-secondary` (rich gray), `--bg-tertiary` (medium gray)
- **Accent Colors**: `--accent-primary` (vibrant blue), `--accent-secondary` (soft slate), `--accent-tertiary` (lighter blue), `--accent-warning` (amber)
- **Text Colors**: `--text-primary`, `--text-secondary`, `--text-disabled`
- **Borders**: `--border-color-light`, `--border-color-medium`, `--border-color-focus`

### Typography

- **Font Family**: `--font-primary` (Quicksand)
- **Font Sizes**: `--font-size-xs` (12px), `--font-size-sm` (14px), `--font-size-md` (16px), `--font-size-lg` (20px), `--font-size-xl` (24px), `--font-size-xxl` (32px)
- **Font Weights**: `--font-weight-normal` (400), `--font-weight-medium` (500), `--font-weight-bold` (600)

### Spacing

- `--space-xs` (4px)
- `--space-sm` (8px)
- `--space-md` (16px)
- `--space-lg` (24px)
- `--space-xl` (32px)
- `--space-xxl` (48px)

### Component Specifications

- **Cards**: `--card-radius`, `--card-shadow`, `--card-padding`
- **Buttons**: `--button-radius`, `--button-shadow`, `--button-shadow-hover`, `--button-shadow-active`
- **Transitions**: `--transition-fast`, `--transition-normal`, `--transition-slow`
- **Z-index Levels**: Various z-index values for layering

## Base Elements

Basic styling is applied to HTML elements without classes:

- **Typography**: Headings (h1-h6), paragraphs, links
- **Links**: Basic styling with optional `.underline-effect` class for animated underlines
- **Images/Media**: Images have rounded corners (12px)
- **Global Box Model**: All elements use border-box sizing
- **Font Helper**: `.quicksand-font` for explicitly applying the Quicksand font

## Layout System

### Container

- `.container`: Centered content with 1200px max-width
- `.section`: Vertical spacing between major content blocks
- `.dashboard-grid`: CSS Grid for dashboard widgets

### Flex Row/Column System

- `.row`: Flexbox row with negative margin for gutters
- `.col`: Basic column with padding for gutters
- Responsive columns: `.col-md-*`, `.col-lg-*` (see Responsive Design section)

## UI Components

### Cards

- `.card`: Base card with shadow and hover effects
- `.card::before`: Top border highlight on hover (automatic)
- `.card__title`: Card header
- `.card__content`: Card body 
- `.card__footer`: Card footer with top border

### Buttons

- `.btn`: Base button styles
- `.btn::after`: Ripple effect on click (automatic)
- Variants: `.btn-primary`, `.btn-secondary`
- Sizes: `.btn-sm`, `.btn-lg`
- States:
  - `:hover:not(:disabled)`: Hover state when not disabled
  - `:active:not(:disabled)`: Active/pressed state when not disabled
  - `:focus-visible`: Keyboard focus state
  - `:disabled`: Disabled state
- `.btn-icon`: For buttons with icons

### Forms

- `.form-group`: Container for label + input
- `.form-label`: Input label
- `.form-input`: Text inputs
- `.form-select`: Select dropdowns
- `.form-textarea`: Multiline text input
- `.form-input::placeholder`, `.form-textarea::placeholder`: Placeholder text styling
- State classes: `.is-valid`, `.is-invalid`
- Feedback: `.feedback-text`, `.feedback-valid`, `.feedback-invalid`

### Tables

- `.data-table`: Basic table styling
- `.data-table th`: Table header cells
- `.data-table td`: Table data cells
- `.data-table tbody tr:hover td`: Row hover effect
- `.table-responsive`: Wrapper for horizontal scrolling

### Navigation

- `.navbar`: Main navigation bar
- `.navbar-logo`: Site logo/title
- `.navbar-menu`: Navigation link container
- `.navbar-menu.open`: Visible state for mobile menu
- `.navbar-link`: Navigation links with hover effect
- `.navbar-link.active`: Active/current page link styling
- `.navbar-toggle`: Mobile menu toggle
- `.navbar-toggle.active`: Active state for toggle (hamburger to X)

## Alerts

- `.alert`: Base alert component
- `.alert-icon`: Icon container within alert
- `.alert-content`: Text content container
- Alert variants: `.status-success`, `.status-warning`, `.status-error`, `.status-info`
- `.print-alert`: Special class to make an alert printable

### Modals

- `.modal`: Modal container
- `.modal-overlay`: Background overlay
- `.modal-container`: Content container
- `.modal-header`, `.modal-body`, `.modal-footer`: Modal sections
- `.modal-title`: Modal title styling
- `.modal.open`: Visible state
- `.modal.closing`: Closing animation state
- `.btn-close`: Close button

## Badges

- `.badge`: Base badge styles
- Variants:
  - `.badge-primary`: Blue badge
  - `.badge-secondary`: Gray badge
  - `.badge-success`: Green badge
  - `.badge-warning`: Amber badge
  - `.badge-error`: Red badge
  - `.badge-info`: Sky blue badge

## Role-specific Styling

Classes specifically for different user roles:

### Profile Styling

- `.profile-admin`, `.profile-teacher`, `.profile-student`, `.profile-parent`: Border colors for profile elements

### Role Badges

- `.role-badge`: Base role badge
- `.role-admin`, `.role-teacher`, `.role-student`, `.role-parent`: Role-specific badge styles

## Status & Indicators

### Generic Status Indicators

- `.status-indicator`: Base indicator
- `.status-success`, `.status-warning`, `.status-error`, `.status-info`: Status variants

### Attendance Status

- `.attendance-status`: Base attendance indicator
- `.status-present`, `.status-absent`, `.status-late`: Attendance states

### Grade Display

- `.grade`: Base grade display
- `.grade-high`, `.grade-medium`, `.grade-low`: Grade performance levels

## Animations

- `.page-transition`: Fade-in for page transitions
- `.card-entrance`: Slide-up entrance for cards
- `.pulse`: Subtle pulsing effect
- Modal animations: `modalSlideIn`, `modalFadeOut`

## Spacing Utilities

### Margin Utilities

- **Zero Margin**:
  - `.m-0`: All margins 0
  - `.mt-0`: Top margin 0
  - `.mr-0`: Right margin 0
  - `.mb-0`: Bottom margin 0
  - `.ml-0`: Left margin 0
  - `.mx-0`: Horizontal margins 0
  - `.my-0`: Vertical margins 0

- **Tiny (4px)**:
  - `.m-xs`, `.mt-xs`, `.mr-xs`, `.mb-xs`, `.ml-xs`, `.mx-xs`, `.my-xs`

- **Small (8px)**:
  - `.m-sm`, `.mt-sm`, `.mr-sm`, `.mb-sm`, `.ml-sm`, `.mx-sm`, `.my-sm`

- **Medium (16px)**:
  - `.m-md`, `.mt-md`, `.mr-md`, `.mb-md`, `.ml-md`, `.mx-md`, `.my-md`

- **Large (24px)**:
  - `.m-lg`, `.mt-lg`, `.mr-lg`, `.mb-lg`, `.ml-lg`, `.mx-lg`, `.my-lg`

- **Extra Large (32px)**:
  - `.m-xl`, `.mt-xl`, `.mr-xl`, `.mb-xl`, `.ml-xl`, `.mx-xl`, `.my-xl`

- **Auto Margin**:
  - `.m-auto`: All margins auto
  - `.mt-auto`: Top margin auto
  - `.mr-auto`: Right margin auto
  - `.mb-auto`: Bottom margin auto
  - `.ml-auto`: Left margin auto
  - `.mx-auto`: Horizontal margins auto
  - `.my-auto`: Vertical margins auto

### Padding Utilities

- **Zero Padding**:
  - `.p-0`: All padding 0
  - `.pt-0`: Top padding 0
  - `.pr-0`: Right padding 0
  - `.pb-0`: Bottom padding 0
  - `.pl-0`: Left padding 0
  - `.px-0`: Horizontal padding 0
  - `.py-0`: Vertical padding 0

- **Tiny (4px)**:
  - `.p-xs`, `.pt-xs`, `.pr-xs`, `.pb-xs`, `.pl-xs`, `.px-xs`, `.py-xs`

- **Small (8px)**:
  - `.p-sm`, `.pt-sm`, `.pr-sm`, `.pb-sm`, `.pl-sm`, `.px-sm`, `.py-sm`

- **Medium (16px)**:
  - `.p-md`, `.pt-md`, `.pr-md`, `.pb-md`, `.pl-md`, `.px-md`, `.py-md`

- **Large (24px)**:
  - `.p-lg`, `.pt-lg`, `.pr-lg`, `.pb-lg`, `.pl-lg`, `.px-lg`, `.py-lg`

- **Extra Large (32px)**:
  - `.p-xl`, `.pt-xl`, `.pr-xl`, `.pb-xl`, `.pl-xl`, `.px-xl`, `.py-xl`

## Display Utilities

- **Basic Display**:
  - `.d-block`: Display as block
  - `.d-inline-block`: Display as inline-block
  - `.d-inline`: Display as inline
  - `.d-flex`: Display as flex container
  - `.d-inline-flex`: Display as inline flex container
  - `.d-grid`: Display as grid container
  - `.d-none`: Hide element
  
- **Responsive Display** (applied at specific breakpoints):
  - Small devices:
    - `.d-sm-block`: Display as block on small devices
  
  - Medium devices (768px and up):
    - `.d-md-block`: Display as block
    - `.d-md-inline-block`: Display as inline-block
    - `.d-md-flex`: Display as flex container
    - `.d-md-grid`: Display as grid container
    - `.d-md-none`: Hide element
  
  - Large devices (1024px and up):
    - `.d-lg-block`: Display as block
    - `.d-lg-inline-block`: Display as inline-block
    - `.d-lg-flex`: Display as flex container
    - `.d-lg-grid`: Display as grid container
    - `.d-lg-none`: Hide element

## Flex Utilities

- **Direction**: 
  - `.flex-row`, `.flex-row-reverse`
  - `.flex-column`, `.flex-column-reverse`
  
- **Wrapping**:
  - `.flex-wrap`, `.flex-nowrap`
  
- **Growth/Shrink**:
  - `.flex-grow-1`, `.flex-shrink-0`
  
- **Alignment**:
  - Vertical: `.items-start`, `.items-end`, `.items-center`, `.items-baseline`, `.items-stretch`
  - Horizontal: `.justify-start`, `.justify-end`, `.justify-center`, `.justify-between`, `.justify-around`, `.justify-evenly`
  
- **Spacing**:
  - `.gap-xs`, `.gap-sm`, `.gap-md`, `.gap-lg`, `.gap-xl`

## Text Utilities

- **Alignment**:
  - `.text-left`, `.text-center`, `.text-right`
  
- **Color**:
  - `.text-primary`, `.text-secondary`, `.text-disabled`
  - `.text-accent`, `.text-warning`, `.text-success`, `.text-error`
  
- **Size**:
  - `.text-xs`, `.text-sm`, `.text-md`, `.text-lg`, `.text-xl`
  
- **Weight**:
  - `.font-normal`, `.font-medium`, `.font-bold`
  
- **Formatting**:
  - `.text-uppercase`
  - `.text-nowrap`

## Visual Utilities

- **Border Radius**:
  - `.rounded-sm`: 8px border radius
  - `.rounded`: Button-radius border radius
  - `.rounded-lg`: Card-radius border radius
  - `.rounded-full`: Circular/pill shape (9999px)
  
- **Shadows**:
  - `.shadow-sm`: Small shadow
  - `.shadow`: Medium shadow
  - `.shadow-lg`: Large shadow
  - `.shadow-none`: No shadow
  
- **Opacity**:
  - `.opacity-75`: 75% opacity
  - `.opacity-50`: 50% opacity
  - `.opacity-25`: 25% opacity
  - `.opacity-0`: 0% opacity (invisible)

## Responsive Design

The stylesheet uses a mobile-first approach with breakpoints:

- **Mobile** (base): Up to 767px
- **Tablet**: 768px and up
- **Desktop**: 1024px and up
- **Large Desktop**: 1440px and up

### Responsive Features

- Mobile navigation with hamburger menu (toggles `.navbar-menu.open`)
- Stacking columns on mobile with responsive column classes
- Display utilities that apply at specific breakpoints
- `.row-md` for rows that stack on mobile but display as rows on tablet+

### Responsive Column Classes

- `.col-md-*`: Applied at 768px and up (tablet)
  - Available classes: `.col-md-3`, `.col-md-4`, `.col-md-6`, `.col-md-8`, `.col-md-9`, `.col-md-12`
- `.col-lg-*`: Applied at 1024px and up (desktop)
  - Available classes: `.col-lg-2`, `.col-lg-3`, `.col-lg-4`, `.col-lg-6`, `.col-lg-8`, `.col-lg-9`, `.col-lg-10`, `.col-lg-12`

### Responsive Display Utilities

- Small screens: `.d-sm-block`
- Medium screens: `.d-md-block`, `.d-md-inline-block`, `.d-md-flex`, `.d-md-grid`, `.d-md-none`
- Large screens: `.d-lg-block`, `.d-lg-inline-block`, `.d-lg-flex`, `.d-lg-grid`, `.d-lg-none`

## Accessibility

- `.sr-only`: Visually hidden but screen reader accessible text
- `:focus-visible` styling for keyboard navigation
- Adequate color contrast in the dark theme
- Scrollbar styling:
  - `::-webkit-scrollbar`
  - `::-webkit-scrollbar-track`
  - `::-webkit-scrollbar-thumb`
  - `::-webkit-scrollbar-thumb:hover`

## Print Styles

Special styles for printed pages:

- Color adjustments for print legibility
- Hiding of non-essential UI elements
- Page break controls for headings and content blocks
- URL display for links
- Legible font sizes for print
