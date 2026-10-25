# MatomoAnalytics

MediaWiki extension for integration with Matomo for analytics. See [documentation on MediaWiki.org](https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:MatomoAnalytics) for more details.

Developed by Miraheze.

Licensed under the GPLv3 (or later) LICENSE.

## Configuration

### Core settings

- `MatomoAnalyticsServerURL` (string)
  - URL of your Matomo installation, used for tracking JS and API queries.
- `MatomoAnalyticsTokenAuth` (string)
  - Matomo API token for API requests.
- `MatomoAnalyticsSiteID` (int)
  - Fallback Matomo site ID when DB mapping is not used.
- `MatomoAnalyticsUseDB` (bool)
  - Whether to use the extension DB mapping table for wiki -> Matomo site IDs.
- `MatomoAnalyticsGlobalID` (int)
  - Optional second/global tracker site ID.

### Tracking behavior

- `MatomoAnalyticsDisableJS` (bool)
  - Disable all client-side tracking code injection.
- `MatomoAnalyticsDisableCookie` (bool)
  - Ask Matomo JS tracker not to set cookies.
- `MatomoAnalyticsForceGetRequest` (bool)
  - Force GET request method (legacy-like behavior).
- `MatomoAnalyticsEnableCustomDimensionsUserType` (bool)
  - Send custom dimension 1 as `User` / `Anonymous`.

### Newly integrated compatibility features (from MatomoNew)

- `MatomoAnalyticsIgnoreBots` (bool, default `true`)
  - Skip tracking for users with bot rights.
- `MatomoAnalyticsTrackUsernames` (bool, default `false`)
  - For registered users, set Matomo User ID to username (`setUserId`).

### Hooks used for tracking/search enrichment

- `SkinAfterBottomScripts`
  - Injects Matomo tracking script.
- `SpecialSearchResults`
  - Captures searched term and search result count.
- `SpecialSearchSetupEngine`
  - Captures search profile/category.

When search context is present, the extension sends Matomo site-search tracking via `trackSiteSearch(...)`.

## Operational notes for migrated logic

The migrated MatomoNew behavior was adapted to current MatomoAnalytics architecture and MediaWiki services:

1. Uses the existing `_paq` tracker pipeline (instead of custom XHR), so it remains compatible with all existing MatomoAnalytics options and global tracker behavior.
2. Keeps existing privacy/permission guardrails in place before tracking:
   - `MatomoAnalyticsDisableJS`
   - CookieConsent statistics consent checks (if available)
   - `noanalytics` user right
   - `MatomoAnalyticsIgnoreBots`
3. Search tracking is only emitted when search hooks actually provided context, so normal page views are unchanged.
4. Username tracking is opt-in (`MatomoAnalyticsTrackUsernames = false` by default).
5. All user-derived values are JS-escaped before output (`Html::encodeJsVar`).

## Security Vulnerabilities

If you believe you have found a security vulnerability in any part of our code, please do not post it publicly by using our wikis or bug trackers for that; rather, please read our [security page](https://meta.miraheze.org/wiki/Special:MyLanguage/Security) carefully, and follow the instructions.

As a quick overview, you can email security concerns to security@miraheze.org or if you'd like, you can instead directly create a security-related task [here](https://issue-tracker.miraheze.org/maniphest/task/edit/form/2/), but please leave the "Security" project on the issue.

