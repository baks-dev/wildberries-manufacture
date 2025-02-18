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


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Wildberries\Manufacture\Type\ManufacturePartComplete\ManufacturePartCompleteWildberriesFbs;
use BaksDev\Wildberries\Package\Entity\Supply\WbSupply;
use BaksDev\Wildberries\Package\Repository\Supply\ExistOpenSupplyProfile\ExistOpenSupplyProfileInterface;
use BaksDev\Wildberries\Package\UseCase\Supply\New\WbSupplyNewDTO;
use BaksDev\Wildberries\Package\UseCase\Supply\New\WbSupplyNewHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class NewSupplyByPartCompleted
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ExistOpenSupplyProfileInterface $ExistOpenSupplyProfile,
        private WbSupplyNewHandler $WbSupplyNewHandler,
        private DeduplicatorInterface $deduplicator,
    ) {}

    /**
     * Метод открывает новую поставку если завершающий этап производства Wildberries FBS
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('wildberries-manufacture')
            ->deduplication([$message, self::class]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(!$ManufacturePartEvent)
        {
            return;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartComplete(ManufacturePartCompleteWildberriesFbs::class))
        {
            return;
        }

        $ExistOpenSupply = $this->ExistOpenSupplyProfile
            ->forProfile($ManufacturePartEvent->getProfile())
            ->isExistNewOrOpenSupply();

        /** Не открываем новую поставку, если уже открыта */
        if(true === $ExistOpenSupply)
        {
            $Deduplicator->save();
            return;
        }

        /**
         * Открываем новую системную поставку на указанный профиль
         */

        $WbSupplyNewDTO = new WbSupplyNewDTO($ManufacturePartEvent->getProfile());
        $WbSupply = $this->WbSupplyNewHandler->handle($WbSupplyNewDTO);

        if(false === ($WbSupply instanceof WbSupply))
        {
            $this->logger->critical(
                'wildberries-manufacture: Ошибка при открытии новой поставки при завершающем этапе производства Wildberries FBS',
                [$WbSupply, self::class.':'.__LINE__]
            );

            return;
        }

        $Deduplicator->save();
    }
}