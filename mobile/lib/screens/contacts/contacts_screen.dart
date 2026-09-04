import 'package:flutter/material.dart';
import '../calls/active_call_screen.dart';
import '../chat/chat_conversation_screen.dart';
import 'customer_360_screen.dart';

class ContactsScreen extends StatelessWidget {
  const ContactsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('👥 Contacts', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        actions: [
          IconButton(icon: const Icon(Icons.person_add), onPressed: () {}),
        ],
      ),
      body: Column(
        children: [
          // Search Bar
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search contacts...',
                prefixIcon: const Icon(Icons.search, color: Colors.grey),
                filled: true,
                fillColor: const Color(0xFF0D221C),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 0),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              ),
            ),
          ),

          // Contacts List
          Expanded(
            child: ListView(
              children: [
                _buildSectionHeader('A'),
                _buildContactTile(context, 'Amit Kumar', '+91 98765 43210', '🟢 Lead'),
                _buildContactTile(context, 'Ananya Singh', '+91 98765 11111', '🟡 Customer'),
                _buildSectionHeader('B'),
                _buildContactTile(context, 'Bikash Singh', '+91 98765 22222', '🟡 Customer'),
                _buildSectionHeader('P'),
                _buildContactTile(context, 'Priya Sharma', '+91 98765 33333', '🔵 Prospect'),
                _buildSectionHeader('R'),
                _buildContactTile(context, 'Rahul Verma', '+91 98765 44444', '🟢 Lead'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String letter) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      color: const Color(0xFF071410),
      child: Text(letter, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF10B981), fontSize: 13)),
    );
  }

  Widget _buildContactTile(BuildContext context, String name, String phone, String tag) {
    return ListTile(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => Customer360Screen(customerName: name, customerPhone: phone),
          ),
        );
      },
      leading: CircleAvatar(
        backgroundColor: const Color(0xFF10B981).withOpacity(0.15),
        child: Text(name[0], style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
      ),
      title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
      subtitle: Row(
        children: [
          Text(phone, style: const TextStyle(fontSize: 12, color: Colors.grey)),
          const SizedBox(width: 8),
          Text(tag, style: const TextStyle(fontSize: 11)),
        ],
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline, color: Color(0xFF10B981), size: 20),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ChatConversationScreen(conversationId: '1', customerName: name, customerPhone: phone),
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.phone_outlined, color: Color(0xFF06B6D4), size: 20),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ActiveCallScreen(customerName: name, customerPhone: phone),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
