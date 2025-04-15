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

namespace BaksDev\Wildberries\Manufacture\Messenger\Schedules\ResetWbFbsStocks;

use BaksDev\Wildberries\Manufacture\Api\Fbs\PutWbFbsStocksRequest;
use BaksDev\Wildberries\Manufacture\Api\Warehouses\GetWbFbsWarehousesRequest;
use BaksDev\Wildberries\Manufacture\Repository\AllWbStocksBarcodes\AllWbStocksBarcodesInterface;
use BaksDev\Wildberries\Manufacture\Repository\AllWbStocksBarcodes\AllWbStocksBarcodesResult;
use Random\Randomizer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResetWbFbsStocksDispatcher
{
    /** Минимальное количество на складе, при котором обнулять остаток FBS */
    private const int REDUCTION = 5;

    public function __construct(
        private AllWbStocksBarcodesInterface $allWbStocksBarcodes,
        private PutWbFbsStocksRequest $putWbFbsStocksRequest,
        private GetWbFbsWarehousesRequest $getWbFbsWarehousesRequest,
    ) {}

    public function __invoke(ResetWbFbsStocksMessage $message): void
    {
        $profile = $message->getProfile();

        $barcodes = $this->allWbStocksBarcodes
            ->forProfile($profile)
            ->findAll();

        $warehouses = $this->getWbFbsWarehousesRequest
            ->profile($profile)
            ->findAll();

        $data = [];

        /**  @var AllWbStocksBarcodesResult $result */
        foreach($barcodes as $key => $result)
        {
            $data[] = [
                "sku" => $result->getBarcode(),
                "amount" => $result->getQuantity() > self::REDUCTION ? 0 : new Randomizer()->getInt(1000, 2000),
            ];

            /**
             * Т.к. 0 считается кратным любому числу, если мы начнем отсчет с этого числа, в первый раз условие
             * выполнится при наличии только одного элемента в массиве. Поэтому мы начнем отсчет ключей с числа 1.
             */
            if(($key + 1) % 1000 === 0)
            {
                foreach($warehouses as $warehouse)
                {
                    $this->putWbFbsStocksRequest
                        ->profile($profile)
                        ->warehouse($warehouse["officeId"])
                        ->update($data);
                }

                $data = [];
            }
        }

        /**
         * Обновляет оставшиеся склады
         */
        if(count($data) > 0)
        {
            foreach($warehouses as $warehouse)
            {
                $this->putWbFbsStocksRequest
                    ->profile($profile)
                    ->warehouse($warehouse["officeId"])
                    ->update($data);
            }
        }
    }
}