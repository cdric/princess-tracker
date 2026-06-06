# Changelog

All notable changes to this project are tracked in this file.

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
