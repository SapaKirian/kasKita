import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'endpoints.dart';

final prefsProvider = Provider<SharedPreferences>((_) => throw UnimplementedError());

class ApiException implements Exception {
  final String message;
  ApiException(this.message);
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient(this._prefs) {
    dio = Dio(BaseOptions(
      baseUrl: Api.baseUrl,
      connectTimeout: const Duration(seconds: 15),
      headers: {'Accept': 'application/json'},
    ));
    dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
      final t = _prefs.getString('token');
      if (t != null) o.headers['Authorization'] = 'Bearer $t';
      h.next(o);
    }));
  }

  final SharedPreferences _prefs;
  late final Dio dio;

  // Jika Laravel membungkus response dengan {"data": ...}, otomatis dibuka.
  dynamic _unwrap(dynamic d) => (d is Map && d.containsKey('data')) ? d['data'] : d;

  Future<dynamic> _run(Future<Response> Function() call) async {
    try {
      return _unwrap((await call()).data);
    } on DioException catch (e) {
      final d = e.response?.data;
      var msg = 'Tidak dapat terhubung ke server';
      if (d is Map) {
        msg = (d['message'] ?? msg).toString();
        final errs = d['errors'];
        if (errs is Map && errs.isNotEmpty && errs.values.first is List) {
          msg = (errs.values.first as List).first.toString();
        }
      }
      throw ApiException(msg);
    }
  }

  Future<dynamic> get(String p, {Map<String, dynamic>? query}) =>
      _run(() => dio.get(p, queryParameters: query));
  Future<dynamic> post(String p, {Object? data}) => _run(() => dio.post(p, data: data));
  Future<dynamic> patch(String p, {Object? data}) => _run(() => dio.patch(p, data: data));
  Future<dynamic> put(String p, {Object? data}) => _run(() => dio.put(p, data: data));

  /// Unduh file (mis. PDF) dengan token, simpan ke [savePath].
  Future<void> download(String p, String savePath, {Map<String, dynamic>? query}) async {
    try {
      await dio.download(p, savePath, queryParameters: query);
    } on DioException catch (e) {
      throw ApiException(e.response?.statusCode == 422 ? 'Rentang tanggal tidak valid' : 'Gagal mengunduh file');
    }
  }
}

final apiProvider = Provider<ApiClient>((ref) => ApiClient(ref.read(prefsProvider)));

/// GET generik: ref.watch(dataProvider('/angkatan/dashboard'))
final dataProvider = FutureProvider.family<dynamic, String>(
  (ref, path) => ref.read(apiProvider).get(path),
);

/// State login. null = belum login. Berisi data user: {name, email, role, ...}
class AuthController extends Notifier<Map<String, dynamic>?> {
  @override
  Map<String, dynamic>? build() {
    final raw = ref.read(prefsProvider).getString('user');
    return raw == null ? null : Map<String, dynamic>.from(jsonDecode(raw));
  }

  /// POST /login {username, password} -> {data:{user, token}}
  Future<void> login(String username, String password) async {
    final res = await ref.read(apiProvider).post(Api.login, data: {'username': username, 'password': password});
    await _save(res);
  }

  Future<void> _save(dynamic res) async {
    final prefs = ref.read(prefsProvider);
    await prefs.setString('token', res['token'].toString());
    await prefs.setString('user', jsonEncode(res['user']));
    state = Map<String, dynamic>.from(res['user']);
  }

  /// Perbarui data user di memori + penyimpanan (setelah edit profil).
  Future<void> updateUser(Map<String, dynamic> user) async {
    await ref.read(prefsProvider).setString('user', jsonEncode(user));
    state = Map<String, dynamic>.from(user);
  }

  Future<void> logout() async {
    try {
      await ref.read(apiProvider).post(Api.logout);
    } catch (_) {}
    final prefs = ref.read(prefsProvider);
    await prefs.remove('token');
    await prefs.remove('user');
    state = null;
  }
}

final authProvider = NotifierProvider<AuthController, Map<String, dynamic>?>(AuthController.new);
