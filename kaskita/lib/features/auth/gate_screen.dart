import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'login_screen.dart';

class AuthHeader extends StatelessWidget {
  const AuthHeader({super.key});
  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 30, height: 30,
            decoration: BoxDecoration(color: C.primary, borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.mail_outline, color: Colors.white, size: 16),
          ),
          const SizedBox(width: 8),
          const Text('KasKita', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: C.navy)),
        ]),
        const SizedBox(height: 24),
        const Text('Kas tertata.\nKuliah lebih tenang.', style: TextStyle(fontSize: 26, height: 1.2, fontWeight: FontWeight.w800, color: C.navy)),
        const SizedBox(height: 8),
        const Text('Kelola iuran, pantau pembayaran, dan jaga kepercayaan bersama.', style: TextStyle(fontSize: 12, color: C.muted)),
        const SizedBox(height: 20),
      ]);
}

class GateScreen extends StatelessWidget {
  const GateScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const AuthHeader(),
              const Spacer(),
              KkButton('Login', onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()))),
              const SizedBox(height: 10),
              const InfoBanner('Akun dibuat oleh bendahara angkatan atau bendahara kelas. Minta akunmu ke bendahara.'),
              const Center(child: Text('KASKITA · KAS BERSAMA, TRANSPARAN', style: TextStyle(fontSize: 9, color: C.muted))),
            ]),
          ),
        ),
      );
}
