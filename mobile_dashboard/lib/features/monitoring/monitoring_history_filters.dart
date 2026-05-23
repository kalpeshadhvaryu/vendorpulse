import 'package:flutter/material.dart';

/// History / uptime window filters aligned with web_dashboard monitoring history.
class MonitoringHistoryFilters extends StatelessWidget {
  final DateTime fromAt;
  final DateTime toAt;
  final String? status;
  final TextEditingController searchController;
  final bool downtimeOnly;
  final bool loading;
  final VoidCallback onApply;
  final void Function(int days) onPresetDays;
  final Future<void> Function(bool isFrom) onPickDateTime;
  final void Function(String? value) onStatusChanged;
  final void Function(bool value) onDowntimeOnlyChanged;

  static const List<String> statusOptions = [
    '',
    'ok',
    'failed',
    'error',
    'degraded',
    'skipped',
  ];

  const MonitoringHistoryFilters({
    super.key,
    required this.fromAt,
    required this.toAt,
    required this.status,
    required this.searchController,
    required this.downtimeOnly,
    required this.loading,
    required this.onApply,
    required this.onPresetDays,
    required this.onPickDateTime,
    required this.onStatusChanged,
    required this.onDowntimeOnlyChanged,
  });

  String _formatLabel(DateTime value) {
    String pad(int n) => n.toString().padLeft(2, '0');
    return '${value.year}-${pad(value.month)}-${pad(value.day)} ${pad(value.hour)}:${pad(value.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Filters',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 4),
            const Text(
              'Date range, status, and search apply to History and Uptime (same as web dashboard).',
              style: TextStyle(fontSize: 11, color: Color(0xFF71717A)),
            ),
            const SizedBox(height: 12),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('From', style: TextStyle(fontSize: 12)),
              subtitle: Text(_formatLabel(fromAt)),
              trailing: const Icon(Icons.calendar_today, size: 18),
              onTap: () => onPickDateTime(true),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('To', style: TextStyle(fontSize: 12)),
              subtitle: Text(_formatLabel(toAt)),
              trailing: const Icon(Icons.calendar_today, size: 18),
              onTap: () => onPickDateTime(false),
            ),
            const SizedBox(height: 8),
            DropdownButtonFormField<String?>(
              value: status == null || status!.isEmpty ? null : status,
              decoration: const InputDecoration(
                labelText: 'Status',
                isDense: true,
              ),
              items: const [
                DropdownMenuItem<String?>(value: null, child: Text('All')),
                DropdownMenuItem<String?>(value: 'ok', child: Text('ok')),
                DropdownMenuItem<String?>(value: 'failed', child: Text('failed')),
                DropdownMenuItem<String?>(value: 'error', child: Text('error')),
                DropdownMenuItem<String?>(value: 'degraded', child: Text('degraded')),
                DropdownMenuItem<String?>(value: 'skipped', child: Text('skipped')),
              ],
              onChanged: onStatusChanged,
            ),
            const SizedBox(height: 10),
            TextField(
              controller: searchController,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => onApply(),
              decoration: const InputDecoration(
                labelText: 'Search',
                hintText: 'Message, status, HTTP…',
                prefixIcon: Icon(Icons.search, size: 18),
                isDense: true,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ActionChip(
                  label: const Text('Last 7 days'),
                  onPressed: loading ? null : () => onPresetDays(7),
                ),
                ActionChip(
                  label: const Text('Last 30 days'),
                  onPressed: loading ? null : () => onPresetDays(30),
                ),
                ActionChip(
                  label: const Text('Today'),
                  onPressed: loading ? null : () => onPresetDays(1),
                ),
                FilterChip(
                  label: const Text('Downtime only'),
                  selected: downtimeOnly,
                  onSelected: loading ? null : onDowntimeOnlyChanged,
                ),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: loading ? null : onApply,
              icon: loading
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.filter_alt, size: 18),
              label: Text(loading ? 'Loading…' : 'Apply filters'),
            ),
          ],
        ),
      ),
    );
  }
}
