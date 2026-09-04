import 'package:flutter/material.dart';

class HomeScreen extends StatelessWidget {
  final VoidCallback onNavigateToChat;
  final VoidCallback onNavigateToCalls;

  const HomeScreen({
    super.key,
    required this.onNavigateToChat,
    required this.onNavigateToCalls,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: const [
            Text('Growbridge Connect', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            Text('Good morning, Rahul', style: TextStyle(fontSize: 12, color: Colors.grey)),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_none),
            onPressed: () {},
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // 3 Core KPI Metric Cards
          Row(
            children: [
              Expanded(
                child: _buildKpiCard(
                  icon: Icons.chat_bubble,
                  iconColor: const Color(0xFF10B981),
                  title: 'WhatsApp',
                  count: '128',
                  onTap: onNavigateToChat,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildKpiCard(
                  icon: Icons.phone,
                  iconColor: const Color(0xFF06B6D4),
                  title: 'Calls',
                  count: '24',
                  onTap: onNavigateToCalls,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildKpiCard(
                  icon: Icons.people,
                  iconColor: const Color(0xFF8B5CF6),
                  title: 'Leads',
                  count: '56',
                  onTap: () {},
                ),
              ),
            ],
          ),

          const SizedBox(height: 24),

          // Section Header: Recent Conversations
          Row(
            mainAxisAlignment: MainAxisAlignment.between,
            children: [
              const Text(
                'Recent Conversations',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              TextButton(
                onPressed: onNavigateToChat,
                child: const Text('See all', style: TextStyle(color: Color(0xFF10B981))),
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Recent Conversations List Items
          _buildConversationTile(
            name: 'Amit Kumar',
            message: "What's the price for the 10 HP model?",
            time: '10:32 AM',
            unread: 2,
            isAi: true,
          ),
          _buildConversationTile(
            name: 'Priya Sharma',
            message: 'I want a callback regarding bulk discount',
            time: '09:45 AM',
            unread: 0,
            isAi: false,
          ),
          _buildConversationTile(
            name: 'Rahul Verma',
            message: 'Payment of ₹25,000 completed via UPI',
            time: 'Yesterday',
            unread: 0,
            isAi: true,
          ),
        ],
      ),
    );
  }

  Widget _buildKpiCard({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String count,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        decoration: BoxDecoration(
          color: const Color(0xFF0D221C),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF163E32)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: iconColor, size: 24),
            const SizedBox(height: 12),
            Text(count, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
            const SizedBox(height: 2),
            Text(title, style: const TextStyle(fontSize: 11, color: Colors.grey)),
          ],
        ),
      ),
    );
  }

  Widget _buildConversationTile({
    required String name,
    required String message,
    required String time,
    required int unread,
    required bool isAi,
  }) {
    return Card(
      color: const Color(0xFF0D221C),
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: Color(0xFF163E32)),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
        leading: CircleAvatar(
          backgroundColor: const Color(0xFF10B981).withOpacity(0.15),
          child: Text(name[0], style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
        ),
        title: Row(
          children: [
            Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))),
            Text(time, style: const TextStyle(color: Colors.grey, fontSize: 11)),
          ],
        ),
        subtitle: Row(
          children: [
            if (isAi)
              Container(
                margin: const EdgeInsets.only(right: 6),
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                decoration: BoxDecoration(
                  color: Colors.purple.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Text('AI', style: TextStyle(fontSize: 9, color: Colors.purpleAccent, fontWeight: FontWeight.bold)),
              ),
            Expanded(
              child: Text(
                message,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: unread > 0 ? Colors.white : Colors.grey, fontSize: 12),
              ),
            ),
          ],
        ),
        trailing: unread > 0
            ? CircleAvatar(
                radius: 10,
                backgroundColor: const Color(0xFF10B981),
                child: Text('$unread', style: const TextStyle(fontSize: 10, color: Colors.black, fontWeight: FontWeight.bold)),
              )
            : null,
      ),
    );
  }
}
