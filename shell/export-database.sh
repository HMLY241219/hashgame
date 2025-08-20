#!/bin/bash

DB_NAME="p77data"
DB_USER_NAME="p77data"
DB_PASSWORD="YMtdAJbbw4c4z7Dj"
TMP_IGNORE_TABLES="/tmp/ignore_tables.txt"
IGNORE_TABLES=()

# 生成临时文件保存表名
mysql -N -u ${DB_USER_NAME} -p${DB_PASSWORD} -e \
"SELECT table_name FROM information_schema.tables \
WHERE table_schema='${DB_NAME}' AND table_name REGEXP '_[0-9]{8}$';" > ${TMP_IGNORE_TABLES}

# 获取需要忽略的表名
while read -r TABLE; do
    IGNORE_TABLES+=("--ignore-table=${DB_NAME}.${TABLE}")
done < ${TMP_IGNORE_TABLES}

# 忽略备份表
IGNORE_TABLES+=("--ignore-table=${DB_NAME}.br_withdraw_log_backup")
IGNORE_TABLES+=("--ignore-table=${DB_NAME}.br_order_backup")
IGNORE_TABLES+=("--ignore-table=${DB_NAME}.br_userinfo_backup")
IGNORE_TABLES+=("--ignore-table=${DB_NAME}.br_share_strlog_backup")

# 删除临时文件
rm -f ${TMP_IGNORE_TABLES}

# 导出数据库，排除指定表
mysqldump -u ${DB_USER_NAME} -p${DB_PASSWORD} \
--single-transaction \
--routines \
--triggers \
--events \
--no-data \
${IGNORE_TABLES[@]} \
${DB_NAME} > "/tmp/${DB_NAME}_backup_$(date +%Y%m%d).sql"