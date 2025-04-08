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

namespace BaksDev\Wildberries\Manufacture\Messenger\Schedules\GetWbOrders;

use BaksDev\Core\Deduplicator\Deduplicator;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Wildberries\Manufacture\Api\Orders\GetWbOrdersRequest;
use BaksDev\Wildberries\Manufacture\Messenger\UpdateWbOrders\UpdateWbOrdersMessage;
use BaksDev\Wildberries\Manufacture\Schedule\WbNewOrders\RefreshWbOrdersSchedule;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use DateTimeZone;
use DateTimeImmutable;

/**
 * Получаем данные о последних заказах WB и отправляем сообщения для дальнейшего сохранения в базу
 */
#[AsMessageHandler]
final readonly class GetWbOrdersDispatcher
{
    public function __construct(
        private GetWbOrdersRequest $request,
        private MessageDispatchInterface $messageDispatch,
        private Deduplicator $deduplicator,
    ){}

    public function __invoke(GetWbOrdersMessage $message): void
    {
        $profile = $message->getProfile();

        $timezone = new DateTimeZone(date_default_timezone_get());
        $dateFrom = new DateTimeImmutable()->modify("-".RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD)->setTimezone($timezone)->format('Y-m-d\TH:i:sP');

        $responses = $this->request->profile($profile)->dateFrom($dateFrom)->findAll();

        foreach($responses as $response)
        {
            $Deduplicator = $this->deduplicator
                ->namespace('wildberries-manufacture')
                ->deduplication([$response->getId(), self::class]);

            if($Deduplicator->isExecuted())
            {
                continue;
            }

            $this->messageDispatch->dispatch(
                message: new UpdateWbOrdersMessage($response)
            );
        }
    }
}
