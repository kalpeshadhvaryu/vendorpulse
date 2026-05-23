import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../models/api_models.dart';

class VendorPulseApi {
  final http.Client _http;

  VendorPulseApi({http.Client? httpClient}) : _http = httpClient ?? http.Client();

  Future<LoginResult> login({
    required String email,
    required String password,
    required String deviceName,
  }) async {
    final payload = {
      'email': email.trim().toLowerCase(),
      'password': password,
      'device_name': deviceName,
    };

    final data = await _request(
      method: 'POST',
      path: '/auth/login',
      body: payload,
    );

    final token = (data['token'] ?? '').toString();
    final userJson = _asMap(data['user']);
    if (token.isEmpty || userJson == null) {
      throw const ApiException('Unexpected login response format.');
    }

    return LoginResult(token: token, user: UserProfile.fromJson(userJson));
  }

  Future<UserProfile> me({required String token}) async {
    final data = await _request(
      method: 'GET',
      path: '/auth/me',
      token: token,
    );

    final user = _asMap(data);
    if (user == null) {
      throw const ApiException('Unexpected /auth/me response format.');
    }
    return UserProfile.fromJson(user);
  }

  Future<DashboardTrends> fetchDashboardTrends({
    required String token,
    required String organizationId,
    int days = 30,
  }) async {
    final data = await _request(
      method: 'GET',
      path: '/dashboard/trends?days=$days',
      token: token,
      organizationId: organizationId,
    );

    final payload = _asMap(data);
    if (payload == null) {
      throw const ApiException('Unexpected /dashboard/trends response format.');
    }
    return DashboardTrends.fromJson(payload);
  }

  Future<MonitoringFallbackTrends> fetchMonitoringFallbackTrends({
    required String token,
    required String organizationId,
    int days = 30,
  }) async {
    final data = await _request(
      method: 'GET',
      path: '/dashboard/monitoring-create-fallbacks?days=$days',
      token: token,
      organizationId: organizationId,
    );

    final payload = _asMap(data);
    if (payload == null) {
      throw const ApiException('Unexpected monitoring fallback response format.');
    }
    return MonitoringFallbackTrends.fromJson(payload);
  }

  Future<List<MonitoringCheck>> fetchMonitoringChecks({
    required String token,
    required String organizationId,
    int perPage = 25,
  }) async {
    final response = await _rawRequest(
      method: 'GET',
      path: '/monitoring-checks?per_page=$perPage',
      token: token,
      organizationId: organizationId,
    );

    final body = _decodeJson(response.body);
    final success = body['success'] == true;
    if (!success) {
      throw ApiException((body['message'] ?? 'Request failed.').toString());
    }

    final items = body['data'] as List<dynamic>? ?? const [];
    return items
        .whereType<Map<String, dynamic>>()
        .map(MonitoringCheck.fromJson)
        .toList();
  }

  Future<void> runMonitoringCheck({
    required String token,
    required String organizationId,
    required String checkId,
  }) async {
    await _request(
      method: 'POST',
      path: '/monitoring-checks/$checkId/run',
      token: token,
      organizationId: organizationId,
    );
  }

  Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    String? token,
    String? organizationId,
    Map<String, dynamic>? body,
  }) async {
    final response = await _rawRequest(
      method: method,
      path: path,
      token: token,
      organizationId: organizationId,
      body: body,
    );

    final payload = _decodeJson(response.body);
    final success = payload['success'] == true;
    if (!success) {
      throw ApiException((payload['message'] ?? 'Request failed.').toString());
    }

    return payload['data'] as Map<String, dynamic>? ?? payload;
  }

  Future<http.Response> _rawRequest({
    required String method,
    required String path,
    String? token,
    String? organizationId,
    Map<String, dynamic>? body,
  }) async {
    final url = _resolveUrl(path);
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    if (organizationId != null && organizationId.isNotEmpty) {
      headers['X-Organization-Id'] = organizationId;
    }

    final response = switch (method) {
      'GET' => await _http
          .get(url, headers: headers)
          .timeout(AppConfig.requestTimeout),
      'POST' => await _http
          .post(url, headers: headers, body: jsonEncode(body ?? const {}))
          .timeout(AppConfig.requestTimeout),
      _ => throw ApiException('Unsupported HTTP method: $method'),
    };

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response;
    }

    final payload = _decodeJson(response.body);
    final message = (payload['message'] ?? 'HTTP ${response.statusCode}').toString();
    throw ApiException(message, statusCode: response.statusCode);
  }

  Uri _resolveUrl(String path) {
    final base = AppConfig.apiBaseUrl.endsWith('/')
        ? AppConfig.apiBaseUrl.substring(0, AppConfig.apiBaseUrl.length - 1)
        : AppConfig.apiBaseUrl;
    final normalizedPath = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$base$normalizedPath');
  }

  Map<String, dynamic> _decodeJson(String source) {
    if (source.isEmpty) {
      return <String, dynamic>{};
    }
    final decoded = jsonDecode(source);
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }
    return <String, dynamic>{'data': decoded};
  }

  Map<String, dynamic>? _asMap(dynamic value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    return null;
  }
}

class LoginResult {
  final String token;
  final UserProfile user;

  const LoginResult({required this.token, required this.user});
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;

  const ApiException(this.message, {this.statusCode});

  @override
  String toString() {
    return message;
  }
}
