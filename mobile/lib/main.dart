import 'package:flutter/material.dart';
import 'screens/home/home_screen.dart';
import 'screens/chat/chat_inbox_screen.dart';
import 'screens/calls/call_history_screen.dart';
import 'screens/contacts/contacts_screen.dart';
import 'screens/locked/upgrade_required_screen.dart';

void main() {
  runApp(const GrowbridgeConnectApp());
}

class GrowbridgeConnectApp extends StatelessWidget {
  const GrowbridgeConnectApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Growbridge Connect',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        scaffoldBackgroundColor: const Color(0xFF071410),
        primaryColor: const Color(0xFF10B981),
        colorScheme: const ColorScheme.dark(
          primary: Color(0xFF10B981),
          secondary: Color(0xFF059669),
          surface: Color(0xFF0D221C),
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF0D221C),
          elevation: 0,
          centerTitle: false,
        ),
      ),
      home: const MainNavigationShell(),
    );
  }
}

class MainNavigationShell extends StatefulWidget {
  const MainNavigationShell({super.key});

  @override
  State<MainNavigationShell> createState() => _MainNavigationShellState();
}

class _MainNavigationShellState extends State<MainNavigationShell> {
  int _currentIndex = 0;
  bool _hasVoiceEntitlement = true; // Injected from bootstrap API

  @override
  Widget build(BuildContext context) {
    final List<Widget> screens = [
      HomeScreen(
        onNavigateToChat: () => setState(() => _currentIndex = 1),
        onNavigateToCalls: () => setState(() => _currentIndex = 2),
      ),
      const ChatInboxScreen(),
      _hasVoiceEntitlement
          ? const CallHistoryScreen()
          : const UpgradeRequiredScreen(
              featureName: 'Business Calling & VoIP',
              description: 'Upgrade your Growbridge plan to activate VoIP calling and Twilio virtual numbers.',
            ),
      const ContactsScreen(),
      _buildMoreMenu(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: Color(0xFF0A1C16),
          border: Border(top: BorderSide(color: Color(0xFF13362B), width: 1)),
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) => setState(() => _currentIndex = index),
          backgroundColor: Colors.transparent,
          selectedItemColor: const Color(0xFF10B981),
          unselectedItemColor: const Color(0xFF6B7280),
          type: BottomNavigationBarType.fixed,
          selectedFontSize: 11,
          unselectedFontSize: 11,
          items: [
            const BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home),
              label: 'Home',
            ),
            const BottomNavigationBarItem(
              icon: Icon(Icons.chat_bubble_outline),
              activeIcon: Icon(Icons.chat_bubble),
              label: 'Chat',
            ),
            BottomNavigationBarItem(
              icon: Stack(
                children: [
                  const Icon(Icons.phone_outlined),
                  if (!_hasVoiceEntitlement)
                    const Positioned(
                      right: 0,
                      top: 0,
                      child: Icon(Icons.lock, size: 10, color: Colors.amber),
                    ),
                ],
              ),
              activeIcon: const Icon(Icons.phone),
              label: 'Call',
            ),
            const BottomNavigationBarItem(
              icon: Icon(Icons.people_outline),
              activeIcon: Icon(Icons.people),
              label: 'Contacts',
            ),
            const BottomNavigationBarItem(
              icon: Icon(Icons.menu),
              label: 'More',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMoreMenu() {
    return Scaffold(
      appBar: AppBar(title: const Text('Growbridge Connect')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildMenuItem(Icons.auto_awesome, 'AI Agents & Knowledge', () {}),
          _buildMenuItem(Icons.campaign, 'Marketing Campaigns', () {}),
          _buildMenuItem(Icons.bolt, 'Automations & Workflows', () {}),
          _buildMenuItem(Icons.insights, 'Analytics & Reporting', () {}),
          _buildMenuItem(Icons.hub, 'CRM Integrations', () {}),
          _buildMenuItem(Icons.credit_card, 'Plans & Wallet', () {}),
          _buildMenuItem(Icons.settings, 'Settings & Notifications', () {}),
        ],
      ),
    );
  }

  Widget _buildMenuItem(IconData icon, String title, VoidCallback onTap) {
    return Card(
      color: const Color(0xFF0D221C),
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: Icon(icon, color: const Color(0xFF10B981)),
        title: Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
        trailing: const Icon(Icons.chevron_right, color: Colors.grey),
        onTap: onTap,
      ),
    );
  }
}
