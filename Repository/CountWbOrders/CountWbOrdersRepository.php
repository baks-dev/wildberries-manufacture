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

namespace BaksDev\Wildberries\Manufacture\Repository\CountWbOrders;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Wildberries\Manufacture\Entity\WbOrder;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Generator;

final class CountWbOrdersRepository implements CountWbOrdersInterface
{
    /** За какое время запрашивать данные... */
    private const string INTERVAL = '14 days';

    private DateInterval $interval;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        $this->interval = DateInterval::createFromDateString(self::INTERVAL);
    }

    public function interval(string|DateInterval $interval): self
    {
        if(false === ($interval instanceof DateInterval))
        {
            $interval = DateInterval::createFromDateString($interval);
        }

        $this->interval = $interval;

        return $this;
    }

    /**
     * Метод возвращает количество заказов за определенный период
     * @return Generator<int, CountWbOrdersResult>|false
     */
    public function countAll(): Generator|false
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $interval = new DateTimeImmutable()->sub($this->interval);

        $dbal
            ->from(WbOrder::class, 'wb_order')
            ->select('COUNT(*) AS count')
            ->addSelect('wb_order.invariable AS invariable')
            ->where('DATE(date) >= :interval')
            ->setParameter(
                key: 'interval',
                value: $interval,
                type: Types::DATE_IMMUTABLE
            )
            ->groupBy('wb_order.invariable');

        return $dbal->fetchAllHydrate(CountWbOrdersResult::class) ?: false;
    }
}