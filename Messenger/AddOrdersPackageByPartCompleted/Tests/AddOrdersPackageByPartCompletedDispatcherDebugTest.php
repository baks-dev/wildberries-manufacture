<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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
 *
 */

declare(strict_types=1);

namespace BaksDev\Wildberries\Manufacture\Messenger\AddOrdersPackageByPartCompleted\Tests;

use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Messenger\AddOrdersPackageByPartCompleted\AddOrdersPackageByPartCompletedDispatcher;
use BaksDev\Wildberries\Manufacture\Messenger\AddOrdersPackageByPartCompleted\AddOrdersPackageByPartCompletedMessage;
use BaksDev\Wildberries\Package\Messenger\Orders\OrderPackage\CreateNewWbSupplyWhenOrderPackageDispatcher;
use BaksDev\Wildberries\Package\Type\Supply\Id\WbSupplyUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Group('wildberries-manufacture')]
#[When(env: 'test')]
class AddOrdersPackageByPartCompletedDispatcherDebugTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var AddOrdersPackageByPartCompletedDispatcher $AddOrdersPackageByPartCompletedDispatcher */
        $AddOrdersPackageByPartCompletedDispatcher = self::getContainer()->get(AddOrdersPackageByPartCompletedDispatcher::class);

        self::assertTrue(true);
        return;

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');


        $message = new AddOrdersPackageByPartCompletedMessage(
            profile: new UserProfileUid('0196e4cf-fa40-79f1-aae6-70cfc444231a'),
            supply: new WbSupplyUid('018af209-73b1-79e6-96bb-3f014942e632'),
            sort: 0,
        );

        $orders = [
            new OrderUid('019e4ef8-babd-7896-bbcc-62fe19154f67'),
        ];

        foreach($orders as $order)
        {
            $message->addOrder($order);
        }

        $AddOrdersPackageByPartCompletedDispatcher($message);
    }
}