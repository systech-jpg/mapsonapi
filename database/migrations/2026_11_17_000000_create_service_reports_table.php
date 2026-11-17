<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formulir Service Report / Maintenance Alat (007/01/TKS-MAP/F-03).
 *
 * Tabel milik Laravel, bukan tabel Dolibarr, sama seperti push_subscriptions:
 * ERP tidak punya modulnya, jadi tidak ada prefiks llxjp_ dan PK-nya `id`.
 *
 * Baris produk (bagian C) dan baris pemeriksaan (bagian D) disimpan sebagai
 * JSON, bukan tabel anak. Isinya cuma teks bebas tanpa relasi ke produk ERP,
 * selalu dibaca dan ditulis bersama header, dan jumlahnya kecil (formulir
 * kertasnya menyediakan enam baris).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_reports', function (Blueprint $table) {
            $table->id();
            // Diisi sesudah insert karena urutannya diturunkan dari id —
            // lihat ServiceReportController::nomori().
            $table->string('no_report', 30)->nullable()->unique();

            // installation / service / pm / training; boleh lebih dari satu,
            // sama seperti kotak centang di formulir kertas.
            $table->json('jenis');

            // A. Customer
            $table->string('customer_nama', 150);
            $table->text('customer_alamat')->nullable();

            // B. Room ID
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('customer_ref', 100)->nullable();
            $table->string('telepon', 50)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('email', 150)->nullable();

            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();

            // C. Product Survey: [{description, type, nie, sn, sw_version}]
            $table->json('produk')->nullable();

            // D. Problem Identification: [{tahap, solusi}]
            $table->json('pemeriksaan')->nullable();

            $table->string('nama_engineer', 100)->nullable();
            $table->string('nama_customer', 100)->nullable();

            // 0 Baru, 1 Diproses, 2 Selesai.
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->text('catatan_tindak_lanjut')->nullable();
            // rowid llxjp_user petugas yang terakhir menindaklanjuti.
            $table->unsignedInteger('fk_user_tindak_lanjut')->nullable();
            $table->dateTime('tanggal_tindak_lanjut')->nullable();

            // Formulirnya terbuka tanpa login; alamat pengirim disimpan supaya
            // kiriman iseng masih bisa ditelusuri.
            $table->string('ip_pelapor', 45)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reports');
    }
};
