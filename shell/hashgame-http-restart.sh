#!/bin/bash
set -e

# 服务端口
port1=9506
port2=9508
# 服务ID
serverId1=1
serverId2=2
# nginx配置文件路径
nginxConfHttp=/www/server/panel/vhost/nginx/hashgamehttp.9507.conf

# 检测端口是否可访问
checkPort() {
  nc -zvw3 127.0.0.1 "$1" >/dev/null 2>&1
}

# 启动端口
startPort() {
  ./supervisorctl start "$1"-HASH_GAME_HTTP_"$2":"$1"-HASH_GAME_HTTP_"$2"_00
}

# 重启端口
restartPort() {
  ./supervisorctl restart "$1"-HASH_GAME_HTTP_"$2":"$1"-HASH_GAME_HTTP_"$2"_00
}

# 停止端口
stopPort() {
  ./supervisorctl stop "$1"-HASH_GAME_HTTP_"$2":"$1"-HASH_GAME_HTTP_"$2"_00
}

# 启动服务
startServer() {
  if checkPort $port1; then
    # 启动端口2服务
    if checkPort $port2; then
      restartPort $port2 $serverId2
    else
      startPort $port2 $serverId2
    fi

    # 修改nginx配置文件可用端口为端口2
    sed -i "127.0.0.1/s/:$port1/:$port2/g" $nginxConfHttp
    # 重启nginx
    nginx -s reload

    # 关闭端口1服务
    stopPort $port1 $serverId1
    echo "started $port2"
  else
    # 启动端口1服务
    startPort $port1 $serverId1

    # 修改nginx配置文件可用端口为端口1
    sed -i "127.0.0.1/s/:$port2/:$port1/g" $nginxConfHttp
    # 重启nginx
    nginx -s reload

    # 关闭端口2服务
    stopPort $port2 $serverId2
    echo "started $port1"
  fi
}

cd /www/server/panel/pyenv/bin/
# 启动服务
startServer

# 修改nginx配置文件端口
#  sed -i "s/:$port2/:$port1/g" $nginxConfSocket
# 重启nginx
#  nginx -s reload
#ps -ef | grep bin/hyperf.php | grep -v grep | cut -c 9-16 | xargs kill -9