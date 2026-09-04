import 'package:flutter/material.dart';
import '../calls/active_call_screen.dart';

class Customer360Screen extends StatelessWidget {
  final String customerName;
  final String customerPhone;
  final String email;

  const Customer360Screen({
    super.key,
    required this.customerName,
    required this.customerPhone,
    this.email = 'amit@example.com',
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Customer 360° Profile', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Header Card
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFF0D221C),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFF163E32)),
            ),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 36,
                  backgroundColor: const Color(0xFF10B981).withOpacity(0.15),
                  child: Text(customerName[0], style: const TextStyle(fontSize: 28, color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
                ),
                const SizedBox(height: 12),
                Text(customerName, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white)),
                const SizedBox(height: 4),
                Text('$customerPhone • $email', style: const TextStyle(fontSize: 12, color: Colors.grey)),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(6)),
                      child: const Text('🟢 Interested Lead', style: TextStyle(fontSize: 11, color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: Colors.blue.withOpacity(0.15), borderRadius: BorderRadius.circular(6)),
                      child: const Text('CRM: HubSpot ✓', style: TextStyle(fontSize: 11, color: Colors.blueAccent, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                // Action Buttons
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _buildActionButton(Icons.phone, 'Call', () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => ActiveCallScreen(customerName: customerName, customerPhone: customerPhone),
                        ),
                      );
                    }),
                    _buildActionButton(Icons.chat_bubble, 'WhatsApp', () => Navigator.pop(context)),
                    _buildActionButton(Icons.note_add, 'Add Note', () {}),
                    _buildActionButton(Icons.hub, 'Sync CRM', () {}),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // AI Memory Card
          _buildCard(
            title: '🤖 AI Customer Memory',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text('• Interested in 10 HP heavy machinery', style: TextStyle(fontSize: 13, color: Colors.white)),
                SizedBox(height: 4),
                Text('• Budget: ₹5,00,000 INR', style: TextStyle(fontSize: 13, color: Colors.white)),
                SizedBox(height: 4),
                Text('• Preference: Morning WhatsApp follow-up', style: TextStyle(fontSize: 13, color: Colors.white)),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Multi-Channel Timeline: WhatsApp + Calls + Notes
          _buildCard(
            title: '📜 Unified Timeline (WhatsApp & Calls)',
            child: Column(
              children: [
                _buildTimelineItem(Icons.chat_bubble, 'WhatsApp Message', 'What is the price?', '10:32 AM'),
                _buildTimelineItem(Icons.phone_in_talk, 'Inbound Call (02:34)', 'AI Voice Assistant discussed catalog', '09:45 AM'),
                _buildTimelineItem(Icons.note, 'Internal Note', 'Customer verified company GST number', 'Yesterday'),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildActionButton(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        child: Column(
          children: [
            Icon(icon, color: const Color(0xFF10B981), size: 20),
            const SizedBox(height: 4),
            Text(label, style: const TextStyle(fontSize: 11, color: Colors.white)),
          ],
        ),
      ),
    );
  }

  Widget _buildCard({required String title, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF0D221C),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF163E32)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.white)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }

  Widget _buildTimelineItem(IconData icon, String title, String subtitle, String time) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: const Color(0xFF10B981), size: 16),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                Text(subtitle, style: const TextStyle(fontSize: 12, color: Colors.grey)),
              ],
            ),
          ),
          Text(time, style: const TextStyle(fontSize: 11, color: Colors.grey)),
        ],
      ),
    );
  }
}
