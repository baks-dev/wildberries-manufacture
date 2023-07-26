<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Wildberries\Products\Type\Cards\Id\WbCardUid;
use BaksDev\Wildberries\Products\Type\Cards\Id\WbCardUidType;
use BaksDev\Wildberries\Products\Type\Settings\Event\WbProductSettingsEventType;
use BaksDev\Wildberries\Products\Type\Settings\Event\WbProductSettingsEventUid;
use Symfony\Config\DoctrineConfig;

return static function(DoctrineConfig $doctrine): void {

    $doctrine->dbal()
        ->type(WbProductSettingsEventUid::TYPE)
        ->class(WbProductSettingsEventType::class);

    $doctrine->dbal()
        ->type(WbCardUid::TYPE)
        ->class(WbCardUidType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $MODULE = substr(__DIR__, 0, strpos(__DIR__, "Resources"));

    $emDefault->mapping('WildberriesProducts')
        ->type('attribute')
        ->dir($MODULE.'Entity')
        ->isBundle(false)
        ->prefix('BaksDev\Wildberries\Products\Entity')
        ->alias('WildberriesProducts');
};
