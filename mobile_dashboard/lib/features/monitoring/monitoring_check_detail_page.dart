import 'package:flutter/material.dart';

import '../../models/api_models.dart';
import '../../services/vendorpulse_api.dart';
import 'monitoring_history_filters.dart';

class MonitoringCheckDetailPage extends StatefulWidget {
  final MonitoringCheck check;
  final String token;
  final String organizationId;
  final VendorPulseApi api;
  final Future<void> Function(String checkId)? onRunNow;

  const MonitoringCheckDetailPage({
    super.key,
    required this.check,
    required this.token,
    required this.organizationId,
    required this.api,
    this.onRunNow,
  });

  @override
  State<MonitoringCheckDetailPage> createState() =>
      _MonitoringCheckDetailPageState();
}

class _MonitoringCheckDetailPageState extends State<MonitoringCheckDetailPage>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  late final TextEditingController _logSearchController;
  late MonitoringCheck _check;

  late DateTime _fromAt;
  late DateTime _toAt;
  String? _logStatus;
  int _logsPage = 1;
  bool _downtimeOnly = false;

  List<MonitoringLog> _logs = <MonitoringLog>[];
  int _logsLastPage = 1;
  MonitoringLogSummary? _summary;

  bool _loadingLogs = false;
  bool _loadingSummary = false;
  String? _logsError;
  String? _summaryError;

  @override
  void initState() {
    super.initState();
    _check = widget.check;
    _logSearchController = TextEditingController();
    _tabController = TabController(length: 2, vsync: this);
    _applyPresetDays(7);
    _loadAll();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _logSearchController.dispose();
    super.dispose();
  }

  void _applyPresetDays(int days) {
    final to = DateTime.now();
    final from = days <= 1
        ? DateTime(to.year, to.month, to.day)
        : to.subtract(Duration(days: days));
    setState(() {
      _fromAt = from;
      _toAt = to;
      _logsPage = 1;
    });
  }

  (String fromAt, String toAt) _windowRange() {
    return (_formatDateTimeLocal(_fromAt), _formatDateTimeLocal(_toAt));
  }

  String _formatDateTimeLocal(DateTime value) {
    String pad(int n) => n.toString().padLeft(2, '0');
    return '${value.year}-${pad(value.month)}-${pad(value.day)}T${pad(value.hour)}:${pad(value.minute)}';
  }

  Future<void> _pickDateTime(bool isFrom) async {
    final initial = isFrom ? _fromAt : _toAt;
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
    );
    if (date == null || !mounted) {
      return;
    }

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) {
      return;
    }

    final picked = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );

    setState(() {
      if (isFrom) {
        _fromAt = picked;
      } else {
        _toAt = picked;
      }
      _logsPage = 1;
    });
  }

  Future<void> _loadAll() async {
    await Future.wait([_loadLogs(page: 1), _loadSummary()]);
  }

  Future<void> _loadLogs({required int page}) async {
    setState(() {
      _loadingLogs = true;
      _logsError = null;
      if (page == 1) {
        _logs = <MonitoringLog>[];
      }
    });

    final range = _windowRange();

    try {
      final result = await widget.api.fetchMonitoringLogs(
        token: widget.token,
        organizationId: widget.organizationId,
        checkId: _check.id,
        page: page,
        fromAt: range.$1,
        toAt: range.$2,
        status: _logStatus,
        search: _logSearchController.text,
        downtimeOnly: _downtimeOnly,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _logsPage = result.currentPage;
        _logsLastPage = result.lastPage;
        if (page == 1) {
          _logs = result.items;
        } else {
          _logs = [..._logs, ...result.items];
        }
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _logsError = error.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _loadingLogs = false;
        });
      }
    }
  }

  Future<void> _loadSummary() async {
    setState(() {
      _loadingSummary = true;
      _summaryError = null;
    });

    final range = _windowRange();

    try {
      final summary = await widget.api.fetchMonitoringLogSummary(
        token: widget.token,
        organizationId: widget.organizationId,
        checkId: _check.id,
        fromAt: range.$1,
        toAt: range.$2,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _summary = summary;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _summaryError = error.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _loadingSummary = false;
        });
      }
    }
  }

  String _formatUptimeSince(String? raw) {
    if (raw == null || raw.isEmpty) {
      return '—';
    }
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) {
      return raw;
    }
    final diff = DateTime.now().difference(parsed.toLocal());
    if (diff.inDays >= 1) {
      return '${diff.inDays}d';
    }
    if (diff.inHours >= 1) {
      return '${diff.inHours}h';
    }
    return '${diff.inMinutes}m';
  }

  @override
  Widget build(BuildContext context) {
    final status = (_check.lastStatus ?? 'unknown').toLowerCase();

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(_check.name, maxLines: 1, overflow: TextOverflow.ellipsis),
            const Text(
              'History & uptime',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ],
        ),
        titleSpacing: 16,
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: _HeaderCard(
              check: _check,
              status: status,
              uptimeLabel: _formatUptimeSince(_check.uptimeSince),
              onRunNow: widget.onRunNow == null
                  ? null
                  : () => widget.onRunNow!(_check.id),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: MonitoringHistoryFilters(
              fromAt: _fromAt,
              toAt: _toAt,
              status: _logStatus,
              searchController: _logSearchController,
              downtimeOnly: _downtimeOnly,
              loading: _loadingLogs || _loadingSummary,
              onApply: _loadAll,
              onPresetDays: (days) {
                _applyPresetDays(days);
                _loadAll();
              },
              onPickDateTime: _pickDateTime,
              onStatusChanged: (value) {
                setState(() {
                  _logStatus = value;
                  _logsPage = 1;
                });
              },
              onDowntimeOnlyChanged: (value) {
                setState(() {
                  _downtimeOnly = value;
                  _logsPage = 1;
                });
              },
            ),
          ),
          Material(
            color: Theme.of(context).colorScheme.surface,
            child: TabBar(
              controller: _tabController,
              labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              tabs: const [
                Tab(text: 'Logs'),
                Tab(text: 'Uptime summary'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _HistoryTab(
                  logs: _logs,
                  loading: _loadingLogs,
                  error: _logsError,
                  page: _logsPage,
                  lastPage: _logsLastPage,
                  onRefresh: () => _loadLogs(page: 1),
                  onLoadMore: _logsPage < _logsLastPage
                      ? () => _loadLogs(page: _logsPage + 1)
                      : null,
                ),
                _UptimeTab(
                  summary: _summary,
                  loading: _loadingSummary,
                  error: _summaryError,
                  fromAt: _fromAt,
                  toAt: _toAt,
                  onRefresh: _loadSummary,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  final MonitoringCheck check;
  final String status;
  final String uptimeLabel;
  final VoidCallback? onRunNow;

  const _HeaderCard({
    required this.check,
    required this.status,
    required this.uptimeLabel,
    required this.onRunNow,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${check.type.toUpperCase()} · ${check.endpoint ?? 'no endpoint'}',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF71717A)),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      _ChipLabel(label: 'Status: $status'),
                      if (check.lastStatus == 'ok' && check.uptimeSince != null)
                        _ChipLabel(label: 'Up for $uptimeLabel'),
                      _ChipLabel(
                        label: check.enabled ? 'Enabled' : 'Disabled',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (onRunNow != null)
              IconButton(
                tooltip: 'Run now',
                onPressed: onRunNow,
                icon: const Icon(Icons.play_arrow),
              ),
          ],
        ),
      ),
    );
  }
}

class _HistoryTab extends StatelessWidget {
  final List<MonitoringLog> logs;
  final bool loading;
  final String? error;
  final int page;
  final int lastPage;
  final Future<void> Function() onRefresh;
  final VoidCallback? onLoadMore;

  const _HistoryTab({
    required this.logs,
    required this.loading,
    required this.error,
    required this.page,
    required this.lastPage,
    required this.onRefresh,
    required this.onLoadMore,
  });

  @override
  Widget build(BuildContext context) {
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Text(error!, style: const TextStyle(color: Color(0xFFF43F5E))),
        ),
      );
    }

    if (loading && logs.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (logs.isEmpty) {
      return const Center(
        child: Text(
          'No runs in this window.',
          style: TextStyle(color: Color(0xFF71717A)),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        itemCount: logs.length + (onLoadMore != null ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == logs.length) {
            return Padding(
              padding: const EdgeInsets.only(top: 8),
              child: OutlinedButton(
                onPressed: loading ? null : onLoadMore,
                child: Text(loading ? 'Loading…' : 'Load more (page $page of $lastPage)'),
              ),
            );
          }

          final log = logs[index];
          return _LogRow(log: log);
        },
      ),
    );
  }
}

class _LogRow extends StatelessWidget {
  final MonitoringLog log;

  const _LogRow({required this.log});

  @override
  Widget build(BuildContext context) {
    final when = log.createdAt == null
        ? '—'
        : DateTime.tryParse(log.createdAt!)?.toLocal().toString().substring(0, 19) ??
            log.createdAt!;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _StatusDot(status: log.status),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    log.status.toUpperCase(),
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                  ),
                ),
                Text(
                  when,
                  style: const TextStyle(fontSize: 11, color: Color(0xFF71717A)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              [
                if (log.httpStatus != null) 'HTTP ${log.httpStatus}',
                if (log.responseTimeMs != null) '${log.responseTimeMs} ms',
              ].join(' · '),
              style: const TextStyle(fontSize: 12, color: Color(0xFF71717A)),
            ),
            if (log.message != null && log.message!.isNotEmpty) ...[
              const SizedBox(height: 6),
              Text(log.message!, style: const TextStyle(fontSize: 12)),
            ],
          ],
        ),
      ),
    );
  }
}

class _UptimeTab extends StatelessWidget {
  final MonitoringLogSummary? summary;
  final bool loading;
  final String? error;
  final DateTime fromAt;
  final DateTime toAt;
  final Future<void> Function() onRefresh;

  const _UptimeTab({
    required this.summary,
    required this.loading,
    required this.error,
    required this.fromAt,
    required this.toAt,
    required this.onRefresh,
  });

  String _shortDate(DateTime value) {
    String pad(int n) => n.toString().padLeft(2, '0');
    return '${value.year}-${pad(value.month)}-${pad(value.day)}';
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          Text(
            'Uptime ${_shortDate(fromAt)} → ${_shortDate(toAt)}',
            style: const TextStyle(fontSize: 12, color: Color(0xFF71717A)),
          ),
          const SizedBox(height: 12),
          if (loading && summary == null)
            const Center(child: CircularProgressIndicator())
          else if (error != null)
            Text(error!, style: const TextStyle(color: Color(0xFFF43F5E)))
          else if (summary == null)
            const Text('No summary data.', style: TextStyle(color: Color(0xFF71717A)))
          else
            _SummaryGrid(summary: summary!),
        ],
      ),
    );
  }
}

class _SummaryGrid extends StatelessWidget {
  final MonitoringLogSummary summary;

  const _SummaryGrid({required this.summary});

  @override
  Widget build(BuildContext context) {
    final ratio = summary.uptimeRatio;
    final durations = summary.durationSeconds;

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _SummaryTile(
          label: 'Uptime ratio',
          value: ratio != null ? '${(ratio * 100).toStringAsFixed(1)}%' : '—',
        ),
        _SummaryTile(
          label: 'Time up (ok)',
          value: _formatDuration(durations.up),
        ),
        _SummaryTile(
          label: 'Time down',
          value: _formatDuration(durations.down),
        ),
        _SummaryTile(
          label: 'Degraded time',
          value: _formatDuration(durations.degraded),
        ),
        _SummaryTile(
          label: 'Failed/error runs',
          value: '${summary.downtimeIncidents}',
        ),
        _SummaryTile(
          label: 'Runs in window',
          value: '${summary.logCountInWindow}',
        ),
        _SummaryTile(
          label: 'Skipped time',
          value: _formatDuration(durations.skipped),
        ),
        _SummaryTile(
          label: 'Unknown time',
          value: _formatDuration(durations.unknown),
        ),
      ],
    );
  }

  String _formatDuration(int seconds) {
    if (seconds <= 0) {
      return '0s';
    }
    final hours = seconds ~/ 3600;
    final minutes = (seconds % 3600) ~/ 60;
    final secs = seconds % 60;
    if (hours > 0) {
      return '${hours}h ${minutes}m';
    }
    if (minutes > 0) {
      return '${minutes}m ${secs}s';
    }
    return '${secs}s';
  }
}

class _SummaryTile extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryTile({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 160,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF71717A))),
              const SizedBox(height: 4),
              Text(
                value,
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ChipLabel extends StatelessWidget {
  final String label;

  const _ChipLabel({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFFF4F4F5),
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: const Color(0xFFE4E4E7)),
      ),
      child: Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}

class _StatusDot extends StatelessWidget {
  final String status;

  const _StatusDot({required this.status});

  @override
  Widget build(BuildContext context) {
    final color = switch (status.toLowerCase()) {
      'ok' => const Color(0xFF10B981),
      'degraded' => const Color(0xFFF59E0B),
      'failed' || 'error' => const Color(0xFFF43F5E),
      _ => const Color(0xFF71717A),
    };

    return Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}
