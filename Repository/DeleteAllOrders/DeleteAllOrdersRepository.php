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

namespace BaksDev\Wildberries\Manufacture\Repository\DeleteAllOrders;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;

final class DeleteAllOrdersRepository implements DeleteAllOrdersInterface
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
     * Метод очищает устаревшие данные (по умолчанию старше 14 дней)
     * @see self::INTERVAL
     */
    public function delete(): int
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $interval = new DateTimeImmutable()->sub($this->interval);

        $dbal
            ->delete('wb_order')
            ->where('DATE(date) < :interval')
            ->setParameter(
                key: 'interval',
                value: $interval,
                type: Types::DATE_IMMUTABLE
            );

        return $dbal->executeStatement();
    }
}