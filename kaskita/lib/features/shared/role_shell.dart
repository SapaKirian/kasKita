import 'package:flutter/material.dart';

class ShellTab {
  const ShellTab(this.label, this.icon, this.page);
  final String label;
  final IconData icon;
  final Widget page;
}

class RoleShell extends StatefulWidget {
  const RoleShell({super.key, required this.tabs});
  final List<ShellTab> tabs;
  @override
  State<RoleShell> createState() => _RoleShellState();
}

class _RoleShellState extends State<RoleShell> {
  int _i = 0;
  @override
  Widget build(BuildContext context) => Scaffold(
        body: IndexedStack(index: _i, children: [for (final t in widget.tabs) t.page]),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _i,
          onDestinationSelected: (v) => setState(() => _i = v),
          destinations: [for (final t in widget.tabs) NavigationDestination(icon: Icon(t.icon), label: t.label)],
        ),
      );
}
