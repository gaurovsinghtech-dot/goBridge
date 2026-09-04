import 'package:flutter/material.dart';

class UpgradeRequiredScreen extends StatelessWidget {
  final String featureName;
  final String description;

  const UpgradeRequiredScreen({
    super.key,
    this.featureName = 'Business Calling & VoIP',
    this.description = 'Upgrade your Growbridge Connect plan to activate business calling, Twilio virtual numbers, and AI voice agents.',
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: Colors.amber.withOpacity(0.15),
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.amber.withOpacity(0.3)),
                ),
                child: const Icon(Icons.lock_outline, size: 40, color: Colors.amber),
              ),
              const SizedBox(height: 24),
              Text(
                'Upgrade Required',
                style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 8),
              Text(
                featureName,
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF10B981)),
              ),
              const SizedBox(height: 12),
              Text(
                description,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, color: Colors.grey, height: 1.5),
              ),
              const SizedBox(height: 32),
              ElevatedButton.icon(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Opening web billing portal to upgrade plan...')),
                  );
                },
                icon: const Icon(Icons.rocket_launch, size: 18, color: Colors.black),
                label: const Text('Upgrade Plan Now', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF10B981),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
