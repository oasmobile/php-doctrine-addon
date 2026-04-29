#!/usr/bin/env bash
# Release 3.0 — Task 5: 手工测试与集成验证
# 自动化测试脚本，覆盖 sub-task 5.2 / 5.3 / 5.4
set -euo pipefail

PASS=0
FAIL=0
RESULTS=()

report() {
    local label="$1" status="$2"
    if [ "$status" = "PASS" ]; then
        PASS=$((PASS + 1))
        RESULTS+=("✅ PASS: $label")
    else
        FAIL=$((FAIL + 1))
        RESULTS+=("❌ FAIL: $label")
    fi
}

echo "========================================"
echo " Task 5.3 — Composer 配置验证"
echo "========================================"

echo "--- 5.3a: composer validate ---"
if composer validate --no-check-publish 2>&1; then
    report "composer validate" "PASS"
else
    report "composer validate" "FAIL"
fi

echo ""
echo "--- 5.3b: composer install --dry-run ---"
if composer install --dry-run 2>&1; then
    report "composer install --dry-run" "PASS"
else
    report "composer install --dry-run" "FAIL"
fi

echo ""
echo "========================================"
echo " Task 5.4 — Annotation 残留扫描"
echo "========================================"

echo "--- 5.4: grep -r '@ORM\\' src/ ut/Entity/ ---"
if grep -r '@ORM\\' src/ ut/Entity/ 2>&1; then
    report "Annotation 残留扫描 (应无结果)" "FAIL"
else
    report "Annotation 残留扫描 (无残留)" "PASS"
fi

echo ""
echo "========================================"
echo " Task 5.2 — 全量测试验证"
echo "========================================"

echo "--- 5.2: vendor/bin/phpunit ---"
PHPUNIT_OUTPUT=$(vendor/bin/phpunit 2>&1) || true
echo "$PHPUNIT_OUTPUT"

# 检查 exit code（通过 grep 判断 OK 行）
if echo "$PHPUNIT_OUTPUT" | grep -qE '^OK \('; then
    report "PHPUnit 全量测试通过" "PASS"
else
    report "PHPUnit 全量测试通过" "FAIL"
fi

# 检查 deprecation warning
if echo "$PHPUNIT_OUTPUT" | grep -qi 'deprecat'; then
    report "零 deprecation warning" "FAIL"
else
    report "零 deprecation warning" "PASS"
fi

echo ""
echo "========================================"
echo " 汇总"
echo "========================================"
for r in "${RESULTS[@]}"; do
    echo "  $r"
done
echo ""
echo "PASS: $PASS / FAIL: $FAIL / TOTAL: $((PASS + FAIL))"

if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
exit 0
