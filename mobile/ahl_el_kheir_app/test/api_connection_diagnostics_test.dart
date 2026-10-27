import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ahl_el_kheir_app/main.dart';

void main() {
  test('uses the verified phone-facing port 8081 and migrates only the known stale local API endpoint from port 881', () {
    expect(defaultMobileApiBaseUrl, 'http://192.168.18.9:8081/AhlElKheir/api/v1');
    expect(
      migrateKnownLegacyApiBaseUrl('http://192.168.18.9:8081/AhlElKheir/api/v1'),
      'http://192.168.18.9:8081/AhlElKheir/api/v1',
    );
    expect(
      migrateKnownLegacyApiBaseUrl('http://192.168.18.9:881/AhlElKheir/api/v1'),
      'http://192.168.18.9:8081/AhlElKheir/api/v1',
    );
    expect(
      migrateKnownLegacyApiBaseUrl('http://other-host:881/AhlElKheir/api/v1'),
      'http://other-host:881/AhlElKheir/api/v1',
    );
  });

  test('API probe rejects a malformed base URL without network access', () async {
    final result = await probeApiEndpoint('http://example.com', english: false);
    expect(result.success, isFalse);
    expect(result.message, contains('/api/v1'));
  });

  test('normalizes complete API URLs and preserves unrelated configured hosts', () {
    expect(
      normalizeApiBaseUrl(' http://192.168.18.9:8081/AhlElKheir/api/v1/ '),
      'http://192.168.18.9:8081/AhlElKheir/api/v1',
    );
    expect(normalizeApiBaseUrl('http://192.168.18.9:8081/AhlElKheir'), isNull);
    expect(
      migrateKnownLegacyApiBaseUrl('https://api.example.test/AhlElKheir/api/v1'),
      'https://api.example.test/AhlElKheir/api/v1',
    );
  });


}
