# VendorPulse Mobile Dashboard

Flutter mobile client for Android and iOS that connects to the VendorPulse backend API.

## What is included

- App shell and theme
- Login flow using `POST /api/v1/auth/login`
- Profile load using `GET /api/v1/auth/me`
- Organization context handling via `X-Organization-Id`
- Dashboard trends from `GET /api/v1/dashboard/trends`
- Monitoring fallback trends from `GET /api/v1/dashboard/monitoring-create-fallbacks`
- Monitoring checks list from `GET /api/v1/monitoring-checks`
- Run-now action via `POST /api/v1/monitoring-checks/{id}/run`
- Persistent secure storage for token and selected organization (`flutter_secure_storage`)
- Tabbed mobile UI: Overview, Monitoring, Profile

## Local setup

1. Install Flutter SDK (stable) on your machine.
2. Open this folder:

   cd mobile_dashboard

3. Generate native platform folders (Android and iOS):

   flutter create --platforms=android,ios .

4. Install dependencies:

   flutter pub get

5. Run on Android:

   flutter run

6. Run on iOS (requires macOS + Xcode):

   flutter run -d ios

## API configuration

By default, the app chooses the local Laravel API URL by platform:

- Android emulator: `http://10.0.2.2:8000/api/v1`
- iOS simulator / desktop Flutter: `http://127.0.0.1:8000/api/v1`

You can override it at runtime with a dart define:

```bash
flutter run --dart-define=VP_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Important: keep `/api/v1` in the base URL because mobile calls versioned API routes.

For Android emulator:

- Use `http://10.0.2.2:8000/api/v1`

For iOS simulator:

- Use `http://127.0.0.1:8000/api/v1`

For physical device:

- Use your machine LAN IP, for example `http://192.168.1.10:8000/api/v1`

## Implemented screen flow

1. App opens to login screen.
2. After sign-in, app fetches `/auth/me`.
3. User can switch organization context in-app.
4. Dashboard displays availability, run counts, fallback events, and monitoring checks.
5. Each check has a run-now action.
