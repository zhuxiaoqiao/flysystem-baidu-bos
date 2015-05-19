# flysystem-baidu-bos
Flysystem adapter for Baidu Bos SDK v0.8.1

[![Author](http://img.shields.io/badge/author-@zhuxiaoqiao-blue.svg?style=flat-square)](Author)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/zhuxiaoqiao/flysystem-baidu-bos.svg?style=flat-square)](https://packagist.org/packages/zhuxiaoqiao/flysystem-baidu-bos)
[![Total Downloads](https://img.shields.io/packagist/dt/zhuxiaoqiao/flysystem-baidu-bos.svg?style=flat-square)](https://packagist.org/packages/zhuxiaoqiao/flysystem-baidu-bos)


## Installation

```bash
composer require "zhuxiaoqiao/flysystem-baidu-bos": "1.0.*@dev"
```

## Usage

```php
use Zhuxiaoqiao\Flysystem\BaiduBos\BaiduBosAdapter;
use BaiduBce\Services\Bos\BosClient;
use League\Flysystem\Filesystem;

$BOS_TEST_CONFIG = array(
    'credentials' => array(
      'ak' => 'You AK',
      'sk' => 'You SK',
    ),
    'endpoint' => 'http://bj.bcebos.com',
);
$client = new BosClient($BOS_TEST_CONFIG);

$adapter = new BaiduBosAdapter($client, 'bucket-name');

$filesystem = new Filesystem($adapter);
```
