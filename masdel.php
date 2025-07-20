<?php
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

$deleted = [];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = trim($_POST['filename'] ?? '');
    $targetDir = trim($_POST['targetdir'] ?? '');

    if (!$filename || !$targetDir) {
        $error = 'Both fields are required.';
    } else {
        $realDir = realpath($targetDir);
        if (!$realDir || !is_dir($realDir)) {
            $error = 'Target directory does not exist.';
        } else {
            deleteFilesByName($realDir, $filename, $deleted);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mass File Remover</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 30px; }
        form { background: #fff; padding: 20px; max-width: 500px; margin: auto; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; }
        input[type="submit"] { padding: 10px 20px; }
        .error { color: red; text-align: center; }
        ul { max-width: 600px; margin: 30px auto; background: #fff; padding: 15px; border-radius: 10px; }
    </style>
</head>
<body>

<form method="post">
    <h2>Mass File Remover</h2>
    <label>File name to delete:</label>
    <input type="text" name="filename" placeholder="e.g. error_log" required>

    <label>Target directory:</label>
    <input type="text" name="targetdir" placeholder="e.g. ., ../uploads, subfolder" required>

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
    <p style="text-align:center;">No matching files found.</p>
<?php endif; ?>

</body>
</html>
