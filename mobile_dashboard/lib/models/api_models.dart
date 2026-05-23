class Organization {
  final String id;
  final String name;
  final String? slug;

  const Organization({required this.id, required this.name, required this.slug});

  factory Organization.fromJson(Map<String, dynamic> json) {
    return Organization(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      slug: json['slug']?.toString(),
    );
  }
}

class UserProfile {
  final String id;
  final String name;
  final String email;
  final bool isAdmin;
  final String? defaultOrganizationId;
  final List<Organization> organizations;

  const UserProfile({
    required this.id,
    required this.name,
    required this.email,
    required this.isAdmin,
    required this.defaultOrganizationId,
    required this.organizations,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    final orgs = (json['organizations'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(Organization.fromJson)
        .toList();

    return UserProfile(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      isAdmin: json['is_admin'] == true,
      defaultOrganizationId: json['default_organization_id']?.toString(),
      organizations: orgs,
    );
  }
}

class DashboardPoint {
  final String date;
  final int totalRuns;
  final int downtimeRuns;
  final double? availabilityRatio;

  const DashboardPoint({
    required this.date,
    required this.totalRuns,
    required this.downtimeRuns,
    required this.availabilityRatio,
  });

  factory DashboardPoint.fromJson(Map<String, dynamic> json) {
    return DashboardPoint(
      date: (json['date'] ?? '').toString(),
      totalRuns: (json['total_runs'] as num? ?? 0).toInt(),
      downtimeRuns: (json['downtime_runs'] as num? ?? 0).toInt(),
      availabilityRatio: (json['availability_ratio'] as num?)?.toDouble(),
    );
  }
}

class InvoicePoint {
  final String date;
  final int issuedCount;
  final int paidCount;
  final int issuedAmountCents;
  final int paidAmountCents;

  const InvoicePoint({
    required this.date,
    required this.issuedCount,
    required this.paidCount,
    required this.issuedAmountCents,
    required this.paidAmountCents,
  });

  factory InvoicePoint.fromJson(Map<String, dynamic> json) {
    return InvoicePoint(
      date: (json['date'] ?? '').toString(),
      issuedCount: (json['issued_count'] as num? ?? 0).toInt(),
      paidCount: (json['paid_count'] as num? ?? 0).toInt(),
      issuedAmountCents: (json['issued_amount_cents'] as num? ?? 0).toInt(),
      paidAmountCents: (json['paid_amount_cents'] as num? ?? 0).toInt(),
    );
  }
}

class DashboardTrends {
  final List<DashboardPoint> monitoring;
  final List<InvoicePoint> invoices;

  const DashboardTrends({required this.monitoring, required this.invoices});

  factory DashboardTrends.fromJson(Map<String, dynamic> json) {
    final monitoring = (json['monitoring'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(DashboardPoint.fromJson)
        .toList();

    final invoices = (json['invoices'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(InvoicePoint.fromJson)
        .toList();

    return DashboardTrends(monitoring: monitoring, invoices: invoices);
  }
}

class MonitoringCheck {
  final String id;
  final String name;
  final String type;
  final String? endpoint;
  final String? lastStatus;
  final int? lastResponseTimeMs;
  final int consecutiveFailures;
  final bool enabled;

  const MonitoringCheck({
    required this.id,
    required this.name,
    required this.type,
    required this.endpoint,
    required this.lastStatus,
    required this.lastResponseTimeMs,
    required this.consecutiveFailures,
    required this.enabled,
  });

  factory MonitoringCheck.fromJson(Map<String, dynamic> json) {
    return MonitoringCheck(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      type: (json['type'] ?? '').toString(),
      endpoint: json['endpoint']?.toString(),
      lastStatus: json['last_status']?.toString(),
      lastResponseTimeMs: (json['last_response_time_ms'] as num?)?.toInt(),
      consecutiveFailures: (json['consecutive_failures'] as num? ?? 0).toInt(),
      enabled: json['enabled'] == true,
    );
  }

  MonitoringCheck copyWith({
    String? id,
    String? name,
    String? type,
    String? endpoint,
    String? lastStatus,
    int? lastResponseTimeMs,
    int? consecutiveFailures,
    bool? enabled,
  }) {
    return MonitoringCheck(
      id: id ?? this.id,
      name: name ?? this.name,
      type: type ?? this.type,
      endpoint: endpoint ?? this.endpoint,
      lastStatus: lastStatus ?? this.lastStatus,
      lastResponseTimeMs: lastResponseTimeMs ?? this.lastResponseTimeMs,
      consecutiveFailures: consecutiveFailures ?? this.consecutiveFailures,
      enabled: enabled ?? this.enabled,
    );
  }
}

class MonitoringFallbackPoint {
  final String date;
  final int count;

  const MonitoringFallbackPoint({required this.date, required this.count});

  factory MonitoringFallbackPoint.fromJson(Map<String, dynamic> json) {
    return MonitoringFallbackPoint(
      date: (json['date'] ?? '').toString(),
      count: (json['count'] as num? ?? 0).toInt(),
    );
  }
}

class MonitoringFallbackTrends {
  final int total;
  final List<MonitoringFallbackPoint> series;

  const MonitoringFallbackTrends({required this.total, required this.series});

  factory MonitoringFallbackTrends.fromJson(Map<String, dynamic> json) {
    final items = (json['series'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(MonitoringFallbackPoint.fromJson)
        .toList();

    return MonitoringFallbackTrends(
      total: (json['total'] as num? ?? 0).toInt(),
      series: items,
    );
  }
}
