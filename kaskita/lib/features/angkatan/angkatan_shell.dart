import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import '../../core/api.dart';
import '../../core/endpoints.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../shared/role_shell.dart';
import '../shared/shared_screens.dart';

class AngkatanShell extends StatelessWidget {
  const AngkatanShell({super.key});
  @override
  Widget build(BuildContext context) => const RoleShell(tabs: [
        ShellTab('Beranda', Icons.home_outlined, AngkatanDashboard()),
        ShellTab('Setoran', Icons.sync_alt, SetoranTab()),
        ShellTab('Kas', Icons.account_balance_wallet_outlined, KasTab()),
        ShellTab('Laporan', Icons.bar_chart, LaporanTab()),
        ShellTab('Profil', Icons.person_outline, ProfileTab()),
      ]);
}

/// Dashboard = GET /cash-transactions/summary + /remittances + /payments/pending + /classes
class AngkatanDashboard extends ConsumerWidget {
  const AngkatanDashboard({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(dataProvider(Api.cashSummary));
    final rem = ref.watch(dataProvider(Api.remittances)).value;
    final pending = ref.watch(dataProvider(Api.paymentsPending)).value;
    final classes = ref.watch(dataProvider(Api.classes)).value;
    final menunggu = asList(rem).where((e) => e['status'] == 'menunggu').toList();
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(dataProvider),
        child: ListView(padding: const EdgeInsets.all(16), children: [
          const GreetingHeader(),
          summary.when(
            loading: () => const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
            error: (e, _) => InfoBanner('$e', warn: true),
            data: (d) {
              final m = asMap(d);
              return Column(children: [
                BalanceCard(label: 'Saldo kas angkatan', amount: m['saldo'], sub: '${txt(asMap(classes)['total'] ?? asList(classes).length, '0')} kelas terhubung'),
                Row(children: [
                  Expanded(child: Stat('Total pemasukan', rupiah(m['total_pemasukan']))),
                  Expanded(child: Stat('Total pengeluaran', rupiah(m['total_pengeluaran']))),
                ]),
              ]);
            },
          ),
          Row(children: [
            Expanded(child: Stat('Setoran menunggu', '${menunggu.length} kelas')),
            Expanded(child: Stat('Pembayaran menunggu', '${asMap(pending)['total'] ?? asList(pending).length}')),
          ]),
          GridView.count(
            crossAxisCount: 4, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), childAspectRatio: .85,
            children: [
              _quick(context, 'Tunggakan', Icons.schedule, const TunggakanScreen()),
              _quick(context, 'Atur iuran', Icons.tune, const PengaturanIuranScreen()),
              _quick(context, 'Bendahara', Icons.groups_outlined, const KelolaBendaharaScreen()),
              _quick(context, 'Verifikasi', Icons.fact_check_outlined, const PendingPaymentsScreen()),
              _quick(context, 'Tagihan', Icons.receipt_long_outlined, const TagihanScreen()),
              _quick(context, 'Pengguna', Icons.person_add_alt, const PenggunaScreen()),
              _quick(context, 'Pengeluaran', Icons.remove_circle_outline, const PengeluaranScreen()),
              _quick(context, 'Notifikasi', Icons.notifications_none, const NotifikasiScreen()),
            ],
          ),
          const SizedBox(height: 8),
          const SectionTitle('Perlu ditinjau'),
          for (final r in menunggu)
            ListRow(
              title: 'Setoran ${txt(asMap(r['classroom'])['name'])}',
              subtitle: 'Periode ${periode(r['period_month'], r['period_year'])}',
              trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text(rupiah(r['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                StatusBadge(r['status']?.toString()),
              ]),
              onTap: () => push(context, SetoranDetailScreen(r['id'])),
            ),
          if (menunggu.isEmpty) const InfoBanner('Tidak ada setoran yang menunggu persetujuan.'),
          const InfoBanner('Setoran kelas hanya diverifikasi oleh bendahara angkatan. Angkatan hanya menerima setoran dari bendahara kelas.'),
        ]),
      ),
    );
  }

  Widget _quick(BuildContext c, String t, IconData i, Widget w) => InkWell(
        onTap: () => push(c, w),
        borderRadius: BorderRadius.circular(12),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: C.line)),
            child: Icon(i, size: 20, color: C.primary),
          ),
          const SizedBox(height: 4),
          Text(t, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: C.navy), textAlign: TextAlign.center),
        ]),
      );
}

/// Rekap pembayaran kelas. GET /remittances -> paginator of {id, amount, period_month, period_year, status, classroom:{name}}
class SetoranTab extends StatefulWidget {
  const SetoranTab({super.key});
  @override
  State<SetoranTab> createState() => _SetoranTabState();
}

class _SetoranTabState extends State<SetoranTab> {
  int _m = DateTime.now().month, _y = DateTime.now().year;
  bool _all = true;

  @override
  Widget build(BuildContext context) => tabPage(
        'Pembayaran kelas',
        AsyncBody(
          path: Api.remittances,
          builder: (d) {
            final all = asList(d);
            final items = _all ? all : all.where((r) => '${r['period_month']}' == '$_m' && '${r['period_year']}' == '$_y').toList();
            final approved = items.where((r) => r['status'] == 'disetujui');
            final total = approved.fold<num>(0, (a, r) => a + (num.tryParse('${r['amount']}') ?? 0));
            final waiting = items.where((r) => r['status'] == 'menunggu').length;
            return ListView(padding: const EdgeInsets.all(16), children: [
              Row(children: [
                FilterChip(label: const Text('Semua periode'), selected: _all, onSelected: (v) => setState(() => _all = v)),
              ]),
              if (!_all) PeriodPicker(month: _m, year: _y, onChanged: (m, y) => setState(() { _m = m; _y = y; })),
              const SizedBox(height: 12),
              Row(children: [
                Expanded(child: Stat('Setoran diterima', rupiah(total))),
                Expanded(child: Stat('Menunggu verifikasi', '$waiting kelas')),
              ]),
              const SectionTitle('Setoran per kelas'),
              for (final r in items)
                ListRow(
                  title: txt(asMap(r['classroom'])['name']),
                  subtitle: '${periode(r['period_month'], r['period_year'])} · ${rupiah(r['amount'])}',
                  trailing: StatusBadge(r['status']?.toString()),
                  onTap: () => push(context, SetoranDetailScreen(r['id'])),
                ),
              if (items.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Belum ada setoran'))),
            ]);
          },
        ),
      );
}

/// GET /remittances/{id}; PATCH /remittances/{id}/verify {status: disetujui|ditolak, notes}
class SetoranDetailScreen extends ConsumerStatefulWidget {
  const SetoranDetailScreen(this.id, {super.key});
  final dynamic id;
  @override
  ConsumerState<SetoranDetailScreen> createState() => _SetoranDetailScreenState();
}

class _SetoranDetailScreenState extends ConsumerState<SetoranDetailScreen> {
  final _notes = TextEditingController();
  bool _busy = false;

  Future<void> _verify(String status) async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).patch('${Api.remittances}/${widget.id}/verify', data: {
          'status': status,
          if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
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
        appBar: AppBar(title: const Text('Detail setoran')),
        body: AsyncBody(
          path: '${Api.remittances}/${widget.id}',
          builder: (d) {
            final m = asMap(d);
            final items = asList(m['items']);
            return ListView(padding: const EdgeInsets.all(16), children: [
              BalanceCard(label: 'Setoran ${txt(asMap(m['classroom'])['name'])}', amount: m['amount'], sub: 'Periode ${txt(m['period_month'])}/${txt(m['period_year'])}'),
              KkCard(child: Column(children: [
                kv('Diajukan oleh', txt(asMap(m['submitter'])['nama'])),
                kv('Catatan', txt(m['notes'])),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [const Text('Status', style: TextStyle(fontSize: 12, color: C.muted)), StatusBadge(m['status']?.toString())]),
              ])),
              const SectionTitle('Rincian pembayaran'),
              for (final it in items)
                ListRow(title: txt(asMap(asMap(it['payment'])['payer'])['nama']), trailing: Text(rupiah(it['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12))),
              if (m['status'] == 'menunggu') ...[
                const SizedBox(height: 8),
                KkInput('Catatan (opsional)', _notes),
                Row(children: [
                  Expanded(child: KkButton('Tolak', outlined: true, danger: true, onPressed: _busy ? null : () => _verify('ditolak'))),
                  const SizedBox(width: 10),
                  Expanded(child: KkButton('Setujui', loading: _busy, onPressed: () => _verify('disetujui'))),
                ]),
              ],
            ]);
          },
        ),
      );
}

/// GET /cash-transactions -> paginator of {transaction_type, category, amount, description, transaction_date, classroom:{name}}
class KasTab extends StatelessWidget {
  const KasTab({super.key});
  @override
  Widget build(BuildContext context) => tabPage(
        'Buku kas',
        AsyncBody(
          path: Api.cashTransactions,
          builder: (d) => ListView(padding: const EdgeInsets.all(16), children: [
            KkButton('Tambah pengeluaran', icon: Icons.add, onPressed: () => push(context, const PengeluaranScreen())),
            const SizedBox(height: 12),
            for (final t in asList(d)) _trx(t),
            if (asList(d).isEmpty) const Padding(padding: EdgeInsets.all(32), child: Center(child: Text('Belum ada transaksi'))),
          ]),
        ),
      );
}

Widget _trx(dynamic t) {
  final keluar = t['transaction_type'] == 'pengeluaran';
  return ListRow(
    title: txt(t['category']),
    subtitle: '${d10(t['transaction_date'])} · ${txt(t['description'], '')}',
    trailing: Text('${keluar ? '-' : '+'}${rupiah(t['amount'])}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: keluar ? C.bad : C.ok)),
  );
}

/// POST /cash-transactions {category, amount, description, transaction_date}
class PengeluaranScreen extends ConsumerStatefulWidget {
  const PengeluaranScreen({super.key});
  @override
  ConsumerState<PengeluaranScreen> createState() => _PengeluaranScreenState();
}

class _PengeluaranScreenState extends ConsumerState<PengeluaranScreen> {
  final _kategori = TextEditingController();
  final _nominal = TextEditingController();
  final _desc = TextEditingController();
  final _tgl = TextEditingController(text: today());
  bool _busy = false;

  Future<void> _pickDate() async {
    final d = await showDatePicker(context: context, initialDate: DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2100));
    if (d != null) _tgl.text = DateFormat('yyyy-MM-dd').format(d);
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.cashTransactions, data: {
          'category': _kategori.text.trim(),
          'amount': num.tryParse(_nominal.text) ?? 0,
          if (_desc.text.trim().isNotEmpty) 'description': _desc.text.trim(),
          'transaction_date': _tgl.text,
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
        appBar: AppBar(title: const Text('Tambah pengeluaran')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          KkInput('Kategori *', _kategori, hint: 'mis. Operasional'),
          KkInput('Nominal *', _nominal, hint: '50000', keyboard: TextInputType.number),
          KkInput('Keterangan', _desc),
          KkInput('Tanggal *', _tgl, readOnly: true, onTap: _pickDate, suffix: Icons.calendar_today_outlined),
          KkButton('Simpan pengeluaran', loading: _busy, onPressed: _submit),
        ]),
      );
}

/// GET /reports/cash-flow?start_date=&end_date= -> {saldo_awal, total_pemasukan, total_pengeluaran, saldo_akhir, transaksi:paginator}
/// PDF: GET /reports/cash-flow/pdf
class LaporanTab extends ConsumerStatefulWidget {
  const LaporanTab({super.key});
  @override
  ConsumerState<LaporanTab> createState() => _LaporanTabState();
}

class _LaporanTabState extends ConsumerState<LaporanTab> {
  DateTime _start = DateTime(DateTime.now().year, DateTime.now().month, 1);
  DateTime _end = DateTime.now();
  final _f = DateFormat('yyyy-MM-dd');
  bool _busy = false;

  Future<void> _pick(bool start) async {
    final d = await showDatePicker(context: context, initialDate: start ? _start : _end, firstDate: DateTime(2020), lastDate: DateTime(2100));
    if (d != null) setState(() => start ? _start = d : _end = d);
  }

  Future<void> _pdf() async {
    setState(() => _busy = true);
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/laporan-kas-${_f.format(_start)}_${_f.format(_end)}.pdf';
    if (!mounted) return;
    final ok = await guard(context, () => ref.read(apiProvider).download(Api.cashFlowPdf, path, query: {'start_date': _f.format(_start), 'end_date': _f.format(_end)}));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) await OpenFilex.open(path);
  }

  @override
  Widget build(BuildContext context) => tabPage(
        'Laporan angkatan',
        Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Row(children: [
              Expanded(child: OutlinedButton(onPressed: () => _pick(true), child: Text('Dari ${_f.format(_start)}'))),
              const SizedBox(width: 8),
              Expanded(child: OutlinedButton(onPressed: () => _pick(false), child: Text('Sampai ${_f.format(_end)}'))),
            ]),
          ),
          Expanded(
            child: AsyncBody(
              path: '${Api.cashFlow}?start_date=${_f.format(_start)}&end_date=${_f.format(_end)}',
              builder: (d) {
                final m = asMap(d);
                final masuk = num.tryParse('${m['total_pemasukan']}') ?? 0;
                final keluar = num.tryParse('${m['total_pengeluaran']}') ?? 0;
                final share = (masuk + keluar) == 0 ? 0.0 : (masuk / (masuk + keluar)).toDouble();
                return ListView(padding: const EdgeInsets.all(16), children: [
                  BalanceCard(label: 'Saldo akhir periode', amount: m['saldo_akhir'], sub: 'Saldo awal ${rupiah(m['saldo_awal'])}'),
                  KkCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('Arus kas bulan ini', style: TextStyle(fontWeight: FontWeight.w800, color: C.navy)),
                    const SizedBox(height: 10),
                    kv('Saldo awal', rupiah(m['saldo_awal'])),
                    kv('Pemasukan', rupiah(masuk)),
                    kv('Pengeluaran', rupiah(keluar)),
                    const SizedBox(height: 4),
                    const Text('Komposisi transaksi', style: TextStyle(fontSize: 11, color: C.muted)),
                    const SizedBox(height: 6),
                    ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(value: share, minHeight: 8, backgroundColor: C.navy.withOpacity(.85), color: C.primary)),
                    const SizedBox(height: 4),
                    Text('Pemasukan ${(share * 100).round()}% · Pengeluaran ${100 - (share * 100).round()}%', style: const TextStyle(fontSize: 10, color: C.muted)),
                  ])),
                  KkButton('Unduh laporan PDF', icon: Icons.download, outlined: true, loading: _busy, onPressed: _pdf),
                  const SectionTitle('Transaksi periode ini'),
                  for (final t in asList(m['transaksi'])) _trx(t),
                ]);
              },
            ),
          ),
        ]),
      );
}

/// Pengaturan iuran. GET /dues, POST /dues {name, description, amount}, PUT /dues/{id}, PATCH /dues/{id}/status {is_active}
class PengaturanIuranScreen extends ConsumerWidget {
  const PengaturanIuranScreen({super.key});

  Future<void> _form(BuildContext context, WidgetRef ref, [Map<String, dynamic>? due]) async {
    final nama = TextEditingController(text: txt(due?['name'], ''));
    final tarif = TextEditingController(text: due == null ? '' : (num.tryParse('${due['amount']}') ?? 0).toStringAsFixed(0));
    final desc = TextEditingController(text: txt(due?['description'], ''));
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(due == null ? 'Buat iuran' : 'Edit iuran'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          KkInput('Nama iuran', nama, hint: 'Kas Bulanan TI 2024'),
          KkInput('Tarif per mahasiswa', tarif, hint: '25000', keyboard: TextInputType.number),
          KkInput('Deskripsi (opsional)', desc),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Batal')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Simpan')),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    final body = {'name': nama.text.trim(), 'amount': num.tryParse(tarif.text) ?? 0, 'description': desc.text.trim()};
    final done = await guard(context, () => due == null
        ? ref.read(apiProvider).post(Api.dues, data: body)
        : ref.read(apiProvider).put('${Api.dues}/${due['id']}', data: body));
    if (done) ref.invalidate(dataProvider(Api.dues));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('Pengaturan iuran')),
        floatingActionButton: FloatingActionButton(backgroundColor: C.primary, onPressed: () => _form(context, ref), child: const Icon(Icons.add, color: Colors.white)),
        body: AsyncBody(
          path: Api.dues,
          builder: (d) => ListView(padding: const EdgeInsets.all(16), children: [
            const InfoBanner('Tarif berlaku untuk semua anggota angkatan saat tagihan dibuat. Perubahan tarif tidak mengubah tagihan yang sudah ada.'),
            for (final due in asList(d))
              KkCard(
                onTap: () => _form(context, ref, asMap(due)),
                child: Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(txt(due['name']), style: const TextStyle(fontWeight: FontWeight.w700, color: C.navy)),
                    const SizedBox(height: 2),
                    Text('${rupiah(due['amount'])} / mahasiswa · ${txt(due['bills_count'], '0')} tagihan', style: const TextStyle(fontSize: 11, color: C.muted)),
                  ])),
                  Switch(
                    value: due['is_active'] == true || due['is_active'] == 1,
                    onChanged: (v) async {
                      final ok = await guard(context, () => ref.read(apiProvider).patch('${Api.dues}/${due['id']}/status', data: {'is_active': v}));
                      if (ok) ref.invalidate(dataProvider(Api.dues));
                    },
                  ),
                ]),
              ),
            const SizedBox(height: 8),
            KkButton('Buat tagihan dari iuran', icon: Icons.receipt_long_outlined, onPressed: () => push(context, const BuatTagihanScreen())),
            if (asList(d).isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('Belum ada iuran. Tekan + untuk membuat.'))),
          ]),
        ),
      );
}

/// Tunggakan iuran. GET /arrears?period_month&period_year, POST /arrears/remind
class TunggakanScreen extends ConsumerStatefulWidget {
  const TunggakanScreen({super.key});
  @override
  ConsumerState<TunggakanScreen> createState() => _TunggakanScreenState();
}

class _TunggakanScreenState extends ConsumerState<TunggakanScreen> {
  int _m = DateTime.now().month, _y = DateTime.now().year;
  bool _busy = false;

  Future<void> _remind() async {
    setState(() => _busy = true);
    String? msg;
    final ok = await guard(context, () async {
      final res = await ref.read(apiProvider).post('${Api.arrears}/remind', data: {'period_month': _m, 'period_year': _y});
      msg = 'Pengingat terkirim ke ${asMap(res)['terkirim'] ?? 0} bendahara kelas';
    });
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg ?? 'Pengingat terkirim')));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Tunggakan iuran')),
        body: Column(children: [
          Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0), child: PeriodPicker(month: _m, year: _y, onChanged: (m, y) => setState(() { _m = m; _y = y; }))),
          Expanded(
            child: AsyncBody(
              path: '${Api.arrears}?period_month=$_m&period_year=$_y',
              builder: (d) {
                final m = asMap(d);
                final kelas = asList(m['kelas']);
                return ListView(padding: const EdgeInsets.all(16), children: [
                  Row(children: [
                    Expanded(child: Stat('Belum bayar', rupiah(m['total_belum']))),
                    Expanded(child: Stat('Menunggu verifikasi', rupiah(m['total_menunggu']))),
                  ]),
                  SectionTitle('Belum bayar per kelas (${txt(m['jumlah_mahasiswa_belum'], '0')} mahasiswa)'),
                  for (final k in kelas)
                    ListRow(
                      title: txt(k['kelas']),
                      subtitle: '${k['jumlah_belum']} belum bayar · ${k['jumlah_menunggu']} menunggu verifikasi',
                      trailing: Text(rupiah(k['nominal_belum']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: C.bad)),
                    ),
                  if (kelas.isEmpty) const InfoBanner('Tidak ada tunggakan pada periode ini.'),
                  const SizedBox(height: 8),
                  if (kelas.isNotEmpty) KkButton('Kirim pengingat ke bendahara', icon: Icons.send, loading: _busy, onPressed: _remind),
                ]);
              },
            ),
          ),
        ]),
      );
}

/// Kelola bendahara kelas. GET /classes, POST /classes {name}, GET /classes/{id}/available-treasurers, PUT /classes/{id}/treasurer
class KelolaBendaharaScreen extends ConsumerStatefulWidget {
  const KelolaBendaharaScreen({super.key});
  @override
  ConsumerState<KelolaBendaharaScreen> createState() => _KelolaBendaharaScreenState();
}

class _KelolaBendaharaScreenState extends ConsumerState<KelolaBendaharaScreen> {
  String _q = '';

  Future<void> _tambahKelas() async {
    final c = TextEditingController();
    final nama = await showDialog<String>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Tambah kelas'),
        content: TextField(controller: c, decoration: const InputDecoration(hintText: 'Nama kelas, mis. TI-2A')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal')),
          FilledButton(onPressed: () => Navigator.pop(context, c.text.trim()), child: const Text('Simpan')),
        ],
      ),
    );
    if (nama == null || nama.isEmpty || !mounted) return;
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.classes, data: {'name': nama}));
    if (ok) ref.invalidate(dataProvider(Api.classes));
  }

  Future<void> _tugaskan(Map<String, dynamic> kelas) async {
    List cand = [];
    final ok = await guard(context, () async {
      cand = asList(await ref.read(apiProvider).get('${Api.classes}/${kelas['id']}/available-treasurers'));
    });
    if (!ok || !mounted) return;
    final picked = await showModalBottomSheet<dynamic>(
      context: context,
      builder: (_) => SafeArea(
        child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(16), children: [
          Text('Pilih bendahara untuk ${txt(kelas['name'])}', style: const TextStyle(fontWeight: FontWeight.w800, color: C.navy)),
          const SizedBox(height: 8),
          if (cand.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('Belum ada akun bendahara kelas yang tersedia. Buat dulu lewat "Tambah bendahara kelas".')),
          for (final t in cand)
            ListTile(title: Text(txt(t['nama'])), subtitle: Text('@${txt(t['username'])}'), onTap: () => Navigator.pop(context, t['id'])),
        ]),
      ),
    );
    if (picked == null || !mounted) return;
    final done = await guard(context, () => ref.read(apiProvider).put('${Api.classes}/${kelas['id']}/treasurer', data: {'treasurer_id': picked}));
    if (done) ref.invalidate(dataProvider(Api.classes));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Bendahara kelas')),
        body: AsyncBody(
          path: Api.classes,
          builder: (d) {
            final list = asList(d).where((k) => txt(k['name'], '').toLowerCase().contains(_q.toLowerCase())).toList();
            return ListView(padding: const EdgeInsets.all(16), children: [
              TextField(onChanged: (v) => setState(() => _q = v), decoration: const InputDecoration(hintText: 'Cari nama kelas', prefixIcon: Icon(Icons.search, size: 18))),
              const SizedBox(height: 12),
              const SectionTitle('Pengelola aktif'),
              for (final k in list)
                ListRow(
                  title: txt(k['name']),
                  subtitle: 'Bendahara: ${txt(asMap(k['treasurer'])['nama'], 'belum ditentukan')} · ${txt(k['members_count'], '0')} anggota',
                  trailing: const Icon(Icons.swap_horiz, size: 18, color: C.primary),
                  onTap: () => _tugaskan(asMap(k)),
                ),
              const InfoBanner('Setiap bendahara hanya mengelola anggota, pembayaran, dan setoran kelasnya. Ketuk kelas untuk mengganti bendahara.'),
              KkButton('Tambah bendahara kelas', icon: Icons.person_add_alt, onPressed: () => push(context, const TambahUserScreen(roles: ['bendahara_kelas']))),
              const SizedBox(height: 8),
              KkButton('Tambah kelas', outlined: true, onPressed: _tambahKelas),
            ]);
          },
        ),
      );
}


/// GET /users (angkatan) + tombol tambah (bendahara kelas / anggota)
class PenggunaScreen extends StatelessWidget {
  const PenggunaScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Pengguna angkatan')),
        floatingActionButton: FloatingActionButton(
          backgroundColor: C.primary,
          onPressed: () => push(context, const TambahUserScreen(roles: ['bendahara_kelas', 'anggota'])),
          child: const Icon(Icons.add, color: Colors.white),
        ),
        body: const UserListBody(),
      );
}

/// GET /bills (angkatan) + buat tagihan: POST /bills {dues_id, title, period_month, period_year, due_date}
class TagihanScreen extends StatelessWidget {
  const TagihanScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Tagihan angkatan')),
        floatingActionButton: FloatingActionButton(
          backgroundColor: C.primary,
          onPressed: () => push(context, const BuatTagihanScreen()),
          child: const Icon(Icons.add, color: Colors.white),
        ),
        body: const BillListBody(),
      );
}

class BuatTagihanScreen extends ConsumerStatefulWidget {
  const BuatTagihanScreen({super.key});
  @override
  ConsumerState<BuatTagihanScreen> createState() => _BuatTagihanScreenState();
}

class _BuatTagihanScreenState extends ConsumerState<BuatTagihanScreen> {
  final _title = TextEditingController();
  final _tahun = TextEditingController(text: '${DateTime.now().year}');
  final _due = TextEditingController();
  dynamic _duesId;
  int _bulan = DateTime.now().month;
  bool _busy = false;

  Future<void> _pickDate() async {
    final d = await showDatePicker(context: context, initialDate: DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2100));
    if (d != null) _due.text = DateFormat('yyyy-MM-dd').format(d);
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.bills, data: {
          'dues_id': _duesId,
          'title': _title.text.trim(),
          'period_month': _bulan,
          'period_year': int.tryParse(_tahun.text) ?? DateTime.now().year,
          'due_date': _due.text,
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
    final dues = asList(ref.watch(dataProvider(Api.dues)).value);
    return Scaffold(
      appBar: AppBar(title: const Text('Buat tagihan')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const InfoBanner('Tagihan dibuat untuk seluruh anggota angkatan sesuai nominal iuran.'),
        const Text('Iuran *', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
        const SizedBox(height: 6),
        DropdownButtonFormField<dynamic>(
          value: _duesId,
          hint: const Text('Pilih iuran', style: TextStyle(fontSize: 13)),
          items: [for (final d in dues) DropdownMenuItem(value: d['id'], child: Text('${txt(d['name'])} (${rupiah(d['amount'])})'))],
          onChanged: (v) => setState(() => _duesId = v),
        ),
        TextButton.icon(onPressed: () => push(context, const PengaturanIuranScreen()), icon: const Icon(Icons.add, size: 16), label: const Text('Buat iuran baru')),
        KkInput('Judul tagihan *', _title, hint: 'Kas November 2026'),
        const Text('Bulan *', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
        const SizedBox(height: 6),
        DropdownButtonFormField<int>(
          value: _bulan,
          items: [for (var i = 1; i <= 12; i++) DropdownMenuItem(value: i, child: Text('$i'))],
          onChanged: (v) => setState(() => _bulan = v!),
        ),
        const SizedBox(height: 12),
        KkInput('Tahun *', _tahun, keyboard: TextInputType.number),
        KkInput('Jatuh tempo *', _due, readOnly: true, onTap: _pickDate, suffix: Icons.calendar_today_outlined),
        KkButton('Buat tagihan', loading: _busy, onPressed: _submit),
      ]),
    );
  }
}
