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

namespace BaksDev\Wildberries\Manufacture\Api\Stocks\Tests;

use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Api\Stocks\GetWbStocksRequest;
use BaksDev\Wildberries\Manufacture\Api\Stocks\WbStocksRequestDTO;
use BaksDev\Wildberries\Type\Authorization\WbAuthorizationToken;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('wildberries-manufacture')]
#[When(env: 'test')]
final class GetWbStocksRequestTest extends KernelTestCase
{
    private static WbAuthorizationToken $Authorization;

    public static function setUpBeforeClass(): void
    {
        /** @see .env.test */
        self::$Authorization = new WbAuthorizationToken(
            profile: new UserProfileUid($_SERVER['TEST_WILDBERRIES_PROFILE']),
            token: $_SERVER['TEST_WILDBERRIES_TOKEN'],
            warehouse: $_SERVER['TEST_WILDBERRIES_WAREHOUSE'] ?? null,
            percent: $_SERVER['TEST_WILDBERRIES_PERCENT'] ?? "0",
            card: $_SERVER['TEST_WILDBERRIES_CARD'] === "true" ?? false,
            stock: $_SERVER['TEST_WILDBERRIES_STOCK'] === "true" ?? false,
        );
    }

    public function testRequest(): void
    {
        /** @var GetWbStocksRequest $request */
        $request = self::getContainer()->get(GetWbStocksRequest::class);
        $request->TokenHttpClient(self::$Authorization);

        $dateFrom = new DateTimeImmutable()
            ->setTimezone(new DateTimeZone('GMT'))
            ->sub(DateInterval::createFromDateString('1 week'));

        $result = $request
            ->dateFrom($dateFrom)
            ->findAll();

        foreach($result as $WbStocksRequestDTO)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(WbStocksRequestDTO::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($WbStocksRequestDTO);
                    //dump($data);
                }
            }

        }

        self::assertNotFalse($result);
    }
}