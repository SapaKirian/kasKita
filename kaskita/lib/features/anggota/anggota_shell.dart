import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/api.dart';
import '../../core/endpoints.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../shared/role_shell.dart';
import '../shared/shared_screens.dart';

class AnggotaShell extends StatelessWidget {
  const AnggotaShell({super.key});
  @override
  Widget build(BuildContext context) => const RoleShell(tabs: [
        ShellTab('Beranda', Icons.home_outlined, AnggotaDashboard()),
        ShellTab('Riwayat', Icons.history, RiwayatTab()),
        ShellTab('Notifikasi', Icons.notifications_none, NotifikasiTabPage()),
        ShellTab('Profil', Icons.person_outline, ProfileTab()),
      ]);
}

class NotifikasiTabPage extends StatelessWidget {
  const NotifikasiTabPage({super.key});
  @override
  Widget build(BuildContext context) => tabPage('Notifikasi', const NotifikasiBody());
}

/// GET /anggota/summary -> {total_sisa, jumlah_tagihan_terbuka, tagihan_terdekat:{bill_id, recipient_id, title, due_date, sisa, status}, tagihan_terbuka:[...]}
/// GET /payments/my -> pembayaran terakhir
class AnggotaDashboard extends ConsumerWidget {
  const AnggotaDashboard({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(dataProvider(Api.anggotaSummary));
    final pay = asList(ref.watch(dataProvider(Api.paymentsMy)).value);
    final m = asMap(s.value);
    final t = m['tagihan_terdekat'] == null ? null : asMap(m['tagihan_terdekat']);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(dataProvider),
        child: ListView(padding: const EdgeInsets.all(16), children: [
          const GreetingHeader(),
          if (s.hasError) InfoBanner('${s.error}', warn: true),
          if (s.isLoading) const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
          if (s.hasValue) ...[
            if (t == null)
              const InfoBanner('Tidak ada tagihan aktif. Semua iuran sudah lunas. Terima kasih!')
            else ...[
              BalanceCard(label: 'Tagihan terdekat', amount: t['sisa'], sub: '${txt(t['title'])} · jatuh tempo ${d10(t['due_date'])}'),
              KkButton('Lihat tagihan & bayar', icon: Icons.arrow_forward, onPressed: () => push(context, TagihanDetailScreen(t['bill_id']))),
            ],
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: Stat('Total sisa', rupiah(m['total_sisa']))),
              Expanded(child: Stat('Tagihan terbuka', txt(m['jumlah_tagihan_terbuka'], '0'))),
            ]),
            if (asList(m['tagihan_terbuka']).length > 1) ...[
              const SectionTitle('Tagihan lainnya'),
              for (final b in asList(m['tagihan_terbuka']).skip(1))
                ListRow(
                  title: txt(b['title']),
                  subtitle: 'Jatuh tempo ${d10(b['due_date'])}',
                  trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text(rupiah(b['sisa']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                    StatusBadge(b['status']?.toString()),
                  ]),
                  onTap: () => push(context, TagihanDetailScreen(b['bill_id'])),
                ),
            ],
            const SectionTitle('Pembayaran terakhir'),
            for (final p in pay.take(3))
              ListRow(
                title: txt(asMap(asMap(p['bill_recipient'])['bill'])['title']),
                subtitle: '${d10(p['payment_date'])} · ${txt(p['payment_method'])}',
                trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text(rupiah(p['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                  StatusBadge(p['status']?.toString()),
                ]),
              ),
            if (pay.isEmpty) const InfoBanner('Belum ada pembayaran. Riwayat akan muncul di sini setelah kamu membayar.'),
          ],
        ]),
      ),
    );
  }
}

/// GET /bills/{id} (anggota) -> {title, amount, due_date, recipients:[{id, bill_amount, paid_amount, status}]}  (hanya milik sendiri)
class TagihanDetailScreen extends StatelessWidget {
  const TagihanDetailScreen(this.id, {super.key});
  final dynamic id;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Detail tagihan')),
        body: AsyncBody(
          path: '${Api.bills}/$id',
          builder: (d) {
            final m = asMap(d);
            final rec = asList(m['recipients']);
            if (rec.isEmpty) return ListView(children: const [Padding(padding: EdgeInsets.all(32), child: Text('Data tagihan tidak ditemukan'))]);
            final r = asMap(rec.first);
            final sisa = (num.tryParse('${r['bill_amount']}') ?? 0) - (num.tryParse('${r['paid_amount']}') ?? 0);
            final menunggu = r['status'] == 'menunggu_verifikasi';
            return ListView(padding: const EdgeInsets.all(16), children: [
              BalanceCard(label: txt(m['title']), amount: sisa, sub: 'Sisa tagihan · jatuh tempo ${d10(m['due_date'])}'),
              KkCard(child: Column(children: [
                kv('Periode', periode(m['period_month'], m['period_year'])),
                kv('Total tagihan', rupiah(r['bill_amount'])),
                kv('Sudah dibayar', rupiah(r['paid_amount'])),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Status', style: TextStyle(fontSize: 12, color: C.muted)), StatusBadge(r['status']?.toString())]),
              ])),
              if (menunggu) const InfoBanner('Pembayaranmu sedang menunggu verifikasi bendahara. Kamu akan mendapat notifikasi saat selesai.', warn: true),
              if (sisa > 0 && !menunggu) ...[
                const SectionTitle('Pilih metode pembayaran'),
                ListRow(
                  title: 'Tunai ke bendahara',
                  subtitle: 'Serahkan langsung, bendahara memverifikasi',
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => push(context, BayarTunaiScreen(recipientId: r['id'], sisa: sisa, bayar: txt(m['title']))),
                ),
                ListRow(
                  title: 'Transfer & unggah bukti',
                  subtitle: 'Kirim bukti transfer untuk diverifikasi',
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => push(context, TransferScreen(recipientId: r['id'], sisa: sisa)),
                ),
              ],
              if (sisa <= 0) const InfoBanner('Tagihan ini sudah lunas. Terima kasih!'),
            ]);
          },
        ),
      );
}

/// Kirim pembayaran ke POST /payments (multipart). Dipakai tunai & transfer.
Future<bool> kirimPembayaran(BuildContext context, WidgetRef ref, {required dynamic recipientId, required String amount, required String method, XFile? bukti}) async {
  final ok = await guard(context, () async {
    final form = FormData.fromMap({
      'bill_recipient_id': recipientId,
      'amount': amount,
      'payment_method': method,
      if (bukti != null) 'proof': await MultipartFile.fromFile(bukti.path, filename: bukti.name),
    });
    await ref.read(apiProvider).post(Api.payments, data: form);
  });
  if (ok) ref.invalidate(dataProvider);
  return ok;
}

/// Layar "Bukti berhasil dikirim / menunggu verifikasi".
class MenungguVerifikasiScreen extends StatelessWidget {
  const MenungguVerifikasiScreen({super.key, required this.amount, required this.method});
  final String amount, method;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(automaticallyImplyLeading: false, title: const Text('Status pembayaran')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          const SizedBox(height: 24),
          Center(child: Container(width: 72, height: 72, decoration: BoxDecoration(color: C.warnBg, shape: BoxShape.circle), child: const Icon(Icons.hourglass_top, color: C.warn, size: 32))),
          const SizedBox(height: 16),
          Center(child: Text(method == 'transfer' ? 'Bukti berhasil dikirim' : 'Pengajuan berhasil dikirim', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: C.navy))),
          const SizedBox(height: 8),
          const Center(child: StatusBadge('menunggu_verifikasi')),
          const SizedBox(height: 16),
          KkCard(child: Column(children: [
            kv('Nominal', rupiah(amount)),
            kv('Metode', method),
          ])),
          const InfoBanner('Bendahara akan memeriksa pembayaranmu. Status lunas muncul setelah diverifikasi dan kamu akan mendapat notifikasi.'),
          KkButton('Lihat riwayat pembayaran', onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst)),
          const SizedBox(height: 8),
          KkButton('Kembali ke beranda', outlined: true, onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst)),
        ]),
      );
}

/// Bayar tunai: instruksi + ajukan (POST /payments, payment_method=tunai)
class BayarTunaiScreen extends ConsumerStatefulWidget {
  const BayarTunaiScreen({super.key, required this.recipientId, required this.sisa, required this.bayar});
  final dynamic recipientId;
  final num sisa;
  final String bayar;
  @override
  ConsumerState<BayarTunaiScreen> createState() => _BayarTunaiScreenState();
}

class _BayarTunaiScreenState extends ConsumerState<BayarTunaiScreen> {
  late final _amount = TextEditingController(text: widget.sisa.toStringAsFixed(0));
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await kirimPembayaran(context, ref, recipientId: widget.recipientId, amount: _amount.text, method: 'tunai');
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => MenungguVerifikasiScreen(amount: _amount.text, method: 'tunai')));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Bayar tunai')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const InfoBanner('Serahkan uang tunai langsung ke bendahara kelas, lalu ajukan pembayaran agar bendahara dapat mencatat lunas.'),
          const SectionTitle('Cara pembayaran'),
          for (final e in ['Temui bendahara kelasmu.', 'Serahkan nominal tagihan secara tunai.', 'Ajukan pembayaran di sini, lalu tunggu bendahara memverifikasi.'].asMap().entries)
            ListRow(title: '${e.key + 1}. ${e.value}'),
          KkInput('Nominal', _amount, keyboard: TextInputType.number),
          KkButton('Ajukan pembayaran tunai', icon: Icons.send, loading: _busy, onPressed: _submit),
        ]),
      );
}

/// Transfer & bukti: unggah bukti + popup konfirmasi (POST /payments, payment_method=transfer, proof)
class TransferScreen extends ConsumerStatefulWidget {
  const TransferScreen({super.key, required this.recipientId, required this.sisa});
  final dynamic recipientId;
  final num sisa;
  @override
  ConsumerState<TransferScreen> createState() => _TransferScreenState();
}

class _TransferScreenState extends ConsumerState<TransferScreen> {
  late final _amount = TextEditingController(text: widget.sisa.toStringAsFixed(0));
  XFile? _file;
  bool _busy = false;

  Future<void> _pick() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f != null) setState(() => _file = f);
  }

  Future<void> _submit() async {
    if (_file == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pilih bukti transfer dulu')));
      return;
    }
    final yes = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Kirim bukti pembayaran?'),
        content: Text('Pastikan tanggal, nominal, dan nomor transfer pada bukti sudah benar.\n\nNominal: ${rupiah(_amount.text)}'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Periksa kembali')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Ya, kirim bukti')),
        ],
      ),
    );
    if (yes != true) return;
    setState(() => _busy = true);
    final ok = await kirimPembayaran(context, ref, recipientId: widget.recipientId, amount: _amount.text, method: 'transfer', bukti: _file);
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => MenungguVerifikasiScreen(amount: _amount.text, method: 'transfer')));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Transfer & bukti')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const InfoBanner('Transfer ke rekening yang diberikan bendahara kelasmu, lalu unggah bukti transfernya di sini.'),
          KkInput('Nominal transfer', _amount, keyboard: TextInputType.number),
          const SectionTitle('Bukti transfer *'),
          GestureDetector(
            onTap: _pick,
            child: Container(
              height: 180,
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: C.line)),
              child: _file == null
                  ? const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.upload_file, color: C.muted), SizedBox(height: 6), Text('Ketuk untuk pilih gambar', style: TextStyle(fontSize: 12, color: C.muted))]))
                  : Center(child: Text('Dipilih: ${_file!.name}', style: const TextStyle(fontSize: 12))),
            ),
          ),
          const SizedBox(height: 16),
          KkButton('Kirim bukti pembayaran', icon: Icons.send, loading: _busy, onPressed: _submit),
        ]),
      );
}

/// GET /payments/my -> paginator of {amount, payment_method, status, payment_date, rejection_reason?, bill_recipient:{bill:{title}}}
class RiwayatTab extends StatefulWidget {
  const RiwayatTab({super.key});
  @override
  State<RiwayatTab> createState() => _RiwayatTabState();
}

class _RiwayatTabState extends State<RiwayatTab> {
  String _f = '';
  @override
  Widget build(BuildContext context) => tabPage(
        'Riwayat pembayaran',
        AsyncBody(
          path: Api.paymentsMy,
          builder: (d) {
            final all = asList(d);
            num sum(String st) => all.where((p) => p['status'] == st).fold<num>(0, (a, p) => a + (num.tryParse('${p['amount']}') ?? 0));
            final items = _f.isEmpty ? all : all.where((p) => p['status'] == _f).toList();
            return ListView(padding: const EdgeInsets.all(16), children: [
              Row(children: [
                Expanded(child: Stat('Total dibayar', rupiah(sum('disetujui')))),
                Expanded(child: Stat('Menunggu', rupiah(sum('menunggu_verifikasi')))),
              ]),
              Wrap(spacing: 8, children: [
                for (final e in const {'': 'Semua', 'disetujui': 'Lunas', 'menunggu_verifikasi': 'Menunggu', 'ditolak': 'Ditolak'}.entries)
                  ChoiceChip(label: Text(e.value), selected: _f == e.key, onSelected: (_) => setState(() => _f = e.key)),
              ]),
              const SizedBox(height: 8),
              for (final p in items)
                ListRow(
                  title: txt(asMap(asMap(p['bill_recipient'])['bill'])['title']),
                  subtitle: '${d10(p['payment_date'])} · ${txt(p['payment_method'])}${p['rejection_reason'] != null ? '\nDitolak: ${p['rejection_reason']}' : ''}',
                  trailing: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text(rupiah(p['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                    StatusBadge(p['status']?.toString()),
                  ]),
                ),
              if (items.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Belum ada riwayat pembayaran'))),
            ]);
          },
        ),
      );
}
