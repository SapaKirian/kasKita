import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/api.dart';
import '../../core/endpoints.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../shared/role_shell.dart';
import '../shared/shared_screens.dart';

class KelasShell extends StatelessWidget {
  const KelasShell({super.key});
  @override
  Widget build(BuildContext context) => const RoleShell(tabs: [
        ShellTab('Beranda', Icons.home_outlined, KelasDashboard()),
        ShellTab('Anggota', Icons.people_outline, AnggotaKelasTab()),
        ShellTab('Kas', Icons.account_balance_wallet_outlined, KasKelasTab()),
        ShellTab('Laporan', Icons.bar_chart, LaporanKelasTab()),
        ShellTab('Profil', Icons.person_outline, ProfileTab()),
      ]);
}

bool _mandiri(dynamic summary) => asMap(asMap(summary)['kelas'])['management_type'] == 'kelas_mandiri';

Widget _trxRow(dynamic t) {
  final keluar = t['tipe'] == 'keluar';
  return ListRow(
    title: txt(t['judul']),
    subtitle: '${d10(t['tanggal'])} · ${txt(t['keterangan'], '')}',
    trailing: Text('${keluar ? '-' : '+'}${rupiah(t['nominal'])}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: keluar ? C.bad : C.ok)),
  );
}

/// GET /kelas/summary + /kelas/transactions
class KelasDashboard extends ConsumerWidget {
  const KelasDashboard({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(dataProvider(Api.kelasSummary));
    final trx = ref.watch(dataProvider(Api.kelasTransactions)).value;
    final m = asMap(s.value);
    final mandiri = _mandiri(m);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(dataProvider),
        child: ListView(padding: const EdgeInsets.all(16), children: [
          const GreetingHeader(),
          if (s.hasError) InfoBanner('${s.error}', warn: true),
          if (s.isLoading) const Padding(padding: EdgeInsets.all(24), child: Center(child: CircularProgressIndicator())),
          if (s.hasValue) ...[
            BalanceCard(
              label: 'Saldo kas ${txt(asMap(m['kelas'])['name'], 'kelas')}',
              amount: m['saldo'],
              sub: '${txt(m['lunas'], '0')}/${txt(m['penerima'], '0')} anggota lunas${mandiri ? ' · kelas mandiri' : ''}',
            ),
            Row(children: [
              Expanded(child: Stat('Anggota lunas', '${txt(m['lunas'], '0')}/${txt(m['penerima'], '0')}')),
              Expanded(child: Stat('Bukti menunggu', txt(m['pembayaran_menunggu'], '0'))),
            ]),
            GridView.count(
              crossAxisCount: 4, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), childAspectRatio: .85,
              children: [
                _quick(context, 'Catat lunas', Icons.check_circle_outline, const CatatPembayaranScreen()),
                _quick(context, 'Verifikasi', Icons.fact_check_outlined, const PendingPaymentsScreen(withHistory: true)),
                _quick(context, 'Iuran', Icons.receipt_long_outlined, const IuranKelasScreen()),
                if (!mandiri) _quick(context, 'Setoran', Icons.north_east, const SetoranAngkatanScreen()),
                _quick(context, 'Notifikasi', Icons.notifications_none, const NotifikasiScreen()),
              ],
            ),
            const SizedBox(height: 8),
            const SectionTitle('Aktivitas kelas'),
            for (final t in asList(asMap(trx)['transaksi']).take(4)) _trxRow(t),
            if (asList(asMap(trx)['transaksi']).isEmpty) const InfoBanner('Belum ada aktivitas kas.'),
          ],
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

/// GET /kelas/members -> {total, lunas, menunggu, belum, members:[{id, nama, username, status}]}
class AnggotaKelasTab extends StatefulWidget {
  const AnggotaKelasTab({super.key});
  @override
  State<AnggotaKelasTab> createState() => _AnggotaKelasTabState();
}

class _AnggotaKelasTabState extends State<AnggotaKelasTab> {
  String _q = '';
  @override
  Widget build(BuildContext context) => tabPage(
        'Anggota kelas',
        AsyncBody(
          path: Api.kelasMembers,
          builder: (d) {
            final m = asMap(d);
            final list = asList(m['members']).where((e) => '${e['nama']} ${e['username']}'.toLowerCase().contains(_q.toLowerCase())).toList();
            return ListView(padding: const EdgeInsets.all(16), children: [
              TextField(onChanged: (v) => setState(() => _q = v), decoration: const InputDecoration(hintText: 'Cari nama/username', prefixIcon: Icon(Icons.search, size: 18))),
              const SizedBox(height: 12),
              Row(children: [
                Expanded(child: Stat('Total', txt(m['total'], '0'))),
                Expanded(child: Stat('Lunas', txt(m['lunas'], '0'))),
                Expanded(child: Stat('Belum', txt(m['belum'], '0'))),
              ]),
              const SectionTitle('Daftar anggota'),
              for (final a in list) ListRow(title: txt(a['nama']), subtitle: '@${txt(a['username'])}', trailing: StatusBadge(a['status']?.toString())),
              KkButton('Tambah anggota', icon: Icons.person_add_alt, onPressed: () => push(context, const TambahUserScreen(roles: ['anggota']))),
            ]);
          },
        ),
      );
}

/// GET /kelas/transactions?tipe= -> {saldo, pemasukan, pengeluaran, transaksi:[{tipe, judul, keterangan, nominal, tanggal}]}
class KasKelasTab extends StatefulWidget {
  const KasKelasTab({super.key});
  @override
  State<KasKelasTab> createState() => _KasKelasTabState();
}

class _KasKelasTabState extends State<KasKelasTab> {
  String _tipe = '';
  @override
  Widget build(BuildContext context) => tabPage(
        'Kas kelas',
        AsyncBody(
          path: _tipe.isEmpty ? Api.kelasTransactions : '${Api.kelasTransactions}?tipe=$_tipe',
          builder: (d) {
            final m = asMap(d);
            return ListView(padding: const EdgeInsets.all(16), children: [
              BalanceCard(label: 'Saldo kas kelas', amount: m['saldo']),
              Row(children: [
                Expanded(child: Stat('Pemasukan', rupiah(m['pemasukan']))),
                Expanded(child: Stat('Pengeluaran + setoran', rupiah(m['pengeluaran']))),
              ]),
              Wrap(spacing: 8, children: [
                for (final e in const {'': 'Semua', 'masuk': 'Masuk', 'keluar': 'Keluar'}.entries)
                  ChoiceChip(label: Text(e.value), selected: _tipe == e.key, onSelected: (_) => setState(() => _tipe = e.key)),
              ]),
              const SectionTitle('Transaksi terbaru'),
              for (final t in asList(m['transaksi'])) _trxRow(t),
              if (asList(m['transaksi']).isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('Belum ada transaksi'))),
              const SizedBox(height: 8),
              KkButton('Tambah pengeluaran', icon: Icons.add, onPressed: () => push(context, const PengeluaranKelasScreen())),
            ]);
          },
        ),
      );
}

/// POST /kelas/expenses {category, amount, description, transaction_date}
class PengeluaranKelasScreen extends ConsumerStatefulWidget {
  const PengeluaranKelasScreen({super.key});
  @override
  ConsumerState<PengeluaranKelasScreen> createState() => _PengeluaranKelasScreenState();
}

class _PengeluaranKelasScreenState extends ConsumerState<PengeluaranKelasScreen> {
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
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.kelasExpenses, data: {
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
  Widget build(BuildContext context) {
    final saldo = asMap(ref.watch(dataProvider(Api.kelasSummary)).value)['saldo'];
    return Scaffold(
      appBar: AppBar(title: const Text('Tambah pengeluaran')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        KkInput('Kategori *', _kategori, hint: 'mis. Fotokopi modul'),
        KkInput('Nominal *', _nominal, hint: '50000', keyboard: TextInputType.number),
        KkInput('Keterangan', _desc),
        KkInput('Tanggal *', _tgl, readOnly: true, onTap: _pickDate, suffix: Icons.calendar_today_outlined),
        KkCard(child: kv('Saldo saat ini', rupiah(saldo))),
        KkButton('Simpan pengeluaran', loading: _busy, onPressed: _submit),
      ]),
    );
  }
}

/// GET /kelas/report?start_date&end_date
class LaporanKelasTab extends StatefulWidget {
  const LaporanKelasTab({super.key});
  @override
  State<LaporanKelasTab> createState() => _LaporanKelasTabState();
}

class _LaporanKelasTabState extends State<LaporanKelasTab> {
  DateTime _start = DateTime(DateTime.now().year, DateTime.now().month, 1);
  DateTime _end = DateTime.now();
  final _f = DateFormat('yyyy-MM-dd');

  Future<void> _pick(bool start) async {
    final d = await showDatePicker(context: context, initialDate: start ? _start : _end, firstDate: DateTime(2020), lastDate: DateTime(2100));
    if (d != null) setState(() => start ? _start = d : _end = d);
  }

  @override
  Widget build(BuildContext context) => tabPage(
        'Laporan kelas',
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
              path: '${Api.kelasReport}?start_date=${_f.format(_start)}&end_date=${_f.format(_end)}',
              builder: (d) {
                final m = asMap(d);
                return ListView(padding: const EdgeInsets.all(16), children: [
                  BalanceCard(label: 'Saldo akhir periode', amount: m['saldo_akhir'], sub: txt(m['kelas'], '')),
                  KkCard(child: Column(children: [
                    kv('Saldo awal', rupiah(m['saldo_awal'])),
                    kv('Pemasukan iuran', rupiah(m['total_pemasukan'])),
                    kv('Pengeluaran', rupiah(m['total_pengeluaran'])),
                    kv('Setoran ke angkatan', rupiah(m['total_setoran'])),
                  ])),
                  const SectionTitle('Rincian transaksi'),
                  for (final t in asList(m['transaksi'])) _trxRow(t),
                ]);
              },
            ),
          ),
        ]),
      );
}

/// Iuran kelas = GET /bills (kelas). Kelas mandiri bisa membuat tagihan sendiri: POST /kelas/bills
class IuranKelasScreen extends ConsumerWidget {
  const IuranKelasScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mandiri = _mandiri(ref.watch(dataProvider(Api.kelasSummary)).value);
    return Scaffold(
      appBar: AppBar(title: const Text('Iuran kelas')),
      floatingActionButton: mandiri
          ? FloatingActionButton(backgroundColor: C.primary, onPressed: () => push(context, const BuatTagihanKelasScreen()), child: const Icon(Icons.add, color: Colors.white))
          : null,
      body: const BillListBody(),
    );
  }
}

class BuatTagihanKelasScreen extends ConsumerStatefulWidget {
  const BuatTagihanKelasScreen({super.key});
  @override
  ConsumerState<BuatTagihanKelasScreen> createState() => _BuatTagihanKelasScreenState();
}

class _BuatTagihanKelasScreenState extends ConsumerState<BuatTagihanKelasScreen> {
  final _title = TextEditingController();
  final _amount = TextEditingController();
  final _due = TextEditingController();
  int _m = DateTime.now().month, _y = DateTime.now().year;
  bool _busy = false;

  Future<void> _pickDate() async {
    final d = await showDatePicker(context: context, initialDate: DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2100));
    if (d != null) _due.text = DateFormat('yyyy-MM-dd').format(d);
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.kelasBills, data: {
          'title': _title.text.trim(),
          'amount': num.tryParse(_amount.text) ?? 0,
          'period_month': _m,
          'period_year': _y,
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
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Buat tagihan kelas')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          KkInput('Judul tagihan *', _title, hint: 'Kas Oktober 2026'),
          KkInput('Nominal per anggota *', _amount, keyboard: TextInputType.number),
          const Text('Periode *', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
          const SizedBox(height: 6),
          PeriodPicker(month: _m, year: _y, onChanged: (m, y) => setState(() { _m = m; _y = y; })),
          const SizedBox(height: 12),
          KkInput('Jatuh tempo *', _due, readOnly: true, onTap: _pickDate, suffix: Icons.calendar_today_outlined),
          KkButton('Buat tagihan', loading: _busy, onPressed: _submit),
        ]),
      );
}

/// Catat pembayaran tunai. Pilih tagihan (GET /bills) -> anggota belum lunas (GET /bills/{id}) -> POST /kelas/payments/cash
class CatatPembayaranScreen extends ConsumerStatefulWidget {
  const CatatPembayaranScreen({super.key});
  @override
  ConsumerState<CatatPembayaranScreen> createState() => _CatatPembayaranScreenState();
}

class _CatatPembayaranScreenState extends ConsumerState<CatatPembayaranScreen> {
  final _nominal = TextEditingController();
  dynamic _billId;
  dynamic _recipientId;
  bool _busy = false;

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.kelasCashPayment, data: {
          'bill_recipient_id': _recipientId,
          'amount': num.tryParse(_nominal.text) ?? 0,
        }));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      ref.invalidate(dataProvider);
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pembayaran tunai dicatat lunas')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final bills = asList(ref.watch(dataProvider(Api.bills)).value);
    final detail = _billId == null ? null : ref.watch(dataProvider('${Api.bills}/$_billId')).value;
    final open = asList(asMap(detail)['recipients']).where((r) => r['status'] != 'lunas').toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Catat pembayaran')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const InfoBanner('Catat iuran tunai dari anggota. Pembayaran langsung ditandai lunas dan anggota mendapat notifikasi.'),
        const Text('Tagihan', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
        const SizedBox(height: 6),
        DropdownButtonFormField<dynamic>(
          value: _billId,
          hint: const Text('Pilih tagihan', style: TextStyle(fontSize: 13)),
          items: [for (final b in bills) DropdownMenuItem(value: b['id'], child: Text(txt(b['title'])))],
          onChanged: (v) => setState(() { _billId = v; _recipientId = null; _nominal.clear(); }),
        ),
        const SizedBox(height: 12),
        const Text('Anggota', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
        const SizedBox(height: 6),
        DropdownButtonFormField<dynamic>(
          value: _recipientId,
          isExpanded: true,
          hint: Text(_billId == null ? 'Pilih tagihan dulu' : 'Pilih anggota', style: const TextStyle(fontSize: 13)),
          items: [
            for (final r in open)
              DropdownMenuItem(
                value: r['id'],
                child: Text('${txt(asMap(r['user'])['nama'])} · sisa ${rupiah((num.tryParse('${r['bill_amount']}') ?? 0) - (num.tryParse('${r['paid_amount']}') ?? 0))}', overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (v) {
            final r = asMap(open.firstWhere((e) => e['id'] == v));
            setState(() {
              _recipientId = v;
              _nominal.text = ((num.tryParse('${r['bill_amount']}') ?? 0) - (num.tryParse('${r['paid_amount']}') ?? 0)).toStringAsFixed(0);
            });
          },
        ),
        const SizedBox(height: 12),
        KkInput('Nominal pembayaran', _nominal, keyboard: TextInputType.number),
        KkButton('Konfirmasi & catat lunas', icon: Icons.check_circle_outline, loading: _busy, onPressed: _recipientId == null ? null : _submit),
      ]),
    );
  }
}

/// Setoran ke angkatan. GET /kelas/approved-payments?period_month&period_year, POST /remittances, GET /kelas/remittances
class SetoranAngkatanScreen extends ConsumerStatefulWidget {
  const SetoranAngkatanScreen({super.key});
  @override
  ConsumerState<SetoranAngkatanScreen> createState() => _SetoranAngkatanScreenState();
}

class _SetoranAngkatanScreenState extends ConsumerState<SetoranAngkatanScreen> {
  int _m = DateTime.now().month, _y = DateTime.now().year;
  final Set<dynamic> _skip = {};
  final _notes = TextEditingController();
  bool _busy = false;

  Future<void> _submit(List items) async {
    final ids = [for (final p in items) if (!_skip.contains(p['id'])) p['id']];
    if (ids.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Pilih minimal satu pembayaran')));
      return;
    }
    setState(() => _busy = true);
    final ok = await guard(context, () => ref.read(apiProvider).post(Api.remittanceCreate, data: {
          'period_month': _m,
          'period_year': _y,
          'payment_ids': ids,
          if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
        }));
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      ref.invalidate(dataProvider);
      _skip.clear();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Setoran dikirim, menunggu persetujuan bendahara angkatan')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final approvedPath = '${Api.kelasApproved}?period_month=$_m&period_year=$_y';
    final hist = asList(ref.watch(dataProvider(Api.kelasRemittances)).value);
    return Scaffold(
      appBar: AppBar(title: const Text('Setoran ke angkatan')),
      body: AsyncBody(
        path: approvedPath,
        builder: (d) {
          final m = asMap(d);
          final items = asList(m['items']);
          final selected = items.where((p) => !_skip.contains(p['id']));
          final total = selected.fold<num>(0, (a, p) => a + (num.tryParse('${p['amount']}') ?? 0));
          return ListView(padding: const EdgeInsets.all(16), children: [
            const InfoBanner('Pilih pembayaran yang sudah disetujui untuk disetorkan ke bendahara angkatan.'),
            PeriodPicker(month: _m, year: _y, onChanged: (m, y) => setState(() { _m = m; _y = y; _skip.clear(); })),
            const SizedBox(height: 12),
            SectionTitle('Pembayaran siap disetor (${items.length})'),
            for (final p in items)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: !_skip.contains(p['id']),
                onChanged: (v) => setState(() => v == true ? _skip.remove(p['id']) : _skip.add(p['id'])),
                title: Text(txt(asMap(p['payer'])['nama']), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                subtitle: Text(txt(asMap(asMap(p['bill_recipient'])['bill'])['title']), style: const TextStyle(fontSize: 11)),
                secondary: Text(rupiah(p['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
              ),
            if (items.isEmpty) const InfoBanner('Tidak ada pembayaran disetujui yang belum disetor pada periode ini.'),
            KkCard(child: kv('Total setoran', rupiah(total))),
            KkInput('Catatan (opsional)', _notes),
            KkButton('Ajukan setoran', icon: Icons.north_east, loading: _busy, onPressed: items.isEmpty ? null : () => _submit(items)),
            const SizedBox(height: 16),
            const SectionTitle('Riwayat setoran'),
            for (final r in hist)
              ListRow(
                title: periode(r['period_month'], r['period_year']),
                subtitle: '${txt(r['items_count'], '0')} pembayaran',
                trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text(rupiah(r['amount']), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                  StatusBadge(r['status']?.toString()),
                ]),
              ),
          ]);
        },
      ),
    );
  }
}
