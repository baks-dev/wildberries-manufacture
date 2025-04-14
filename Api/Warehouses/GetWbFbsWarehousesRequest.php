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

namespace BaksDev\Wildberries\Manufacture\Api\Warehouses;

use BaksDev\Wildberries\Api\Wildberries;

final class GetWbFbsWarehousesRequest extends Wildberries
{

    /**
     * Метод предоставляет список всех складов продавца.
     *
     * @see https://dev.wildberries.ru/openapi/work-with-products/#tag/Sklady-prodavca/paths/~1api~1v3~1warehouses/get
     */
    public function findAll(): array|false
    {
        $response = $this
            ->marketplace()
            ->TokenHttpClient()
            ->request(
                method: 'GET',
                url: '/api/v3/warehouses',
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('wildberries-manufacture: Ошибка обновления остатков FBS'),
                [
                    self::class.':'.__LINE__,
                    $content
                ]);

            return false;
        }

        return $content;
    }
}