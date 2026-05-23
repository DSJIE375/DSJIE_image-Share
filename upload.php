<?php
// declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/metadata.php';
requireAdmin();

$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0775, true);
}

$message = '';
$error = '';
$metadata = loadImageMetadata();
$editFile = '';
$editMeta = ['title' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'upload';
    if ($action === 'upload' && isset($_FILES['images'])) {
        $title = normalizeMetadataValue($_POST['title'] ?? '');
        $description = normalizeMetadataValue($_POST['description'] ?? '');
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $rawFiles = $_FILES['images'];
        $files = [];
        if (is_array($rawFiles['name'])) {
            foreach ($rawFiles['name'] as $index => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $rawFiles['type'][$index] ?? '',
                    'tmp_name' => $rawFiles['tmp_name'][$index] ?? '',
                    'error' => $rawFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $rawFiles['size'][$index] ?? 0,
                ];
            }
        } else {
            $files[] = $rawFiles;
        }

        $successCount = 0;
        $errors = [];
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileName = $file['name'] ?: '未知文件';
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = sprintf('文件 %s 上传失败，错误代码 %d。', $fileName, $file['error']);
                continue;
            }
            if (!in_array($file['type'], $allowedTypes, true)) {
                $errors[] = sprintf('文件 %s 类型不支持。', $fileName);
                continue;
            }

            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
            if ($safeName === '') {
                $safeName = 'image';
            }
            if (!isValidImageExtension($ext)) {
                $errors[] = sprintf('文件 %s 后缀不受支持。', $fileName);
                continue;
            }
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false || !isValidImageMimeType($imageInfo['mime'] ?? '')) {
                $errors[] = sprintf('文件 %s 不是有效图片。', $fileName);
                continue;
            }

            $targetFile = sprintf('%s/%s.%s', $imagesDir, $safeName, $ext);
            $count = 1;
            while (file_exists($targetFile)) {
                $targetFile = sprintf('%s/%s_%d.%s', $imagesDir, $safeName, $count, $ext);
                $count++;
            }
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                $filename = basename($targetFile);
                $metadata[$filename] = [
                    'title' => $title,
                    'description' => $description,
                ];
                saveImageMetadata($metadata);
                $successCount++;
            } else {
                $errors[] = sprintf('文件 %s 保存失败。', $fileName);
            }
        }

        if ($successCount > 0) {
            $message = sprintf('成功上传 %d 张图片。', $successCount);
            if ($errors !== []) {
                $message .= ' 部分文件未能上传：' . implode(' ', $errors);
            }
        } elseif ($errors !== []) {
            $error = implode(' ', $errors);
        } else {
            $error = '未选择任何图片。';
        }
    } elseif ($action === 'update' && !empty($_POST['file'])) {
        $filename = basename($_POST['file']);
        if (!file_exists($imagesDir . '/' . $filename)) {
            $error = '要编辑的图片不存在。';
        } else {
            $metadata[$filename] = [
                'title' => normalizeMetadataValue($_POST['title'] ?? ''),
                'description' => normalizeMetadataValue($_POST['description'] ?? ''),
            ];
            saveImageMetadata($metadata);
            $message = '已更新图片信息：' . $filename;
        }
    } elseif ($action === 'delete' && !empty($_POST['file'])) {
        $filename = basename($_POST['file']);
        $filePath = $imagesDir . '/' . $filename;
        if (!file_exists($filePath)) {
            $error = '要删除的图片不存在。';
        } else {
            if (@unlink($filePath)) {
                unset($metadata[$filename]);
                saveImageMetadata($metadata);
                $message = '已删除图片：' . $filename;
            } else {
                $error = '删除图片失败。';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['edit'])) {
    $editFile = basename($_GET['edit']);
    if (isset($metadata[$editFile])) {
        $editMeta = $metadata[$editFile];
    }
}

$allFiles = scandir($imagesDir);
$images = array_values(array_filter($allFiles, function ($filename) use ($imagesDir) {
    $path = $imagesDir . '/' . $filename;
    return is_file($path) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $filename);
}));
sort($images, SORT_NATURAL | SORT_FLAG_CASE);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台上传 - DSJIE_image Share</title>
    <style>
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: #f8fafc;
            color: #111827;
        }

        .container {
            max-width: 920px;
            margin: 36px auto;
            padding: 24px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
        }

        h1 {
            margin-bottom: 8px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .actions a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .message {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 12px;
        }

        .success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .upload-form {
            display: grid;
            gap: 12px;
            margin-bottom: 24px;
        }

        .upload-form input[type="file"],
        .upload-form input[type="text"],
        .upload-form textarea {
            width: 95%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font: inherit;
        }

        .upload-form button {
            padding: 12px 18px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
        }

        .field-row {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }

        .field-row label {
            font-weight: 600;
            color: #334155;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 16px;
        }

        .cancel-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 12px;
            background: #f8fafc;
            color: #334155;
            text-decoration: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            word-break: break-word;
        }

        th {
            background: #f8fafc;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 10px;
            background: #eef2ff;
            color: #1d4ed8;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .action-button.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .small {
            font-size: .95rem;
            color: #4b5563;
        }

        .mobile-list {
            display: none;
        }

        .mobile-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .mobile-card .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .mobile-card .row span {
            flex: 1 1 50%;
        }

        .mobile-card .label {
            display: block;
            font-size: .92rem;
            color: #475569;
            margin-bottom: 4px;
        }

        .mobile-card .value {
            color: #111827;
            word-break: break-word;
        }

        .mobile-card .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        @media (max-width: 640px) {
            .upload-form {
                grid-template-columns: 1fr;
            }

            .upload-form button {
                width: 100%;
            }

            .desktop-table {
                display: none;
            }

            .mobile-list {
                display: grid;
                gap: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="actions">
            <div>
                <h1>DSJIE_image Share 管理后台</h1>
                <p class="small">登录后才能上传图片或短视频，首页仍然展示已上传媒体文件和 EXIF 信息。</p>
            </div>
            <div>
                <a href="index.php">返回首页</a>
                <a href="logout.php">退出登录</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= h($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($editFile): ?>
            <div class="panel" style="margin-bottom: 24px;">
                <h2>编辑图片信息：<?= h($editFile) ?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="file" value="<?= h($editFile) ?>">
                    <div class="field-row">
                        <label for="edit-title">标题</label>
                        <input id="edit-title" type="text" name="title" value="<?= h($editMeta['title']) ?>"
                            placeholder="请输入图片标题">
                    </div>
                    <div class="field-row">
                        <label for="edit-description">描述</label>
                        <textarea id="edit-description" name="description" rows="3"
                            placeholder="请输入图片描述"><?= h($editMeta['description']) ?></textarea>
                    </div>
                    <div class="button-row">
                        <button type="submit">保存修改</button>
                        <a class="cancel-link" href="upload.php">取消</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2>上传新图片</h2>
            <form class="upload-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                <p class="small">可以一次选择多张图片上传，支持 JPEG、PNG、GIF、WEBP。</p>
                <div class="field-row">
                    <label for="title">标题（可选）</label>
                    <input id="title" type="text" name="title" placeholder="上传后显示在首页">
                </div>
                <div class="field-row">
                    <label for="description">描述（可选）</label>
                    <textarea id="description" name="description" rows="3" placeholder="上传后显示在首页"></textarea>
                </div>
                <button type="submit">上传</button>
            </form>
        </div>

        <div class="panel" style="margin-top: 24px;">
            <h2>已上传图片</h2>
            <?php if (count($images) === 0): ?>
                <p>当前 `images/` 目录中没有图片。</p>
            <?php else: ?>
                <div class="table-wrap desktop-table">
                    <table>
                        <thead>
                            <tr>
                                <th>文件名</th>
                                <th>标题</th>
                                <th>描述</th>
                                <th>类型</th>
                                <th>大小</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($images as $image): ?>
                                <?php $path = $imagesDir . '/' . $image;
                                $size = filesize($path);
                                $mime = mime_content_type($path);
                                $meta = $metadata[$image] ?? ['title' => '', 'description' => '']; ?>
                                <tr>
                                    <td><?= h($image) ?></td>
                                    <td><?= h($meta['title']) ?></td>
                                    <td><?= h($meta['description']) ?></td>
                                    <td><?= h($mime) ?></td>
                                    <td><?= h(formatBytes((int) $size)) ?></td>
                                    <td>
                                        <a class="action-button" href="upload.php?edit=<?= rawurlencode($image) ?>">编辑</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('确认删除该图片吗？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="file" value="<?= h($image) ?>">
                                            <button class="action-button danger" type="submit">删除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-list">
                    <?php foreach ($images as $image): ?>
                        <?php $path = $imagesDir . '/' . $image;
                        $size = filesize($path);
                        $mime = mime_content_type($path);
                        $meta = $metadata[$image] ?? ['title' => '', 'description' => '']; ?>
                        <div class="mobile-card">
                            <div class="row">
                                <span><span class="label">文件名</span><span class="value"><?= h($image) ?></span></span>
                                <span><span class="label">大小</span><span
                                        class="value"><?= h(formatBytes((int) $size)) ?></span></span>
                            </div>
                            <div class="row">
                                <span><span class="label">标题</span><span class="value"><?= h($meta['title']) ?></span></span>
                                <span><span class="label">类型</span><span class="value"><?= h($mime) ?></span></span>
                            </div>
                            <div class="row">
                                <span style="width:100%;"><span class="label">描述</span><span
                                        class="value"><?= h($meta['description']) ?></span></span>
                            </div>
                            <div class="actions">
                                <a class="action-button" href="upload.php?edit=<?= rawurlencode($image) ?>">编辑</a>
                                <form method="post" style="display:inline; width:auto;" onsubmit="return confirm('确认删除该图片吗？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file" value="<?= h($image) ?>">
                                    <button class="action-button danger" type="submit">删除</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>