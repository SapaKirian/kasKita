import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class C {
  static const primary = Color(0xFF1F5FE0);
  static const navy = Color(0xFF0B1F4B);
  static const bg = Color(0xFFF2F6FD);
  static const line = Color(0xFFDDE6F4);
  static const muted = Color(0xFF6B7A99);
  static const ok = Color(0xFF1E9E5A);
  static const okBg = Color(0xFFE0F5E9);
  static const warn = Color(0xFFB7791F);
  static const warnBg = Color(0xFFFFF3D6);
  static const bad = Color(0xFFD64545);
  static const badBg = Color(0xFFFDE6E6);
}

ThemeData buildTheme() {
  final border = OutlineInputBorder(
    borderRadius: BorderRadius.circular(10),
    borderSide: const BorderSide(color: C.line),
  );
  return ThemeData(
    useMaterial3: true,
    scaffoldBackgroundColor: C.bg,
    colorScheme: ColorScheme.fromSeed(seedColor: C.primary, primary: C.primary),
    textTheme: GoogleFonts.interTextTheme(),
    appBarTheme: AppBarTheme(
      backgroundColor: C.bg,
      elevation: 0,
      scrolledUnderElevation: 0,
      foregroundColor: C.navy,
      titleTextStyle: GoogleFonts.inter(fontSize: 18, fontWeight: FontWeight.w800, color: C.navy),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(borderSide: const BorderSide(color: C.primary)),
    ),
    navigationBarTheme: const NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: Color(0xFFE3ECFC),
    ),
  );
}
