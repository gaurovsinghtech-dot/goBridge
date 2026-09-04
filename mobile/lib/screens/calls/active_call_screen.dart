import 'dart:async';
import 'package:flutter/material.dart';

class ActiveCallScreen extends StatefulWidget {
  final String customerName;
  final String customerPhone;

  const ActiveCallScreen({
    super.key,
    required this.customerName,
    required this.customerPhone,
  });

  @override
  State<ActiveCallScreen> createState() => _ActiveCallScreenState();
}

class _ActiveCallScreenState extends State<ActiveCallScreen> {
  int _seconds = 0;
  Timer? _timer;
  bool _isMuted = false;
  bool _isSpeaker = false;
  bool _isOnHold = false;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      setState(() => _seconds++);
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String _formatDuration(int seconds) {
    final mins = (seconds ~/ 60).toString().padLeft(2, '0');
    final secs = (seconds % 60).toString().padLeft(2, '0');
    return '$mins:$secs';
  }

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
              // Caller Info Header
              Column(
                children: [
                  const SizedBox(height: 20),
                  CircleAvatar(
                    radius: 48,
                    backgroundColor: const Color(0xFF10B981).withOpacity(0.15),
                    child: const Icon(Icons.person, size: 54, color: Color(0xFF10B981)),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    widget.customerName,
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.customerPhone,
                    style: const TextStyle(fontSize: 14, color: Colors.grey),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _isOnHold ? 'On Hold' : 'Calling... ${_formatDuration(_seconds)}',
                    style: TextStyle(
                      fontSize: 14,
                      color: _isOnHold ? Colors.amber : const Color(0xFF10B981),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),

              // VoIP Control Grid
              Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _buildControlButton(
                        icon: _isMuted ? Icons.mic_off : Icons.mic,
                        label: 'Mute',
                        isActive: _isMuted,
                        onTap: () => setState(() => _isMuted = !_isMuted),
                      ),
                      _buildControlButton(
                        icon: _isSpeaker ? Icons.volume_up : Icons.volume_down,
                        label: 'Speaker',
                        isActive: _isSpeaker,
                        onTap: () => setState(() => _isSpeaker = !_isSpeaker),
                      ),
                      _buildControlButton(
                        icon: Icons.dialpad,
                        label: 'Keypad',
                        isActive: false,
                        onTap: () {},
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _buildControlButton(
                        icon: Icons.pause,
                        label: 'Hold',
                        isActive: _isOnHold,
                        onTap: () => setState(() => _isOnHold = !_isOnHold),
                      ),
                      _buildControlButton(
                        icon: Icons.phone_forwarded,
                        label: 'Transfer',
                        isActive: false,
                        onTap: () {},
                      ),
                      _buildControlButton(
                        icon: Icons.person_add_alt,
                        label: 'Add Call',
                        isActive: false,
                        onTap: () {},
                      ),
                    ],
                  ),
                  const SizedBox(height: 40),

                  // 🔴 End Call Action Button
                  InkWell(
                    onTap: () => Navigator.pop(context),
                    borderRadius: BorderRadius.circular(40),
                    child: Container(
                      width: 72,
                      height: 72,
                      decoration: const BoxDecoration(
                        color: Colors.red,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(color: Colors.redAccent, blurRadius: 16, offset: Offset(0, 4)),
                        ],
                      ),
                      child: const Icon(Icons.call_end, color: Colors.white, size: 36),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildControlButton({
    required IconData icon,
    required String label,
    required bool isActive,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(30),
      child: Column(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: isActive ? Colors.white : const Color(0xFF163E32),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: isActive ? Colors.black : Colors.white, size: 24),
          ),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey)),
        ],
      ),
    );
  }
}
