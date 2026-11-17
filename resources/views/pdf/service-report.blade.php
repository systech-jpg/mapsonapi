<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Service Report {{ $sr['no_report'] }}</title>
  {{-- Tata letak meniru formulir kertas 007/01/TKS-MAP/F-03. Semua memakai
       <table>: DomPDF tidak menangani flex/float dengan baik. --}}
  <style>
    @page { margin: 22px 28px; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; color: #000; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    .tanpa-garis td { border: none; }
    .form-no { text-align: right; font-size: 9px; margin-bottom: 2px; }
    .judul { text-align: center; font-weight: bold; font-size: 14px; }
    .kecil { font-size: 8.5px; }
    .seksi { background: #eee; font-weight: bold; font-style: italic; }
    .label { font-style: italic; width: 18%; }
    .kotak { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; text-align: center; line-height: 11px; font-size: 10px; margin-left: 3px; }
    .jenis td { border: none; text-align: center; font-weight: bold; font-style: italic; font-size: 11px; padding: 6px; }
    .bingkai { border: 1px solid #000; margin-top: 8px; }
    th { font-style: italic; text-align: left; background: #fff; }
    .ttd { height: 60px; }
    .ringkas { margin-top: 10px; font-size: 9px; }
  </style>
</head>
<body>
  <div class="form-no">Form No.: 007/01/TKS-MAP/F-03</div>

  <table>
    <tr>
      <td rowspan="4" style="width: 30%; text-align: center; vertical-align: middle;">
        @if ($logo)
          <img src="{{ $logo }}" style="height: 48px;" alt="Logo"><br>
        @endif
        <span class="kecil">PT. MAPSON ARYA PARAHITA<br>TANGERANG SELATAN - INDONESIA</span>
      </td>
      <td rowspan="4" class="judul" style="width: 40%; vertical-align: middle;">
        FORMULIR <i>SERVICE REPORT</i>/<br><i>MAINTENANCE</i> ALAT
      </td>
      <td class="kecil" style="width: 13%;"><i>No. Service/ Maintenance</i></td>
      <td class="kecil">{{ $sr['no_report'] }}</td>
    </tr>
    <tr><td class="kecil">Revisi Ke</td><td class="kecil">00</td></tr>
    <tr><td class="kecil">Lamp. Dok. No.</td><td class="kecil">007/01/TKS-MAP/04/2025</td></tr>
    <tr><td class="kecil">Halaman</td><td class="kecil">1 dari 1</td></tr>
  </table>

  <table class="bingkai jenis">
    <tr>
      @foreach ($jenisSemua as $kode => $label)
        <td>{{ $label }} <span class="kotak">{{ in_array($kode, $sr['jenis'], true) ? 'X' : '' }}</span></td>
      @endforeach
    </tr>
  </table>

  <table style="margin-top: 8px;">
    <tr><td colspan="4" class="seksi">A. Customer</td></tr>
    <tr><td class="label">Name Customer</td><td colspan="3">{{ $sr['customer_nama'] }}</td></tr>
    <tr><td class="label">Address</td><td colspan="3">{{ $sr['customer_alamat'] }}</td></tr>
    <tr><td colspan="4" class="seksi">B. Room ID</td></tr>
    <tr>
      <td class="label">Department</td><td style="width: 32%;">: {{ $sr['department'] }}</td>
      <td class="label">Position</td><td>: {{ $sr['position'] }}</td>
    </tr>
    <tr>
      <td class="label">Customer Ref</td><td>: {{ $sr['customer_ref'] }}</td>
      <td class="label">Tlp No.</td><td>: {{ $sr['telepon'] }}</td>
    </tr>
    <tr>
      <td class="label">Contact Person</td><td>: {{ $sr['contact_person'] }}</td>
      <td class="label">Email</td><td>: {{ $sr['email'] }}</td>
    </tr>
  </table>

  @php
    $tgl = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('d/m/Y') : '';
    // Formulir kertas selalu menyediakan enam baris; baris kosong tetap
    // digambar supaya cetakannya bisa dilengkapi dengan tangan.
    $produk = array_pad($sr['produk'], max(6, count($sr['produk'])), []);
  @endphp

  <table style="margin-top: 8px;">
    <tr>
      <td style="width: 50%;"><b><i>Date Service Begin</i></b> : {{ $tgl($sr['tanggal_mulai']) }}</td>
      <td><b><i>Date End of Service</i></b> : {{ $tgl($sr['tanggal_selesai']) }}</td>
    </tr>
  </table>
  <table>
    <tr><td colspan="6" class="seksi">C. Product Survey</td></tr>
    <tr>
      <th style="width: 5%;">No.</th><th style="width: 28%;">Description</th><th style="width: 12%;">Type</th>
      <th style="width: 15%;">NIE (Unit)</th><th style="width: 15%;">No. SN</th><th>S.W Version</th>
    </tr>
    @foreach ($produk as $i => $p)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $p['description'] ?? '' }}</td>
        <td>{{ $p['type'] ?? '' }}</td>
        <td>{{ $p['nie'] ?? '' }}</td>
        <td>{{ $p['sn'] ?? '' }}</td>
        <td>{{ $p['sw_version'] ?? '' }}</td>
      </tr>
    @endforeach
  </table>

  <table style="margin-top: 8px;">
    <tr><td colspan="3" class="seksi">D. Problem Identification</td></tr>
    <tr>
      <th style="width: 7%; text-align: center;">No</th>
      <th style="width: 50%; text-align: center;">Tahap Pemeriksaan</th>
      <th style="text-align: center;">Solusi/Rekomendasi</th>
    </tr>
    @forelse ($sr['pemeriksaan'] as $i => $b)
      <tr>
        <td style="text-align: center;">{{ $i + 1 }}</td>
        <td>{!! nl2br(e($b['tahap'] ?? '')) !!}</td>
        <td>{!! nl2br(e($b['solusi'] ?? '')) !!}</td>
      </tr>
    @empty
      <tr><td style="height: 120px;"></td><td></td><td></td></tr>
    @endforelse
  </table>

  <table style="margin-top: 10px;">
    <tr>
      <td style="width: 50%; text-align: center;"><i>Engineering</i> PT. MAPSON ARYA PARAHITA</td>
      <td style="text-align: center;"><i>Customer</i></td>
    </tr>
    <tr><td class="ttd"></td><td class="ttd"></td></tr>
    <tr>
      <td><i>Name :</i> {{ $sr['nama_engineer'] }}</td>
      <td><i>Name :</i> {{ $sr['nama_customer'] }}</td>
    </tr>
  </table>

  @if ($sr['catatan_tindak_lanjut'])
    <div class="ringkas">
      <b>Catatan tindak lanjut ({{ $sr['status_label'] }}):</b><br>
      {!! nl2br(e($sr['catatan_tindak_lanjut'])) !!}
    </div>
  @endif
</body>
</html>
