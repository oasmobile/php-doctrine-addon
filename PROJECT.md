# Project: oasis/doctrine-addon

Doctrine ORM 扩展组件，提供自动 ID trait 和级联删除缓存失效机制。

---

## 技术栈

- 语言：PHP
- 框架/依赖：Doctrine ORM ^2.5, oasis/logging ^1.1
- 测试：PHPUnit ^5.3
- 包管理：Composer

---

## 命名空间

- 源码：`Oasis\Mlib\Doctrine\` → `src/`
- 测试：`Oasis\Mlib\Doctrine\Ut\` → `ut/`

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 运行全量测试（需要 MySQL + Memcached）
vendor/bin/phpunit
```

---

## 测试环境依赖

- MySQL：`localhost`，数据库 `doctrine_addon`，用户 `doctrine_addon`，密码见 `ut/TestEnv.php`
- Memcached：`localhost:11211`

---

## 版本号位置

- `composer.json` → `version` 字段（当前未显式声明，由 Packagist 从 git tag 推断）

---

## 敏感文件

- `ut/TestEnv.php`：包含数据库连接凭据（仅用于本地测试）

---

## 入口

- 库组件，无独立运行入口
- 通过 `composer require oasis/doctrine-addon` 安装后在项目中使用
