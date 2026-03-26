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
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartEvent\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartInvariable\ManufacturePartInvariableInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Ozon\Package\Type\Supply\Id\OzonSupplyUid;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Wildberries\Orders\Messenger\Statistics\UpdateStatisticMessage;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Wildberries\Package\Entity\Package\WbPackage;
use BaksDev\Wildberries\Package\Repository\Package\ExistOrderPackage\ExistOrderPackageInterface;
use BaksDev\Wildberries\Package\Repository\Supply\ExistOpenSupplyProfile\ExistOpenSupplyProfileInterface;
use BaksDev\Wildberries\Package\Repository\Supply\OpenWbSupplyIdentifier\OpenWbSupplyIdentifierInterface;
use BaksDev\Wildberries\Package\Type\Supply\Id\WbSupplyUid;
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
#[AsMessageHandler(priority: 10)]
final readonly class AddOrdersPackageByPartCompletedHandler
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ExistOpenSupplyProfileInterface $ExistOpenSupplyProfile,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private OpenWbSupplyIdentifierInterface $OpenWbSupplyIdentifier,
        private ManufacturePartInvariableInterface $ManufacturePartInvariableRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierByEventRepository
    ) {}


    public function __invoke(ManufacturePartMessage $message): bool
    {
        $Deduplicator = $this->deduplicator
            ->namespace('wildberries-manufacture')
            ->deduplication([$message->getId(), self::class]);

        if($Deduplicator->isExecuted())
        {
            return false;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->error(
                'manufacture-part: ManufacturePartEvent не определено',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            $this->logger->error(
                'manufacture-part: Статус производственной партии не является COMPLETED «Укомплектована»',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFbsWildberries::class))
        {
            return false;
        }

        $ManufacturePartInvariable = $this->ManufacturePartInvariableRepository
            ->forPart($message->getId())
            ->find();

        if(false === ($ManufacturePartInvariable instanceof ManufacturePartInvariable))
        {
            return false;
        }


        /** Проверяем, что имеется открытая поставка со статусом OPEN (отправлено на API получен номер) */

        $ExistOpenSupply = $this->ExistOpenSupplyProfile
            ->forProfile($ManufacturePartInvariable->getProfile())
            ->isExistOpenSupply();


        if(false === $ExistOpenSupply)
        {
            /**
             * Пробуем добавить через интервал в ожидании открытия, если нет открытой поставки Wildberries
             *
             * @see NewSupplyByPartCompletedDispatcher
             */

            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('3 seconds')],
                    transport: 'wildberries-manufacture-low',
                );

            $this->logger->warning('Открытой поставки поставки Wildberries не найдено. Пробуем через 5 секунд ...');

            return false;
        }


        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        $UserProfileUid = $ManufacturePartInvariable->getProfile();


        /**
         * Добавляем все заказы Wildberries со статусом «На упаковке» в открытую системную поставку
         */

        $WbSupplyUid = $this
            ->OpenWbSupplyIdentifier
            ->forProfile($UserProfileUid)
            ->find();

        if(false === ($WbSupplyUid instanceof WbSupplyUid))
        {
            $this->logger->warning(
                'wildberries-manufacture: Открытая поставка Wildberries не найдена. Пробуем через время ',
                [$message, self::class.':'.__LINE__],
            );

            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('15 seconds')],
                transport: 'wildberries-manufacture',
            );

            return false;
        }


        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /** Если коллекция заказов, закрепленных за продуктом из производственной партии пустая - пропускаем */
            if(true === $ManufacturePartProductsDTO->getOrd()->isEmpty())
            {
                $this->logger->critical(
                    'wildberries-manufacture: заказы в производственной партии не найдены',
                    [ManufacturePartProductsDTO::class, self::class.':'.__LINE__],
                );

                continue;
            }

            /**
             * Добавляем заказ в открытую системную поставку
             */

            $AddOrdersPackageByPartCompletedMessage = new AddOrdersPackageByPartCompletedMessage
            (
                profile: $UserProfileUid,
                supply: $WbSupplyUid,
                sort: $ManufacturePartProductsDTO->getSort(),
            );

            foreach($ManufacturePartProductsDTO->getOrd() as $ManufacturePartProductOrderDTO)
            {
                $AddOrdersPackageByPartCompletedMessage->addOrder($ManufacturePartProductOrderDTO->getOrd());
            }

            $this->messageDispatch->dispatch(
                message: $AddOrdersPackageByPartCompletedMessage,
                stamps: [new MessageDelay(sprintf('%s seconds', $ManufacturePartProductsDTO->getTotal()))],
                transport: 'orders-order-low',
            );


            /**
             * Отправляем сообщение для пересчета статистических данных
             */

            $CurrentProductIdentifierResult = $this->CurrentProductIdentifierByEventRepository
                ->forEvent($ManufacturePartProductsDTO->getProduct())
                ->forOffer($ManufacturePartProductsDTO->getOffer())
                ->forVariation($ManufacturePartProductsDTO->getVariation())
                ->forModification($ManufacturePartProductsDTO->getModification())
                ->find();

            if(
                false === $CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult
                || false === $CurrentProductIdentifierResult->getProductInvariable() instanceof ProductInvariableUid
            )
            {
                $this->logger->critical(
                    'wildberries-manufacture: Невозможно определить ProductInvariable продукта для пересчета статистических данных',
                    [$message, self::class.':'.__LINE__, var_export($ManufacturePartProductsDTO, true)],
                );

                continue;
            }

            $this->messageDispatch->dispatch(
                message: new UpdateStatisticMessage(
                    invariable: $CurrentProductIdentifierResult->getProductInvariable(),
                    event: $CurrentProductIdentifierResult->getEvent(),
                    offer: $CurrentProductIdentifierResult->getOffer(),
                    variation: $CurrentProductIdentifierResult->getVariation(),
                    modification: $CurrentProductIdentifierResult->getModification(),
                ),
                stamps: [new MessageDelay(sprintf('%s seconds', $ManufacturePartProductsDTO->getTotal()))],
                transport: 'orders-order-low',
            );
        }

        $Deduplicator->save();

        return true;
    }
}