<?php

namespace App\Support;

/**
 * Warna badge status, dipakai bersama oleh daftar tindakan, halaman detail,
 * dan pratinjau laporan pemakaian.
 *
 * Salinan dari utils/StatusColor.kt di aplikasi Android supaya label yang sama
 * tidak pernah berwarna berbeda antara web dan Android.
 *
 * Pencocokannya memakai potongan teks label, bukan angka status, karena kedua
 * skema (tindakan dan usage report) memakai angka yang sama untuk arti berbeda.
 */
class StatusTindakan
{
    /**
     * Urutan pengecekan penting: label usage report diperiksa lebih dulu karena
     * beberapa di antaranya memuat kata yang juga dipakai status tindakan.
     * "Validated (Menunggu Tarik Barang)" misalnya, harus ditangkap sebagai
     * status usage report, bukan tersangkut di aturan umum "Validated".
     */
    public static function warna(?string $label): string
    {
        $teks = $label ?? '';

        return match (true) {
            // --- Status Usage Report ---
            self::ada($teks, 'Menunggu Tarik Barang') => 'hijau',
            self::ada($teks, 'Barang Ditarik') => 'ungu',
            self::ada($teks, 'Accepted') => 'toska',
            self::ada($teks, 'Ordered') => 'brand',

            // --- Status Tindakan ---
            self::ada($teks, 'Draft') => 'abu',
            self::ada($teks, 'Cancelled') => 'merah',
            self::ada($teks, 'In Delivery') => 'oranye',
            self::ada($teks, 'Delivered'), self::ada($teks, 'Ready') => 'biru',
            self::ada($teks, 'CLOSED'), self::ada($teks, 'DONE') => 'toska',
            self::ada($teks, 'Confirmed'),
            self::ada($teks, 'Validated'),
            self::ada($teks, 'Created') => 'hijau',

            default => 'netral',
        };
    }

    protected static function ada(string $teks, string $kata): bool
    {
        return stripos($teks, $kata) !== false;
    }
}
