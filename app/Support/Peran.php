<?php

namespace App\Support;

/**
 * Pembacaan peran user dari session hasil login.
 *
 * Peran menentukan tombol mana yang digambar, bukan apa yang boleh dilakukan —
 * kewenangan sebenarnya tetap diperiksa server. Aturannya disamakan dengan
 * TindakanDetailFragment di Android: cukup ada satu grup yang memuat "TS".
 */
class Peran
{
    public static function daftar(): array
    {
        return (array) session('api_user.roles', []);
    }

    public static function isTS(): bool
    {
        foreach (self::daftar() as $peran) {
            if (stripos((string) $peran, 'TS') !== false) {
                return true;
            }
        }

        return false;
    }
}
