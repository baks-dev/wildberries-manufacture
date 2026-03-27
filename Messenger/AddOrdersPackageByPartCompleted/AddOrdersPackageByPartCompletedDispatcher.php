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
 */

declare(strict_types=1);

namespace BaksDev\Wildberries\Manufacture\Messenger\AddOrdersPackageByPartCompleted;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Wildberries\Orders\Messenger\Statistics\UpdateStatisticMessage;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Wildberries\Package\Entity\Package\WbPackage;
use BaksDev\Wildberries\Package\Repository\Package\ExistOrderPackage\ExistOrderPackageInterface;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\Orders\WbPackageOrderDTO;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\WbPackageDTO;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\WbPackageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Метод добавляет заказы Wildberries в открытую системную поставку
 * при выполненной производственной парии Wildberries Fbs
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final class AddOrdersPackageByPartCompletedDispatcher
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ExistOrderPackageInterface $ExistOrderPackageRepository,
        private WbPackageHandler $WbPackageHandler,
        private MessageDispatchInterface $messageDispatch
    ) {}

    public function __invoke(AddOrdersPackageByPartCompletedMessage $message): void
    {
        /** Создаем упаковку на заказы одного продукта */
        $WbPackageDTO = new WbPackageDTO($message->getProfile())
            ->setPackageSupply($message->getSupply());

        foreach($message->getOrders() as $order)
        {
            $OrderEvent = $this->CurrentOrderEventRepository
                ->forOrder($order)
                ->find();

            if(false === ($OrderEvent instanceof OrderEvent))
            {
                $this->logger->critical(
                    'ozon-manufacture: не найдено активное событие заказа',
                    [self::class.':'.__LINE__, $order],
                );

                continue;
            }

            /** Пропускаем, если тип заказа не Wildberries FBS */
            if(false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsWildberries::TYPE))
            {
                $this->logger->error(
                    sprintf('%s: Заказ не является Wildberries FBS', $OrderEvent->getPostingNumber()),
                    [self::class.':'.__LINE__, $order],
                );

                continue;
            }

            /** Пропускаем, если заказ уже укомплектован */
            if(true === $OrderEvent->isStatusEquals(OrderStatusCompleted::class))
            {
                $this->logger->error(
                    sprintf('%s: Заказ заказ уже укомплектован и не подходит для упаковки', $OrderEvent->getPostingNumber()),
                    [self::class.':'.__LINE__, $order],
                );

                continue;
            }

            /** Пропускаем и пробуем позже, если заказ не на упаковке */
            if(false === $OrderEvent->isStatusEquals(OrderStatusPackage::class))
            {
                $this->messageDispatch->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('5 seconds')],
                    transport: 'orders-order-low',
                );

                return;
            }

            /** Не добавляем заказ в упаковку, если он уже в поставке */
            if(true === $this->ExistOrderPackageRepository->forOrder($OrderEvent->getMain())->isExist())
            {
                continue;
            }

            /* Скрываем у всех заказ */
            $this->CentrifugoPublish
                ->addData(['identifier' => $OrderEvent->getMain()]) // ID заказа
                ->addData(['profile' => false])
                ->send('remove');

            /** Добавляем заказ в упаковку */
            $WbPackageOrderDTO = new WbPackageOrderDTO()
                ->setId($OrderEvent->getMain())
                ->setSort($message->getSort());

            $WbPackageDTO->addOrd($WbPackageOrderDTO);
        }


        /** Если упаковка пуста - приступаем к следующему продукту */
        if($WbPackageDTO->getOrd()->isEmpty())
        {
            return;
        }

        /**
         * Сохраняем упаковку и приступаем к этапу отправки заказов по API
         *
         * @see AddWildberriesSupplyOrdersHandler
         */

        $WbPackage = $this->WbPackageHandler->handle($WbPackageDTO);

        if(false === ($WbPackage instanceof WbPackage))
        {
            $this->logger->critical(
                sprintf('wildberries-manufacture: Ошибка %s при сохранении упаковки', $WbPackage),
                [$message, self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->info(
            'Добавили упаковку в поставку',
            [$WbPackage, $message->getSupply(), self::class.':'.__LINE__],
        );
    }
}
