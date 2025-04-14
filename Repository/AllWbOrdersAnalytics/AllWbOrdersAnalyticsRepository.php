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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusClosed;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Info\CategoryProductInfo;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Material\ProductMaterial;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\ProductInvariable;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Wildberries\Manufacture\Entity\WbOrderAnalyitcs;
use BaksDev\Wildberries\Manufacture\Entity\WbStock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

final class AllWbOrdersAnalyticsRepository implements AllWbOrdersAnalyticsInterface
{
    /** Мин. количество дней, на которые могут быть рассчитаны остатки на складе без необходимости доп. поставки */
    const int DAYS_MINIMUM = 14;

    private ?ProductFilterDTO $filter = null;

    private ?SearchDTO $search = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductFilterDTO $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /*
     * Получаем среднее количество заказов товаров в день и их количество на складе
     */
    public function findPaginator(DeliveryUid|false $part): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('analytics.invariable AS invariable')
            ->addSelect('analytics.average AS average')
            ->addSelect('analytics.average * :minimum - stock.quantity AS needed_amount')
            ->from(WbOrderAnalyitcs::class, 'analytics')
            ->where('analytics.average IS NOT NULL AND analytics.average > 0')
            ->setParameter(
                'minimum',
                self::DAYS_MINIMUM,
                ParameterType::INTEGER
            );

        $dbal
            ->addSelect('stock.quantity AS quantity')
            ->join(
                'analytics',
                WbStock::class,
                'stock',
                "stock.invariable = analytics.invariable "
            )
            ->andWhere('stock.quantity IS NOT NULL AND (stock.quantity / analytics.average) < :minimum');

        $dbal->join(
            'analytics',
            ProductInvariable::class,
            'product_invariable',
            "analytics.invariable = product_invariable.id"
        );

        $dbal
            ->addSelect("product.id AS product_id")
            ->addSelect("product.event as product_event")
            ->join(
                'product_invariable',
                Product::class,
                'product',
                "product.id = product_invariable.product"
            );

        $dbal
            ->addSelect('product_info.url AS product_url')
            ->leftJoin(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id'
            );

        /**  Название */
        $dbal
            ->addSelect('product_trans.name AS product_trans_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );

        /** OFFER */
        $dbal
            ->addSelect("product_offer.value as product_offer_value")
            ->addSelect("product_offer.id AS product_offer_id")
            ->leftJoin(
                'product_invariable',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event AND product_offer.const = product_invariable.offer'
            );

        /** VARIATION */
        $dbal
            ->addSelect("product_variation.value as product_variation_value")
            ->addSelect("product_variation.id AS product_variation_id")
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = product_invariable.variation'
            );

        /** MODIFICATION */
        $dbal
            ->addSelect("product_modification.value as product_modification_value")
            ->addSelect("product_modification.id AS product_modification_id")
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = product_invariable.modification'
            );

        /** Фото */

        $dbal->leftJoin(
            'product',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product.event AND product_photo.root = TRUE'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = TRUE'
        );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationImage::class,
            'product_variation_images',
            'product_variation_images.variation = product_variation.id AND product_variation_images.root = TRUE'
        );

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_images',
            'product_modification_images.modification = product_modification.id AND product_modification_images.root = TRUE'
        );

        $dbal->addSelect(
            "
			CASE
			
			    WHEN product_modification_images.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_images.name)
			   
			   WHEN product_variation_images.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_images.name)
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   
			   ELSE NULL
			END AS product_image
		"
        );

        /** Расширение */
        $dbal->addSelect("
			CASE
			   WHEN product_variation_images.name IS NOT NULL 
			   THEN product_variation_images.ext
			   
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.ext
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.ext
			   
			   ELSE NULL
			END AS product_image_ext
		");

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
			CASE
			   WHEN product_variation_images.name IS NOT NULL 
			   THEN product_variation_images.cdn
					
			   WHEN product_offer_images.name IS NOT NULL 
			   THEN product_offer_images.cdn
					
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.cdn
			   
			   ELSE NULL
			END AS product_image_cdn
		");

        /** Артикул продукта */
        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');

        $dbal
            ->addSelect("product_event.id AS product_event_id")
            ->leftJoin(
                'product',
                ProductEvent::class,
                'product_event',
                'product_event.id = product.event'
            );


        /* Категория товара */
        $dbal->leftJoin(
            'product_event',
            ProductCategory::class,
            'product_category',
            'product_category.event = product_event.id'
        );

        $dbal->leftJoin(
            'product_event',
            CategoryProduct::class,
            'category',
            'category.id = product_category.category');

        $dbal
            ->addSelect('category_info.url AS category_url')
            ->leftJoin(
                'category',
                CategoryProductInfo::class,
                'category_info',
                'category_info.event = category.event AND category_info.active = true'
            );


        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );

        $dbal->leftJoin(
            'analytics',
            OrderProduct::class,
            'orders_product',
            'orders_product.product = analytics.invariable'
        );

        $dbal
            ->addSelect('SUM(orders_product_price.total) AS order_total')
            ->leftJoin(
                'orders_product',
                OrderPrice::class,
                'orders_product_price',
                'orders_product_price.product = orders_product.id'
            );

        /** Фильтры */
        if($this->filter?->getMaterials())
        {
            $dbal->andWhereNotExists(ProductMaterial::class, 'tmp', 'tmp.event = order_product.product');
        }

        if($this->filter?->getCategory())
        {
            $dbal
                ->andWhere(
                    'product_category.event = product_info.event AND
                    product_category.category = :category AND
                    product_category.root = true'
                )
                ->setParameter(
                    key: 'category',
                    value: $this->filter->getCategory(),
                    type: CategoryProductUid::TYPE
                );
        }

        if($this->filter?->getOffer())
        {
            $dbal
                ->andWhere('product_offer.value = :offer')
                ->setParameter('offer', $this->filter->getOffer());
        }

        /** ФИЛЬТР по множественным вариантам */
        if($this->filter?->getVariation())
        {
            $dbal
                ->andWhere('product_variation.value = :variation')
                ->setParameter('variation', $this->filter->getVariation());
        }

        /** ФИЛЬТР по модификациям множественного варианта */
        if($this->filter?->getModification())
        {
            $dbal
                ->andWhere('product_modification.value = :modification')
                ->setParameter('modification', $this->filter->getModification());
        }


        $dbal->allGroupByExclude();

        if($part)
        {
            /** Только товары, которых нет в производстве */

            $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

            $dbalExist->from(ManufacturePartProduct::class, 'exist_product');

            $dbalExist->join(
                'exist_product',
                ManufacturePart::class,
                'exist_part',
                'exist_part.event = exist_product.event'
            );

            $dbalExist
                ->select('exist_part_invariable.number')
                ->leftJoin(
                    'exist_part',
                    ManufacturePartInvariable::class,
                    'exist_part_invariable',
                    'exist_part_invariable.main = exist_part.id'
                );


            $dbalExist->andWhere('exist_product.product = product_event.id');
            $dbalExist->andWhere('(exist_product.offer IS NULL OR exist_product.offer = product_offer.id)');
            $dbalExist->andWhere('(exist_product.variation IS NULL OR exist_product.variation = product_variation.id)');


            /**
             * Только продукция в процессе производства
             * Только продукция на указанный завершающий этап
             */
            $dbalExist
                ->join('exist_part',
                    ManufacturePartEvent::class,
                    'exist_product_event',
                    '
                exist_product_event.id = exist_part.event AND
                exist_product_event.complete = :complete AND
                exist_product_event.status NOT IN (:status_part)
            ');

            $dbal
                ->setParameter(
                    'status_part',
                    [
                        ManufacturePartStatusClosed::STATUS,
                        ManufacturePartStatusCompleted::STATUS
                    ],
                    ArrayParameterType::STRING
                )
                ->setParameter(
                    key: 'complete',
                    value: $part,
                    type: DeliveryUid::TYPE
                );

            $dbalExist->setMaxResults(1);

            $dbal->addSelect('(SELECT ('.$dbalExist->getSQL().')) AS exist_manufacture');
            $dbal->addOrderBy('exist_manufacture', 'DESC');

        }
        else
        {
            $dbal->addSelect('FALSE AS exist_manufacture');
        }

        $dbal->orderBy('needed_amount', 'DESC');

        if($this->search && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_variation.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_info.article')
                ->addSearchLike('product_trans.name');
        }

        return $this->paginator->fetchAllAssociative($dbal);
    }
}