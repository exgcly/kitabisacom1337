<?php
// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Fungsi untuk menghitung jumlah baris file (tidak dipakai langsung, tapi tetap disimpan)
function getFileRowCount($filename) {
    $file = fopen($filename, "r");
    $rowCount = 0;
    while (!feof($file)) {
        fgets($file);
        $rowCount++;
    }
    fclose($file);
    return $rowCount;
}

// Dapatkan URL dasar
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$fullUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($fullUrl);
$host = $_SERVER['HTTP_HOST'];
$path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
$basePath = str_replace("program.php", "", $path);
$baseUrl = $protocol . "://" . $host . $basePath;

// File input (list.txt berisi daftar brand atau id)
$judulFile = "list.txt";
$fileLines = file($judulFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Buat sitemap.xml
$sitemapFile = fopen("sitemap.xml", "w");
fwrite($sitemapFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
fwrite($sitemapFile, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

foreach ($fileLines as $judul) {
    $sitemapLink = htmlspecialchars($baseUrl . '?id=' . urlencode($judul));
    fwrite($sitemapFile, "  <url>\n");
    fwrite($sitemapFile, "    <loc>$sitemapLink</loc>\n");
    fwrite($sitemapFile, "    <lastmod>" . date('Y-m-d') . "</lastmod>\n");
    fwrite($sitemapFile, "    <changefreq>monthly</changefreq>\n");
    fwrite($sitemapFile, "    <priority>0.8</priority>\n");
    fwrite($sitemapFile, "  </url>\n");
}
fwrite($sitemapFile, '</urlset>' . PHP_EOL);
fclose($sitemapFile);

// Buat robots.txt
$robotsFile = fopen("robots.txt", "w");
$sitemapPath = $baseUrl . 'sitemap.xml';
fwrite($robotsFile, "User-agent: *\n");
fwrite($robotsFile, "Allow: /\n");
fwrite($robotsFile, "Sitemap: $sitemapPath\n");
fclose($robotsFile);

// Output ke browser (opsional)
echo "<strong>Sitemap dan robots.txt berhasil dibuat!</strong><br>";
echo "→ <a href='sitemap.xml' target='_blank'>Lihat sitemap.xml</a><br>";
echo "→ <a href='robots.txt' target='_blank'>Lihat robots.txt</a>";
