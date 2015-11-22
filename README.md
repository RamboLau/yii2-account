yii2 account extension
======================
yii2账户体系，主要牵扯到账户金额交易，包含账户之间的转账和支付等

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist lubaogui/yii2-account "*"
```

or add

```
"lubaogui/yii2-account": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
<?= \lubaogui\account\AutoloadExample::widget(); ?>```