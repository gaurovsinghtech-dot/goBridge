import 'package:flutter/material.dart';
import 'active_call_screen.dart';

class IncomingCallScreen extends StatelessWidget {
  final String callerName;
  final String callerNumber;
  final String leadStatus;
  final bool isCrmConnected;

  const IncomingCallScreen({
    super.key,
    this.callerName = 'Amit Kumar',
    this.callerNumber = '+91 98765 43210',
    this.leadStatus = 'Hot Lead',
    this.isCrmConnected = true,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF071410),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Header
              Column(
                children: [
                  const SizedBox(height: 20),
                  const Text('📞 Incoming Business Call', style: TextStyle(fontSize: 16, color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
                  const SizedBox(height: 24),
                  CircleAvatar(
                    radius: 48,
                    backgroundColor: const Color(0xFF10B981).withOpacity(0.15),
                    child: const Icon(Icons.person, size: 54, color: Color(0xFF10B981)),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    callerName,
                    style: const TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    callerNumber,
                    style: const TextStyle(fontSize: 14, color: Colors.grey),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: Colors.amber.withOpacity(0.2), borderRadius: BorderRadius.circular(6)),
                        child: Text('Lead: $leadStatus', style: const TextStyle(fontSize: 11, color: Colors.amber, fontWeight: FontWeight.bold)),
                      ),
                      if (isCrmConnected) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(color: Colors.blue.withOpacity(0.2), borderRadius: BorderRadius.circular(6)),
                          child: const Text('CRM: Connected', style: TextStyle(fontSize: 11, color: Colors.blueAccent, fontWeight: FontWeight.bold)),
                        ),
                      ],
                    ],
                  ),
                ],
              ),

              // Action Buttons: [ Decline ] and [ Answer ]
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  Column(
                    children: [
                      InkWell(
                        onTap: () => Navigator.pop(context),
                        borderRadius: BorderRadius.circular(36),
                        child: Container(
                          width: 68,
                          height: 68,
                          decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                          child: const Icon(Icons.call_end, color: Colors.white, size: 32),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text('Decline', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                  Column(
                    children: [
                      InkWell(
                        onTap: () {
                          Navigator.pushReplacement(
                            context,
                            MaterialPageRoute(
                              builder: (context) => ActiveCallScreen(
                                customerName: callerName,
                                customerPhone: callerNumber,
                              ),
                            ),
                          );
                        },
                        borderRadius: BorderRadius.circular(36),
                        child: Container(
                          width: 68,
                          height: 68,
                          decoration: const BoxDecoration(color: Color(0xFF10B981), shape: BoxShape.circle),
                          child: const Icon(Icons.call, color: Colors.black, size: 32),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text('Answer', style: TextStyle(fontSize: 12, color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
