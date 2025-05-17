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

namespace BaksDev\Wildberries\Manufacture\Api\Orders;

use BaksDev\Wildberries\Api\Wildberries;
use BaksDev\Wildberries\Manufacture\Schedule\WbNewOrders\RefreshWbOrdersSchedule;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Symfony\Contracts\Cache\ItemInterface;

final class GetWbOrdersRequest extends Wildberries
{
    /*
     * Дата и время последнего изменения по заказу.
     * Дата в формате RFC3339. Можно передать дату или дату со временем. Время можно указывать с точностью до секунд или миллисекунд.
     * Время передаётся в часовом поясе Мск (UTC+3).
     * Примеры:
     *
     * `2019-06-20`
     * `2019-06-20T23:59:59`
     * `2019-06-20T00:00:00.12345`
     * `2017-03-25T00:00:00`
     */
    private DateTimeImmutable|false $dateFrom = false;

    /*
     * Если параметр flag=0 (или не указан в строке запроса), при вызове API возвращаются данные, у которых значение поля lastChangeDate (дата время обновления информации в сервисе) больше или равно переданному значению параметра dateFrom. При этом количество возвращенных строк данных варьируется в интервале от 0 до примерно 100 000.
     * Если параметр flag=1, то будет выгружена информация обо всех заказах или продажах с датой, равной переданному параметру dateFrom (в данном случае время в дате значения не имеет). При этом количество возвращенных строк данных будет равно количеству всех заказов или продаж, сделанных в указанную дату, переданную в параметре dateFrom.
     * Храним в булевом типе, приводим к int при отправке
    */
    private bool $flag = false;

    public function dateFrom(DateTimeImmutable $date): self
    {
        $this->dateFrom = $date;

        return $this;
    }

    /**
     * Метод предоставляет информацию обо всех заказах.
     * Данные обновляются раз в 30 минут.
     *
     * 1 строка = 1 заказ = 1 cборочное задание = 1 единица товара.
     * Для определения заказа рекомендуем использовать поле srid.
     *
     * Информация о заказе хранится 90 дней с момента оформления.
     *
     * Для одного ответа на запрос с flag=0 или без flag в системе установлено условное ограничение 80000 строк. Поэтому, чтобы получить все заказы, может потребоваться более, чем один запрос. Во втором и далее запросе в параметре dateFrom используйте полное значение поля lastChangeDate из последней строки ответа на предыдущий запрос.
     * Если в ответе отдаётся пустой массив [], все заказы уже выгружены.
     *
     * @see https://dev.wildberries.ru/ru/openapi/reports/#tag/Osnovnye-otchyoty/paths/~1api~1v1~1supplier~1orders/get
     * @return Generator<int, WbOrdersRequestDTO>|false
     */
    public function findALl(): Generator|false
    {
        $this->dateFrom ?: $this->dateFrom = new DateTimeImmutable()
            ->setTimezone(new DateTimeZone('GMT'))
            ->sub(DateInterval::createFromDateString(RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD))
            ->sub(DateInterval::createFromDateString('1 minute'));


        $this->dateFrom = match (true)
        {

            str_contains(RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD, 'minute') =>
            $this->dateFrom->setTime((int) $this->dateFrom->format('H'), (int) $this->dateFrom->format('i'), 0),

            str_contains(RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD, 'hour') =>
            $this->dateFrom->setTime((int) $this->dateFrom->format('H'), 0, 0),

            str_contains(RefreshWbOrdersSchedule::ORDER_REFRESH_PERIOD, 'day') =>
            $this->dateFrom->setTime(0, 0, 0),
        };

        while(true)
        {
            $cache = $this->getCacheInit('wildberries-manufacture');
            $key = md5($this->getProfile().$this->dateFrom->format(DateTimeInterface::ATOM).$this->flag.self::class);

            $content = $cache->get($key, function(ItemInterface $item): bool|array {

                $item->expiresAfter(DateInterval::createFromDateString('1 seconds'));

                $response = $this
                    ->statistics()
                    ->TokenHttpClient()
                    ->request(
                        method: 'GET',
                        url: '/api/v1/supplier/orders',
                        options: [
                            "query" => [
                                "dateFrom" => $this->dateFrom->format(DateTimeInterface::ATOM),
                                "flag" => (int) $this->flag,
                            ]
                        ]
                    );

                /** Если попадаем на блокировку - включаем ожидание */
                if($response->getStatusCode() === 429)
                {
                    return true;
                }

                $content = $response->toArray(false);

                if($response->getStatusCode() !== 200)
                {
                    $this->logger->critical(
                        sprintf('wildberries-manufacture: Ошибка получения заказов'),
                        [
                            self::class.':'.__LINE__,
                            $content
                        ]);

                    return false;
                }

                if(empty($content))
                {
                    return false;
                }

                $item->expiresAfter(DateInterval::createFromDateString('1 hours'));

                return $content;
            });

            /** Если попали на блокировку - пробуем повторно через 30 сек... */
            if(true === $content)
            {
                echo 'Пробуем повторно через 60 сек ...'.PHP_EOL;
                sleep(60);
                continue;
            }

            if(false === $content)
            {
                return false;
            }

            /** @var array $product */
            foreach($content as $product)
            {
                yield new WbOrdersRequestDTO($product);
            }

            $this->dateFrom = new DateTimeImmutable(end($content)['lastChangeDate']);
        }
    }
}