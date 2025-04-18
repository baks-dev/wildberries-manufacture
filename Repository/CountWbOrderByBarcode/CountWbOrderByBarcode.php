<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

declare(strict_types=1);

namespace BaksDev\Wildberries\Manufacture\Repository\CountWbOrderByBarcode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;


interface CountWbOrderByBarcodeInterface
{
    /** Метод возвращает количество заказов по штрихкоду */
    public function count(string $barcode): int;
}

final class CountWbOrderByBarcode implements CountWbOrderByBarcodeInterface
{
    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /** Метод возвращает количество заказов по штрихкоду */
    public function count(string $barcode): int
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->select('id');
        $dbal->from(ClasssName::class, 'aliace');

        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllAssociative();
    }
}