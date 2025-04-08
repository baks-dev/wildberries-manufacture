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

namespace BaksDev\Wildberries\Manufacture\Api\Stocks;

use BaksDev\Wildberries\Api\Wildberries;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Generator;
use Symfony\Contracts\Cache\ItemInterface;
use DateInterval;

#[Autoconfigure(public: true)]
final class GetWbStocksRequest extends Wildberries
{
    /**
     * Дата и время последнего изменения по товару.
     * Для получения полного остатка следует указывать максимально раннее значение.
     * Например, 2019-06-20
     * Дата в формате RFC3339. Можно передать дату или дату со временем. Время можно указывать с точностью до секунд или миллисекунд.
     * Время передаётся в часовом поясе Мск (UTC+3).
     * Примеры:
     *
     * `2019-06-20`
     * `2019-06-20T23:59:59`
     * `2019-06-20T00:00:00.12345`
     * `2017-03-25T00:00:00`
     */
    private string|false $dateFrom = false;

    public function dateFrom(string $date): self
    {
        $this->dateFrom = $date;

        return $this;
    }

    /**
     * Метод предоставляет количество остатков товаров на складах WB.
     * Данные обновляются раз в 30 минут.
     *
     * Для одного ответа в системе установлено условное ограничение 60000 строк. Поэтому, чтобы получить все остатки, может потребоваться более, чем один запрос. Во втором и далее запросе в параметре dateFrom используйте полное значение поля lastChangeDate из последней строки ответа на предыдущий запрос.
     * Если в ответе отдаётся пустой массив [], все остатки уже выгружены.
     *
     * Максимум 1 запрос в минуту на один аккаунт продавца
     */
    public function findAll(): Generator|false
    {
        $dateFrom = $this->dateFrom;

        while(true)
        {
            $cache = $this->getCacheInit('wildberries-manufacture');
            $key = md5(self::class.$this->getProfile().$this->dateFrom);

            $content = $cache->get($key, function(ItemInterface $item) use ($dateFrom) {
                $item->expiresAfter(DateInterval::createFromDateString('1 seconds'));

                $response = $this
                    ->statistics()
                    ->TokenHttpClient()
                    ->request(
                        method: 'GET',
                        url: '/api/v1/supplier/stocks',
                        options: [
                            "query" => [
                                "dateFrom" => $dateFrom,
                            ]
                        ]
                    );

                $content = $response->toArray(false);

                if($response->getStatusCode() !== 200)
                {
                    $this->logger->critical(
                        sprintf('wildberries-manufacture: Ошибка получения данных о запасах'),
                        [
                            self::class.':'.__LINE__,
                            $content
                        ]);
                    return false;
                }

                $item->expiresAfter(DateInterval::createFromDateString('1 hours'));

                return $content;
            });

            if(!$content)
            {
                return false;
            }

            /** @var array $product */
            foreach($content as $product)
            {
                yield new WbStocksRequestDTO($product);
            }

            $dateFrom = end($content)['lastChangeDate'];
        }
    }

}