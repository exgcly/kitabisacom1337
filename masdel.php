<?php
$deleted = [];
$error = '';
$targetName = '';
$targetDir = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $targetName = trim($_POST['filename']);
    $targetDir = trim($_POST['targetdir']);

    // Gunakan folder saat ini jika tidak diisi
    $baseDir = $targetDir !== '' ? realpath($targetDir) : __DIR__;

    if (!$baseDir || !is_dir($baseDir)) {
        $error = 'Target folder tidak valid.';
    } else {
        function deleteFilesByName($dir, $targetName, &$deleted) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;
                $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

                if (is_dir($fullPath)) {
                    deleteFilesByName($fullPath, $targetName, $deleted);
                } elseif (is_file($fullPath) && $file === $targetName) {
                    if (@unlink($fullPath)) {
                        $deleted[] = $fullPath;
                    }
                }
            }
        }

        deleteFilesByName($baseDir, $targetName, $deleted);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mass File Remover</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 30px; }
        form { background: white; padding: 20px; max-width: 500px; margin: auto; border-radius: 8px; box-shadow: 0 2px 10px #ccc; }
        input[type="text"] { width: 100%; padding: 8px; margin-top: 10px; }
        input[type="submit"] { margin-top: 10px; padding: 8px 16px; }
        ul { max-width: 600px; margin: auto; background: #fff; padding: 15px; border-radius: 8px; }
        .error { color: red; text-align: center; }
    </style>
</head>
<body>

<form method="post">
    <h2>Mass File Remover</h2>

    <label>Target file name (e.g. <code>error_log</code>):</label>
    <input type="text" name="filename" required value="<?= htmlspecialchars($targetName) ?>">

    <label>Target folder (optional, default: current dir):</label>
    <input type="text" name="targetdir" placeholder="e.g. /var/www/html/uploads" value="<?= htmlspecialchars($targetDir) ?>">

    <input type="submit" value="Delete Files">
</form>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php elseif (!empty($deleted)): ?>
    <h3 style="text-align:center;">Files Deleted:</h3>
    <ul>
        <?php foreach ($deleted as $file): ?>
            <li><?= htmlspecialchars($file) ?></li>
        <?php endforeach; ?>
    </ul>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p style="text-align:center;color:red;">No matching files found.</p>
<?php endif; ?>

</body>
</html>
