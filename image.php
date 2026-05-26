<?php
// declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/metadata.php';

$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0775, true);
}

$metadata = loadImageMetadata();

$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$allowedFiles = array_values(array_filter(scandir($imagesDir), function ($name) use ($imagesDir) {
    $path = $imagesDir . '/' . $name;
    return is_file($path) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $name);
}));

if ($filename === '' || !in_array($filename, $allowedFiles, true)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>图片未找到</title></head><body><h1>图片未找到</h1><p>请返回 <a href="index.php">首页</a>。</p></body></html>';
    exit;
}

$path = $imagesDir . '/' . $filename;
$mime = mime_content_type($path) ?: 'application/octet-stream';
$size = filesize($path);
$resolution = '';
$sizeInfo = getimagesize($path);
if ($sizeInfo) {
    $resolution = $sizeInfo[0] . ' × ' . $sizeInfo[1];
}

$exif = [];
if (function_exists('exif_read_data') && preg_match('/\.(jpe?g|tiff?)$/i', $filename)) {
    $exif = @exif_read_data($path, 0, true) ?: [];
}

$title = normalizeMetadataValue($metadata[$filename]['title'] ?? '');
if ($title === '') {
    $title = pathinfo($filename, PATHINFO_FILENAME);
    if (!empty($exif['IFD0']['ImageDescription'])) {
        $title = formatExifValue($exif['IFD0']['ImageDescription']);
    }
}

$description = normalizeMetadataValue($metadata[$filename]['description'] ?? '');
if ($description === '') {
    if (!empty($exif['IFD0']['XPTitle'])) {
        $description = formatExifValue($exif['IFD0']['XPTitle']);
    } elseif (!empty($exif['IFD0']['XPSubject'])) {
        $description = formatExifValue($exif['IFD0']['XPSubject']);
    } elseif (!empty($exif['EXIF']['UserComment'])) {
        $description = formatExifValue($exif['EXIF']['UserComment']);
    } elseif (!empty($exif['IFD0']['ImageDescription'])) {
        $description = formatExifValue($exif['IFD0']['ImageDescription']);
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function translateExifSection(string $section): string
{
    $map = [
        'IFD0' => '基本信息',
        'EXIF' => '拍摄信息',
        'GPS' => 'GPS 信息',
        'COMPUTED' => '计算信息',
        'INTEROP' => '互操作信息',
        'THUMBNAIL' => '缩略图',
    ];
    return $map[$section] ?? $section;
}

function translateExifTitle(string $key): string
{
    $map = [
        'ImageDescription' => '图像描述',
        'Make' => '相机厂商',
        'Model' => '相机型号',
        'Software' => '软件',
        'DateTime' => '文件时间',
        'ExposureTime' => '曝光时间',
        'FNumber' => '光圈',
        'ISOSpeedRatings' => 'ISO',
        'DateTimeOriginal' => '拍摄时间',
        'FocalLength' => '焦距',
        'Flash' => '闪光灯',
        'WhiteBalance' => '白平衡',
        'MeteringMode' => '测光模式',
        'ExposureProgram' => '曝光程序',
        'LensModel' => '镜头型号',
        'GPSLatitude' => '纬度',
        'GPSLongitude' => '经度',
        'GPSAltitude' => '海拔',
        'UserComment' => '用户备注',
        'XPTitle' => '标题',
        'XPSubject' => '主题',
        'XPComment' => '注释',
    ];
    return $map[$key] ?? $key;
}

function getImportantExif(array $exif): array
{
    $priorityKeys = [
        'ImageDescription',
        'Make',
        'Model',
        'Software',
        'DateTime',
        'DateTimeOriginal',
        'ExposureTime',
        'FNumber',
        'ISOSpeedRatings',
        'FocalLength',
        'Flash',
        'WhiteBalance',
        'MeteringMode',
        'ExposureProgram',
        'LensModel',
        'UserComment',
        'GPSLatitude',
        'GPSLongitude',
        'GPSAltitude',
        'XPTitle',
        'XPSubject',
        'XPComment',
    ];

    $filtered = [];
    foreach (['IFD0', 'EXIF', 'GPS'] as $section) {
        if (!isset($exif[$section]) || !is_array($exif[$section])) {
            continue;
        }
        foreach ($priorityKeys as $key) {
            if (array_key_exists($key, $exif[$section])) {
                $filtered[$section][$key] = $exif[$section][$key];
            }
        }
    }

    return $filtered;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片详情 - <?= h($title) ?></title>
    <style>
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f7fb;
            color: #111827;
        }

        .container {
            max-width: 1040px;
            margin: 0 auto;
            padding: 24px;
        }

        .top-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 24px;
        }

        .top-bar a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #d6dde8;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .08);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
        }

        .detail-image {
            border-radius: 20px;
            overflow: hidden;
        }

        .detail-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        .info-box {
            display: grid;
    gap: 18px;
    align-content: center;
    align-items: center;
    justify-content: space-around;
        }

        .info-box h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        .info-box p {
            margin: 0;
            color: #475569;
            line-height: 1.75;
        }

        .info-table,
        .exif-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table th,
        .info-table td,
        .exif-table th,
        .exif-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-table th,
        .exif-table th {
            text-align: left;
            background: #f8fafc;
            width: 35%;
        }

        .section-title {
            margin: 0 0 14px;
            font-size: 1.15rem;
        }

        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.95rem;
        }

        .footer {
            margin-top: 28px;
            font-size: 0.95rem;
            color: #475569;
        }

        @media (max-width: 860px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top-bar">
            <div>
                <a href="index.php">← 返回首页</a>
            </div>
            <div class="badge-row">
                <span class="badge">图片详情</span>
                <span class="badge"><?= h($title) ?></span>
            </div>
        </div>

        <div class="panel">
            <div class="detail-grid">
                <div class="detail-image">
                    <a href="images/<?= urlencode($filename) ?>" target="_blank" rel="noopener">
                        <img src="images/<?= urlencode($filename) ?>" alt="<?= h($title) ?>">
                    </a>
                </div>
                <div class="info-box">
                    <div>
                        <h1>标题：<?= h($title) ?></h1>
                        <p>描述：<?= h($description ?: '无描述') ?></p>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px;">
                            <a class="action-button" href="images/<?= urlencode($filename) ?>" target="_blank"
                                rel="noopener"
                                style="display:inline-flex; padding:10px 16px; border:none; border-radius:12px; background:#2563eb; color:#fff; text-decoration:none; font-weight:600;">查看原图</a>
                            <a class="action-button" href="images/<?= urlencode($filename) ?>"
                                download="<?= h($filename) ?>"
                                style="display:inline-flex; padding:10px 16px; border:none; border-radius:12px; background:#2563eb; color:#fff; text-decoration:none; font-weight:600;">下载原图</a>
                            <button id="copyLinkButton" type="button"
                                style="padding:10px 16px; border:none; border-radius:12px; background:#2563eb; color:#fff; font-weight:600; cursor:pointer;">复制原图链接</button>
                        </div>
                    </div>
                    <div>
                        <h2 class="section-title">基本信息</h2>
                        <table class="info-table">
                            <tr>
                                <th>文件名</th>
                                <td><?= h($filename) ?></td>
                            </tr>
                            <tr>
                                <th>大小</th>
                                <td><?= h(formatBytes($size)) ?></td>
                            </tr>
                            <tr>
                                <th>类型</th>
                                <td><?= h($mime) ?></td>
                            </tr>
                            <?php if ($resolution): ?>
                                <tr>
                                    <th>分辨率</th>
                                    <td><?= h($resolution) ?></td>
                                </tr><?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div style="margin-top: 28px;">
                <h2 class="section-title">EXIF 信息</h2>
                <?php $importantExif = getImportantExif($exif); ?>
                <?php if (count($importantExif) === 0): ?>
                    <p>未检测到关键 EXIF 信息或当前图片类型不支持 EXIF。</p>
                <?php else: ?>
                    <?php foreach ($importantExif as $section => $content): ?>
                        <?php if (empty($content)) {
                            continue;
                        } ?>
                        <h3 class="section-title"><?= h(translateExifSection($section)) ?></h3>
                        <table class="exif-table">
                            <?php foreach ($content as $key => $value): ?>
                                <tr>
                                    <th><?= h(translateExifTitle($key)) ?></th>
                                    <td><?= h(formatExifValue($value)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <p>DSJIE_image Share 源码已上传至 <a href="https://github.com/DSJIE375/DSJIE_image-Share" target="_blank" rel="noopener noreferrer">GitHub</a>，需要自取。</p>
        </div>
    </div>
    <script>
        (function () {
            var button = document.getElementById('copyLinkButton');
            if (!button) {
                return;
            }
            button.addEventListener('click', function () {
                var imageUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/') + 'images/' + encodeURIComponent(<?= json_encode($filename, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(imageUrl).then(function () {
                        alert('图片链接已复制到剪贴板');
                    }, function () {
                        fallbackCopy(imageUrl);
                    });
                } else {
                    fallbackCopy(imageUrl);
                }
            });

            function fallbackCopy(text) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                try {
                    document.execCommand('copy');
                    alert('图片链接已复制到剪贴板');
                } catch (err) {
                    alert('无法复制链接，请手动复制：' + text);
                }
                document.body.removeChild(textarea);
            }
        })();
    </script>
</body>

</html>