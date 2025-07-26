<?php
/**
 * Mass File Deleter - FINAL
 * Web-based tool to delete all files with a specific name recursively from selected directory
 * Author: ChatGPT Final Version
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

function findAndDeleteFiles($dir, $targetFile, &$deleted, &$failed) {
    if (!is_readable($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            findAndDeleteFiles($path, $targetFile, $deleted, $failed);
        } elseif (is_file($path) && basename($path) === $targetFile) {
            if (@unlink($path)) {
                $deleted[] = $path;
            } else {
                $failed[] = $path;
            }
        }
    }
}

$deleted = $failed = [];
$targetFile = '';
$targetDir = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['filename']) && !empty($_POST['directory'])) {
    $targetFile = trim($_POST['filename']);
    $targetDir = realpath(trim($_POST['directory']));

    if ($targetDir && is_dir($targetDir)) {
        findAndDeleteFiles($targetDir, $targetFile, $deleted, $failed);
    } else {
        $failed[] = 'Invalid directory: ' . htmlspecialchars($_POST['directory']);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mass File Deleter</title>
    <style>
        body { font-family: Arial; background: #f7f7f7; padding: 30px; }
        .container { max-width: 700px; margin: auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; }
        button { padding: 10px 20px; background: crimson; color: white; border: none; border-radius: 5px; cursor: pointer; }
        pre { background: #eee; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h2, h3, h4 { color: #333; }
        label { font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Mass Delete Files by Name</h2>
    <form method="POST">
        <label>Enter File Name to Delete (e.g. <code>shell.php</code>):</label>
        <input type="text" name="filename" value="<?= htmlspecialchars($targetFile) ?>" required>

        <label>Enter Target Directory (absolute or relative):</label>
        <input type="text" name="directory" value="<?= htmlspecialchars($targetDir ?: __DIR__) ?>" required>

        <button type="submit">Delete Files</button>
    </form>

    <?php if (!empty($deleted) || !empty($failed)): ?>
        <hr>
        <h3>Result:</h3>
        <p><strong style="color:green"><?= count($deleted) ?></strong> file(s) deleted.</p>
        <p><strong style="color:red"><?= count($failed) ?></strong> file(s) failed to delete.</p>

        <?php if ($deleted): ?>
            <h4>✅ Deleted Files:</h4>
            <pre><?= implode("\n", $deleted) ?></pre>
        <?php endif; ?>

        <?php if ($failed): ?>
            <h4>❌ Failed to Delete:</h4>
            <pre><?= implode("\n", $failed) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
