# uwuweb Alert Implementation Guide

This guide explains how to properly implement and animate alerts in the uwuweb Grade Management System using the
predefined CSS classes. Follow these guidelines for consistent, accessible, and visually appealing alerts.

## Alert Structure

Alerts should follow this HTML structure:

```html

<div class="alert status-[type]">
    <div class="alert-icon">
        <!-- Icon here (optional) -->
    </div>
    <div class="alert-content">
        <strong>Alert Title</strong>
        <p>Alert message with details about the action or information.</p>
    </div>
</div>
```

## Status Variants

Use one of these status variant classes with the base `.alert` class:

- `.status-success`: Green alert for successful operations
- `.status-warning`: Amber alert for warnings and cautions
- `.status-error`: Red alert for errors and critical issues
- `.status-info`: Sky blue alert for informational messages

## Adding Animations

Combine alert components with animation classes for dynamic behavior:

- `.page-transition`: Fade-in effect (use for alerts that appear on page load)
- `.card-entrance`: Slide-up entrance (use for alerts appearing after user action)
- `.pulse`: Subtle pulsing effect (use for important alerts that need attention)

Example:

```html

<div class="alert status-success card-entrance">
    <!-- Alert content -->
</div>
```

## Programmatically Creating Alerts

Use this JavaScript function to dynamically create alerts:

```javascript
/**
 * Creates and inserts an alert message
 * @param {string} message - The alert message
 * @param {string} type - Alert type: 'success', 'warning', 'error', 'info'
 * @param {string} container - Selector for container element
 * @param {boolean} autoRemove - Whether to automatically remove the alert
 * @param {number} duration - Time in ms before auto-removing (default: 5000ms)
 */
function createAlert(message, type = 'info', container = '.alert-container', autoRemove = true, duration = 5000) {
    // Create alert elements
    const alertElement = document.createElement('div');
    alertElement.className = `alert status-${type} card-entrance`;

    const iconElement = document.createElement('div');
    iconElement.className = 'alert-icon';

    // Use appropriate icon based on type (these would be SVG or font icons)
    let iconContent = '';
    switch (type) {
        case 'success':
            iconContent = '✓';
            break;
        case 'warning':
            iconContent = '⚠';
            break;
        case 'error':
            iconContent = '✕';
            break;
        case 'info':
        default:
            iconContent = 'ℹ';
            break;
    }
    iconElement.innerHTML = iconContent;

    const contentElement = document.createElement('div');
    contentElement.className = 'alert-content';
    contentElement.innerHTML = message;

    // Assemble alert
    alertElement.appendChild(iconElement);
    alertElement.appendChild(contentElement);

    // Insert into DOM
    const containerElement = document.querySelector(container);
    containerElement.appendChild(alertElement);

    // Auto-remove if needed
    if (autoRemove) {
        setTimeout(() => {
            alertElement.classList.add('closing');
            setTimeout(() => {
                containerElement.removeChild(alertElement);
            }, 300); // Match this timing with transition duration in CSS
        }, duration);
    }

    return alertElement;
}
```

Make sure to have an alert container element in your HTML:

```html

<div class="alert-container"></div>
```

## Common Implementation Mistakes

1. **Missing container structure**: Always use both `.alert-icon` and `.alert-content` for proper layout.
2. **Incorrect class combinations**: Don't mix status classes (use only one per alert).
3. **Animation overuse**: Limit animations to one per alert for better performance.
4. **No dismissal functionality**: Long-lasting alerts should have a dismissal option.
5. **Position conflicts**: Ensure alerts don't obscure critical UI elements.

## PHP Function for Server-Side Alerts

Implement this function to generate alerts from PHP:

```php
/**
 * Generates HTML for an alert message
 * @param string $message The alert message
 * @param string $type Alert type: 'success', 'warning', 'error', 'info'
 * @param bool $animate Whether to add animation class
 * @param string $animation Animation class to use
 * @return string HTML for the alert
 */
function generateAlert(string $message, string $type = 'info', bool $animate = true, string $animation = 'card-entrance'): string {
    $animClass = $animate ? ' ' . $animation : '';
    $iconMap = [
        'success' => '✓',
        'warning' => '⚠',
        'error' => '✕',
        'info' => 'ℹ',
    ];

    $icon = $iconMap[$type] ?? $iconMap['info'];

    return <<<HTML
    <div class="alert status-{$type}{$animClass}">
        <div class="alert-icon">{$icon}</div>
        <div class="alert-content">{$message}</div>
    </div>
    HTML;
}
```

## Example Usage Scenarios

1. **Form submission feedback**:
    - Success alert when data is saved
    - Error alert when validation fails

2. **System notifications**:
    - Warning alert for maintenance mode
    - Info alerts for new features

3. **Time-sensitive alerts**:
    - Attendance recording reminders
    - Session timeout warnings

## Accessibility Considerations

1. For critical alerts, add `role="alert"` to the alert element
2. Use `aria-live="polite"` for non-critical alerts
3. Ensure color is not the only indicator of alert type (use icons)
4. Maintain sufficient contrast between text and background
