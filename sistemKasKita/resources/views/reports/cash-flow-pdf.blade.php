
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan KasKita</title>

    <style>
        @page {
            margin: 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #222;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0 0 5px;
            font-size: 20px;
        }

        .header p {
            margin: 3px 0;
        }

        .summary {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .summary td {
            border: 1px solid #ccc;
            padding: 10px;
            width: 50%;
        }

        .summary strong {
            display: block;
            margin-bottom: 5px;
        }

        .section-title {
            font-size: 13px;
            margin-bottom: 8px;
        }

        table.transactions {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions th,
        .transactions td {
            border: 1px solid #ccc;
            padding: 7px;
            text-align: left;
        }

        .transactions th {
            background-color: #eeeeee;
            text-align: center;
        }

        .text-right {
            text-align: right !important;
        }

        .total {
            margin-top: 15px;
            text-align: right;
            font-size: 12px;
            font-weight: bold;
        }

        .footer {
            margin-top: 35px;
            text-align: right;
        }

        .signature {
            margin-top: 55px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>LAPORAN KEUANGAN KAS</h2>
        <p>KasKita - {{ $cohort->name }}</p>
        <p>
            Periode:
            {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
            -
            {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        </p>
    </div>

    <h3>Ringkasan Keuangan</h3>

    <table class="summary">
        <tr>
            <td>
                <strong>Saldo Awal</strong>
                Rp {{ number_format($saldoAwal, 0, ',', '.') }}
            </td>
            <td>
                <strong>Total Pemasukan</strong>
                Rp {{ number_format($totalPemasukan, 0, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>Total Pengeluaran</strong>
                Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}
            </td>
            <td>
                <strong>Saldo Akhir</strong>
                Rp {{ number_format($saldoAkhir, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <h3 class="section-title">Rincian Transaksi</h3>

    <table class="transactions">
        <thead>
            <tr>
                <th>No.</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Jenis</th>
                <th>Kelas</th>
                <th>Jumlah</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($transactions as $index => $transaction)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{ $transaction->transaction_date->format('d/m/Y H:i') }}
                    </td>
                    <td>{{ $transaction->category }}</td>
                    <td>{{ ucfirst($transaction->transaction_type) }}</td>
                    <td>{{ $transaction->classroom?->name ?? '-' }}</td>
                    <td class="text-right">
                        Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;">
                        Tidak ada transaksi pada periode ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total">
        Saldo Akhir:
        Rp {{ number_format($saldoAkhir, 0, ',', '.') }}
    </div>

    <div class="footer">
        <p>
            Dicetak pada:
            {{ now()->format('d/m/Y H:i') }}
        </p>

        <p>Bendahara Angkatan</p>

        <div class="signature">
            {{ $user->nama }}
        </div>
    </div>

</body>
</html>
