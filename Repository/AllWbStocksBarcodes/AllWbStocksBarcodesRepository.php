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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbStocksBarcodes;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Barcode\ProductOfferBarcode;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Barcode\ProductVariationBarcode;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Barcode\ProductModificationBarcode;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use Generator;

final class AllWbStocksBarcodesRepository implements AllWbStocksBarcodesInterface
{
    private UserProfileUid|false $profile = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfile|UserProfileUid|string $profile): static
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

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

    /**
     * Метод возвращает штрихкод и его остаток на складах Wildberries
     *
     * @return Generator<AllWbStocksBarcodesResult>|false
     */
    public function findAll(): Generator|false
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('wb_stocks.quantity AS quantity')
            ->from(WbStock::class, 'wb_stocks');

        $dbal->join(
            'wb_stocks',
            ProductInvariable::class, 'invariable',
            'wb_stocks.invariable = invariable.id'
        );

        $dbal->join(
            'invariable',
            ProductInfo::class, 'product_info',
            'product_info.product = invariable.product'
        );

        if($this->profile)
        {
            $dbal->andWhere('product_info.profile = :profile');
            $dbal->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );
        }

        /**
         * Offer
         */

        $dbal->leftJoin(
            'invariable',
            ProductOffer::class,
            'offer',
            '
                (invariable.offer IS NULL AND offer.const IS NULL) OR
                (invariable.offer IS NOT NULL AND offer.const = invariable.offer)
            ');

        /** Offer Barcode */

        $dbal
            ->leftJoin(
                'offer',
                ProductOfferBarcode::class,
                'product_offer_barcode',
                'product_offer_barcode.offer = offer.id'
            );

        /**
         * Variation
         */

        $dbal->leftJoin(
            'invariable',
            ProductVariation::class,
            'variation',
            '
                (invariable.variation IS NULL AND variation.const IS NULL) OR
                (invariable.variation IS NOT NULL AND variation.const = invariable.variation)
            ');

        /** Variation Barcode */

        $dbal
            ->leftJoin(
                'variation',
                ProductVariationBarcode::class,
                'product_variation_barcode',
                'product_variation_barcode.variation = variation.id'
            );

        /**
         * Modification
         */

        $dbal->leftJoin(
            'invariable',
            ProductModification::class,
            'modification',
            '
                (invariable.modification IS NULL AND modification.const IS NULL) OR
                (invariable.modification IS NOT NULL AND modification.const = invariable.modification)
            ');

        /** Modification Barcode */

        $dbal
            ->leftJoin(
                'modification',
                ProductModificationBarcode::class,
                'product_modification_barcode',
                'product_modification_barcode.modification = modification.id'
            );

        /** Штрихкоды продукта */

        $dbal->addSelect(
            "
            JSON_AGG
                    (DISTINCT
         			CASE
         			    WHEN product_modification_barcode.value IS NOT NULL
                        THEN product_modification_barcode.value
                        
                        WHEN product_variation_barcode.value IS NOT NULL
                        THEN product_variation_barcode.value
                        
                        WHEN product_offer_barcode.value IS NOT NULL
                        THEN product_offer_barcode.value
                        
                        WHEN product_info.barcode IS NOT NULL
                        THEN product_info.barcode
                        
                        ELSE NULL
                    END
                    )
                    AS barcodes"
        );

        $dbal->addSelect('
            CASE
                WHEN modification.barcode_old IS NOT NULL
                THEN modification.barcode_old
                
                WHEN variation.barcode_old IS NOT NULL
                THEN variation.barcode_old
                
                WHEN offer.barcode_old IS NOT NULL
                THEN offer.barcode_old
                           
                WHEN product_info.barcode IS NOT NULL
                THEN product_info.barcode

                ELSE NULL

            END AS barcode');

        $dbal->allGroupByExclude();

        return $dbal->fetchAllHydrate(AllWbStocksBarcodesResult::class);
    }
}