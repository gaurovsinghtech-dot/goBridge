import 'package:flutter/material.dart';

class CallSummaryDialog extends StatelessWidget {
  final String customerName;
  final String duration;

  const CallSummaryDialog({
    super.key,
    required this.customerName,
    required this.duration,
  });

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: const Color(0xFF0D221C),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: const BorderSide(color: Color(0xFF163E32))),
      title: Row(
        children: const [
          Icon(Icons.auto_awesome, color: Colors.purpleAccent, size: 20),
          SizedBox(width: 8),
          Text('Call Summary', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
        ],
      ),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Customer: $customerName', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
            Text('Duration: $duration', style: const TextStyle(color: Colors.grey, fontSize: 12)),
            const Divider(color: Color(0xFF163E32), height: 24),
            const Text('Intent:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
            const Text('Interested in enterprise package', style: TextStyle(fontSize: 13, color: Colors.white)),
            const SizedBox(height: 12),
            const Text('Lead Qualification:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
            Container(
              margin: const EdgeInsets.only(top: 4),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: Colors.amber.withOpacity(0.2), borderRadius: BorderRadius.circular(4)),
              child: const Text('🔥 Hot Lead', style: TextStyle(fontSize: 11, color: Colors.amber, fontWeight: FontWeight.bold)),
            ),
            const SizedBox(height: 12),
            const Text('AI Summary:', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.grey)),
            const Text(
              'Customer wants pricing and requested a callback tomorrow at 2:00 PM.',
              style: TextStyle(fontSize: 13, color: Colors.white70),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close', style: TextStyle(color: Colors.grey)),
        ),
        ElevatedButton(
          onPressed: () {
            Navigator.pop(context);
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('CRM Lead created successfully in HubSpot!')),
            );
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF10B981),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: const Text('Create CRM Lead', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
        ),
      ],
    );
  }
}
