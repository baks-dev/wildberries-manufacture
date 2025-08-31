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
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('wildberries-manufacture')]
#[When(env: 'test')]
final class AllWbOrdersAnalyticsTest extends KernelTestCase
{
    public function testRepository()
    {
        /** @var AllWbOrdersAnalyticsInterface $AllWbOrdersAnaltics */
        $AllWbOrdersAnaltics = self::getContainer()->get(AllWbOrdersAnalyticsInterface::class);
        $paginator = $AllWbOrdersAnaltics
            ->forProfile(new UserProfileUid())
            ->findPaginator(new DeliveryUid(TypeDeliveryFboWildberries::TYPE));

        $data = $paginator->getData();

        if(empty($data))
        {
            self::assertTrue(true);
            return;
        }

        foreach($data as $item)
        {
            self::assertInstanceOf(ProductInvariableUid::class, $item->getInvariable());
            self::assertIsInt($item->getAverage());
            self::assertIsInt($item->getNeededAmount());
            self::assertIsInt($item->getQuantity());
            self::assertIsInt($item->recommended());
            self::assertIsInt($item->getDays());
            self::assertIsInt($item->getOrdersCount());
            self::assertIsString($item->getProductTransName());

            self::assertInstanceOf(ProductUid::class, $item->getProductId());
            self::assertInstanceOf(ProductEventUid::class, $item->getProductEvent());
            self::assertIsString($item->getProductUrl());
            self::assertIsString($item->getProductName());
            self::assertIsString($item->getProductArticle());

            self::assertTrue($item->getCategoryUrl() === null || is_string($item->getCategoryUrl()));
            self::assertTrue($item->getCategoryName() === null || is_string($item->getCategoryName()));
            self::assertTrue($item->getOrderTotal() === null || is_string($item->getOrderTotal()));
            self::assertTrue($item->isExistManufacture() === false || is_string($item->isExistManufacture()));

            self::assertTrue(
                $item->getProductOfferValue() === null ||
                is_string($item->getProductOfferValue())
            );
            self::assertTrue(
                $item->getProductOfferId() === null ||
                $item->getProductOfferId() instanceof ProductOfferUid
            );
            self::assertTrue(
                $item->getProductOfferReference() === null ||
                is_string($item->getProductOfferReference())
            );
            self::assertTrue(
                $item->getProductOfferPostfix() === null ||
                is_string($item->getProductOfferPostfix())
            );

            self::assertTrue(
                $item->getProductVariationValue() === null ||
                is_string($item->getProductVariationValue())
            );
            self::assertTrue(
                $item->getProductVariationId() === null ||
                $item->getProductVariationId() instanceof ProductVariationUid
            );
            self::assertTrue(
                $item->getProductVariationReference() === null ||
                is_string($item->getProductVariationReference())
            );
            self::assertTrue(
                $item->getProductVariationPostfix() === null ||
                is_string($item->getProductVariationPostfix())
            );

            self::assertTrue(
                $item->getProductModificationValue() === null ||
                is_string($item->getProductModificationValue())
            );
            self::assertTrue(
                $item->getProductModificationId() === null ||
                $item->getProductModificationId() instanceof ProductModificationUid
            );
            self::assertTrue(
                $item->getProductModificationReference() === null ||
                is_string($item->getProductModificationReference())
            );
            self::assertTrue(
                $item->getProductModificationPostfix() === null ||
                is_string($item->getProductModificationPostfix())
            );

            self::assertTrue($item->getProductImage() === null || is_string($item->getProductImage()));
            self::assertTrue($item->getProductImageExt() === null || is_string($item->getProductImageExt()));
            self::assertTrue($item->getProductImageCdn() === null || is_bool($item->getProductImageCdn()));

            break;
        }
    }
}