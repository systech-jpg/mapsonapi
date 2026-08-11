<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>SPH - {{ $info->ref }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 0; color: #000; }

        /* Layout header pakai table: DomPDF tidak menangani flex/float dengan baik */
        .header-table { width: 100%; margin-bottom: 10px; }
        .header-table td { vertical-align: top; }
        .company-box { text-align: right; font-size: 10px; }
        .company-name { font-weight: bold; font-size: 14px; margin-bottom: 5px; }
        .logo { height: 45px; }

        .doc-title { text-align: center; font-weight: bold; font-size: 16px; text-decoration: underline; margin: 15px 0; }

        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; }
        .info-grid td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
        .info-label { font-weight: bold; width: 18%; background-color: #f9f9f9; }
        .info-val { width: 32%; }

        .section-header { font-weight: bold; font-size: 12px; margin-top: 10px; margin-bottom: 5px; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; }

        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 11px; }
        .items-table th { border: 1px solid #000; padding: 5px; background-color: #eee; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        .col-center { text-align: center; }
        .col-right { text-align: right; }
        .total-label { text-align: right; font-weight: bold; background-color: #f9f9f9; }

        .note-box { margin-top: 12px; font-size: 11px; }
        .sign-table { width: 100%; margin-top: 25px; }
        .sign-table td { vertical-align: top; text-align: right; }
        .sign-space { height: 55px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="40%">
                @if (!empty($logo))
                    <img src="{{ $logo }}" class="logo" alt="Logo">
                @endif
            </td>
            <td width="60%" class="company-box">
                <div class="company-name">PT Mapson Arya Parahita</div>
                <div>Roseville Business District No.3 Sunburst CBD Lot 1.8</div>
                <div>Jl. Kapten Soebianto Djojohadikusumo, BSD City</div>
                <div>Serpong, Tangerang Selatan, Banten 15321</div>
                <div>Phone: +62 811 9972 800 | Hp: +62 811 919 0092</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">SURAT PENAWARAN HARGA</div>

    <table class="info-grid">
        <tr>
            <td class="info-label">No. Quotation</td>
            <td class="info-val">{{ $info->ref_quotation ?: '-' }}</td>
            <td class="info-label">Tanggal SPH</td>
            <td class="info-val">{{ $info->date_sph ? \Carbon\Carbon::parse($info->date_sph)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Principal</td>
            <td class="info-val">{{ $info->principal_name ?: '-' }}</td>
            <td class="info-label">Berlaku s/d</td>
            <td class="info-val">{{ $info->date_valid ? \Carbon\Carbon::parse($info->date_valid)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">PIC</td>
            <td class="info-val">{{ $info->sales_name ?: '-' }}</td>
            <td class="info-label"></td>
            <td class="info-val"></td>
        </tr>
        <tr>
            <td class="info-label">Kepada Yth.</td>
            <td colspan="3">
                <strong>{{ $info->customer_name ?: '-' }}</strong>
                @if (!empty($info->customer_address))
                    <br>{{ $info->customer_address }}
                @endif
            </td>
        </tr>
    </table>

    <div style="margin-top: 15px; margin-bottom: 10px; font-size: 11px;">Dengan hormat, berikut kami sampaikan penawaran harga:</div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="16%">Kode</th>
                <th width="41%">Deskripsi</th>
                <th width="8%">Qty</th>
                <th width="15%">Harga</th>
                <th width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $i => $line)
                <tr>
                    <td class="col-center">{{ $i + 1 }}</td>
                    <td>{{ $line->product_ref }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="col-center">{{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="col-right">{{ number_format((float) $line->subprice, 0, ',', '.') }}</td>
                    <td class="col-right">{{ number_format((float) $line->total_ht, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="col-center">Belum ada barang pada penawaran ini.</td></tr>
            @endforelse

            <!-- Total section dihilangkan sesuai permintaan -->
        </tbody>
    </table>

    @if (!empty($info->note))
        <div class="note-box">
            <strong>Catatan:</strong><br>
            {!! nl2br(e($info->note)) !!}
        </div>
    @endif

    <div class="note-box" style="margin-top: 15px;">
        <strong>Syarat & Ketentuan:</strong>
        <ol style="padding-left: 15px; margin-top: 5px; margin-bottom: 5px; line-height: 1.4;">
            <li>Harga yang tercantum dalam penawaran ini berlaku selama 90 (sembilan puluh) hari kalender sejak tanggal penawaran.</li>
            <li>Ketentuan Pembayaran:<br>Pelunasan 100% dilakukan paling lambat 30 (tiga puluh) hari kalender setelah barang diterima.</li>
            <li>Harga yang tercantum belum termasuk PPN dan belum termasuk biaya pengiriman untuk tujuan di luar wilayah Jabodetabek.</li>
            <li>Ketersediaan barang tergantung pada konfirmasi stok pada saat pemesanan.</li>
            <li>Semua pembayaran wajib dilakukan melalui transfer ke rekening resmi berikut:<br>
            Bank : BCA<br>
            No. Rekening : 497-9000020<br>
            Atas Nama : PT Mapson Arya Parahita
            </li>
        </ol>
        Nomor rekening ini juga tercantum pada invoice resmi perusahaan. Perusahaan tidak bertanggung jawab atas pembayaran yang dilakukan ke rekening lain di luar rekening resmi tersebut.
    </div>

    <table class="sign-table">
        <tr>
            <td>
                Hormat kami,
                <div class="sign-space"></div>
                <strong>Drh. Maharani Asmara Putri</strong>
            </td>
        </tr>
    </table>

</body>
</html>
