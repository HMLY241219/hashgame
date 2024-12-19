#!/bin/bash
set -e

# 服务端口
port1=9510
port2=9512
# 服务ID
serverId1=1
serverId2=2

# 检测端口是否可访问
checkPort() {
  nc -zvw3 127.0.0.1 "$1" >/dev/null 2>&1
}

# 启动任务
startTask() {
  ./supervisorctl start "$1"-PUSH_LATEST_BLOCK_"$2":"$1"-PUSH_LATEST_BLOCK_"$2"_00
}

# 重启任务
restartTask() {
  ./supervisorctl restart "$1"-PUSH_LATEST_BLOCK_"$2":"$1"-PUSH_LATEST_BLOCK_"$2"_00
}

# 启动服务
startServer() {
  if checkPort "$1"; then
    # 重启端口
    restartTask "$1" "$2"
    echo "restarted $1"
  else
    # 启动端口
    startTask "$1" "$2"
    echo "started $1"
  fi
}
echo 666
cd /www/server/panel/pyenv/bin/
# 启动服务1
startServer $port1 $serverId1
# 睡眠3秒
sleep 3s
# 启动服务2
startServer $port2 $serverId2

# 修改nginx配置文件端口
#  sed -i "s/:$port2/:$port1/g" $nginxConfSocket
# 重启nginx
#  nginx -s reload
#ps -ef | grep bin/hyperf.php | grep -v grep | cut -c 9-16 | xargs kill -9