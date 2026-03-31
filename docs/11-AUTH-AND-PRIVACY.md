# Auth & Privacy

> **RE-ENGINEERING NOTES:**
> 1. **Auth pages are thin wrappers around admin core** — All auth logic (login, register, OAuth, SAML, 2FA) lives in the admin app. The child app's `auth/` files are just includes. This is correct architecture but means auth customization requires modifying the admin, not the child app.
> 2. **Synchronous data export** — `requestDataExport` in privacyActions.php builds the export in real-time during the HTTP request. For users with large histories, this could timeout. A background job pattern (create request → process async → download when ready) would be more robust.
> 3. **Privacy page duplicates profile tabs** — `views/privacy.php` and `views/profile.php` both contain GDPR management tabs. See views documentation for details.
> 4. **Device session table queried without index hint** — `revokeSession` and `revokeAllSessions` query `device_sessions` by `user_id`. If the admin table lacks an index on `user_id`, this will be slow for large deployments.

---

## Auth Flow

```
                    ┌─── register.php ──── Email + password + consent
                    │
User ──── auth/ ────┼─── login.php ─────── Email/password or OAuth
                    │
                    ├─── register_invite.php ── Token-based registration
                    │
                    ├─── forgot.php ─────── Password reset request
                    │
                    ├─── reset.php ──────── Password reset form
                    │
                    ├─── confirm.php ────── Email confirmation
                    │
                    ├─── 2fa.php ────────── Two-factor verification
                    │
                    ├─── consent.php ────── Re-consent (after ToS update)
                    │
                    ├─── oauth_callback.php ── OAuth redirect handler
                    │
                    ├─── saml_callback.php ─── SAML redirect handler
                    │
                    └─── saml_metadata.php ─── SAML metadata endpoint
```

All auth pages use `include/common_auth.php` (no session guard).

## Auth File Breakdown

| File | Lines | Purpose |
|------|-------|---------|
| login.php | ~30 | Login form (delegates to admin template) |
| register.php | ~40 | Registration with ToS/Privacy consent checkbox |
| register_invite.php | ~50 | Invite token validation + registration |
| forgot.php | ~25 | Password reset email request |
| reset.php | ~30 | New password form (token-validated) |
| confirm.php | ~20 | Email confirmation landing |
| 2fa.php | ~25 | TOTP/SMS verification |
| consent.php | ~25 | Re-consent after policy update |
| oauth_callback.php | ~15 | OAuth provider redirect |
| saml_callback.php | ~15 | SAML IdP redirect |
| saml_metadata.php | ~10 | SAML SP metadata XML |

## GDPR / Privacy (privacyActions.php — 229 lines)

### Consent Management

| Action | Description |
|--------|-------------|
| `updateConsent` | Toggle: marketing_email, cookie_analytics, cookie_marketing. Only records changes. |

Tables: `consent_records`, `consent_versions` (admin core)

### Data Export

| Action | Description |
|--------|-------------|
| `requestDataExport` | Builds export synchronously. Includes admin user data + child app items, prefs, history. Formats: JSON or CSV. |
| `downloadExport` | Serves completed export file with MIME type and expiry check. |

### Account Deletion

| Action | Description |
|--------|-------------|
| `requestAccountDeletion` | Requires typing "DELETE" as confirmation. Creates 30-day cooling-off period. |
| `cancelAccountDeletion` | Cancels pending deletion during grace period. |

Table: `account_deletion_requests` (admin core)

### Session Management

| Action | Description |
|--------|-------------|
| `revokeSession` | Revoke single device by device_id (user-scoped) |
| `revokeAllSessions` | Revoke all sessions except current |

Table: `device_sessions` (admin core)

## Profile Management (profileActions.php — 163 lines)

| Action | Description |
|--------|-------------|
| `updateProfile` | Update first_name, last_name, email. Email uniqueness validated. |
| `changePassword` | Requires current password. Blocks OAuth-only accounts. |
| `updateAppPreferences` | Items per page, default status, email notifications, bio. |

## Onboarding (onboardingActions.php — 78 lines)

Post-registration 3-step profile collection:

**Step 1:** life_stage, age_range, industry
**Step 2:** household_role, business_tenure, prior_ds_experience
**Step 3:** team_size, engagement_level, product_relationship, primary_motivation, secondary_motivations, driving_circumstance

Creates assessment instance with profile data. Redeems pending invite if present. Assigns seller role.

## Notification Categories

Registered in `notificationRegistration.php`:
- `coach_linked` — When coach-seller relationship created
- `coach_note` — When coach shares a note
- `seller_linked` — When seller accepts invite
- `seller_inactive` — Weekly inactivity check
- `assessment_complete` — When assessment finished
- `framework_updated` — When framework data synced
