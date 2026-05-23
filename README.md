# DSJIE_image Share

这是一个简单的 PHP 图片展示与 EXIF 信息查看网站，适合本地部署、Docker 容器运行，并可直接开源到 GitHub。

## 功能

- 自动扫描 `images/` 目录中的图片
- 显示图片缩略图与大图预览
- 显示 JPEG/TIFF 图片的 EXIF 信息
- 首页展示已上传图片和 EXIF 信息
- 上传功能仅在后台可用，需要管理员登录

## 本地部署

1. 将项目目录放入本地 PHP 环境（例如 `phpstudy` 或 `XAMPP`）
2. 访问 `http://localhost/your-project-path/`

如果你有 PHP 8 环境，也可以直接使用内置服务器：

```bash
php -S localhost:8000
```

然后在浏览器访问 `http://localhost:8000`

## Docker 部署

构建镜像：

```bash
docker build -t image-exif-viewer .
```

运行容器：

```bash
docker run --rm -p 2375:80 -v "%cd%/images:/var/www/html/images" -e ADMIN_USER=admin -e ADMIN_PASS=password image-exif-viewer
```

然后打开 `http://localhost:8080`

或者使用 `docker-compose`：

```bash
docker-compose up --build
```

## 目录说明

- `index.php` - 主页，展示已上传图片与 EXIF 信息
- `login.php` - 管理员登录页面
- `upload.php` - 登录后可访问的图片上传后台
- `logout.php` - 管理员退出登录
- `auth.php` - 管理员登录状态和权限检查
- `images/` - 存放图片文件的目录
- `Dockerfile` - Docker 容器镜像配置
- `docker-compose.yml` - 可选的 Docker Compose 配置
- `.dockerignore` - 构建镜像时忽略本地图片

## 贡献与开源

你可以把这个项目整个目录推送到 GitHub，其他人可以直接克隆并使用 `Dockerfile` 或 `docker-compose.yml` 启动。
