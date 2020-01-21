# Simple-ezShip
ezShip物流串接

# 環境
- CURL
- php 5.6 以上

# 備註
- 相關資訊請參考 https://www.ezship.com.tw/service_doc/service_home_w18v1.jsp?vDocNo=1702&vDefPage=01
- 目前只有xml才能傳送產品資訊到ezShip，參數版無法

# 操作
<h3>設定參數</h3>

```php
//基本設定
$config = [
    'suId' => '', //ezShip帳號
    'useFormat' => '' //傳送時使用的格式
];
$ezship = new Ezship($config);
```

<h3>傳送資料</h3>

```php
//訂單資訊
$orderData = [
    'number' => '0116144022', //長度最多為10
    'amount' => 2000, //金額
    'name' => '', //收件人姓名
    'email' => '', //收件人email
    'mobile' => '', //收件人聯絡手機
    'address' => '', //收件人地址
    'zipcode' => '', //郵遞區號
    'web_para' => '', 
    'ezship' => [
        'status' => 'A02',
        'type' => '3', // 1:付款 3:不付款
        'stCate' => 'TFM',
        'stCode' => '14482',
    ],
    'goods' => [
        [
            'number' => 'test123',
            'name' => '測試123',
            'spc' => '測試規格',
            'price' => 500,
            'qty' => 4,
        ]
    ]
];
$result = $ezship->send($orderData, $returnUrl);
```

<h3>訂單狀態查詢</h3>

```php
$orderNumber = ''; //ezShip回傳的編號
$webPara = ''; //回傳驗證
$returnUrl = ''; //回傳位置
$result = $ezship->getStatus($orderNumber, $webPara, $returnUrl);
```

<h3>電子地圖</h3>

```php
$returnUrl = ''; //回傳位置
$webPara = ''; //驗證判斷用
$ezship->getMap($returnUrl, $webPara);
```
