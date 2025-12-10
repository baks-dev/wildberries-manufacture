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

namespace BaksDev\Wildberries\Manufacture\Messenger\UpdateWbStocks;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\CurrentProductByBarcodeResult;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByBarcodeInterface;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use BaksDev\Wildberries\Manufacture\UseCase\WbStocks\New\WbStockNewDTO;
use BaksDev\Wildberries\Manufacture\UseCase\WbStocks\New\WbStockNewHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обновляем в базе данные информацию о запасах товара на складах WB
 */
#[AsMessageHandler]
final readonly class UpdateWbStocksDispatcher
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private ProductConstByBarcodeInterface $ProductConstByBarcodeRepository,
        private WbStockNewHandler $WbStockNewHandler,
        private DeduplicatorInterface $deduplicator
    ) {}

    public function __invoke(UpdateWbStocksMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('wildberries-manufacture')
            ->expiresAfter('1 hour')
            ->deduplication([$message->getBarcode(), self::class]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $Deduplicator->save();

        $CurrentProductDTO = $this->ProductConstByBarcodeRepository
            ->find($message->getBarcode());

        if(false === ($CurrentProductDTO instanceof CurrentProductByBarcodeResult))
        {
            return;
        }

        $invariable = $CurrentProductDTO->getInvariable();
        $quantity = $message->getQuantity();
        $barcode = $message->getBarcode();

        $dto = new WbStockNewDTO()
            ->setInvariable($invariable)
            ->setQuantity($quantity);

        $wbStock = $this->WbStockNewHandler->handle($dto);

        if($wbStock instanceof WbStock)
        {
            $this->logger->info(sprintf(
                '%s: Обновили остаток товара FBO => %s',
                $barcode,
                $quantity),
            );

            return;
        }

        $this->logger->critical(sprintf('%s: Ошибка обновления остатка товара', $wbStock), [$barcode]);
    }
}