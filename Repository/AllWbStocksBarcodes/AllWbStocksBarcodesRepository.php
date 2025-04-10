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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbStocksBarcodes;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use Generator;
use BaksDev\Products\Product\Entity\Info\ProductInfo;

final class AllWbStocksBarcodesRepository implements AllWbStocksBarcodesInterface
{
    private UserProfileUid|false $profile = false;

    public function profile(UserProfile|UserProfileUid|string $profile): static
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function findAll(): Generator|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(WbStock::class, 'wb_stocks')
            ->join(
                'wb_stocks',
                ProductInvariable::class, 'invariable',
                'wb_stocks.invariable = invariable.id'
            );
        
        $dbal->join(
            'invariable',
            ProductInfo::class, 'product_info',
            'product_info.product = invariable.product'
        );

        $dbal->leftJoin(
            'invariable',
            ProductOffer::class, 'offer',
            'offer.const = invariable.offer AND invariable.variation IS NULL AND invariable.modification IS NULL'
        );

        $dbal->leftJoin(
            'invariable',
            ProductVariation::class, 'variation',
            'variation.const = invariable.variation AND invariable.modification IS NULL'
        );

        $dbal->leftJoin(
            'invariable',
            ProductModification::class, 'modification',
            'modification.const = invariable.modification'
        );


        $dbal->select('
            CASE
                WHEN modification.barcode IS NOT NULL
                THEN modification.barcode
                
                WHEN variation.barcode IS NOT NULL
                THEN variation.barcode
                
                WHEN offer.barcode IS NOT NULL
                THEN offer.barcode
                           
                WHEN product_info.barcode IS NOT NULL
                THEN product_info.barcode

            END AS barcode');

        $dbal->addSelect('wb_stocks.quantity AS quantity');

        if($this->profile)
        {
            $dbal->andWhere("product_info.profile = :profile");
            $dbal->setParameter("profile", $this->profile);
        }

        return $dbal->fetchAllHydrate(AllWbStocksBarcodesResult::class);
    }
}