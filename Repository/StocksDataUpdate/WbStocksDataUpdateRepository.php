<?php
/*
 * Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Wildberries\Manufacture\Repository\StocksDataUpdate;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use InvalidArgumentException;

final  class WbStocksDataUpdateRepository implements WbStocksDataUpdateInterface
{
    private ProductInvariableUid|false $invariable = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forInvariable(ProductInvariableUid|ProductInvariable|string $invariable): self
    {
        if(empty($invariable))
        {
            $this->invariable = false;
            return $this;
        }

        if(is_string($invariable))
        {
            $invariable = new ProductInvariableUid($invariable);
        }

        if($invariable instanceof ProductInvariable)
        {
            $invariable = $invariable->getId();
        }

        $this->invariable = $invariable;

        return $this;
    }

    public function find(): WbStock|false
    {
        if(false === ($this->invariable instanceof ProductInvariableUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductInvariable');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('wb_stock')
            ->from(WbStock::class, 'wb_stock')
            ->where('wb_stock.invariable = :invariable')
            ->setParameter(
                'invariable',
                $this->invariable,
                ProductInvariableUid::TYPE
            );

        return $orm->getOneOrNullResult() ?: false;

    }
}