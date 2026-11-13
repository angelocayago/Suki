# Laravel API integration patch

Copy the `app` and `routes` folders in this directory into the root of the extracted `Suki-skeleton` Laravel project, preserving their paths. This adds the mobile APIs and replaces the matching existing auth, buyer cart, and API route files. No database migration is required.

The patch exposes authenticated buyer order history/details, tracking, cancellation, receipt confirmation, and JSON Buy Again. It exposes rider dashboard, availability, assigned deliveries and actions, scan lookup/actions, proof-of-delivery upload, failure/issue reporting, history, earnings, reports, COD remittance, profile, and password update. Mobile login can issue Sanctum tokens for eligible buyers and riders.

The app uses `/api` routes. Configure the mobile `API_BASE_URL` to the deployed Laravel `/api` URL. Rider access requires an active, verified rider assigned to an approved logistics provider, matching the web application policy.
