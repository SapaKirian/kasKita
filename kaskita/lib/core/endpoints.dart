import 'package:flutter/foundation.dart' show kIsWeb;
/// Sesuai routes/api.php backend KasKita (prefix /api/v1).
class Api {
  // Emulator Android -> `php artisan serve`. HP fisik: ganti dengan IP laptop, mis. http://192.168.1.10:8000/api/v1
  static final String baseUrl = kIsWeb
      ? 'http://127.0.0.1:8000/api/v1'
      : 'http://10.0.2.2:8000/api/v1';

  static const login = '/login';
  static const profile = '/me'; // PUT
  static const password = '/me/password'; // PUT
  static const notifications = '/notifications'; // GET, PATCH /read-all, PATCH /{id}/read
  static const anggotaSummary = '/anggota/summary';
  static const arrears = '/arrears'; // GET ?period_month&period_year, POST /remind
  static const cashFlowPdf = '/reports/cash-flow/pdf';

  // Bendahara Kelas
  static const kelasSummary = '/kelas/summary';
  static const kelasMembers = '/kelas/members';
  static const kelasTransactions = '/kelas/transactions';
  static const kelasExpenses = '/kelas/expenses';
  static const kelasReport = '/kelas/report';
  static const kelasPayments = '/kelas/payments'; // ?status=
  static const kelasCashPayment = '/kelas/payments/cash';
  static const kelasApproved = '/kelas/approved-payments';
  static const kelasRemittances = '/kelas/remittances';
  static const kelasBills = '/kelas/bills'; // POST (kelas mandiri)
  static const remittanceCreate = '/remittances'; // POST (bendahara kelas)
  static const logout = '/logout';
  static const me = '/me';

  static const users = '/users'; // GET, POST
  static const classes = '/classes'; // angkatan
  static const dues = '/dues'; // angkatan
  static const bills = '/bills'; // semua role (isi beda per role)
  static const remittances = '/remittances'; // angkatan
  static const cashTransactions = '/cash-transactions'; // angkatan
  static const cashSummary = '/cash-transactions/summary';
  static const cashFlow = '/reports/cash-flow'; // ?start_date=&end_date=
  static const paymentsPending = '/payments/pending'; // bendahara kelas & angkatan
  static const paymentsMy = '/payments/my'; // anggota
  static const payments = '/payments'; // POST (anggota)
  static const paymentProofs = '/payment-proofs'; // GET /{id}
}
