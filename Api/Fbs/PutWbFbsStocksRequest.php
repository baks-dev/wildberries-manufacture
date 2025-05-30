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

namespace BaksDev\Wildberries\Manufacture\Api\Fbs;

use BaksDev\Wildberries\Api\Wildberries;

final class PutWbFbsStocksRequest extends Wildberries
{
    /**
     * ID склада продавца
     */
    private int|false $warehouse = false;

    public function warehouse(int $warehouse): self
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    /**
     * Метод обновляет количество остатков товаров продавца.
     *
     * @see https://dev.wildberries.ru/openapi/work-with-products/#tag/Ostatki-na-skladah-prodavca/paths/~1api~1v3~1stocks~1{warehouseId}/put
     */
    public function update(array $stocks): bool
    {
        /* TODO: временно заблокировано !!! */
        return true;

        /** Проверка, чтобы в тестовом окружении не изменялись данные */
        if(false === $this->isExecuteEnvironment())
        {
            return false;
        }

        $response = $this
            ->marketplace()
            ->TokenHttpClient()
            ->request(
                method: 'PUT',
                url: '/api/v3/stocks/'.$this->warehouse,
                options: [
                    "json" => [
                        "stocks" => $stocks,
                    ]
                ]
            );

        if($response->getStatusCode() !== 204)
        {
            $content = $response->toArray(false);

            $this->logger->critical(
                sprintf('wildberries-manufacture: Ошибка обновления остатков FBS'),
                [
                    self::class.':'.__LINE__,
                    $content
                ]);

            return false;
        }

        return true;
    }
}