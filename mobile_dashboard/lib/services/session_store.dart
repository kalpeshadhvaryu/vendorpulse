import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStore {
  static const String _tokenKey = 'vp_token';
  static const String _organizationIdKey = 'vp_organization_id';
  static const FlutterSecureStorage _storage = FlutterSecureStorage();

  Future<void> saveToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<String?> readToken() async {
    return _storage.read(key: _tokenKey);
  }

  Future<void> saveOrganizationId(String? organizationId) async {
    if (organizationId == null || organizationId.isEmpty) {
      await _storage.delete(key: _organizationIdKey);
      return;
    }
    await _storage.write(key: _organizationIdKey, value: organizationId);
  }

  Future<String?> readOrganizationId() async {
    return _storage.read(key: _organizationIdKey);
  }

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _organizationIdKey);
  }
}
