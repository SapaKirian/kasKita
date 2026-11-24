import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'api.dart';
import 'theme.dart';

String rupiah(dynamic v) {
  final n = num.tryParse('${v ?? 0}') ?? 0;
  return NumberFormat.currency(locale: 'id_ID', symbol: 'Rp', decimalDigits: 0).format(n);
}

String txt(dynamic v, [String d = '-']) => (v == null || '$v'.isEmpty) ? d : '$v';
/// Menerima List langsung, atau paginator Laravel {data:[...], current_page, ...}
List asList(dynamic v) => v is List ? v : (v is Map && v['data'] is List ? v['data'] as List : const []);
/// Ambil 'YYYY-MM-DD' dari timestamp Laravel.
String d10(dynamic v) => v == null ? '-' : '$v'.split('T').first;
String label(dynamic v) => txt(v).replaceAll('_', ' ');
String? roleLabel(dynamic r) => const {'bendahara_angkatan': 'Bendahara Angkatan', 'bendahara_kelas': 'Bendahara Kelas', 'anggota': 'Anggota'}[r];
Map<String, dynamic> asMap(dynamic v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

Future<bool> guard(BuildContext c, Future<void> Function() fn) async {
  try {
    await fn();
    return true;
  } catch (e) {
    if (c.mounted) ScaffoldMessenger.of(c).showSnackBar(SnackBar(content: Text('$e')));
    return false;
  }
}

class KkButton extends StatelessWidget {
  const KkButton(this.label, {super.key, this.onPressed, this.loading = false, this.outlined = false, this.icon, this.danger = false});
  final String label;
  final VoidCallback? onPressed;
  final bool loading, outlined, danger;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(10));
    final child = loading
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
        : Row(mainAxisSize: MainAxisSize.min, children: [
            if (icon != null) ...[Icon(icon, size: 16), const SizedBox(width: 8)],
            Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
          ]);
    return SizedBox(
      width: double.infinity,
      height: 48,
      child: outlined
          ? OutlinedButton(
              style: OutlinedButton.styleFrom(shape: shape, foregroundColor: danger ? C.bad : C.navy, side: BorderSide(color: danger ? C.bad : C.line)),
              onPressed: loading ? null : onPressed,
              child: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)))
          : FilledButton(
              style: FilledButton.styleFrom(shape: shape, backgroundColor: C.primary),
              onPressed: loading ? null : onPressed,
              child: child),
    );
  }
}

class KkInput extends StatefulWidget {
  const KkInput(this.label, this.ctrl, {super.key, this.hint, this.password = false, this.keyboard, this.readOnly = false, this.onTap, this.suffix});
  final String label;
  final TextEditingController ctrl;
  final String? hint;
  final bool password, readOnly;
  final TextInputType? keyboard;
  final VoidCallback? onTap;
  final IconData? suffix;
  @override
  State<KkInput> createState() => _KkInputState();
}

class _KkInputState extends State<KkInput> {
  late bool _hide = widget.password;
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(widget.label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: C.navy)),
        const SizedBox(height: 6),
        TextField(
          controller: widget.ctrl,
          obscureText: _hide,
          keyboardType: widget.keyboard,
          readOnly: widget.readOnly,
          onTap: widget.onTap,
          style: const TextStyle(fontSize: 13),
          decoration: InputDecoration(
            hintText: widget.hint,
            hintStyle: const TextStyle(color: C.muted, fontSize: 13),
            suffixIcon: widget.password
                ? IconButton(icon: Icon(_hide ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 18), onPressed: () => setState(() => _hide = !_hide))
                : (widget.suffix != null ? Icon(widget.suffix, size: 18) : null),
          ),
        ),
      ]),
    );
  }
}

class KkCard extends StatelessWidget {
  const KkCard({super.key, required this.child, this.onTap, this.margin = const EdgeInsets.only(bottom: 10)});
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets margin;
  @override
  Widget build(BuildContext context) => Container(
        margin: margin,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: C.line)),
        child: InkWell(borderRadius: BorderRadius.circular(14), onTap: onTap, child: Padding(padding: const EdgeInsets.all(14), child: child)),
      );
}

class StatusBadge extends StatelessWidget {
  const StatusBadge(this.status, {super.key});
  final String? status;
  @override
  Widget build(BuildContext context) {
    final s = (status ?? '').toLowerCase();
    Color fg = C.muted, bg = const Color(0xFFE8EDF5);
    if (['ditolak', 'telat', 'belum', 'tunggak', 'nonaktif'].any(s.contains)) {
      fg = C.bad; bg = C.badBg;
    } else if (['menunggu', 'pending', 'diproses', 'verifikasi'].any(s.contains)) {
      fg = C.warn; bg = C.warnBg;
    } else if (['lunas', 'verified', 'aktif', 'disetujui', 'diterima', 'selesai'].any(s.contains)) {
      fg = C.ok; bg = C.okBg;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(label(status), style: TextStyle(color: fg, fontSize: 10, fontWeight: FontWeight.w700)),
    );
  }
}

class BalanceCard extends StatelessWidget {
  const BalanceCard({super.key, required this.label, required this.amount, this.sub});
  final String label;
  final dynamic amount;
  final String? sub;
  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: C.navy, borderRadius: BorderRadius.circular(14)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
          const SizedBox(height: 6),
          Text(rupiah(amount), style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
          if (sub != null) ...[const SizedBox(height: 6), Text(sub!, style: const TextStyle(color: Colors.white60, fontSize: 11))],
        ]),
      );
}

class Stat extends StatelessWidget {
  const Stat(this.label, this.value, {super.key});
  final String label, value;
  @override
  Widget build(BuildContext context) => KkCard(
        margin: const EdgeInsets.only(bottom: 12, right: 6),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: C.navy)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(fontSize: 11, color: C.muted)),
        ]),
      );
}

class InfoBanner extends StatelessWidget {
  const InfoBanner(this.text, {super.key, this.warn = false});
  final String text;
  final bool warn;
  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: warn ? C.warnBg : const Color(0xFFE3ECFC), borderRadius: BorderRadius.circular(10)),
        child: Text(text, style: TextStyle(fontSize: 11, color: warn ? C.warn : C.primary)),
      );
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 4, bottom: 8),
        child: Text(text, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: C.navy)),
      );
}

class ListRow extends StatelessWidget {
  const ListRow({super.key, required this.title, this.subtitle, this.trailing, this.onTap});
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => KkCard(
        onTap: onTap,
        child: Row(children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: C.navy)),
              if (subtitle != null) Padding(padding: const EdgeInsets.only(top: 2), child: Text(subtitle!, style: const TextStyle(fontSize: 11, color: C.muted))),
            ]),
          ),
          if (trailing != null) trailing!,
        ]),
      );
}

Widget tabPage(String title, Widget body, {Widget? action}) => SafeArea(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
          child: Row(children: [
            Expanded(child: Text(title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: C.navy))),
            if (action != null) action,
          ]),
        ),
        Expanded(child: body),
      ]),
    );

/// Memuat GET [path], tampilkan loading/error/refresh. [builder] harus mengembalikan widget scrollable (ListView).
class AsyncBody extends ConsumerWidget {
  const AsyncBody({super.key, required this.path, required this.builder});
  final String path;
  final Widget Function(dynamic data) builder;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final a = ref.watch(dataProvider(path));
    return a.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text('$e', textAlign: TextAlign.center),
            TextButton(onPressed: () => ref.invalidate(dataProvider(path)), child: const Text('Coba lagi')),
          ]),
        ),
      ),
      data: (d) => RefreshIndicator(onRefresh: () => ref.refresh(dataProvider(path).future), child: builder(d)),
    );
  }
}

const bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
String periode(dynamic m, dynamic y) {
  final i = int.tryParse('$m') ?? 0;
  return '${i >= 1 && i <= 12 ? bulanNama[i] : '-'} ${txt(y, '')}'.trim();
}

/// Pilih bulan + tahun. Dipakai filter periode.
class PeriodPicker extends StatelessWidget {
  const PeriodPicker({super.key, required this.month, required this.year, required this.onChanged});
  final int month, year;
  final void Function(int month, int year) onChanged;
  @override
  Widget build(BuildContext context) {
    final years = [for (var y = DateTime.now().year - 2; y <= DateTime.now().year + 1; y++) y];
    return Row(children: [
      Expanded(
        flex: 3,
        child: DropdownButtonFormField<int>(
          value: month,
          items: [for (var i = 1; i <= 12; i++) DropdownMenuItem(value: i, child: Text(bulanNama[i]))],
          onChanged: (v) => onChanged(v!, year),
        ),
      ),
      const SizedBox(width: 8),
      Expanded(
        flex: 2,
        child: DropdownButtonFormField<int>(
          value: year,
          items: [for (final y in years) DropdownMenuItem(value: y, child: Text('$y'))],
          onChanged: (v) => onChanged(month, v!),
        ),
      ),
    ]);
  }
}
