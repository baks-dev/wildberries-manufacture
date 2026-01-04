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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\Tests;

use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\AllWbOrdersAnalyticsInterface;
use BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\AllWbOrdersAnalyticsResult;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('wildberries-manufacture')]
#[When(env: 'test')]
final class AllWbOrdersAnalyticsTest extends KernelTestCase
{
    public function testRepository()
    {
        self::assertTrue(true);

        /** @var AllWbOrdersAnalyticsInterface $AllWbOrdersAnalytics */
        $AllWbOrdersAnalytics = self::getContainer()->get(AllWbOrdersAnalyticsInterface::class);
        $paginator = $AllWbOrdersAnalytics
            ->forProfile(new UserProfileUid())
            ->findPaginator(new DeliveryUid(TypeDeliveryFboWildberries::TYPE));

        $data = $paginator->getData();

        if(empty($data))
        {

            return;
        }

        foreach($data as $AllWbOrdersAnalyticsResult)
        {
            // Вызываем все геттеры
            $reflectionClass = new ReflectionClass(AllWbOrdersAnalyticsResult::class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method)
            {
                // Методы без аргументов
                if($method->getNumberOfParameters() === 0)
                {
                    // Вызываем метод
                    $data = $method->invoke($AllWbOrdersAnalyticsResult);
                    // dump($data);
                }
            }

        }
    }
}