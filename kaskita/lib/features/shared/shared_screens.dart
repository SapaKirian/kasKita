import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/api.dart';
import '../../core/endpoints.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

void push(BuildContext c, Widget w) => Navigator.push(c, MaterialPageRoute(builder: (_) => w));

/// "Halo, {nama}" di dashboard semua role. Field user dari login: nama, username, email, role.
class GreetingHeader extends ConsumerWidget {
  const GreetingHeader({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final u = ref.watch(authProvider) ?? {};
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text((roleLabel(u['role']) ?? '').toUpperCase(), style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: C.primary, letterSpacing: .5)),
        Row(children: [
          Expanded(child: Text('Halo, ${txt(u['nama'])}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: C.navy))),
          const BellButton(),
        ]),
      ]),
    );
  }
}

class ProfileTab extends ConsumerWidget {
  const ProfileTab({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final u = ref.watch(authProvider) ?? {};
    return tabPage(
      'Profil & akses',
      ListView(padding: const EdgeInsets.all(16), children: [
        KkCard(
          child: Row(children: [
            CircleAvatar(backgroundColor: const Color(0xFFE3ECFC), child: Text(txt(u['nama'], '?')[0].toUpperCase(), style: const TextStyle(color: C.primary, fontWeight: FontWeight.w800))),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(txt(u['nama']), style: const TextStyle(fontWeight: FontWeight.w800, color: C.navy)),
              Text(roleLabel(u['role']) ?? '-', style: const TextStyle(fontSize: 11, color: C.muted)),
            ])),
          ]),
        ),
        const SectionTitle('Informasi akun'),
        ListRow(title: 'Username', subtitle: txt(u['username'])),
        ListRow(title: 'Email', subtitle: txt(u['email'])),
        const SectionTitle('Pengaturan'),
        ListRow(title: 'Edit profil', subtitle: 'Ubah nama dan email', trailing: const Icon(Icons.chevron_right), onTap: () => push(context, const EditProfilScreen())),
        ListRow(title: 'Keamanan akun', subtitle: 'Ganti kata sandi', trailing: const Icon(Icons.chevron_right), onTap: () => push(context, const GantiSandiScreen())),
        const SizedBox(height: 8),
        KkButton('Keluar dari akun', outlined: true, danger: true, onPressed: () => ref.read(authProvider.notifier).logout()),
      ]),
    );
  }
}

/// PUT /me {nama, email}
class EditProfilScreen extends ConsumerStatefulWidget {
  const EditProfilScreen({super.key});
  @override
  ConsumerState<EditProfilScreen> createState() => _EditProfilScreenState();
}

class _EditProfilScreenState extends ConsumerState<EditProfilScreen> {
  late final _nama = TextEditingController(text: txt(ref.read(authProvider)?['nama'], ''));
  late final _email = TextEditingController(text: txt(ref.read(authProvider)?['email'], ''));
  bool _busy = false;

  Future<void> _save() async {
    setState(() => _busy = true);
    final ok = await guard(context, () async {
      final res = await ref.read(apiProvider).put(Api.profile, data: {'nama': _nama.text.trim(), 'email': _email.text.trim().isEmpty ? null : _email.text.trim()});
      await ref.read(authProvider.notifier).updateUser({...?ref.read(authProvider), ...asMap(res)});
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Edit profil')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          KkInput('Nama lengkap', _nama),
          KkInput('Email', _email, keyboard: TextInputType.emailAddress),
          KkButton('Simpan perubahan', loading: _busy, onPressed: _save),
        ]),
      );
}

/// PUT /me/password {current_password, password, password_confirmation}
class GantiSandiScreen extends ConsumerStatefulWidget {
  const GantiSandiScreen({super.key});
  @override
  ConsumerState<GantiSandiScreen> createState() => _GantiSandiScreenState();
}

class _GantiSandiScreenState extends ConsumerState<GantiSandiScreen> {
  final _lama = TextEditingController();
  final _baru = TextEditingController();
  final _ulang = TextEditingController();
  bool _busy = false;

  Future<void> _save() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).put(Api.password, data: {
          'current_password': _lama.text,
          'password': _baru.text,
          'password_confirmation': _ulang.text,
        }));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Kata sandi berhasil diganti')));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Keamanan akun')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          KkInput('Kata sandi saat ini', _lama, password: true),
          KkInput('Kata sandi baru (min. 8 karakter)', _baru, password: true),
          KkInput('Ulangi kata sandi baru', _ulang, password: true),
          KkButton('Ganti kata sandi', loading: _busy, onPressed: _save),
        ]),
      );
}

/// GET /notifications -> {unread, items:[{id, title, message, notification_type, is_read, created_at}]}
class NotifikasiBody extends ConsumerStatefulWidget {
  const NotifikasiBody({super.key});
  @override
  ConsumerState<NotifikasiBody> createState() => _NotifikasiBodyState();
}

class _NotifikasiBodyState extends ConsumerState<NotifikasiBody> {
  bool _unreadOnly = false;

  Future<void> _readAll() async {
    await guard(context, () => ref.read(apiProvider).patch('${Api.notifications}/read-all'));
    ref.invalidate(dataProvider(Api.notifications));
  }

  Future<void> _read(dynamic id) async {
    try {
      await ref.read(apiProvider).patch('${Api.notifications}/$id/read');
      ref.invalidate(dataProvider(Api.notifications));
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) => AsyncBody(
        path: Api.notifications,
        builder: (d) {
          final m = asMap(d);
          var items = asList(m['items']);
          if (_unreadOnly) items = items.where((e) => e['is_read'] != true && e['is_read'] != 1).toList();
          return ListView(padding: const EdgeInsets.all(16), children: [
            Row(children: [
              ChoiceChip(label: const Text('Semua'), selected: !_unreadOnly, onSelected: (_) => setState(() => _unreadOnly = false)),
              const SizedBox(width: 8),
              ChoiceChip(label: Text('Belum dibaca (${txt(m['unread'], '0')})'), selected: _unreadOnly, onSelected: (_) => setState(() => _unreadOnly = true)),
              const Spacer(),
              TextButton(onPressed: _readAll, child: const Text('Tandai dibaca', style: TextStyle(fontSize: 11))),
            ]),
            const SizedBox(height: 8),
            for (final n in items)
              KkCard(
                onTap: () => _read(n['id']),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Container(
                    width: 8, height: 8, margin: const EdgeInsets.only(top: 5, right: 10),
                    decoration: BoxDecoration(shape: BoxShape.circle, color: (n['is_read'] == true || n['is_read'] == 1) ? Colors.transparent : C.primary),
                  ),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(txt(n['title']), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: C.navy)),
                    const SizedBox(height: 2),
                    Text(txt(n['message']), style: const TextStyle(fontSize: 11, color: C.muted)),
                    const SizedBox(height: 4),
                    Text(d10(n['created_at']), style: const TextStyle(fontSize: 10, color: C.muted)),
                  ])),
                ]),
              ),
            if (items.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Belum ada notifikasi'))),
          ]);
        },
      );
}

class NotifikasiScreen extends StatelessWidget {
  const NotifikasiScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('Notifikasi')), body: const NotifikasiBody());
}

/// Ikon lonceng + badge belum dibaca (dashboard bendahara).
class BellButton extends ConsumerWidget {
  const BellButton({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = int.tryParse('${asMap(ref.watch(dataProvider(Api.notifications)).value)['unread'] ?? 0}') ?? 0;
    return IconButton(
      onPressed: () => push(context, const NotifikasiScreen()),
      icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_none)),
    );
  }
}

/// Gambar bukti dari GET /payment-proofs/{id} (butuh header Authorization).
class ProofImage extends ConsumerWidget {
  const ProofImage(this.id, {super.key});
  final dynamic id;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final token = ref.read(prefsProvider).getString('token');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.network(
          '${Api.baseUrl}${Api.paymentProofs}/$id',
          headers: {'Authorization': 'Bearer $token', 'Accept': 'image/*'},
          errorBuilder: (_, __, ___) => const InfoBanner('Bukti tidak dapat ditampilkan (mungkin berupa PDF).', warn: true),
        ),
      ),
    );
  }
}

/// Daftar pembayaran menunggu verifikasi (bendahara kelas & angkatan).
/// GET /payments/pending -> paginator of {id, amount, payment_method, payment_date, payer:{nama}, bill_recipient:{bill:{title}}, proofs:[{id}]}
class PendingPaymentsBody extends StatelessWidget {
  const PendingPaymentsBody({super.key, this.path = Api.paymentsPending});
  final String path;
  @override
  Widget build(BuildContext context) => AsyncBody(
        path: path,
        builder: (d) {
          final items = asList(d);
          if (items.isEmpty) return ListView(children: const [Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Tidak ada data')))]);
          return ListView(padding: const EdgeInsets.all(16), children: [
            for (final p in items)
              ListRow(
                title: txt(asMap(p['payer'])['nama']),
                subtitle: '${txt(asMap(asMap(asMap(p['bill_recipient'])['bill']))['title'])} · ${txt(p['payment_method'])}',
                trailing: Text(rupiah(p['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                onTap: () => push(context, PaymentReviewScreen(asMap(p))),
              ),
          ]);
        },
      );
}

class PendingPaymentsScreen extends StatelessWidget {
  const PendingPaymentsScreen({super.key, this.withHistory = false});
  final bool withHistory; // true untuk bendahara kelas (ada tab Diterima/Ditolak)
  @override
  Widget build(BuildContext context) {
    if (!withHistory) return Scaffold(appBar: AppBar(title: const Text('Verifikasi pembayaran')), body: const PendingPaymentsBody());
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(title: const Text('Verifikasi transfer'), bottom: const TabBar(tabs: [Tab(text: 'Menunggu'), Tab(text: 'Diterima'), Tab(text: 'Ditolak')])),
        body: const TabBarView(children: [
          PendingPaymentsBody(),
          PendingPaymentsBody(path: '${Api.kelasPayments}?status=disetujui'),
          PendingPaymentsBody(path: '${Api.kelasPayments}?status=ditolak'),
        ]),
      ),
    );
  }
}

/// PATCH /payments/{id}/verify {status: disetujui|ditolak, rejection_reason}
class PaymentReviewScreen extends ConsumerStatefulWidget {
  const PaymentReviewScreen(this.p, {super.key});
  final Map<String, dynamic> p;
  @override
  ConsumerState<PaymentReviewScreen> createState() => _PaymentReviewScreenState();
}

class _PaymentReviewScreenState extends ConsumerState<PaymentReviewScreen> {
  final _alasan = TextEditingController();
  bool _busy = false;

  Future<void> _act(String status) async {
    if (status == 'ditolak' && _alasan.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Isi alasan penolakan dulu')));
      return;
    }
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).patch('${Api.payments}/${widget.p['id']}/verify', data: {
          'status': status,
          if (status == 'ditolak') 'rejection_reason': _alasan.text.trim(),
        }));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      ref.invalidate(dataProvider);
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.p;
    final bill = asMap(asMap(p['bill_recipient'])['bill']);
    return Scaffold(
      appBar: AppBar(title: const Text('Tinjau pembayaran')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        KkCard(child: Column(children: [
          kv('Nama', txt(asMap(p['payer'])['nama'])),
          kv('Tagihan', txt(bill['title'])),
          kv('Metode', txt(p['payment_method'])),
          kv('Tanggal', d10(p['payment_date'])),
          kv('Nominal', rupiah(p['amount'])),
        ])),
        if (asList(p['proofs']).isNotEmpty) ...[
          const SectionTitle('Bukti pembayaran'),
          for (final pr in asList(p['proofs'])) ProofImage(pr['id']),
        ] else if (p['status'] == 'menunggu_verifikasi')
          const InfoBanner('Pembayaran tunai: pastikan uang sudah kamu terima sebelum menyetujui.', warn: true),
        if (p['status'] == 'menunggu_verifikasi') ...[
          KkInput('Alasan penolakan (wajib jika ditolak)', _alasan),
          Row(children: [
            Expanded(child: KkButton('Tolak', outlined: true, danger: true, onPressed: _busy ? null : () => _act('ditolak'))),
            const SizedBox(width: 10),
            Expanded(child: KkButton('Setujui', loading: _busy, onPressed: () => _act('disetujui'))),
          ]),
        ] else ...[
          StatusBadge(p['status']?.toString()),
          if (p['rejection_reason'] != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text('Alasan: ${p['rejection_reason']}', style: const TextStyle(fontSize: 12, color: C.bad))),
        ],
      ]),
    );
  }
}

Widget kv(String l, String v) => Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(l, style: const TextStyle(fontSize: 12, color: C.muted)),
        Flexible(child: Text(v, textAlign: TextAlign.right, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy))),
      ]),
    );

/// Detail tagihan untuk bendahara: GET /bills/{id} -> {title, amount, due_date, recipients:[{user:{nama}, bill_amount, paid_amount, status}]}
class BillMonitorScreen extends StatelessWidget {
  const BillMonitorScreen(this.id, {super.key});
  final dynamic id;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Detail tagihan')),
        body: AsyncBody(
          path: '${Api.bills}/$id',
          builder: (d) {
            final m = asMap(d);
            final rec = asList(m['recipients']);
            final lunas = rec.where((r) => r['status'] == 'lunas').length;
            return ListView(padding: const EdgeInsets.all(16), children: [
              BalanceCard(label: txt(m['title']), amount: m['amount'], sub: 'Jatuh tempo ${d10(m['due_date'])}'),
              Row(children: [
                Expanded(child: Stat('Lunas', '$lunas/${rec.length}')),
                Expanded(child: Stat('Belum lunas', '${rec.length - lunas}')),
              ]),
              const SectionTitle('Status per anggota'),
              for (final r in rec)
                ListRow(
                  title: txt(asMap(r['user'])['nama']),
                  subtitle: '${txt(asMap(r['classroom'])['name'], '')}  ${rupiah(r['paid_amount'])} / ${rupiah(r['bill_amount'])}',
                  trailing: StatusBadge(r['status']?.toString()),
                ),
            ]);
          },
        ),
      );
}

/// Daftar tagihan. angkatan/kelas -> BillMonitorScreen. GET /bills -> paginator of {id, title, period_month, period_year, amount, due_date, recipients_count}
class BillListBody extends StatelessWidget {
  const BillListBody({super.key});
  @override
  Widget build(BuildContext context) => AsyncBody(
        path: Api.bills,
        builder: (d) => ListView(padding: const EdgeInsets.all(16), children: [
          for (final b in asList(d))
            ListRow(
              title: txt(b['title']),
              subtitle: 'Periode ${txt(b['period_month'])}/${txt(b['period_year'])} · jatuh tempo ${d10(b['due_date'])}',
              trailing: Text(rupiah(b['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
              onTap: () => push(context, BillMonitorScreen(b['id'])),
            ),
          if (asList(d).isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Belum ada tagihan'))),
        ]),
      );
}

/// Tambah anggota: POST /users {nama, username, email, password, role}
/// Bendahara kelas hanya boleh role=anggota; bendahara angkatan boleh bendahara_kelas atau anggota.
class TambahUserScreen extends ConsumerStatefulWidget {
  const TambahUserScreen({super.key, required this.roles});
  final List<String> roles;
  @override
  ConsumerState<TambahUserScreen> createState() => _TambahUserScreenState();
}

class _TambahUserScreenState extends ConsumerState<TambahUserScreen> {
  final _nama = TextEditingController();
  final _username = TextEditingController();
  final _email = TextEditingController();
  final _pass = TextEditingController();
  late String _role = widget.roles.first;
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.users, data: {
          'nama': _nama.text.trim(),
          'username': _username.text.trim(),
          if (_email.text.trim().isNotEmpty) 'email': _email.text.trim(),
          'password': _pass.text,
          'role': _role,
        }));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      ref.invalidate(dataProvider);
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Tambah pengguna')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          KkInput('Nama lengkap', _nama),
          KkInput('Username', _username),
          KkInput('Email (opsional)', _email, keyboard: TextInputType.emailAddress),
          KkInput('Kata sandi (min. 8 karakter)', _pass, password: true),
          if (widget.roles.length > 1) ...[
            const Text('Peran', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
            const SizedBox(height: 6),
            DropdownButtonFormField<String>(
              value: _role,
              items: [for (final r in widget.roles) DropdownMenuItem(value: r, child: Text(roleLabel(r) ?? r))],
              onChanged: (v) => setState(() => _role = v!),
            ),
            const SizedBox(height: 12),
          ],
          KkButton('Simpan', loading: _busy, onPressed: _submit),
        ]),
      );
}

/// Daftar pengguna: GET /users -> paginator of {id, nama, username, role, is_active}
class UserListBody extends StatelessWidget {
  const UserListBody({super.key});
  @override
  Widget build(BuildContext context) => AsyncBody(
        path: Api.users,
        builder: (d) => ListView(padding: const EdgeInsets.all(16), children: [
          for (final u in asList(d))
            ListRow(
              title: txt(u['nama']),
              subtitle: '@${txt(u['username'])} · ${roleLabel(u['role']) ?? ''}',
              trailing: StatusBadge(u['is_active'] == true || u['is_active'] == 1 ? 'aktif' : 'nonaktif'),
            ),
        ]),
      );
}

String today() => DateFormat('yyyy-MM-dd').format(DateTime.now());
