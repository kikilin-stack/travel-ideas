# 旅行想法管理平台

基于 Laravel + jQuery + MySQL/SQLite 的旅行想法网站，支持用户发布旅行计划、评论互动，并集成天气、酒店、美食三个外部 API。

## 系统要求

- PHP 8.2+
- Composer
- MySQL 8.0+ 或 SQLite（默认）
- Node.js & NPM（可选，仅当使用 Vite 时）

## 安装步骤

### 1. 安装依赖

```bash
composer install
```

### 2. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 配置数据库（默认使用 SQLite，无需改）：

- 若使用 MySQL，设置 `DB_CONNECTION=mysql` 及 `DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`

### 3. 数据库

```bash
# 使用默认 SQLite 时无需创建数据库，直接迁移即可
php artisan migrate
php artisan db:seed
```

### 4. 存储链接（封面图上传）

```bash
php artisan storage:link
```

### 5. 启动开发服务器

**方式一（推荐，路由与静态资源均正常）：**

```bash
./run.sh
# 或
php -S 127.0.0.1:8000 -t public public/router.php
```

然后访问：**http://127.0.0.1:8000/login**（CSS/JS 会从 `public` 正确加载）

**方式二：**

```bash
php artisan serve
```

若提示端口被占用会改用 8001，请用终端里显示的地址访问（如 http://127.0.0.1:8001/login）。

## API 密钥（可选）

未配置时，天气/酒店/美食区域将使用**模拟数据**，不影响其他功能。

在 `.env` 中配置：

```env
OPENWEATHER_API_KEY=你的密钥
AMADEUS_CLIENT_ID=你的ClientID
AMADEUS_CLIENT_SECRET=你的ClientSecret
SPOONACULAR_API_KEY=你的密钥
```

- **OpenWeatherMap**：https://openweathermap.org/api  
- **Amadeus**：https://developers.amadeus.com/  
- **Spoonacular**：https://spoonacular.com/food-api  

## 测试账号

| 姓名 | 邮箱 | 密码 |
|------|------|------|
| 张三 | zhangsan@example.com | 123456 |
| 李四 | lisi@example.com | 123456 |
| 王五 | wangwu@example.com | 123456 |

## 功能清单

- 用户注册 / 登录 / 退出
- 发布、编辑、删除旅行想法（仅作者）
- 列表分页、搜索（目的地 / 标题 / 标签）
- 想法详情页展示天气、酒店、美食（API 或模拟数据）
- 评论（AJAX，255 字限制）
- 响应式布局

## 技术栈

- 后端：Laravel 12、PHP 8.2+
- 前端：jQuery 3.x、Blade、CSS3
- 数据库：SQLite / MySQL
- 外部 API：OpenWeatherMap、Amadeus、Spoonacular
