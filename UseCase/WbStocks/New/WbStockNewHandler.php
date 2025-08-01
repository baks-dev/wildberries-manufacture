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

namespace BaksDev\Wildberries\Manufacture\UseCase\WbStocks\New;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;

final class WbStockNewHandler extends AbstractHandler
{
    public function handle(WbStockNewDTO $dto): WbStock|string
    {
        $invariable = $dto->getInvariable();

        $wbStock = self::getRepository(WbStock::class)->findOneBy(['invariable' => $invariable]);

        /** @var WbStock $wbStock */
        if(false === ($wbStock instanceof WbStock))
        {
            $wbStock = new WbStock()->setInvariable($invariable);
            $this->persist($wbStock);
        }

        $wbStock->setQuantity($dto->getQuantity());

        $this->validatorCollection->add($wbStock);

        /** Валидация всех объектов */
        if ($this->validatorCollection->isInvalid()) {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        return $wbStock;
    }
}