<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $targetName = trim($_POST['filename']);
    $deleted = [];

    function deleteFilesByName($dir, $targetName, &$deleted) {
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($fullPath)) {
                deleteFilesByName($fullPath, $targetName, $deleted);
            } elseif (is_file($fullPath) && $file === $targetName) {
                if (unlink($fullPath)) {
                    $deleted[] = $fullPath;
                }
            }
        }
    }

    deleteFilesByName(__DIR__, $targetName, $deleted);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mass File Remover</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 30px; }
        form { background: white; padding: 20px; max-width: 400px; margin: auto; border-radius: 8px; box-shadow: 0 2px 10px #ccc; }
        input[type="text"] { width: 100%; padding: 8px; margin-top: 10px; }
        input[type="submit"] { margin-top: 10px; padding: 8px 16px; }
        ul { max-width: 600px; margin: auto; background: #fff; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>

<form method="post">
    <h2>Mass File Remover</h2>
    <label>Target file name (e.g. <code>error_log</code>):</label>
    <input type="text" name="filename" required>
    <input type="submit" value="Delete Files">
</form>

<?php if (!empty($deleted)): ?>
    <h3 style="text-align:center;">Files Deleted:</h3>
    <ul>
        <?php foreach ($deleted as $file): ?>
            <li><?= htmlspecialchars($file) ?></li>
        <?php endforeach; ?>
    </ul>
<?php elseif (isset($targetName)): ?>
    <p style="text-align:center;color:red;">No matching files found.</p>
<?php endif; ?>

</body>
</html>
