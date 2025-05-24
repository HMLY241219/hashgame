#!/bin/bash

DB_NAME="your_database_name"
DB_USER_NAME="root"
DB_PASSWORD="123"
IGNORE_TABLES=()

# 获取需要忽略的表名
while read -r TABLE; do
    IGNORE_TABLES+=("--ignore-table=${DB_NAME}.${TABLE}")
done < <(mysql -N -u ${DB_USER_NAME} -p'${DB_PASSWORD}' -e \
"SELECT table_name FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name REGEXP '_\\\\d{8}\$';")

# 导出数据库，排除指定表
mysqldump -u ${DB_USER_NAME} -p'${DB_PASSWORD}' \
--single-transaction \
--routines \
--triggers \
--events \
${IGNORE_TABLES[@]} \
${DB_NAME} > "${DB_NAME}_backup_$(date +%Y%m%d).sql"