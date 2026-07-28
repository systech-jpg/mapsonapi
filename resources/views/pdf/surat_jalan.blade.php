<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Surat Jalan - {{ $info->ref_sj }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 0; color: #000; }
        
        /* Layout Header using Table for DomPDF compatibility */
        .header-table { width: 100%; margin-bottom: 10px; }
        .header-table td { vertical-align: top; }
        .company-box { text-align: right; font-size: 10px; }
        .company-name { font-weight: bold; font-size: 14px; margin-bottom: 5px; }
        
        .sj-title { text-align: center; font-weight: bold; font-size: 16px; text-decoration: underline; margin: 15px 0; }

        /* Grid Info */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; }
        .info-grid td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
        .info-label { font-weight: bold; width: 15%; background-color: #f9f9f9; }
        .info-val { width: 35%; }

        /* Tables */
        .section-header { font-weight: bold; font-size: 12px; margin-top: 15px; margin-bottom: 5px; text-transform: uppercase; border-bottom: 2px solid #000; display: inline-block; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 11px; }
        .items-table th { border: 1px solid #000; padding: 5px; background-color: #eee; text-align: center; font-weight: bold; }
        .items-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .col-center { text-align: center; }
        
        /* Footer using Table for DomPDF compatibility */
        .footer-table { width: 100%; margin-top: 20px; }
        .footer-table td { vertical-align: top; }
        .remarks-box { font-size: 11px; padding-right: 20px; }
        
        .sign-box { width: 100%; border: 1px solid #000; border-collapse: collapse; text-align: center; }
        .sign-box td { border: 1px solid #000; padding: 5px; }
        .sign-space { height: 60px; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="40%">
                <h2 style="margin:0;">PT Mapson Arya Parahita</h2>
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

    <div class="sj-title">Surat Jalan</div>

    <table class="info-grid">
        <tr>
            <td class="info-label">Attn To:</td><td class="info-val"><strong>{{ $info->rs_name }}</strong></td>
            <td class="info-label">Transfer No:</td><td class="info-val"><strong>{{ $info->ref_sj }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Ship To:</td><td class="info-val">{{ $info->name_alias }}</td>
            <td class="info-label">Document Date:</td><td class="info-val">{{ $info->date_delivery ? \Carbon\Carbon::parse($info->date_delivery)->format('d F Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Address:</td><td class="info-val">{{ $info->alamat }}</td>
            <td class="info-label">Nama Pasien:</td><td class="info-val">{{ $info->pasien }}</td>
        </tr>
        <tr>
            <td class="info-label">Note:</td><td class="info-val">{{ $info->ref }}</td>
            <td class="info-label">Nama Dokter:</td><td class="info-val">{{ $info->dokter_name }}</td>
        </tr>
    </table>

    @if(count($paket_tray) > 0)
        <div class="section-header">A. INSTRUMENT / TRAY SET</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="20%">Part No</th>
                    <th>Description</th>
                    <th width="10%">Qty</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($paket_tray as $item)
                    <tr>
                        <td class="col-center">{{ $no++ }}</td>
                        <td>{{ $item->ref }}</td>
                        <td>{{ $item->label }}</td>
                        <td class="col-center">{{ (int)$item->qty }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif


    <div class="section-header">{{ count($paket_tray) > 0 ? 'B' : 'A' }}. IMPLANT / CONSUMABLE</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">NO</th>
                <th width="20%">Part No</th>
                <th>Description</th>
                <th width="15%">AKL</th>
                <th width="10%">Lot</th>
                <th width="8%">Qty</th>
                <th width="8%">Use</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @if(count($set_implant) > 0)
                @foreach($set_implant as $item)
                    <tr>
                        <td class="col-center">{{ $no++ }}</td>
                        <td>{{ $item->ref }}</td>
                        <td>{{ $item->label }}</td>
                        <td class="col-center">{{ $item->noakl }}</td>
                        <td></td>
                        <td class="col-center">{{ (int)$item->qty }}</td>
                        <td class="col-center">{{ $item->qty_used !== null ? (int)$item->qty_used : '' }}</td>
                    </tr>
                @endforeach
            @else
                <tr><td colspan="7" class="col-center">- Tidak ada implant -</td></tr>
            @endif
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold; border-top: 2px solid #000;">
                <td colspan="5" class="col-center">GRAND TOTAL (INSTRUMENT + IMPLANT)</td>
                <td class="col-center">{{ $grand_total }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="footer-table">
        <tr>
            <td width="40%" class="remarks-box">
                <strong>Remarks / Catatan:</strong><br>
                {!! nl2br(e($info->diagnosa)) !!}
            </td>
            <td width="60%">
                <table class="sign-box">
                    <tr style="background-color: #fff; font-weight: bold;">
                        <td width="33%">Approved By</td>
                        <td width="33%">Transfer By</td>
                        <td width="33%">Received By</td>
                    </tr>
                    <tr>
                        <td class="sign-space"></td>
                        <td class="sign-space"></td>
                        <td class="sign-space"></td>
                    </tr>
                    <tr>
                        <td style="height:20px;"></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
