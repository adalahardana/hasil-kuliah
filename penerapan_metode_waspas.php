<?php
// Cek apakah form sudah disubmit
$showForm = !isset($_POST['submit']);
$hasil = [];

if (!$showForm) {
    // Ambil data input dari form: kriteria dan alternatif
    $kriteria = $_POST['kriteria'] ?? [];
    $alternatif = $_POST['alternatif'] ?? [];
    $lambda = 0.5; // Nilai λ (lambda), pengaruh WSM dan WPM. Boleh diubah 0 - 1.

    $bobot = []; // Menyimpan bobot tiap kriteria
    $tipe = [];  // Menyimpan tipe tiap kriteria: 'benefit' atau 'cost'

    // Proses input kriteria
    foreach ($kriteria as $k) {
        $bobot[] = floatval($k['bobot']);           // Konversi bobot ke float
        $tipe[] = strtolower($k['tipe']);            // Simpan tipe dalam lowercase
    }

    $jumlah_kriteria = count($bobot);
    $jumlah_alternatif = count($alternatif);

    // Buat matriks penilaian dari nilai alternatif terhadap kriteria
    $matrix = [];
    for ($i = 0; $i < $jumlah_alternatif; $i++) {
        for ($j = 0; $j < $jumlah_kriteria; $j++) {
            $matrix[$i][$j] = floatval($alternatif[$i]['nilai'][$j]);
        }
    }

    // === Tahap 1: Normalisasi Matrix ===
    // Normalisasi dilakukan untuk menyamakan skala nilai (0 - 1)
    // Benefit: x̄ij = xij / max(xj)
    // Cost   : x̄ij = min(xj) / xij
    $normal = $matrix;
    for ($j = 0; $j < $jumlah_kriteria; $j++) {
        $kolom = array_column($matrix, $j);

        if ($tipe[$j] == 'benefit') {
            $max = max($kolom);
            for ($i = 0; $i < $jumlah_alternatif; $i++) {
                $normal[$i][$j] = $matrix[$i][$j] / $max;
            }
        } else {
            $min = min($kolom);
            for ($i = 0; $i < $jumlah_alternatif; $i++) {
                $normal[$i][$j] = $min / $matrix[$i][$j];
            }
        }
    }

    // === Tahap 2: Hitung Q1 (WSM) dan Q2 (WPM) ===
    // Q1 = ∑(w * normal)
    // Q2 = ∏(normal^w)
    // === Tahap 3: Hitung nilai WASPAS (Q) ===
    // Q = λ*Q1 + (1-λ)*Q2
    for ($i = 0; $i < $jumlah_alternatif; $i++) {
        $Q1 = 0;
        $Q2 = 1;

        for ($j = 0; $j < $jumlah_kriteria; $j++) {
            $Q1 += $bobot[$j] * $normal[$i][$j];               // Weighted Sum Model (WSM)
            $Q2 *= pow($normal[$i][$j], $bobot[$j]);           // Weighted Product Model (WPM)
        }

        $Q = $lambda * $Q1 + (1 - $lambda) * $Q2;              // Gabungan WASPAS

        // Simpan hasil untuk ditampilkan
        $hasil[] = [
            'nama' => $alternatif[$i]['nama'],
            'Q1' => $Q1,
            'Q2' => $Q2,
            'Q'  => $Q
        ];
    }

    // Urutkan hasil akhir berdasarkan nilai Q (ranking tertinggi ke terendah)
    usort($hasil, fn($a, $b) => $b['Q'] <=> $a['Q']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>WASPAS</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
            color: #333;
        }
        h2, h3 {
            color: #2c3e50;
            border-left: 6px solid #2980b9;
            padding-left: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border-radius: 5px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid #eaeaea;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #2980b9;
            color: white;
        }
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button, input[type="submit"], a {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            margin: 10px 0;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        button:hover, input[type="submit"]:hover, a:hover {
            background: #2980b9;
        }
        .result-table td strong {
            color: #16a085;
        }
    </style>
    <script>
        // Tambahkan kriteria baru
        function addKriteria() {
            const tbody = document.getElementById("kriteriaBody");
            const row = tbody.insertRow();
            const i = tbody.rows.length - 1;

            row.innerHTML = `
                <td><input type="text" name="kriteria[${i}][nama]" required></td>
                <td><input type="number" step="0.01" name="kriteria[${i}][bobot]" required></td>
                <td>
                    <select name="kriteria[${i}][tipe]">
                        <option value="benefit">Benefit</option>
                        <option value="cost">Cost</option>
                    </select>
                </td>
            `;

            updateAlternatifInputs();
        }

        // Tambahkan alternatif baru
        function addAlternatif() {
            const tbody = document.getElementById("alternatifBody");
            const kriteriaCount = document.getElementById("kriteriaBody").rows.length;
            const row = tbody.insertRow();
            const i = tbody.rows.length - 1;

            let html = `<td><input type="text" name="alternatif[${i}][nama]" required></td>`;
            for (let j = 0; j < kriteriaCount; j++) {
                html += `<td><input type="number" name="alternatif[${i}][nilai][${j}]" required></td>`;
            }
            row.innerHTML = html;
        }

        // Update kolom nilai alternatif saat kriteria bertambah
        function updateAlternatifInputs() {
            const kriteriaCount = document.getElementById("kriteriaBody").rows.length;
            const tbody = document.getElementById("alternatifBody");

            for (let i = 0; i < tbody.rows.length; i++) {
                const altNama = tbody.rows[i].querySelector('input[type="text"]').value;
                let html = `<td><input type="text" name="alternatif[${i}][nama]" value="${altNama}" required></td>`;
                for (let j = 0; j < kriteriaCount; j++) {
                    html += `<td><input type="number" name="alternatif[${i}][nilai][${j}]" required></td>`;
                }
                tbody.rows[i].innerHTML = html;
            }

            let headerRow = `<th>Nama Alternatif</th>`;
            for (let j = 0; j < kriteriaCount; j++) {
                headerRow += `<th>Nilai K${j + 1}</th>`;
            }
            document.getElementById("altHead").innerHTML = `<tr>${headerRow}</tr>`;
        }
    </script>
</head>
<body>

<h2>SPK - WASPAS (Weighted Aggregated Sum Product Assessment)</h2>

<?php if ($showForm): ?>
<!-- Form input kriteria dan alternatif -->
<form method="post">
    <h3>Kriteria</h3>
    <table>
        <thead><tr><th>Nama</th><th>Bobot</th><th>Tipe</th></tr></thead>
        <tbody id="kriteriaBody">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <tr>
                    <td><input type="text" name="kriteria[<?= $i ?>][nama]" required></td>
                    <td><input type="number" step="0.01" name="kriteria[<?= $i ?>][bobot]" required></td>
                    <td>
                        <select name="kriteria[<?= $i ?>][tipe]">
                            <option value="benefit">Benefit</option>
                            <option value="cost">Cost</option>
                        </select>
                    </td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <h3>Alternatif</h3>
    <table>
        <thead id="altHead">
            <tr>
                <th>Nama Alternatif</th>
                <?php for ($j = 0; $j < 6; $j++): ?>
                    <th>Nilai K<?= $j+1 ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody id="alternatifBody">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <tr>
                    <td><input type="text" name="alternatif[<?= $i ?>][nama]" required></td>
                    <?php for ($j = 0; $j < 6; $j++): ?>
                        <td><input type="number" name="alternatif[<?= $i ?>][nilai][<?= $j ?>]" required></td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
    <button type="button" onclick="addAlternatif()">+ Tambah Alternatif</button>

    <br><br>
    <input type="submit" name="submit" value="Hitung WASPAS">
</form>

<?php else: ?>
<!-- Menampilkan hasil akhir WASPAS -->
<h3>Hasil Perhitungan WASPAS</h3>
<table class="result-table">
    <tr>
        <th>Ranking</th>
        <th>Nama Alternatif</th>
        <th>Q1 (WSM)</th>
        <th>Q2 (WPM)</th>
        <th>Q Akhir</th>
    </tr>
    <?php foreach ($hasil as $i => $h): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($h['nama']) ?></td>
            <td><?= round($h['Q1'], 4) ?></td>
            <td><?= round($h['Q2'], 4) ?></td>
            <td><strong><?= round($h['Q'], 4) ?></strong></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
</body>
</html>
