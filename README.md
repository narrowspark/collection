# collection

[![Author](http://img.shields.io/badge/author-@anolilab-blue.svg?style=flat-square)](https://twitter.com/anolilab)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/narrowspark/collection.svg?style=flat-square)](https://packagist.org/packages/narrowspark/collection)
[![Total Downloads](https://img.shields.io/packagist/dt/narrowspark/collection.svg?style=flat-square)](https://packagist.org/packages/narrowspark/collection)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

## Master

[![Build Status](https://img.shields.io/travis/narrowspark/collection/master.svg?style=flat-square)](https://travis-ci.org/narrowspark/collection)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/narrowspark/collection.svg?style=flat-square)](https://scrutinizer-ci.com/g/narrowspark/collection/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/narrowspark/collection.svg?style=flat-square)](https://scrutinizer-ci.com/g/narrowspark/collection)

## Develop

[![Build Status](https://img.shields.io/travis/narrowspark/collection/master.svg?style=flat-square)](https://travis-ci.org/narrowspark/collection)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/narrowspark/collection.svg?style=flat-square)](https://scrutinizer-ci.com/g/narrowspark/collection/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/narrowspark/collection.svg?style=flat-square)](https://scrutinizer-ci.com/g/narrowspark/collection)

## Install

Via Composer

``` bash
$ composer require narrowspark/collection
```

## Usage

``` php
Use the static helper method "from" to create the collection.

use Narrowspark\Collection\Collection;

$c = Collection::from(['foo', 'bar']);

$c->all(); // ['foo', 'bar']
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

From the project directory, tests can be ran using phpunit

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Daniel Bannert](https://github.com/prisis)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
