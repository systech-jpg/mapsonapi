<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Penulisan waktu di daftar percakapan.
 *
 * Kolom created_at di llxjp_chat_messages ditulis dengan Carbon::now(), yaitu
 * waktu SERVER (Asia/Jakarta) — bukan UTC. Jadi tidak ada konversi zona di
 * sini; menambahkannya justru menggeser jam pesan tujuh jam. Alasan lengkapnya
 * ada di CLAUDE.md bagian 4 nomor 7.
 */
class WaktuChat
{
    /** Hari ini -> jam. Kemarin -> "Kemarin". Selain itu -> tanggal. */
    public static function ringkas(?string $waktu): string
    {
        if (blank($waktu)) {
            return '';
        }

        try {
            $t = Carbon::parse($waktu);
        } catch (\Throwable $e) {
            return '';
        }

        if ($t->isToday()) {
            return $t->format('H:i');
        }

        if ($t->isYesterday()) {
            return 'Kemarin';
        }

        return $t->format('d/m/y');
    }

    /** Jam pada gelembung pesan. */
    public static function jam(?string $waktu): string
    {
        if (blank($waktu)) {
            return '';
        }

        try {
            return Carbon::parse($waktu)->format('H:i');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Pemisah tanggal di antara gelembung, seperti di aplikasi chat umumnya. */
    public static function tanggal(?string $waktu): string
    {
        if (blank($waktu)) {
            return '';
        }

        try {
            $t = Carbon::parse($waktu);
        } catch (\Throwable $e) {
            return '';
        }

        if ($t->isToday()) {
            return 'Hari ini';
        }

        if ($t->isYesterday()) {
            return 'Kemarin';
        }

        return $t->translatedFormat('d F Y');
    }
}
