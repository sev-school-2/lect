<?php
// Включаем отображение ошибок для диагностики
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Обработка загрузки файла через AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $target_dir = "1/";

    // Создаем папку, если её нет
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Ошибка: Не удалось создать директорию.']);
            exit;
        }
    }

    // Проверяем права на запись
    if (!is_writable($target_dir)) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка: Директория недоступна для записи.']);
        exit;
    }

    // Проверяем наличие ошибок загрузки
    if ($_FILES["fileToUpload"]["error"] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Файл превышает upload_max_filesize в php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Файл превышает MAX_FILE_SIZE в форме.',
            UPLOAD_ERR_PARTIAL => 'Файл загружен частично.',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная директория.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP остановило загрузку.'
        ];
        $message = 'Ошибка: Файл не был загружен. ';
        $message .= isset($error_messages[$_FILES["fileToUpload"]["error"]]) ? $error_messages[$_FILES["fileToUpload"]["error"]] : 'Неизвестная ошибка.';
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }

    $original_name = basename($_FILES["fileToUpload"]["name"]);
    $target_file = $target_dir . $original_name;
    $uploadOk = 1;

    // Если файл уже существует, добавляем суффикс "копия"
    $counter = 1;
    while (file_exists($target_file)) {
        $file_info = pathinfo($original_name);
        $target_file = $target_dir . $file_info['filename'] . " копия $counter." . $file_info['extension'];
        $counter++;
    }

    // Проверка размера файла (1 ГБ = 1073741824 байт)
    if ($_FILES["fileToUpload"]["size"] > 1073741824) {
        echo json_encode(['status' => 'error', 'message' => 'Ошибка: Файл слишком большой (более 1 ГБ).']);
        exit;
    }

    // Если всё в порядке, загружаем файл
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            echo json_encode(['status' => 'success', 'filename' => basename($target_file)]);
        } else {
            $error = error_get_last();
            $message = 'Ошибка: Не удалось переместить файл. ';
            if ($error) {
                $message .= 'Детали: ' . $error['message'];
            }
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
    }
    exit;
}

// Обработка удаления файла
if (isset($_GET['delete'])) {
    $fileToDelete = "1/" . basename($_GET['delete']);
    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Обработка скачивания файла
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filepath = "1/" . $file;

    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        die("Ошибка: Файл не найден.");
    }
}

// Обработка AJAX-запроса для получения списка файлов
if (isset($_GET['get_files'])) {
    $directory = "1/";
    $files = [];
    if (is_dir($directory)) {
        if ($dh = opendir($directory)) {
            while (($file = readdir($dh)) !== false) {
                if ($file != "." && $file != "..") {
                    $files[] = $file;
                }
            }
            closedir($dh);
        }
    }
    echo json_encode($files);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка и управление файлами</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #333;
        }
        .upload-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .upload-form input[type="file"] {
            display: none;
        }
        .upload-form label {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-block;
        }
        .upload-form label:hover {
            background-color: #218838;
        }
        .file-list {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-item span {
            font-size: 14px;
            color: #555;
            margin-right: 20px;
        }
        .file-item .actions {
            display: flex;
            gap: 15px;
        }
        .file-item a {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .file-item a:hover {
            background-color: #0056b3;
        }
        .file-item button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .file-item button:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <h1>Загрузка и управление файлами</h1>

    <!-- Форма загрузки файла -->
    <div class="upload-form">
        <label for="fileToUpload">Выбрать файл</label>
        <input type="file" name="fileToUpload" id="fileToUpload">
    </div>

    <!-- Список файлов -->
    <div class="file-list">
        <h2>Список файлов</h2>
        <div id="fileList">
            <?php
            $directory = "1/";
            if (is_dir($directory)) {
                if ($dh = opendir($directory)) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != "." && $file != "..") {
                            echo "<div class='file-item'>
                                    <span>$file</span>
                                    <div class='actions'>
                                        <a href='?download=$file'>Скачать</a>
                                        <button onclick=\"window.location.href='?delete=$file'\">Удалить</button>
                                    </div>
                                  </div>";
                        }
                    }
                    closedir($dh);
                }
            }
            ?>
        </div>
    </div>

    <script>
        // Функция для обновления списка файлов
        function updateFileList() {
            fetch('?get_files')
                .then(response => response.json())
                .then(files => {
                    let fileList = document.getElementById('fileList');
                    fileList.innerHTML = ''; // Очищаем текущий список
                    files.forEach(file => {
                        let fileItem = document.createElement('div');
                        fileItem.className = 'file-item';
                        fileItem.innerHTML = `
                            <span>${file}</span>
                            <div class="actions">
                                <a href="?download=${file}">Скачать</a>
                                <button onclick="window.location.href='?delete=${file}'">Удалить</button>
                            </div>
                        `;
                        fileList.appendChild(fileItem);
                    });
                })
                .catch(error => console.error('Ошибка при обновлении списка файлов:', error));
        }

        // Обработка выбора файла и автоматическая загрузка
        document.getElementById('fileToUpload').addEventListener('change', function() {
            let fileInput = this;
            let formData = new FormData();
            formData.append('fileToUpload', fileInput.files[0]);

            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateFileList(); // Обновляем список после загрузки для всех
                    fileInput.value = ''; // Очищаем input
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при загрузке файла: ' + error.message);
            });
        });

        // Периодическое обновление списка каждые 1 секунду
        setInterval(updateFileList, 1000);

        // Первоначальное обновление списка при загрузке страницы
        updateFileList();
    </script>
</body>
</html>