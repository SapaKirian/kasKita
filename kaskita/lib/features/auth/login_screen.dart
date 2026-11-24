import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api.dart';
import '../../core/widgets.dart';
import 'gate_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _user = TextEditingController();
  final _pass = TextEditingController();
  bool _loading = false;

  Future<void> _submit() async {
    setState(() => _loading = true);
    final ok = await guard(context, () => ref.read(authProvider.notifier).login(_user.text.trim(), _pass.text));
    if (!mounted) return;
    setState(() => _loading = false);
    if (ok) Navigator.of(context).popUntil((r) => r.isFirst); // main.dart otomatis pindah sesuai role
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: SafeArea(
          child: ListView(padding: const EdgeInsets.all(24), children: [
            const AuthHeader(),
            KkInput('Username', _user, hint: 'username kamu', suffix: Icons.person_outline),
            KkInput('Kata sandi', _pass, password: true),
            const InfoBanner('Akses mengikuti peran Anda: bendahara angkatan, bendahara kelas, atau mahasiswa.'),
            KkButton('Masuk ke KasKita', icon: Icons.arrow_forward, loading: _loading, onPressed: _submit),
          ]),
        ),
      );
}
