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

namespace BaksDev\Wildberries\Manufacture\Messenger\Schedules\GetWbAverageOrders;

use BaksDev\Core\Deduplicator\Deduplicator;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Wildberries\Manufacture\Messenger\UpdateWbAverageOrders\UpdateWbAverageOrdersMessage;
use BaksDev\Wildberries\Manufacture\Repository\CountWbOrders\CountWbOrdersRepository;
use BaksDev\Wildberries\Manufacture\Repository\CountWbOrders\CountWbOrdersResult;
use BaksDev\Wildberries\Manufacture\Repository\DeleteAllOrders\DeleteAllOrdersInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWbAverageOrdersDispatcher
{
    /** За какой период высчитываем среднее кол-во заказов? */
    const string INTERVAL = "14 days";

    public function __construct(
        private CountWbOrdersRepository $countWbOrdersRepository,
        private Deduplicator $deduplicator,
        private MessageDispatchInterface $messageDispatch,
        private DeleteAllOrdersInterface $deleteAllOrdersRepository,
    ) {}

    /**
     * Группируем данные о последних заказах по каждому товару, считаем количество за последний период,
     * обновляем таблицу со средним количеством запасов в день
    */
    public function __invoke(GetWbAverageOrdersMessage $message): void
    {
        $WbOrdersCount = $this->countWbOrdersRepository
            ->interval(self::INTERVAL)
            ->countAll();

        if(false === $WbOrdersCount || $WbOrdersCount->valid() === false)
        {
            return;
        }

        /** @var CountWbOrdersResult $product */
        foreach($WbOrdersCount as $product)
        {
            $Deduplicator = $this->deduplicator
                ->namespace('wildberries-manufacture')
                ->expiresAfter('1 hour')
                ->deduplication([$product->getInvariable(), $product->getCount(), self::class]);

            if($Deduplicator->isExecuted())
            {
                continue;
            }

            $this->messageDispatch->dispatch(
                message: new UpdateWbAverageOrdersMessage(
                    invariable: $product->getInvariable(),
                    count: $product->getCount())
            );

            $Deduplicator->save();
        }

        $this->deleteAllOrdersRepository
            ->interval(self::INTERVAL)
            ->delete();
    }
}