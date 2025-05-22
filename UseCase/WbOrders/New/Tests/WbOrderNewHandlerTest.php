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

namespace BaksDev\Wildberries\Manufacture\UseCase\WbOrders\New\Tests;

use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Wildberries\Manufacture\Entity\WbOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use BaksDev\Wildberries\Manufacture\UseCase\WbOrders\New\WbOrderNewDTO;
use DateTimeImmutable;
use BaksDev\Wildberries\Manufacture\UseCase\WbOrders\New\WbOrderNewHandler;

/**
 * @group wildberries-manufacture
 * @group wildberries-manufacture-use-case
 */
final class WbOrderNewHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $WbOrder = $em->getRepository(WbOrder::class)
            ->findOneBy(['invariable' => ProductInvariableUid::TEST]);

        if($WbOrder)
        {
            $em->remove($WbOrder);
        }

        $em->flush();
        $em->clear();
    }

    public function testUseCase(): void
    {
        self::assertTrue(true);

        /** @see WbOrderNewDTO */
        $WbOrderNewDTO = new WbOrderNewDTO();

        $WbOrderNewDTO->setId('test');
        self::assertSame('test', $WbOrderNewDTO->getId());

        $WbOrderNewDTO->setInvariable(ProductInvariableUid::TEST);
        self::assertSame(ProductInvariableUid::TEST, (string)$WbOrderNewDTO->getInvariable());

        $date = new DateTimeImmutable();
        $WbOrderNewDTO->setDate($date);
        self::assertSame($date, $WbOrderNewDTO->getDate());

        /** @var WbOrderNewHandler $WbOrderNewHandler */
        $WbOrderNewHandler = self::getContainer()->get(WbOrderNewHandler::class);
        $handle = $WbOrderNewHandler->handle($WbOrderNewDTO);

        self::assertInstanceOf(WbOrder::class, $handle);
    }
}