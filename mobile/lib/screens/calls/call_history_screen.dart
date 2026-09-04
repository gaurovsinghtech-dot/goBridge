import 'package:flutter/material.dart';
import 'active_call_screen.dart';
import 'call_summary_dialog.dart';

class CallHistoryScreen extends StatefulWidget {
  const CallHistoryScreen({super.key});

  @override
  State<CallHistoryScreen> createState() => _CallHistoryScreenState();
}

class _CallHistoryScreenState extends State<CallHistoryScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  final List<String> _filters = [
    'All',
    'Incoming',
    'Outgoing',
    'Missed',
    'AI Calls',
    'Human Calls',
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _filters.length, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('📞 Calls', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          indicatorColor: const Color(0xFF10B981),
          labelColor: const Color(0xFF10B981),
          unselectedLabelColor: Colors.grey,
          labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
          tabs: _filters.map((f) => Tab(text: f)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: _filters.map((f) => _buildCallList(f)).toList(),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: const Color(0xFF10B981),
        child: const Icon(Icons.dialpad, color: Colors.black),
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => const ActiveCallScreen(
                customerName: 'New Call',
                customerPhone: '+91 98765 00000',
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildCallList(String filter) {
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        _buildSectionHeader('Today'),
        _buildCallItem(
          name: 'Amit Kumar',
          direction: '↗ Outgoing',
          duration: '02:34',
          time: '10:30 AM',
          isMissed: false,
          isAi: true,
        ),
        _buildCallItem(
          name: 'Priya Sharma',
          direction: '↙ Incoming',
          duration: '05:12',
          time: '09:45 AM',
          isMissed: false,
          isAi: false,
        ),
        _buildCallItem(
          name: 'Rahul',
          direction: '↗ Outgoing',
          duration: 'No answer',
          time: '09:10 AM',
          isMissed: true,
          isAi: false,
        ),
      ],
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Text(title, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
    );
  }

  Widget _buildCallItem({
    required String name,
    required String direction,
    required String duration,
    required String time,
    required bool isMissed,
    required bool isAi,
  }) {
    return ListTile(
      onTap: () {
        showDialog(
          context: context,
          builder: (context) => CallSummaryDialog(customerName: name, duration: duration),
        );
      },
      leading: CircleAvatar(
        backgroundColor: isMissed ? Colors.red.withOpacity(0.15) : const Color(0xFF10B981).withOpacity(0.15),
        child: Icon(
          isMissed ? Icons.call_missed : (direction.contains('Incoming') ? Icons.call_received : Icons.call_made),
          color: isMissed ? Colors.redAccent : const Color(0xFF10B981),
          size: 18,
        ),
      ),
      title: Row(
        children: [
          Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
          if (isAi) ...[
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
              decoration: BoxDecoration(color: Colors.purple.withOpacity(0.2), borderRadius: BorderRadius.circular(4)),
              child: const Text('AI Voice', style: TextStyle(fontSize: 9, color: Colors.purpleAccent, fontWeight: FontWeight.bold)),
            ),
          ],
        ],
      ),
      subtitle: Text('$direction • $duration', style: TextStyle(color: isMissed ? Colors.redAccent : Colors.grey, fontSize: 12)),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(time, style: const TextStyle(color: Colors.grey, fontSize: 11)),
          const SizedBox(width: 8),
          IconButton(
            icon: const Icon(Icons.phone, color: Color(0xFF10B981), size: 20),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ActiveCallScreen(customerName: name, customerPhone: '+91 98765 43210'),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
