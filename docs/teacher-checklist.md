# Teacher View Implementation Checklist

IMPORTANT:
Make sure to read `docs/project-outline.md` and `docs/project-functions.md` in full before starting on this checklist.
When working on frontend (HMTL) you must read `docs/modal-guidelines.md`, `docs/css-readme.md` and
`docs/alert-guidelines.md` as well. This document outlines the necessary steps to implement the teacher view in the
application. Each item should be checked off as it is completed. If you are unsure about any item, you must stop your
work and ask for clarification.

## 1. Language Requirements

- [ ] Replace all English text with Slovenian equivalents across all teacher files
- [ ] Check and ensure consistent Slovenian terminology for UI elements
- [ ] Update all button labels, form fields, and status indicators to Slovenian
- [ ] Update page titles and section headings to Slovenian

## 2. Core Functionality

- [ ] Create `download_justification.php` with proper security validation
- [ ] Fix error handling for file operations in justifications.php
- [ ] Complete the file size formatting logic in proper functions file
- [ ] Ensure consistent error handling across all teacher files

## 3. Modal Implementations

- [ ] Remove all instances of `<button class="btn-close">&times;</button>`
- [ ] Replace with standard footer "Cancel" button per guidelines
- [ ] Update Add Period modal to follow modal-guidelines.md structure
- [ ] Implement proper deletion confirmation modal per guidelines
- [ ] Fix modal overlay click behavior to follow standards
- [ ] Update rejection modal in justifications.php

## 4. Alert Implementations

- [ ] Restructure all alerts to include required `.alert-icon` and `.alert-content`
- [ ] Apply proper alert styling across all teacher pages
- [ ] Use correct alert statuses (success, error, warning, info)
- [ ] Implement standardized alert creation functions

## 5. Code Organization

- [ ] Move all inline JavaScript to `/assets/js/main.js`
- [ ] Create shared tab-switching functionality
- [ ] Move all inline CSS to central stylesheet
- [ ] Move helper function `formatFileSize()` to appropriate functions file
- [ ] Standardize API calls and form submissions across files

## 6. File-Specific Fixes

### attendance.php

- [ ] Replace JavaScript `confirm()` with proper modal dialog
- [ ] Implement JavaScript event handling for attendance status changes

### gradebook.php

- [ ] Remove duplicate tab management code
- [ ] Standardize form submission handling
- [ ] Fix grade item deletion functionality

### justifications.php

- [ ] Add proper security validation for file download
- [ ] Fix student attendance record display

## 7. Final Verification

- [ ] Test all functionality using test data
- [ ] Verify consistent styling across all teacher pages
- [ ] Ensure all requirements from project-outline.md are met
- [ ] Check compatibility with API endpoints
- [ ] Validate code against coding standards
