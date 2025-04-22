<?php
// Cq9Slots

return [
    //这里因為你們如果沒有設定錢包秘鑰那就是會預設跟代理秘鑰相同
    'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2NjEzNGVlYTc2ZWViMzc2ZDNmODBhYjUiLCJhY2NvdW50IjoiNzc3Yl9zdyIsIm93bmVyIjoiNjYxMzRlZWE3NmVlYjM3NmQzZjgwYWI1IiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiQlJMIiwiYnJhbmQiOiJjcTkiLCJqdGkiOiI3NzYxOTI5MCIsImlhdCI6MTcxMjU0MTQxOCwiaXNzIjoiQ3lwcmVzcyIsInN1YiI6IlNTVG9rZW4ifQ.fXldi-42lYeIwggyBvHrTfoW_UW3OGpEhxpQeS28FEo',//测试
//    'token' => '',//正式

    //效验的token
    'wtoken' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2NjEzNGVlYTc2ZWViMzc2ZDNmODBhYjUiLCJhY2NvdW50IjoiNzc3Yl9zdyIsIm93bmVyIjoiNjYxMzRlZWE3NmVlYjM3NmQzZjgwYWI1IiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiQlJMIiwiYnJhbmQiOiJjcTkiLCJqdGkiOiI3NzYxOTI5MCIsImlhdCI6MTcxMjU0MTQxOCwiaXNzIjoiQ3lwcmVzcyIsInN1YiI6IlNTVG9rZW4ifQ.fXldi-42lYeIwggyBvHrTfoW_UW3OGpEhxpQeS28FEo',//测试
//    'wtoken' => '',//正式


    // 币种
    'currency'         => 'BRL',


    'language'         => 'en',  //英语
//    'language'         => 'pt-BR',  //葡萄牙语

    //请求头
//    'herder' => ["Content-Type: application/x-www-form-urlencoded","Authorization: %s"],


    // 接口请求地址 TODO::正式环境需要更换地址
    'api_url' => 'https://api.cqgame.games', //测试
//    'api_url' => '',
];






