<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ChatDecryptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:decrypt {id? : ID pesan yang akan didekripsi} {--file= : Nama file attachment yang ingin didownload/dekripsi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Urgent/Investigasi: Mendekripsi pesan chat atau file attachment yang terenkripsi. (Memerlukan OTP)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $filename = $this->option('file');

        if (!$id && !$filename) {
            $this->error('Anda harus memberikan ID pesan atau nama file attachment.');
            $this->info('Contoh: php artisan chat:decrypt 102');
            $this->info('Contoh: php artisan chat:decrypt --file=1722049100_66a4a1.enc');
            return Command::FAILURE;
        }

        // 1. Verifikasi Keamanan OTP
        $adminEmail = $this->ask('Masukkan Email Administrator untuk menerima kode OTP investigasi:');
        
        if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            return Command::FAILURE;
        }

        $this->info("Menghasilkan kode OTP...");
        $otp = rand(100000, 999999);

        try {
            Mail::raw("Kode OTP Investigasi Chat Anda adalah: {$otp}\n\nJANGAN BERIKAN KODE INI KEPADA SIAPAPUN. Kode ini digunakan untuk mendecrypt paksa percakapan dari database.", function($message) use ($adminEmail) {
                $message->to($adminEmail)->subject('[URGENT] Kode OTP Investigasi Chat');
            });
            $this->info('Email OTP telah dikirim ke ' . $adminEmail);
        } catch (\Exception $e) {
            $this->error('Gagal mengirim email OTP. Pastikan konfigurasi SMTP di .env Anda sudah benar.');
            $this->error('Error details: ' . $e->getMessage());
            // Dalam mode darurat, Anda mungkin ingin me-bypass ini sementara jika SMTP belum diatur, 
            // namun karena syaratnya adalah OTP via email, kita akan menghentikan proses.
            return Command::FAILURE;
        }

        $inputOtp = $this->ask('Masukkan kode OTP 6-digit yang telah dikirim ke email:');

        if ($inputOtp != $otp) {
            $this->error('OTP Salah! Akses ditolak.');
            return Command::FAILURE;
        }

        $this->info('Autentikasi OTP Berhasil. Memulai proses dekripsi...');

        // 2. Dekripsi File Attachment
        if ($filename) {
            return $this->decryptFile($filename);
        }

        // 3. Dekripsi Pesan Teks
        if ($id) {
            return $this->decryptMessage($id);
        }

        return Command::SUCCESS;
    }

    private function decryptFile($filename)
    {
        $path = 'chat_secure/' . $filename;

        if (!Storage::exists($path)) {
            $this->error('File tidak ditemukan di storage: ' . $path);
            return Command::FAILURE;
        }

        try {
            $encryptedContents = Storage::get($path);
            $decryptedContents = Crypt::decrypt($encryptedContents);
            
            // Simpan hasil dekripsi ke direktori lokal (misal root project)
            $outputFile = 'decrypted_' . str_replace('.enc', '', $filename);
            file_put_contents(base_path($outputFile), $decryptedContents);
            
            $this->info('File berhasil didekripsi!');
            $this->info('Lokasi file hasil dekripsi: ' . base_path($outputFile));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Gagal mendekripsi file! Kunci aplikasi (APP_KEY) mungkin berubah atau file corrupt.');
            return Command::FAILURE;
        }
    }

    private function decryptMessage($id)
    {
        $message = DB::table('llxjp_chat_messages')->where('id', $id)->first();
        
        if (!$message) {
            $this->error('Pesan dengan ID ' . $id . ' tidak ditemukan di database.');
            return Command::FAILURE;
        }

        $this->info('--- DATA MENTAH DARI DATABASE ---');
        $this->line('Sender ID   : ' . $message->sender_id);
        $this->line('Receiver ID : ' . $message->receiver_id);
        $this->line('Ciphertext  : ' . $message->message);
        
        try {
            $decryptedText = Crypt::decryptString($message->message);
            $this->info("\n--- HASIL DEKRIPSI ---");
            $this->line('Pesan Asli  : ' . $decryptedText);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Gagal mendekripsi teks! Kunci aplikasi (APP_KEY) mungkin berubah atau data corrupt.');
            return Command::FAILURE;
        }
    }
}
