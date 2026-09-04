import 'package:flutter/material.dart';
import '../calls/active_call_screen.dart';
import '../contacts/customer_360_screen.dart';

class ChatConversationScreen extends StatefulWidget {
  final String conversationId;
  final String customerName;
  final String customerPhone;

  const ChatConversationScreen({
    super.key,
    required this.conversationId,
    required this.customerName,
    required this.customerPhone,
  });

  @override
  State<ChatConversationScreen> createState() => _ChatConversationScreenState();
}

class _ChatConversationScreenState extends State<ChatConversationScreen> {
  final TextEditingController _messageController = TextEditingController();
  bool _isAiMode = true; // true = "AI is responding", false = "Human Agent"
  String? _suggestedReply;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: InkWell(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => Customer360Screen(
                  customerName: widget.customerName,
                  customerPhone: widget.customerPhone,
                ),
              ),
            );
          },
          child: Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: const Color(0xFF10B981).withOpacity(0.2),
                child: Text(widget.customerName[0], style: const TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
              ),
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(widget.customerName, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                  const Text('WhatsApp • Lead', style: TextStyle(fontSize: 11, color: Colors.grey)),
                ],
              ),
            ],
          ),
        ),
        actions: [
          // 📞 Top-right Call Button
          IconButton(
            icon: const Icon(Icons.phone, color: Color(0xFF10B981)),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => ActiveCallScreen(
                    customerName: widget.customerName,
                    customerPhone: widget.customerPhone,
                  ),
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.more_vert),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(
        children: [
          // AI Mode Banner / Human Handoff Toggle
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: _isAiMode ? Colors.purple.withOpacity(0.15) : const Color(0xFF10B981).withOpacity(0.15),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.between,
              children: [
                Row(
                  children: [
                    Icon(_isAiMode ? Icons.auto_awesome : Icons.person, size: 16, color: _isAiMode ? Colors.purpleAccent : const Color(0xFF10B981)),
                    const SizedBox(width: 8),
                    Text(
                      _isAiMode ? '🤖 AI is responding' : '👤 Human Agent',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: _isAiMode ? Colors.purpleAccent : const Color(0xFF10B981),
                      ),
                    ),
                  ],
                ),
                TextButton(
                  onPressed: () {
                    setState(() => _isAiMode = !_isAiMode);
                  },
                  style: TextButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: Text(
                    _isAiMode ? 'Take Over' : 'Switch to AI',
                    style: const TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ),

          // Messages View
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildMessageBubble(
                  sender: 'Customer',
                  text: "What's the price for the 10 HP model?",
                  time: '10:30 AM',
                  isCustomer: true,
                ),
                _buildMessageBubble(
                  sender: 'You',
                  text: 'Our price starts at ₹4,999 with 1-year on-site warranty and free setup.',
                  time: '10:31 AM',
                  isCustomer: false,
                ),
                if (_suggestedReply != null)
                  _buildAiSuggestionBox(_suggestedReply!),
              ],
            ),
          ),

          // ✨ AI Assist Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            color: const Color(0xFF0D221C),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _buildAiChip('✨ AI Suggest Reply', () {
                    setState(() {
                      _suggestedReply = 'Would you like to schedule a 10-minute live demonstration call today?';
                    });
                  }),
                  _buildAiChip('✍️ Improve Reply', () {}),
                  _buildAiChip('🌐 Translate', () {}),
                  _buildAiChip('📝 Summarize', () {}),
                  _buildAiChip('🔥 Extract CRM Lead', () {}),
                ],
              ),
            ),
          ),

          // Message Input Field
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            color: const Color(0xFF0A1C16),
            child: Row(
              children: [
                IconButton(icon: const Icon(Icons.attach_file, color: Colors.grey), onPressed: () {}),
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: const InputDecoration(
                      hintText: 'Type message...',
                      border: InputBorder.none,
                      hintStyle: TextStyle(color: Colors.grey, fontSize: 14),
                    ),
                  ),
                ),
                IconButton(icon: const Icon(Icons.mic, color: Colors.grey), onPressed: () {}),
                IconButton(
                  icon: const Icon(Icons.send, color: Color(0xFF10B981)),
                  onPressed: () {
                    if (_messageController.text.isNotEmpty) {
                      _messageController.clear();
                    }
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessageBubble({
    required String sender,
    required String text,
    required String time,
    required bool isCustomer,
  }) {
    return Align(
      alignment: isCustomer ? Alignment.centerLeft : Alignment.centerRight,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        constraints: const BoxConstraints(maxWidth: 280),
        decoration: BoxDecoration(
          color: isCustomer ? const Color(0xFF163E32) : const Color(0xFF047857),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(text, style: const TextStyle(fontSize: 14, color: Colors.white)),
            const SizedBox(height: 4),
            Align(
              alignment: Alignment.bottomRight,
              child: Text(time, style: const TextStyle(fontSize: 10, color: Colors.white70)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAiSuggestionBox(String suggestion) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.purple.withOpacity(0.12),
        border: Border.all(color: Colors.purple.withOpacity(0.3)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.auto_awesome, color: Colors.purpleAccent, size: 14),
              SizedBox(width: 6),
              Text('AI suggested reply', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.purpleAccent)),
            ],
          ),
          const SizedBox(height: 6),
          Text(suggestion, style: const TextStyle(fontSize: 13, color: Colors.white)),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(
                onPressed: () => setState(() => _suggestedReply = null),
                child: const Text('Dismiss', style: TextStyle(fontSize: 11, color: Colors.grey)),
              ),
              ElevatedButton(
                onPressed: () {
                  _messageController.text = suggestion;
                  setState(() => _suggestedReply = null);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF10B981),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  minimumSize: Size.zero,
                ),
                child: const Text('Use Reply', style: TextStyle(fontSize: 11, color: Colors.black, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildAiChip(String label, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: ActionChip(
        label: Text(label, style: const TextStyle(fontSize: 11, color: Colors.white)),
        backgroundColor: const Color(0xFF163E32),
        onPressed: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 4),
      ),
    );
  }
}
