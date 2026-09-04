import 'package:flutter/material.dart';
import 'chat_conversation_screen.dart';

class ChatInboxScreen extends StatefulWidget {
  const ChatInboxScreen({super.key});

  @override
  State<ChatInboxScreen> createState() => _ChatInboxScreenState();
}

class _ChatInboxScreenState extends State<ChatInboxScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  final List<String> _tabs = [
    'All',
    'Unread',
    'Assigned to me',
    'AI',
    'Human',
    'Archived',
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
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
        title: const Text('WhatsApp Chat', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        actions: [
          IconButton(icon: const Icon(Icons.search), onPressed: () {}),
          IconButton(icon: const Icon(Icons.filter_list), onPressed: () {}),
        ],
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          indicatorColor: const Color(0xFF10B981),
          labelColor: const Color(0xFF10B981),
          unselectedLabelColor: Colors.grey,
          labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
          tabs: _tabs.map((tab) => Tab(text: tab)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: _tabs.map((tab) => _buildConversationList(tab)).toList(),
      ),
    );
  }

  Widget _buildConversationList(String filter) {
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        _buildChatRow(
          id: '1',
          name: 'Amit Kumar',
          channel: '🟢 WhatsApp',
          lastMessage: "What's the price for the machine?",
          time: '10:32 AM',
          unread: 2,
          isAi: true,
        ),
        _buildChatRow(
          id: '2',
          name: 'Priya Sharma',
          channel: '🟢 WhatsApp',
          lastMessage: 'I want a callback at 2 PM',
          time: '09:45 AM',
          unread: 0,
          isAi: false,
        ),
        _buildChatRow(
          id: '3',
          name: 'Bikash Singh',
          channel: '🟢 WhatsApp',
          lastMessage: 'Can you share the brochure PDF?',
          time: 'Yesterday',
          unread: 0,
          isAi: true,
        ),
      ],
    );
  }

  Widget _buildChatRow({
    required String id,
    required String name,
    required String channel,
    required String lastMessage,
    required String time,
    required int unread,
    required bool isAi,
  }) {
    return ListTile(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ChatConversationScreen(
              conversationId: id,
              customerName: name,
              customerPhone: '+91 98765 43210',
            ),
          ),
        );
      },
      leading: Stack(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: const Color(0xFF10B981).withOpacity(0.2),
            child: Text(name[0], style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold, fontSize: 16)),
          ),
          Positioned(
            bottom: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.all(2),
              decoration: const BoxDecoration(color: Colors.black, shape: BoxShape.circle),
              child: const Icon(Icons.circle, color: Color(0xFF10B981), size: 10),
            ),
          ),
        ],
      ),
      title: Row(
        mainAxisAlignment: MainAxisAlignment.between,
        children: [
          Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
          Text(time, style: TextStyle(color: unread > 0 ? const Color(0xFF10B981) : Colors.grey, fontSize: 11)),
        ],
      ),
      subtitle: Row(
        children: [
          Expanded(
            child: Text(
              lastMessage,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: unread > 0 ? Colors.white : Colors.grey, fontSize: 13),
            ),
          ),
          if (unread > 0)
            CircleAvatar(
              radius: 9,
              backgroundColor: const Color(0xFF10B981),
              child: Text('$unread', style: const TextStyle(fontSize: 10, color: Colors.black, fontWeight: FontWeight.bold)),
            ),
        ],
      ),
    );
  }
}
