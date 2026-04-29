# Project: oasis/doctrine-addon

Doctrine ORM 扩展组件，提供自动 ID trait 和级联删除缓存失效机制。

---

## 技术栈

- 语言：PHP ^8.4
- 框架/依赖：Doctrine ORM ^2.20, oasis/logging ^3.0
- 测试：PHPUnit ^13.0, giorgiosironi/eris ^1.0（PBT）
- 包管理：Composer

---

## 命名空间

- 源码：`Oasis\Mlib\Doctrine\` → `src/`
- 测试环境：`Oasis\Mlib\Doctrine\Ut\` → `ut/`
- 测试 Entity：`Oasis\Mlib\Doctrine\Ut\Entity\` → `ut/Entity/`
- 测试用例：`Oasis\Mlib\Doctrine\Ut\Test\` → `ut/Test/`

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 运行全量测试（零外部依赖，使用 SQLite in-memory）
vendor/bin/phpunit

# 运行测试并生成覆盖率报告（需要 pcov 扩展）
vendor/bin/phpunit --coverage-text --whitelist=src/
```

---

## 版本号位置

- `composer.json` → `version` 字段（当前未显式声明，由 Packagist 从 git tag 推断）

---

## 入口

- 库组件，无独立运行入口
- 通过 `composer require oasis/doctrine-addon` 安装后在项目中使用
