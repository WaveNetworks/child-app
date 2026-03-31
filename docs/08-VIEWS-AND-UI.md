# Views & UI

> **RE-ENGINEERING NOTES:**
> 1. **privacy.php duplicates profile.php** — The standalone privacy page appears to duplicate the GDPR tabs that are already in profile.php. One should redirect to the other (or be deleted). Currently both exist, creating maintenance burden.
> 2. **Inline JavaScript in views** — Several views (game.php, feedback.php, roadmap.php) contain substantial inline `<script>` blocks (50-100+ lines) for AJAX loading, DOM manipulation, and state management. These should be extracted to dedicated JS files for cacheability and testability.
> 3. **SPA script re-execution** — spa-nav.js must re-execute inline scripts after loading view HTML. This is fragile — scripts that rely on DOMContentLoaded or specific load timing may break. Views should expose an init function that spa-nav calls.
> 4. **No view templating** — Views are raw PHP files with `<?= ?>` output. There's no layout inheritance, partial system, or component abstraction beyond the admin shell. Repeated patterns (filter bars, pagination, status badges) are copy-pasted.

---

## App Router (app/index.php — 147 lines)

Routes `?page=` parameter to view files:

| Page | View File | Role Guard |
|------|-----------|------------|
| `dashboard` | views/dashboard.php | — |
| `assessment` | views/assessment.php | — |
| `results` | views/results.php | — |
| `content` | views/content.php | — |
| `coaches` | views/coaches.php | — |
| `team` | views/team.php | Coach |
| `invite` | views/invite.php | Coach |
| `cohort` | views/cohort.php | Org Lead |
| `admin-framework` | views/admin-framework.php | Admin |
| `onboarding` | views/onboarding.php | — |
| `profile` | views/profile.php | — |
| `feedback` | views/feedback.php | — |
| `items` | views/items.php | — |
| `examples` | views/examples.php | — |
| `roadmap` | views/roadmap.php | — |
| `game` | views/game.php | — |
| `privacy` | views/privacy.php | — |
| `ui-guide` | views/ui-guide.php | — |

### SPA Support

When `?reloadView=true` is set, returns JSON instead of full HTML:
```json
{
  "html": "<div>...view content...</div>",
  "title": "Page Title",
  "page": "dashboard",
  "toast": { "type": "success", "message": "..." }
}
```

### Pre-Template Redirects

- Assessment completion → results page
- Missing onboarding → onboarding page
- Non-coach accessing team → dashboard
- Non-org-lead accessing cohort → dashboard

## Modal System (app/modal_controller.php — 25 lines)

Endpoint: `modal_controller.php?modal=<name>`

| Modal Name | Snippet File |
|------------|-------------|
| `item_detail` | snippets/modal_item_detail.php |
| `confirm_delete` | snippets/modal_confirm_delete.php |
| `example_form` | snippets/modal_example_form.php |
| `seller_detail` | snippets/modal_seller_detail.php |
| `coach_note` | snippets/modal_coach_note.php |

Called via `modalView({modal: 'name', id: 123})` from JavaScript.

## View Summaries

### profile.php (~500 lines)
Tabbed interface: Profile, Password, Preferences, Consent, Login History, Sessions, Data Export, Danger Zone. Includes avatar upload/delete, OAuth detection, GDPR compliance.

### feedback.php (236 lines)
Feedback submission form + AJAX-loaded history table with type/status filters and pagination.

### items.php (200 lines)
Item CRUD with dual-mode view (form/list), status filters, pagination.

### roadmap.php (176 lines)
AJAX-loaded roadmap grouped by status (In Progress, Approved, Proposed, Completed, Paused). Cards with type/priority badges.

### examples.php (326 lines)
UI reference: modal examples, DataTables demo, Bootstrap tabs demo.

### game.php (212 lines)
Self-playing Pong game on canvas background. Slider controls for speed/skill/trails. localStorage persistence.

### ui-guide.php (~300 lines)
Living Bootstrap 5 component reference: Typography, Buttons, Cards, Forms, Tables, Modals, Tabs, Alerts, Badges.

### 404.php (16 lines)
Simple not-found page with warning icon and home button.

## Snippet Templates

### modal_item_detail.php (48 lines)
Read-only display of item properties (title, status, description, dates).

### modal_confirm_delete.php (38 lines)
Danger-styled confirmation dialog with item title.

### modal_example_form.php (61 lines)
Feedback form with subject, message, priority radio buttons. Validation error display.

## Flash Message Pattern

```php
// Set in action handler:
$_SESSION['success'] = 'Item saved.';
$_SESSION['error'] = 'Validation failed.';

// Read in view (via toast.js or inline alert):
// toast.js reads #toast-data div attributes on page load
// SPA nav reads toast from JSON response
```

Profile page uses form-specific keys: `profile_success`, `password_error`, `prefs_success`, etc.
