import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/api.dart';
import 'core/theme.dart';
import 'features/auth/gate_screen.dart';
import 'features/angkatan/angkatan_shell.dart';
import 'features/kelas/kelas_shell.dart';
import 'features/anggota/anggota_shell.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  runApp(ProviderScope(
    overrides: [prefsProvider.overrideWithValue(prefs)],
    child: const KasKitaApp(),
  ));
}

class KasKitaApp extends ConsumerWidget {
  const KasKitaApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider);
    Widget home;
    if (user == null) {
      home = const GateScreen();
    } else {
      switch (user['role']) {
        case 'bendahara_angkatan':
          home = const AngkatanShell();
          break;
        case 'bendahara_kelas':
          home = const KelasShell();
          break;
        default:
          home = const AnggotaShell();
      }
    }
    return MaterialApp(
      title: 'KasKita',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      home: home,
    );
  }
}
