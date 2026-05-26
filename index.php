<?php
// declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/metadata.php';

$imagesDir = __DIR__ . '/images';
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0775, true);
}

$metadata = loadImageMetadata();
$query = normalizeMetadataValue($_GET['q'] ?? '');


$sortValue = $_GET['sort'] ?? 'newest';
$sort = in_array($sortValue, ['newest', 'oldest', 'name'], true)
    ? $sortValue
    : 'newest';

$allFiles = scandir($imagesDir);
$images = array_values(array_filter($allFiles, function ($filename) use ($imagesDir) {
    $path = $imagesDir . '/' . $filename;
    return is_file($path) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $filename);
}));
sort($images, SORT_NATURAL | SORT_FLAG_CASE);

$imageData = [];
foreach ($images as $image) {
    $path = $imagesDir . '/' . $image;
    $size = filesize($path);
    $mime = mime_content_type($path) ?: '';
    $resolution = '';
    $sizeInfo = getimagesize($path);
    if ($sizeInfo) {
        $resolution = $sizeInfo[0] . ' × ' . $sizeInfo[1];
    }

    $exif = [];
    if (function_exists('exif_read_data') && preg_match('/\.(jpe?g|tiff?)$/i', $image)) {
        $exif = @exif_read_data($path, 0, true) ?: [];
    }

    $title = pathinfo($image, PATHINFO_FILENAME);
    if (!empty($exif['IFD0']['ImageDescription'])) {
        $title = formatExifValue($exif['IFD0']['ImageDescription']);
    }

    $description = '';
    if (!empty($exif['IFD0']['XPTitle'])) {
        $description = formatExifValue($exif['IFD0']['XPTitle']);
    } elseif (!empty($exif['IFD0']['XPSubject'])) {
        $description = formatExifValue($exif['IFD0']['XPSubject']);
    } elseif (!empty($exif['EXIF']['UserComment'])) {
        $description = formatExifValue($exif['EXIF']['UserComment']);
    } elseif (!empty($exif['IFD0']['ImageDescription'])) {
        $description = formatExifValue($exif['IFD0']['ImageDescription']);
    }

    $metaItem = $metadata[$image] ?? null;
    if (is_array($metaItem)) {
        $metaTitle = normalizeMetadataValue($metaItem['title'] ?? '');
        $metaDescription = normalizeMetadataValue($metaItem['description'] ?? '');
        if ($metaTitle !== '') {
            $title = $metaTitle;
        }
        if ($metaDescription !== '') {
            $description = $metaDescription;
        }
    }

    $searchSource = $image . ' ' . $title . ' ' . $description;
    if ($query !== '' && stripos($searchSource, $query) === false) {
        continue;
    }

    $imageData[] = [
        'filename' => $image,
        'title' => $title,
        'description' => $description,
        'url' => 'images/' . rawurlencode($image),
        'size' => formatBytes($size),
        'mime' => $mime,
        'resolution' => $resolution,
        'exif' => $exif,
        'uploadedAt' => filemtime($path) ?: 0,
    ];
}

usort($imageData, function (array $a, array $b) use ($sort) {
    if ($sort === 'oldest') {
        return $a['uploadedAt'] <=> $b['uploadedAt'];
    }
    if ($sort === 'name') {
        return strcasecmp($a['filename'], $b['filename']);
    }
    return $b['uploadedAt'] <=> $a['uploadedAt'];
});

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSJIE_image Share</title>
    <style>
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f7fb;
            color: #202124;
        }

        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px;
        }

        h1 {
            margin-bottom: 4px;
        }

        .top-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #d6dde8;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
        }

        .admin-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .admin-links a {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 10px;
            background: #eef2ff;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 600;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(15, 23, 42, .14);
        }

        .card-image {
            position: relative;
            overflow: hidden;
        }

        .card-image img {
            width: 100%;
            height: auto;
            display: block;
            aspect-ratio: 4 / 3;
            object-fit: cover;
        }

        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .84);
            color: #f8fafc;
            opacity: 0;
            transition: opacity .2s ease-in-out;
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .card:hover .overlay {
            opacity: 1;
        }

        .overlay-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .overlay-text {
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 12px;
            max-height: 5.2rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .overlay-scroll {
            max-height: calc(100% - 64px);
            overflow: auto;
            padding-right: 4px;
        }

        .card-body {
            padding: 18px 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .card-title {
            margin: 0 0 8px;
            font-size: 1.05rem;
            line-height: 1.4;
        }

        .card-description {
            margin: 0;
            color: #4b5563;
            line-height: 1.6;
            min-height: 3.6rem;
        }

        .card-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .card-footer span {
            background: #eff6ff;
            border-radius: 999px;
            padding: 6px 10px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 0.92rem;
        }

        .meta-row span:first-child {
            opacity: 0.8;
        }

        .meta-row span:last-child {
            text-align: right;
        }

        .overlay-hr {
            border: 0;
            border-top: 1px solid rgba(255, 255, 255, .18);
            margin: 10px 0;
        }

        .hero {
            display: grid;
            gap: 14px;
            margin-bottom: 24px;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: #2563eb;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            font-size: 0.82rem;
        }

        .hero-description {
            margin: 0;
            color: #475569;
            max-width: 680px;
            line-height: 1.75;
        }

        .search-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
        }

        .search-grid input,
        .search-grid select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 1rem;
            background: #f8fafc;
        }

        .search-grid button {
            padding: 12px 18px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }

        .search-grid label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.95rem;
            color: #334155;
        }

        .main-image {
            width: 100%;
            border-radius: 16px;
            border: 1px solid #d6dde8;
            max-height: 65vh;
            object-fit: contain;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        td,
        th {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            text-align: left;
            background: #f8fafc;
        }

        .meta-section {
            margin-top: 20px;
        }

        .footer {
            margin-top: 32px;
            font-size: 0.95rem;
            color: #4b5563;
        }

        @media (max-width: 960px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 16px;
            }

            .card-body {
                padding: 16px;
            }

            .overlay {
                padding: 14px;
            }

            .hero-description {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top-bar">
            <div>
                <h1>DSJIE_image Share</h1>
                <p>展示已上传的图片与 EXIF 信息。如需上传，请先登录后台。</p>
            </div>
            <div class="admin-links">
                <?php if (isAdmin()): ?>
                    <a href="upload.php">后台上传</a>
                    <a href="logout.php">退出登录</a>
                <?php else: ?>
                    <a href="login.php">管理员登录</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="hero">
                <div>
                    <p class="eyebrow">DSJIE_image Share</p>
                    <h2>DSJIE_图像分享</h2>
                    <p class="hero-description">首页展示标题和描述，支持手机、平板、桌面端。图片查看 EXIF 详情。</p>
                    <p class="hero-description">如果你也想把图片投稿展示到 DSJIE_image Share 平台，欢迎通过邮箱：<a href="mailto:email@dsjie375.cn">email@dsjie375.cn</a>，联系我们。邮箱模板：邮箱标题：DSJIE_image Share投稿，内容：1.图片标题（必填）、2.图片描述（默认无描述）、3.图片文件（必选）。</p>
                </div>
            </div>
            <form method="get" action="index.php" class="search-grid">
                <label>
                    搜索图片
                    <input type="search" name="q" value="<?= h($query) ?>" placeholder="搜索标题、描述或文件名">
                </label>
                <div style="display:flex; gap:12px; width:100%;">
                    <label style="flex:1;">
                        排序方式
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新上传</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>最早上传</option>
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>按名称</option>
                        </select>
                    </label>
                    <button type="submit">搜索</button>
                </div>
            </form>
            <?php if (count($imageData) === 0): ?>
                <p><?= $query !== '' ? '没有找到符合搜索条件的图片。' : '当前 images/ 目录中没有图片文件。' ?></p>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($imageData as $item): ?>
                        <a class="card" target="_blank" href="image.php?file=<?= rawurlencode($item['filename']) ?>">
                            <div class="card-image">
                                <img src="<?= h($item['url']) ?>" alt="<?= h($item['title']) ?>">
                            </div>
                            <div class="card-body">
                                <h3 class="card-title"><?= h($item['title']) ?></h3>
                                <p class="card-description"><?= h($item['description'] ?: '无描述') ?></p>
                                <p class="card-description">点击查看图片详细信息。</p>
                                <div class="card-footer">
                                    <span><?= h($item['exif']['IFD0']['Make'] ?? '未知厂商') ?></span>
                                    <span><?= h($item['exif']['IFD0']['Model'] ?? '未知型号') ?></span>
                                    <span><?= h($item['size']) ?></span>
                                    <?php if ($item['resolution']): ?><span><?= h($item['resolution']) ?></span><?php endif; ?>
                                    <!-- <span><?= h($item['exif']['EXIF']['DateTimeOriginal'] ?? '未知') ?></span> -->
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>DSJIE_image Share 源码已上传至 <a href="https://github.com/DSJIE375/DSJIE_image-Share" target="_blank" rel="noopener noreferrer">GitHub</a>，需要自取。</p>
        </div>
    </div>
</body>

</html>