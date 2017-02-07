# Project
Oscommerce to Prestashop order migration script.
## Getting Started
A PHP script to migrate osCommerce orders, requires two DB connections, reads orders from _Oscommerce DB (Source)_ and writes to _Prestashop DB (Target)_, 
**Note:** it is _RECOMMENDED_ to _PRE-import_ customers and other required Items via Prestashop's inbuilt import features. Various PrestaShop's inbuilt import features can be used (customer, address-book and ZONE based settings _(match osCommerce Zone settings)_)

### Requirements:
```
* PHP 5 or above
* MYSQL 
* COMMAND LINE INTERFACE (if possible)
```
It is RECOMMENDED to first backup your database, before proceed.
### How to run
```
cd <installation folder>
```
e.g. 
`$ cd osc2presta-orders/`

```
$ php orders_new.php
```
You can also use a browser to run, however, it is recommended to use CLI.
```
## License
This project is licensed under the [MIT License](https://opensource.org/licenses/MIT)
