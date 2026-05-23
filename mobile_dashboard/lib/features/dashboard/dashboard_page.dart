import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../models/api_models.dart';
import '../monitoring/monitoring_check_detail_page.dart';
import '../../services/session_store.dart';
import '../../services/vendorpulse_api.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  final VendorPulseApi _api = VendorPulseApi();
  final SessionStore _store = SessionStore();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  String? _token;
  UserProfile? _user;
  String? _organizationId;
  DashboardTrends? _trends;
  MonitoringFallbackTrends? _fallbackTrends;
  List<MonitoringCheck> _monitoringChecks = <MonitoringCheck>[];
  final TextEditingController _monitoringSearchController =
      TextEditingController();
  String? _monitoringTypeFilter;
  final Set<String> _pendingRunCheckIds = <String>{};
  String? _error;
  bool _isLoading = false;
  bool _isAuthenticating = false;
  int _activeTabIndex = 0;

  @override
  void initState() {
    super.initState();
    _bootstrapSession();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _monitoringSearchController.dispose();
    super.dispose();
  }

  static const List<String> _monitoringTypeOptions = <String>[
    'uptime',
    'ssl',
    'domain',
    'http',
    'https',
    'tls',
    'whois',
    'tcp',
    'ping',
    'dns',
    'custom',
    'server',
  ];

  bool get _hasMonitoringFilters =>
      _monitoringSearchController.text.trim().isNotEmpty ||
      _monitoringTypeFilter != null;

  void _clearMonitoringFilters() {
    _monitoringSearchController.clear();
    setState(() {
      _monitoringTypeFilter = null;
    });
    _refreshDashboard();
  }

  Future<void> _bootstrapSession() async {
    final token = await _store.readToken();
    final organizationId = await _store.readOrganizationId();

    if (token == null || token.isEmpty) {
      return;
    }

    setState(() {
      _token = token;
      _organizationId = organizationId;
    });

    try {
      final user = await _api.me(token: token);
      final selectedOrganizationId = _pickOrganizationId(user, organizationId);

      await _store.saveOrganizationId(selectedOrganizationId);
      setState(() {
        _user = user;
        _organizationId = selectedOrganizationId;
      });

      await _refreshDashboard();
    } catch (_) {
      await _logout();
    }
  }

  Future<void> _login() async {
    setState(() {
      _isAuthenticating = true;
      _error = null;
    });

    try {
      final result = await _api.login(
        email: _emailController.text,
        password: _passwordController.text,
        deviceName: 'VendorPulse Mobile',
      );

      final me = await _api.me(token: result.token);
      final selectedOrganizationId =
          _pickOrganizationId(me, me.defaultOrganizationId);

      await _store.saveToken(result.token);
      await _store.saveOrganizationId(selectedOrganizationId);

      if (!mounted) {
        return;
      }

      setState(() {
        _token = result.token;
        _user = me;
        _organizationId = selectedOrganizationId;
      });

      await _refreshDashboard();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = error.message;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = 'Login failed. ${error.toString()}';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isAuthenticating = false;
        });
      }
    }
  }

  Future<void> _logout() async {
    await _store.clear();
    if (!mounted) {
      return;
    }
    setState(() {
      _token = null;
      _user = null;
      _organizationId = null;
      _trends = null;
      _fallbackTrends = null;
      _monitoringChecks = <MonitoringCheck>[];
      _pendingRunCheckIds.clear();
      _activeTabIndex = 0;
      _error = null;
      _passwordController.clear();
    });
  }

  Future<void> _refreshDashboard() async {
    final token = _token;
    final orgId = _organizationId;

    if (token == null || token.isEmpty || orgId == null || orgId.isEmpty) {
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final trends = await _api.fetchDashboardTrends(
        token: token,
        organizationId: orgId,
      );
      final fallback = await _api.fetchMonitoringFallbackTrends(
        token: token,
        organizationId: orgId,
      );
      final checks = await _api.fetchMonitoringChecks(
        token: token,
        organizationId: orgId,
        search: _monitoringSearchController.text.trim().isEmpty
            ? null
            : _monitoringSearchController.text,
        type: _monitoringTypeFilter,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _trends = trends;
        _fallbackTrends = fallback;
        _monitoringChecks = checks;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = error.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  void _openMonitoringCheck(MonitoringCheck check) {
    final token = _token;
    final orgId = _organizationId;

    if (token == null || orgId == null) {
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => MonitoringCheckDetailPage(
          check: check,
          token: token,
          organizationId: orgId,
          api: _api,
          onRunNow: _runNow,
        ),
      ),
    );
  }

  Future<void> _runNow(String checkId) async {
    final token = _token;
    final orgId = _organizationId;

    if (token == null ||
        orgId == null ||
        _pendingRunCheckIds.contains(checkId)) {
      return;
    }

    final previousChecks = List<MonitoringCheck>.from(_monitoringChecks);
    setState(() {
      _pendingRunCheckIds.add(checkId);
      _monitoringChecks = _monitoringChecks
          .map(
            (check) => check.id == checkId
                ? check.copyWith(lastStatus: 'queued', consecutiveFailures: 0)
                : check,
          )
          .toList();
    });

    try {
      await _api.runMonitoringCheck(
        token: token,
        organizationId: orgId,
        checkId: checkId,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Monitoring check queued.')),
      );

      await _refreshDashboard();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _monitoringChecks = previousChecks;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
    } finally {
      if (mounted) {
        setState(() {
          _pendingRunCheckIds.remove(checkId);
        });
      }
    }
  }

  String? _pickOrganizationId(UserProfile user, String? preferredId) {
    if (preferredId != null && preferredId.isNotEmpty) {
      return preferredId;
    }

    if (user.defaultOrganizationId != null &&
        user.defaultOrganizationId!.isNotEmpty) {
      return user.defaultOrganizationId;
    }

    if (user.organizations.isNotEmpty) {
      return user.organizations.first.id;
    }

    return null;
  }

  Organization? _selectedOrganization() {
    final user = _user;
    final orgId = _organizationId;

    if (user == null || orgId == null) {
      return null;
    }

    for (final org in user.organizations) {
      if (org.id == orgId) {
        return org;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final token = _token;
    if (token == null || token.isEmpty) {
      return _buildLogin(context);
    }

    return _buildDashboard(context);
  }

  Widget _buildLogin(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: ListView(
              shrinkWrap: true,
              padding: const EdgeInsets.all(20),
              children: [
                const _BrandHeader(),
                const SizedBox(height: 24),
                _Panel(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _SectionTitle(
                        title: 'Sign in',
                        subtitle: 'Use the same account as the web dashboard.',
                      ),
                      const SizedBox(height: 18),
                      TextField(
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Email',
                          prefixIcon: Icon(Icons.mail_outline, size: 18),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _passwordController,
                        obscureText: true,
                        onSubmitted: (_) {
                          if (!_isAuthenticating) {
                            _login();
                          }
                        },
                        decoration: const InputDecoration(
                          labelText: 'Password',
                          prefixIcon: Icon(Icons.lock_outline, size: 18),
                        ),
                      ),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: _isAuthenticating ? null : _login,
                          icon: _isAuthenticating
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child:
                                      CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.arrow_forward, size: 18),
                          label: Text(
                              _isAuthenticating ? 'Signing in' : 'Sign in'),
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 12),
                        _InlineMessage(message: _error!, tone: _Tone.danger),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildDashboard(BuildContext context) {
    final trends = _trends;
    final fallback = _fallbackTrends;
    final selectedOrg = _selectedOrganization();

    final totalRuns = trends?.monitoring.fold<int>(
          0,
          (sum, point) => sum + point.totalRuns,
        ) ??
        0;
    final totalDowntime = trends?.monitoring.fold<int>(
          0,
          (sum, point) => sum + point.downtimeRuns,
        ) ??
        0;
    final availability = totalRuns > 0
        ? (((totalRuns - totalDowntime) / totalRuns) * 100).toStringAsFixed(2)
        : '--';

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: const Text('VendorPulse'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _isLoading ? null : _refreshDashboard,
            icon: _isLoading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh, size: 20),
          ),
          const SizedBox(width: 8),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, color: _VpColors.border),
        ),
      ),
      body: IndexedStack(
        index: _activeTabIndex,
        children: [
          _OverviewTab(
            user: _user,
            selectedOrgName: selectedOrg?.name,
            availability: availability,
            totalRuns: totalRuns,
            totalDowntime: totalDowntime,
            fallbackTotal: fallback?.total ?? 0,
            monitoringPoints: trends?.monitoring ?? const <DashboardPoint>[],
            fallbackPoints:
                fallback?.series ?? const <MonitoringFallbackPoint>[],
            checks: _monitoringChecks,
            isLoading: _isLoading,
            error: _error,
            onRefresh: _refreshDashboard,
          ),
          _MonitoringTab(
            checks: _monitoringChecks,
            hasActiveFilters: _hasMonitoringFilters,
            searchController: _monitoringSearchController,
            selectedType: _monitoringTypeFilter,
            typeOptions: _monitoringTypeOptions,
            pendingRunCheckIds: _pendingRunCheckIds,
            isLoading: _isLoading,
            error: _error,
            onRefresh: _refreshDashboard,
            onRunNow: _runNow,
            onOpenCheck: _openMonitoringCheck,
            onSearchChanged: () => setState(() {}),
            onSearchSubmitted: _refreshDashboard,
            onTypeChanged: (value) {
              setState(() => _monitoringTypeFilter = value);
              _refreshDashboard();
            },
            onClearFilters: _clearMonitoringFilters,
          ),
          _ProfileTab(
            user: _user,
            organizationId: _organizationId,
            isLoading: _isLoading,
            error: _error,
            onRefresh: _refreshDashboard,
            onChangeOrganization: (nextOrgId) async {
              if (nextOrgId == null) {
                return;
              }
              setState(() {
                _organizationId = nextOrgId;
              });
              await _store.saveOrganizationId(nextOrgId);
              await _refreshDashboard();
            },
            onLogout: _logout,
          ),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        backgroundColor: _VpColors.card,
        indicatorColor: _VpColors.muted,
        height: 64,
        selectedIndex: _activeTabIndex,
        onDestinationSelected: (index) {
          setState(() {
            _activeTabIndex = index;
          });
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Overview',
          ),
          NavigationDestination(
            icon: Icon(Icons.monitor_heart_outlined),
            selectedIcon: Icon(Icons.monitor_heart),
            label: 'Monitoring',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

enum _Tone { neutral, success, warning, danger, info }

class _VpColors {
  static const foreground = Color(0xFF09090B);
  static const muted = Color(0xFFF4F4F5);
  static const mutedForeground = Color(0xFF71717A);
  static const border = Color(0xFFE4E4E7);
  static const card = Color(0xFFFFFFFF);
  static const primary = Color(0xFF4F46E5);
  static const emerald = Color(0xFF10B981);
  static const amber = Color(0xFFF59E0B);
  static const rose = Color(0xFFF43F5E);
  static const zinc = Color(0xFF71717A);
}

class _OverviewTab extends StatelessWidget {
  final UserProfile? user;
  final String? selectedOrgName;
  final String availability;
  final int totalRuns;
  final int totalDowntime;
  final int fallbackTotal;
  final List<DashboardPoint> monitoringPoints;
  final List<MonitoringFallbackPoint> fallbackPoints;
  final List<MonitoringCheck> checks;
  final bool isLoading;
  final String? error;
  final Future<void> Function() onRefresh;

  const _OverviewTab({
    required this.user,
    required this.selectedOrgName,
    required this.availability,
    required this.totalRuns,
    required this.totalDowntime,
    required this.fallbackTotal,
    required this.monitoringPoints,
    required this.fallbackPoints,
    required this.checks,
    required this.isLoading,
    required this.error,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final okChecks = checks.where((check) {
      return (check.lastStatus ?? '').toLowerCase() == 'ok';
    }).length;
    final alertChecks = checks.where((check) {
      return const {'failed', 'error', 'degraded'}
          .contains((check.lastStatus ?? '').toLowerCase());
    }).length;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          const _SectionTitle(
            title: 'Reports and monitoring',
            subtitle:
                'Analytics and operational health for the active organization.',
          ),
          const SizedBox(height: 14),
          if (user != null) ...[
            _Panel(
              child: Row(
                children: [
                  _AvatarLabel(name: user!.name),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user!.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _VpColors.foreground,
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          selectedOrgName ?? user!.email,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _VpColors.mutedForeground,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _StatusPill(
                    label: isLoading ? 'Syncing' : 'Live',
                    tone: isLoading ? _Tone.info : _Tone.success,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],
          GridView.count(
            crossAxisCount: MediaQuery.of(context).size.width >= 680 ? 4 : 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.42,
            children: [
              _KpiCard(
                title: 'Availability',
                description: '30 days',
                value: '$availability%',
                hint: '$totalDowntime downtime runs',
                tone: _Tone.success,
              ),
              _KpiCard(
                title: 'Uptime status',
                description: 'Monitoring checks',
                value: checks.isEmpty ? '--' : '$okChecks/${checks.length} OK',
                hint: '$alertChecks need attention',
                tone: alertChecks > 0 ? _Tone.warning : _Tone.success,
              ),
              _KpiCard(
                title: 'Monitoring runs',
                description: '30 days',
                value: '$totalRuns',
                hint: 'Across active checks',
                tone: _Tone.info,
              ),
              _KpiCard(
                title: 'Fallback events',
                description: '30 days',
                value: '$fallbackTotal',
                hint: 'Context resolution',
                tone: fallbackTotal > 0 ? _Tone.warning : _Tone.neutral,
              ),
            ],
          ),
          const SizedBox(height: 14),
          _TrendPanel(
            monitoringPoints: monitoringPoints,
            fallbackPoints: fallbackPoints,
            totalDowntime: totalDowntime,
            fallbackTotal: fallbackTotal,
          ),
          const SizedBox(height: 14),
          _MonitoringDistribution(checks: checks),
          if (isLoading) ...[
            const SizedBox(height: 14),
            const LinearProgressIndicator(),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            _InlineMessage(message: error!, tone: _Tone.danger),
          ],
        ],
      ),
    );
  }
}

class _MonitoringTab extends StatelessWidget {
  final List<MonitoringCheck> checks;
  final bool hasActiveFilters;
  final TextEditingController searchController;
  final String? selectedType;
  final List<String> typeOptions;
  final Set<String> pendingRunCheckIds;
  final bool isLoading;
  final String? error;
  final Future<void> Function() onRefresh;
  final Future<void> Function(String checkId) onRunNow;
  final VoidCallback onSearchChanged;
  final Future<void> Function() onSearchSubmitted;
  final void Function(String? value) onTypeChanged;
  final VoidCallback onClearFilters;
  final void Function(MonitoringCheck check) onOpenCheck;

  const _MonitoringTab({
    required this.checks,
    required this.hasActiveFilters,
    required this.searchController,
    required this.selectedType,
    required this.typeOptions,
    required this.pendingRunCheckIds,
    required this.isLoading,
    required this.error,
    required this.onRefresh,
    required this.onRunNow,
    required this.onSearchChanged,
    required this.onSearchSubmitted,
    required this.onTypeChanged,
    required this.onClearFilters,
    required this.onOpenCheck,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          const _SectionTitle(
            title: 'Monitoring',
            subtitle: 'Search and filter checks like the web dashboard.',
            icon: Icons.radar_outlined,
          ),
          const SizedBox(height: 14),
          _Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: searchController,
                  textInputAction: TextInputAction.search,
                  onChanged: (_) => onSearchChanged(),
                  onSubmitted: (_) => onSearchSubmitted(),
                  decoration: InputDecoration(
                    labelText: 'Search',
                    hintText: 'Checks, endpoint, status…',
                    prefixIcon: const Icon(Icons.search, size: 18),
                    suffixIcon: searchController.text.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'Clear search',
                            onPressed: () {
                              searchController.clear();
                              onSearchChanged();
                              onSearchSubmitted();
                            },
                            icon: const Icon(Icons.close, size: 18),
                          ),
                  ),
                ),
                const SizedBox(height: 12),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _TypeTabChip(
                        label: 'All',
                        selected: selectedType == null,
                        onTap: () => onTypeChanged(null),
                      ),
                      ...typeOptions.map(
                        (type) => _TypeTabChip(
                          label: type == 'https' ? 'HTTPS' : type.toUpperCase(),
                          selected: selectedType == type,
                          onTap: () => onTypeChanged(type),
                        ),
                      ),
                    ],
                  ),
                ),
                if (hasActiveFilters) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: onClearFilters,
                      icon: const Icon(Icons.filter_alt_off_outlined, size: 18),
                      label: const Text('Clear filters'),
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '${checks.length} check${checks.length == 1 ? '' : 's'}',
            style: const TextStyle(
              color: _VpColors.mutedForeground,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          _MonitoringDistribution(checks: checks),
          const SizedBox(height: 10),
          const Text(
            'Use History & uptime per check (same page as web: filters, then Logs or Uptime summary).',
            style: TextStyle(
              color: _VpColors.mutedForeground,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 14),
          _Panel(
            padding: EdgeInsets.zero,
            child: checks.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      hasActiveFilters
                          ? 'No checks match the current filters.'
                          : 'No monitoring checks configured yet.',
                      style: const TextStyle(color: _VpColors.mutedForeground),
                    ),
                  )
                : Column(
                    children: checks.map((check) {
                      final isPending = pendingRunCheckIds.contains(check.id);
                      return _MonitoringCheckRow(
                        check: check,
                        isPending: isPending,
                        onOpenHistoryUptime: () => onOpenCheck(check),
                        onRunNow: (isLoading || isPending)
                            ? null
                            : () => onRunNow(check.id),
                      );
                    }).toList(),
                  ),
          ),
          if (isLoading) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            _InlineMessage(message: error!, tone: _Tone.danger),
          ],
        ],
      ),
    );
  }
}

class _TypeTabChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _TypeTabChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        showCheckmark: false,
        selectedColor: _VpColors.primary.withValues(alpha: 0.14),
        labelStyle: TextStyle(
          color: selected ? _VpColors.primary : _VpColors.foreground,
          fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          fontSize: 12,
        ),
        side: BorderSide(
          color: selected ? _VpColors.primary : _VpColors.border,
        ),
      ),
    );
  }
}

class _ProfileTab extends StatelessWidget {
  final UserProfile? user;
  final String? organizationId;
  final bool isLoading;
  final String? error;
  final Future<void> Function() onRefresh;
  final Future<void> Function(String? organizationId) onChangeOrganization;
  final Future<void> Function() onLogout;

  const _ProfileTab({
    required this.user,
    required this.organizationId,
    required this.isLoading,
    required this.error,
    required this.onRefresh,
    required this.onChangeOrganization,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          const _SectionTitle(
            title: 'Profile',
            subtitle: 'Account and organization context.',
            icon: Icons.person_outline,
          ),
          const SizedBox(height: 14),
          if (user != null)
            _Panel(
              child: Row(
                children: [
                  _AvatarLabel(name: user!.name, size: 46),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user!.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _VpColors.foreground,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user!.email,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: _VpColors.mutedForeground,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _StatusPill(
                    label: user!.isAdmin ? 'Admin' : 'Member',
                    tone: user!.isAdmin ? _Tone.info : _Tone.neutral,
                  ),
                ],
              ),
            ),
          const SizedBox(height: 14),
          if (user != null && user!.organizations.isNotEmpty)
            DropdownButtonFormField<String>(
              initialValue: organizationId,
              decoration: const InputDecoration(
                labelText: 'Organization context',
                prefixIcon: Icon(Icons.apartment_outlined, size: 18),
              ),
              items: user!.organizations
                  .map(
                    (org) => DropdownMenuItem<String>(
                      value: org.id,
                      child: Text(org.name),
                    ),
                  )
                  .toList(),
              onChanged: onChangeOrganization,
            ),
          if (isLoading) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            _InlineMessage(message: error!, tone: _Tone.danger),
          ],
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onLogout,
            icon: const Icon(Icons.logout, size: 18),
            label: const Text('Log out'),
          ),
        ],
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: _VpColors.foreground,
            borderRadius: BorderRadius.circular(8),
          ),
          alignment: Alignment.center,
          child: const Text(
            'VP',
            style: TextStyle(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        const SizedBox(width: 12),
        const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'VendorPulse',
              style: TextStyle(
                color: _VpColors.foreground,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            SizedBox(height: 2),
            Text(
              'Operations dashboard',
              style: TextStyle(
                color: _VpColors.mutedForeground,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _Panel extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;

  const _Panel({
    required this.child,
    this.padding = const EdgeInsets.all(14),
  });

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: _VpColors.card,
        border: Border.all(color: _VpColors.border),
        borderRadius: BorderRadius.circular(8),
        boxShadow: const [
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 8,
            offset: Offset(0, 1),
          ),
        ],
      ),
      child: Padding(
        padding: padding,
        child: child,
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final String? subtitle;
  final IconData? icon;

  const _SectionTitle({
    required this.title,
    this.subtitle,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 18, color: _VpColors.primary),
          const SizedBox(width: 8),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: _VpColors.foreground,
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 3),
                Text(
                  subtitle!,
                  style: const TextStyle(
                    color: _VpColors.mutedForeground,
                    fontSize: 12,
                    height: 1.3,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _KpiCard extends StatelessWidget {
  final String title;
  final String description;
  final String value;
  final String hint;
  final _Tone tone;

  const _KpiCard({
    required this.title,
    required this.description,
    required this.value,
    required this.hint,
    required this.tone,
  });

  @override
  Widget build(BuildContext context) {
    return _Panel(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: _VpColors.mutedForeground,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              _Dot(tone: tone),
            ],
          ),
          Text(
            description,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _VpColors.mutedForeground,
              fontSize: 11,
            ),
          ),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _VpColors.foreground,
              fontSize: 23,
              fontWeight: FontWeight.w800,
              letterSpacing: 0,
            ),
          ),
          Text(
            hint,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: _VpColors.mutedForeground,
              fontSize: 11,
            ),
          ),
        ],
      ),
    );
  }
}

class _TrendPanel extends StatelessWidget {
  final List<DashboardPoint> monitoringPoints;
  final List<MonitoringFallbackPoint> fallbackPoints;
  final int totalDowntime;
  final int fallbackTotal;

  const _TrendPanel({
    required this.monitoringPoints,
    required this.fallbackPoints,
    required this.totalDowntime,
    required this.fallbackTotal,
  });

  @override
  Widget build(BuildContext context) {
    final averageAvailability = monitoringPoints.isEmpty
        ? null
        : monitoringPoints
                .map((point) => point.availabilityRatio ?? 1)
                .reduce((a, b) => a + b) /
            monitoringPoints.length *
            100;

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            title: '30-day trend strip',
            subtitle: 'Downtime spikes and fallback activity over time.',
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _MiniStat(
                  label: 'Availability avg',
                  value: averageAvailability == null
                      ? '--'
                      : '${averageAvailability.toStringAsFixed(1)}%',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MiniStat(
                  label: 'Downtime events',
                  value: '$totalDowntime',
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _BarStrip(
            title: 'Downtime runs by day',
            values:
                monitoringPoints.map((point) => point.downtimeRuns).toList(),
            color: _VpColors.rose,
          ),
          const SizedBox(height: 12),
          _BarStrip(
            title: 'Create context fallbacks',
            values: fallbackPoints.map((point) => point.count).toList(),
            color: _VpColors.amber,
            trailing: '$fallbackTotal events',
          ),
        ],
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final String label;
  final String value;

  const _MiniStat({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: _VpColors.muted.withValues(alpha: 0.55),
        border: Border.all(color: _VpColors.border),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _VpColors.mutedForeground,
                fontSize: 11,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: const TextStyle(
                color: _VpColors.foreground,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BarStrip extends StatelessWidget {
  final String title;
  final List<int> values;
  final Color color;
  final String? trailing;

  const _BarStrip({
    required this.title,
    required this.values,
    required this.color,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    final data = values.isEmpty ? List<int>.filled(18, 0) : values;
    final maxValue = math.max(1, data.reduce(math.max));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: _VpColors.mutedForeground,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            if (trailing != null)
              Text(
                trailing!,
                style: const TextStyle(
                  color: _VpColors.mutedForeground,
                  fontSize: 11,
                ),
              ),
          ],
        ),
        const SizedBox(height: 6),
        Container(
          height: 58,
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: _VpColors.muted.withValues(alpha: 0.55),
            border: Border.all(color: _VpColors.border),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final value in data)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 1),
                    child: Align(
                      alignment: Alignment.bottomCenter,
                      child: FractionallySizedBox(
                        heightFactor: math.max(0.14, value / maxValue),
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.82),
                            borderRadius: BorderRadius.circular(3),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MonitoringDistribution extends StatelessWidget {
  final List<MonitoringCheck> checks;

  const _MonitoringDistribution({required this.checks});

  @override
  Widget build(BuildContext context) {
    final counts = <String, int>{
      'ok': 0,
      'degraded': 0,
      'failed': 0,
      'error': 0,
      'skipped': 0,
      'unknown': 0,
    };

    for (final check in checks) {
      final status = (check.lastStatus ?? '').toLowerCase();
      final key = counts.containsKey(status) ? status : 'unknown';
      counts[key] = (counts[key] ?? 0) + 1;
    }

    final items = [
      _StatusItem(
          label: 'OK', count: counts['ok'] ?? 0, color: _VpColors.emerald),
      _StatusItem(
          label: 'Degraded',
          count: counts['degraded'] ?? 0,
          color: _VpColors.amber),
      _StatusItem(
          label: 'Failed', count: counts['failed'] ?? 0, color: _VpColors.rose),
      _StatusItem(
          label: 'Error', count: counts['error'] ?? 0, color: _VpColors.rose),
      _StatusItem(
          label: 'Skipped',
          count: counts['skipped'] ?? 0,
          color: _VpColors.zinc),
      _StatusItem(
          label: 'Unknown',
          count: counts['unknown'] ?? 0,
          color: _VpColors.zinc),
    ];

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _SectionTitle(
            title: 'Status distribution',
            subtitle: 'Loaded monitoring checks by latest status.',
          ),
          const SizedBox(height: 14),
          if (checks.isEmpty)
            const Text(
              'No monitoring checks configured yet.',
              style: TextStyle(color: _VpColors.mutedForeground, fontSize: 12),
            )
          else ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: SizedBox(
                height: 12,
                child: Row(
                  children: [
                    for (final item in items)
                      if (item.count > 0)
                        Expanded(
                          flex: item.count,
                          child: ColoredBox(color: item.color),
                        ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              runSpacing: 8,
              spacing: 8,
              children: [
                for (final item in items)
                  _StatusSummaryChip(item: item, total: checks.length),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _StatusItem {
  final String label;
  final int count;
  final Color color;

  const _StatusItem({
    required this.label,
    required this.count,
    required this.color,
  });
}

class _StatusSummaryChip extends StatelessWidget {
  final _StatusItem item;
  final int total;

  const _StatusSummaryChip({
    required this.item,
    required this.total,
  });

  @override
  Widget build(BuildContext context) {
    final percent = total == 0 ? 0 : ((item.count / total) * 100).round();

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: _VpColors.muted.withValues(alpha: 0.55),
        border: Border.all(color: _VpColors.border),
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        '${item.label}: ${item.count} ($percent%)',
        style: const TextStyle(
          color: _VpColors.foreground,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _MonitoringCheckRow extends StatelessWidget {
  final MonitoringCheck check;
  final bool isPending;
  final VoidCallback onOpenHistoryUptime;
  final VoidCallback? onRunNow;

  const _MonitoringCheckRow({
    required this.check,
    required this.isPending,
    required this.onOpenHistoryUptime,
    required this.onRunNow,
  });

  @override
  Widget build(BuildContext context) {
    final status = isPending ? 'queued' : (check.lastStatus ?? 'unknown');
    final tone = _toneForStatus(status);

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: _VpColors.border)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              _Dot(tone: tone),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      check.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _VpColors.foreground,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${check.type.toUpperCase()} ${check.endpoint ?? ''}'.trim(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _VpColors.mutedForeground,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _StatusPill(label: status, tone: tone),
              IconButton(
                tooltip: isPending ? 'Queued' : 'Run now',
                onPressed: onRunNow,
                icon: isPending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.play_arrow, size: 20),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: onOpenHistoryUptime,
              icon: const Icon(Icons.insights_outlined, size: 18),
              label: const Text('History & uptime'),
              style: OutlinedButton.styleFrom(
                visualDensity: VisualDensity.compact,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AvatarLabel extends StatelessWidget {
  final String name;
  final double size;

  const _AvatarLabel({
    required this.name,
    this.size = 38,
  });

  @override
  Widget build(BuildContext context) {
    final initials = name
        .split(' ')
        .where((part) => part.isNotEmpty)
        .map((part) => part.characters.first)
        .take(2)
        .join()
        .toUpperCase();

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: _VpColors.muted,
        border: Border.all(color: _VpColors.border),
        borderRadius: BorderRadius.circular(999),
      ),
      alignment: Alignment.center,
      child: Text(
        initials.isEmpty ? 'U' : initials,
        style: const TextStyle(
          color: _VpColors.foreground,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String label;
  final _Tone tone;

  const _StatusPill({
    required this.label,
    required this.tone,
  });

  @override
  Widget build(BuildContext context) {
    final color = _colorForTone(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        border: Border.all(color: color.withValues(alpha: 0.28)),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _Dot extends StatelessWidget {
  final _Tone tone;

  const _Dot({required this.tone});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(
        color: _colorForTone(tone),
        borderRadius: BorderRadius.circular(999),
      ),
    );
  }
}

class _InlineMessage extends StatelessWidget {
  final String message;
  final _Tone tone;

  const _InlineMessage({
    required this.message,
    required this.tone,
  });

  @override
  Widget build(BuildContext context) {
    final color = _colorForTone(tone);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        border: Border.all(color: color.withValues(alpha: 0.28)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        message,
        style: TextStyle(color: color, fontSize: 12, height: 1.35),
      ),
    );
  }
}

_Tone _toneForStatus(String status) {
  switch (status.toLowerCase()) {
    case 'ok':
      return _Tone.success;
    case 'queued':
    case 'degraded':
      return _Tone.warning;
    case 'failed':
    case 'error':
      return _Tone.danger;
    case 'skipped':
    case 'unknown':
    default:
      return _Tone.neutral;
  }
}

Color _colorForTone(_Tone tone) {
  switch (tone) {
    case _Tone.success:
      return _VpColors.emerald;
    case _Tone.warning:
      return _VpColors.amber;
    case _Tone.danger:
      return _VpColors.rose;
    case _Tone.info:
      return _VpColors.primary;
    case _Tone.neutral:
      return _VpColors.zinc;
  }
}
