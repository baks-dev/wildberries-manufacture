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

namespace BaksDev\Wildberries\Manufacture\Messenger\Schedules\GetWbStocks;

use BaksDev\Core\Deduplicator\Deduplicator;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Wildberries\Manufacture\Api\Stocks\GetWbStocksRequest;
use BaksDev\Wildberries\Manufacture\Messenger\UpdateWbStocks\UpdateWbStocksMessage;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получаем данные о запасах на складах WB и отправляем сообщения для дальнейшего сохранения в базу
 */
#[AsMessageHandler]
final readonly class GetWbStocksDispatcher
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private GetWbStocksRequest $request,
        private MessageDispatchInterface $messageDispatch,
        private Deduplicator $deduplicator,
    ) {}

    public function __invoke(GetWbStocksMessage $message): void
    {
        $profile = $message->getProfile();

        $dateFrom = new DateTimeImmutable()
            ->setTimezone(new DateTimeZone('GMT'))
            ->sub(DateInterval::createFromDateString('30 days'))
            ->sub(DateInterval::createFromDateString('1 minute'));

        $responses = $this->request
            ->profile($profile)
            ->dateFrom($dateFrom)
            ->findAll();


        if(false === $responses || $responses->valid() === false)
        {
            return;
        }

        $barcodes = [];

        foreach($responses as $response)
        {
            if(!isset($barcodes[$response->getBarcode()]))
            {
                $barcodes[$response->getBarcode()] = 0;
            }

            if($response->getQuantity() === 0)
            {
                continue;
            }

            $quantity = $barcodes[$response->getBarcode()];
            $barcodes[$response->getBarcode()] = $quantity + $response->getQuantity();
        }


        foreach($barcodes as $barcode => $quantity)
        {
            /** Обновляем остаток товара */
            echo $info = sprintf('%s: Обновляем остаток товара FBO => %s', $barcode, $quantity).PHP_EOL;
            $this->logger->info($info);

            $UpdateWbStocksMessage = new UpdateWbStocksMessage(
                profile: $profile,
                barcode: (string) $barcode,
                quantity: $quantity
            );

            /** @see UpdateWbStocksDispatcher */
            $this->messageDispatch->dispatch(
                message: $UpdateWbStocksMessage,
                transport: 'wildberries-manufacture-low'
            );
        }
    }
}