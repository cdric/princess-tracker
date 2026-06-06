# Changelog

All notable changes to this project are tracked in this file.

## 1.3.0

### User facing

- Added admin controls to delete specific rows from the stored history table.
- Restricted non-admin users so they cannot change currency or guest home city when running a manual price check.
- Removed the misc/tax flag from the check flow.

### Non user facing

- Added server-side normalization of manual check parameters so restricted users cannot override locked fields via direct POST requests.
- Added history deletion helpers for removing individual stored price-check rows and cleaning up orphaned raw API responses.
- Simplified Princess payload construction by always sending the misc flag internally instead of exposing it as configurable input.

## 1.2.0

### User facing

- Added a second history graph that tracks available cabins across cabin types.
- Added a source column on the history table to show whether each price check came from the web UI or the cron job.
- Changed the history page cruise selector to a dropdown of known cruise codes.
- Changed the admin watch page to use a cruise dropdown and to show the latest known fare for the selected cruise and cabin.
- Added admin support for back-in-stock email alerts when sold out cabins become available again.
- Improved alert emails with a formatted HTML layout.

### Non user facing

- Added watch metadata for alert type and last seen cabin status so availability alerts can trigger on sold-out to available transitions.
- Joined history rows to raw API response metadata to expose check source labels without duplicating source data in the history table.
- Added schema-upgrade handling for existing watch tables so new alert fields can be added in place.
- Added richer alert rendering shared by price-drop and availability notifications.

## 1.1.0

### User facing

- Added a separate restricted user login that only shows `Check cruise price`, `History`, and `Logout`.
- Removed operational notes from the main check page for restricted users.
- Updated the navigation labels to better match the page purpose.

### Non user facing

- Added role-based authentication with distinct `admin` and `user` access levels.
- Blocked non-admin users from opening admin-only pages directly by URL.
- Added environment configuration and documentation for separate admin and user credentials.

## 1.0.0

### User facing

- Added the initial Princess cruise price tracker web interface.
- Added manual cruise price checks with configurable cruise, currency, and guest settings.
- Added price history browsing with graph and table views.
- Added admin watch management for price-drop alerts.
- Added login and logout flows for protected access to the app.
- Added Princess API header capture and management from copied browser cURL requests.

### Non user facing

- Added PHP bootstrap, environment loading, session handling, and shared page layout helpers.
- Added SQLite and MySQL database support for storing raw API responses, price history, and watches.
- Added Princess pricing API integration and persistence for manual and scheduled checks.
- Added mail delivery support for price-drop notifications.
- Added a CLI cron entry point for background price checks.
- Added health/version reporting and deployment/setup documentation for Bluehost-style hosting.
