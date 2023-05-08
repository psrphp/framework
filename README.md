# framework

PHP应用开发框架

## 安装

``` bash
composer require psrphp/framework
```

然后，需要加上：

``` json
{
    "scripts": {
        "post-package-install": "PsrPHP\\Framework\\Script::onInstall",
        "post-package-update": "PsrPHP\\Framework\\Script::onUpdate",
        "pre-package-uninstall": "PsrPHP\\Framework\\Script::onUnInstall"
    }
}
```

## 用例

``` php
\PsrPHP\Framework\Framework::run();
```
