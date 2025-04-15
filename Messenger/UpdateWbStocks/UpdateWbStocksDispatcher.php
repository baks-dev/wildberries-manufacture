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

use BaksDev\Products\Product\Repository\CurrentProductByArticle\CurrentProductDTO;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByBarcodeInterface;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use BaksDev\Wildberries\Manufacture\Repository\StocksDataUpdate\WbStocksDataUpdateInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Обновляем в базе данные о запасах товара на складах WB
 */
#[AsMessageHandler]
final readonly class UpdateWbStocksDispatcher
{
    public function __construct(
        private ProductConstByBarcodeInterface $ProductConstByBarcodeRepository,
        private WbStocksDataUpdateInterface $WbStocksDataUpdateRepository,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(UpdateWbStocksMessage $message): void
    {
        $barcode = $message->getBarcode();

        $product = $this->ProductConstByBarcodeRepository
            ->find($barcode);

        if(false === ($product instanceof CurrentProductDTO))
        {
            return;
        }

        $invariable = $product->getInvariable();

        $WbStock = $this->WbStocksDataUpdateRepository
            ->forInvariable($invariable)
            ->find();

        /** @var WbStock $WbStock */
        if(false === ($WbStock instanceof WbStock))
        {
            $WbStock = new WbStock()->setInvariable($invariable);
        }

        $quantity = $message->getQuantity();
        $WbStock->setQuantity($quantity);

        $errors = $this->validator->validate($WbStock);

        if(count($errors) > 0)
        {
            return;
        }

        $this->entityManager->persist($WbStock);
        $this->entityManager->flush();
    }
}