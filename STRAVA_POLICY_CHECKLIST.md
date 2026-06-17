# Strava Reactivation Policy Checklist (Phase 1)

Updated: 2026-06-17

## Purpose
This checklist maps Strava API Policy (2026) must-have obligations to concrete controls in this project before broad reactivation.

## Must-have controls

1. Consent disclosure before connection
- Status: Implemented
- Location: pages/strava_connect.php
- Notes: Connect page now discloses data categories, withdrawal path, and local deletion path.

2. Withdraw consent (deauthorization)
- Status: Implemented
- Location: includes/strava.php, pages/strava_connect.php
- Notes: Disconnect now attempts Strava revoke endpoint before clearing local tokens.

3. User-requested deletion path
- Status: Implemented (local app data)
- Location: pages/strava_connect.php, includes/strava.php
- Notes: New action deletes local Strava-linked submission detail and clears all Strava identity/token fields.

4. Sensitive logging minimization
- Status: Implemented
- Location: strava_callback.php, includes/strava.php
- Notes: Removed verbose callback debug logs with session/state/code-like details; switched to minimal safe log messages.

5. Scope validation
- Status: Implemented
- Location: strava_callback.php
- Notes: Callback enforces activity read scope before token persistence.

6. Strava feature re-exposure
- Status: Implemented (limited)
- Location: includes/header.php, hr/challenges/index.php, hr/challenges/edit.php
- Notes: Dashboard link restored and HR challenge management for strava type re-enabled.

## Operational checks before rollout

1. Verify Strava app settings
- Authorization Callback Domain matches deployment host.
- Client ID and Client Secret are valid.

2. Verify SSL CA bundle on server
- Ensure one configured CA bundle path exists for callback token exchange.

3. Verify revoke behavior
- Disconnect a test user and confirm subsequent API calls fail with disconnected state.

4. Verify local deletion behavior
- Use delete action and confirm:
  - employees.strava_* fields are NULL
  - challenge_submissions.photo_path is NULL where submission_type='strava'

5. Verify challenge flow
- Submit matching and non-matching strava challenge.
- Confirm user-facing errors do not leak internals.

## Remaining Nice-to-have (Phase 2)

1. Webhook integration for deauthorization/activity updates.
2. Retention automation for stale Strava-derived data and cache windows.
3. Rate-limit observability dashboard and proactive alerting.
4. In-app privacy policy page link dedicated to Strava data lifecycle.

## Rollback guide

If issues are found, temporarily disable exposure by:
1. Commenting Strava dashboard link in includes/header.php.
2. Removing strava from HR challenge allowlist in hr/challenges/index.php.
3. Hiding strava option/condition UI in hr/challenges/edit.php.
