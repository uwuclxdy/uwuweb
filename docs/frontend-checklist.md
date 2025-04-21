# Frontend Implementation Checklist

## Core Assets
- [ ] `/assets/css/style.css` - Main stylesheet (mobile-first, responsive design)
- [ ] `/assets/js/main.js` - Core JavaScript functions

## Common Components
- [ ] `/includes/header.php` - Common header with navigation
- [ ] `/includes/footer.php` - Common footer

## Dashboard
- [ ] `/dashboard.php` - Main dashboard container
- [ ] Role-specific dashboard widgets:
    - [ ] Admin widgets (user stats, system status, school attendance, class averages)
    - [ ] Teacher widgets (class overview, attendance, pending justifications, class averages)
    - [ ] Student widgets (grades, attendance, upcoming classes, class averages)
    - [ ] Parent widgets (child grades, attendance, class averages)

## Admin Pages
- [ ] `/admin/users.php` - User management
- [ ] `/admin/settings.php` - System settings
- [ ] `/admin/class_subjects.php` - Class-subject management

## Teacher Pages
- [ ] `/teacher/gradebook.php` - Grade management interface
- [ ] `/teacher/attendance.php` - Attendance tracking interface
- [ ] `/teacher/justifications.php` - Justification approval interface

## Student Pages
- [ ] `/student/grades.php` - Student grades view
- [ ] `/student/attendance.php` - Student attendance view
- [ ] `/student/justification.php` - Justification submission interface

## Parent Pages
- [ ] `/parent/grades.php` - Child grades view
- [ ] `/parent/attendance.php` - Child attendance view

## Implementation Order Recommendation
1. Start with core CSS and JS files
2. Implement common components (header, footer)
3. Create the dashboard layout and widgets
4. Build role-specific pages

## Key Requirements
- Follow all the instructions from project-outline.md
- Mobile-first responsive design
- Only HTML, CSS, and vanilla JavaScript
- Flexbox/Grid layout
- CSS variables for theming
- Progressive enhancement
- Form validation (HTML5 + JS)
- Fetch API for AJAX requests
- BEM naming convention for CSS
- I previously had in mind to have "terms" but I changed my mind, so remove any mention of "terms" from the code and documentation if you find it.
