<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Wildberries\Manufacture\Messenger;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\RelevantNewOrderByProduct\RelevantNewOrderByProductInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusPackage;
use BaksDev\Products\Stocks\Repository\ProductStocksByOrder\ProductStocksByOrderInterface;
use BaksDev\Products\Stocks\UseCase\Admin\Extradition\ExtraditionProductStockHandler;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Wildberries\Package\Entity\Package\WbPackage;
use BaksDev\Wildberries\Package\Repository\Package\ExistOrderPackage\ExistOrderPackageInterface;
use BaksDev\Wildberries\Package\Repository\Supply\ExistOpenSupplyProfile\ExistOpenSupplyProfileInterface;
use BaksDev\Wildberries\Package\Repository\Supply\OpenWbSupplyIdentifier\OpenWbSupplyIdentifierInterface;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\Orders\WbPackageOrderDTO;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\WbPackageDTO;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\WbPackageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Метод добавляет заказы Wildberries в открытую поставку при выполненной производственной парии Wildberries Fbs
 */
#[AsMessageHandler(priority: 10)]
final readonly class AddOrdersPackageByPartCompleted
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ExistOpenSupplyProfileInterface $ExistOpenSupplyProfile,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private OpenWbSupplyIdentifierInterface $OpenWbSupplyIdentifier,
        private ExistOrderPackageInterface $ExistOrderPackage,
        private WbPackageHandler $WbPackageHandler,
        private CurrentOrderEventInterface $CurrentOrderEvent
    )
    {
        $this->deduplicator->namespace('wildberries-manufacture');
    }


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
            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartComplete(ManufacturePartCompleteWildberriesFbs::class))
        {
            return false;
        }

        /** Проверяем, что имеется открытая поставка со статусом OPEN (отправлено на API получен номер) */

        $ExistOpenSupply = $this->ExistOpenSupplyProfile
            ->forProfile($ManufacturePartEvent->getProfile())
            ->isExistOpenSupply();


        if(false === $ExistOpenSupply)
        {
            /**
             * Пробуем добавить через интервал в ожидании открытия, если нет открытой поставки Wildberries
             * @see NewSupplyByPartCompletedDispatcher
             */

            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('3 seconds')],
                    transport: 'wildberries-manufacture-low',
                );

            $this->logger->warning('wildberries-manufacture: Открытой поставки поставки Wildberries не найдено. Пробуем через 5 секунд ...');

            return false;
        }

        //        /** Получаем всю продукцию в производственной партии */
        //
        //        $ProductsManufacture = $this->ProductsByManufacturePart
        //            ->forPart($message->getId())
        //            ->findAll();

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        $UserProfileUid = $ManufacturePartDTO->getInvariable()->getProfile();


        /**
         * Добавляем все заказы Wildberries DBS со статусом «На упаковке» в открытую системную поставку
         */

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            $WbSupplyUid = $this->OpenWbSupplyIdentifier->forProfile($UserProfileUid)->find();

            /** Создаем упаковку на заказы одного продукта */
            $WbPackageDTO = new WbPackageDTO($UserProfileUid)
                ->setPackageSupply($WbSupplyUid);

            /** @var ManufacturePartProductOrderDTO $ManufacturePartProductOrderDTO */
            foreach($ManufacturePartProductsDTO->getOrd() as $ManufacturePartProductOrderDTO)
            {
                $OrderEvent = $this->CurrentOrderEvent
                    ->forOrder($ManufacturePartProductOrderDTO->getOrd())
                    ->find();

                if(false === ($OrderEvent instanceof OrderEvent))
                {
                    continue;
                }

                if(false === $OrderEvent->isStatusEquals(OrderStatusPackage::class))
                {
                    continue;
                }

                if(false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsWildberries::TYPE))
                {
                    continue;
                }

                /**
                 * Не добавляем заказ в упаковку, если он уже в поставке
                 */
                if($this->ExistOrderPackage->forOrder($OrderEvent->getMain())->isExist())
                {
                    continue;
                }

                $DeduplicatorOrder = $this->deduplicator
                    ->deduplication([$OrderEvent->getMain(), self::class]);

                if($Deduplicator->isExecuted())
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
                    ->setId($OrderEvent->getMain());

                $WbPackageDTO->addOrd($WbPackageOrderDTO);

                $DeduplicatorOrder->save();
            }

            /** Если упаковка пуста - приступаем к следующему продукту */
            if($WbPackageDTO->getOrd()->isEmpty())
            {
                continue;
            }

            /**
             * Сохраняем упаковку и приступаем к этапу отправки заказов по API
             * @see AddWildberriesSupplyOrdersHandler
             */

            $WbPackage = $this->WbPackageHandler->handle($WbPackageDTO);

            if(false === ($WbPackage instanceof WbPackage))
            {
                $this->logger->critical(
                    sprintf('wildberries-manufacture: Ошибка %s при сохранении упаковки', $WbPackage),
                    [$message, self::class.':'.__LINE__]
                );

                return false;
            }

            $this->logger->info(
                'Добавили упаковку в поставку',
                [$WbPackage, $WbSupplyUid, self::class.':'.__LINE__]
            );
        }

        $Deduplicator->save();

        return true;
    }
}