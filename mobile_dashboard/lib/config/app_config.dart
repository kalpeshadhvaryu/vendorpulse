import 'dart:io' show Platform;

class AppConfig {
  // Keep /api/v1 suffix because API routes are namespaced under this prefix.
  static const String _apiBaseUrlFromEnv = String.fromEnvironment('VP_API_BASE_URL');

  static String get apiBaseUrl {
    if (_apiBaseUrlFromEnv.trim().isNotEmpty) {
      return _apiBaseUrlFromEnv.trim();
    }

    if (Platform.isAndroid) {
      // Android emulator uses 10.0.2.2 to reach host machine localhost.
      return 'http://10.0.2.2:8000/api/v1';
    }

    // iOS simulator and desktop Flutter can reach host using 127.0.0.1.
    return 'http://127.0.0.1:8000/api/v1';
  }

  static const Duration requestTimeout = Duration(seconds: 12);
}
