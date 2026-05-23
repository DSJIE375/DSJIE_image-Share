<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$error = '';
if (isAdmin()) {
    header('Location: upload.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = '用户名和密码不能为空。';
    } elseif (loginAdmin($username, $password)) {
        header('Location: upload.php');
        exit;
    } else {
        $error = '用户名或密码错误。';
    }
}

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
    <title>管理员登录 - DSJIE_image Share</title>
    <style>
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: #eef2ff;
            color: #111827;
        }

        .container {
            max-width: 420px;
            margin: 72px auto;
            padding: 24px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .13);
        }

        h1 {
            margin-bottom: 12px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        input {
            width: 95%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }

        .error {
            margin-bottom: 16px;
            color: #b91c1c;
            background: #fee2e2;
            padding: 12px 14px;
            border-radius: 12px;
        }

        .note {
            margin-top: 18px;
            font-size: .95rem;
            color: #4b5563;
        }

        .link {
            margin-top: 12px;
            display: block;
            text-align: center;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>DSJIE_image Share<br /> 管理登录</h1>
        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <div class="field">
                <label for="username">用户名</label>
                <input id="username" name="username" type="text" value="" required>
            </div>
            <div class="field">
                <label for="password">密码</label>
                <input id="password" name="password" type="password" value="" required>
            </div>
            <button type="submit">登录</button>
        </form>
        <p class="note">默认登录：用户名 <strong>admin</strong> 密码 <strong>password</strong>。<br />建议在 Docker 或服务器环境里用环境变量
            `ADMIN_USER` / `ADMIN_PASS` 修改 DSJIE_image Share 的管理员账号。</p>
        <a class="link" href="index.php">返回首页</a>
    </div>
</body>

</html>