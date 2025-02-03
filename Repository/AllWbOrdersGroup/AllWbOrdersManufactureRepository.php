<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersGroup;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusClosed;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Orders\Entity\Event\WbOrdersEvent;
use BaksDev\Wildberries\Orders\Entity\WbOrders;
use BaksDev\Wildberries\Orders\Entity\WbOrdersStatistics;
use BaksDev\Wildberries\Orders\Forms\WbOrdersProductFilter\WbOrdersProductFilterInterface;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryDbsWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Wildberries\Orders\Type\OrderStatus\Status\WbOrderStatusNew;
use BaksDev\Wildberries\Orders\Type\OrderStatus\WbOrderStatus;
use BaksDev\Wildberries\Products\Entity\Cards\WbProductCardOffer;
use BaksDev\Wildberries\Products\Entity\Cards\WbProductCardVariation;
use Doctrine\DBAL\ArrayParameterType;

final class AllWbOrdersManufactureRepository implements AllWbOrdersManufactureInterface
{
    private ?ProductFilterDTO $filter = null;

    private ?SearchDTO $search = null;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator
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

    public function findPaginator(ManufacturePartComplete|false $part): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            //->select('invariable.number')
            ->from(OrderInvariable::class, 'invariable');

        if($this->profile instanceof UserProfileUid)
        {
            $dbal
                ->where('invariable.profile = :profile')
                ->setParameter('profile', $this->profile, UserProfileUid::TYPE);
        }


        $dbal
            ->join(
                'invariable',
                Order::class,
                'orders',
                'orders.id = invariable.main'
            );

        $dbal
            ->addSelect('MIN(event.created) AS order_data')
            ->addSelect('COUNT(*) AS order_total')
            ->join(
                'invariable',
                OrderEvent::class,
                'event',
                'event.id = orders.event AND event.status = :status'
            )
            ->setParameter(
                'status',
                OrderStatus\OrderStatusNew::class,
                OrderStatus::TYPE
            );

        $dbal
            ->leftJoin(
                'orders',
                OrderUser::class,
                'order_user',
                'order_user.event = orders.event'
            );

        $dbal
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                'order_delivery.usr = order_user.id AND 
                    order_delivery.delivery IN (:delivery)
                '
            )->setParameter(
                'delivery',
                [TypeDeliveryDbsWildberries::TYPE, TypeDeliveryFbsWildberries::TYPE],
                ArrayParameterType::STRING
            );

        $dbal->leftJoin(
            'orders',
            OrderProduct::class,
            'order_product',
            'order_product.event = orders.event'
        );


        //        $dbal->leftJoin(
        //            'order_product',
        //            OrderPrice::class,
        //            'order_product_price',
        //            'order_product_price.product = order_product.id'
        //        );


        //$dbal->addSelect('orders.id');
        $dbal->addSelect('order_product.product');
        $dbal->addSelect('order_product.offer');
        $dbal->addSelect('order_product.variation');
        $dbal->addSelect('order_product.modification');

        $dbal
            ->leftJoin(
                'order_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = order_product.product'
            );


        $dbal
            ->addSelect('product_info.article AS card_article')
            ->leftJoin(
                'order_product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product_event.main '
            );


        if($this->filter?->getCategory())
        {
            $dbal->join('order_product',
                ProductCategory::class,
                'product_category',
                'product_category.event = product_info.event AND product_category.category = :category AND product_category.root = true'
            );

            $dbal->setParameter('category', $this->filter->getCategory(), CategoryProductUid::TYPE);


        }


        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );


        /**
         * Торговое предложение
         */


        $dbal
            ->addSelect('product_offer.value AS product_offer_value')
            ->addSelect('product_offer.postfix AS product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = order_product.offer AND product_offer.event = product_event.id'
            );

        if(is_null($this->search?->getQuery()) && $this->filter?->getOffer())
        {
            $dbal->andWhere('product_offer.value = :offer');
            $dbal->setParameter('offer', $this->filter->getOffer());
        }


        /* Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference AS product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );


        /**
         * Множественный вариант
         */


        $dbal
            ->addSelect('product_variation.value AS product_variation_value')
            ->addSelect('product_variation.postfix AS product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = order_product.variation AND product_variation.offer = product_offer.id'
            );


        /* Тип множественного варианта */

        $dbal
            ->addSelect('category_variation.reference AS product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );


        /**
         * Модификации множественного варианта
         */

        $dbal
            ->addSelect('product_modification.value AS product_modification_value')
            ->addSelect('product_modification.postfix AS product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.id = order_product.modification AND product_modification.variation = product_variation.id'
            );

        $dbal
            ->addSelect('category_modification.reference AS product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );


        /* Фото продукта */

        $dbal->leftJoin(
            'product_event',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_image',
            'product_offer_image.offer = product_offer.id AND product_offer_image.root = true'
        );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );


        $dbal->addSelect(
            "
			CASE
			    WHEN product_modification_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)

			   WHEN product_variation_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_image.name)
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
					
			   ELSE NULL
			END AS product_image
		"
        );

        /* Флаг загрузки файла CDN */
        $dbal->addSelect('
			CASE
				WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.ext
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.ext
					
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN product_offer_image.ext
					
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.ext
					
			   ELSE NULL
			END AS product_image_ext
		');

        /* Флаг загрузки файла CDN */
        $dbal->addSelect('
			CASE
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.cdn
			   
				WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.cdn
					
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN product_offer_image.cdn
					
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.cdn
					
			   ELSE NULL
			END AS product_image_cdn
		');


        /** Артикул продукта */

        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');

        $dbal->orderBy('order_data');

        $dbal->allGroupByExclude();


        /** ******************** */


        if($part)
        {
            /** Только товары, которых нет в производстве */

            $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

            $dbalExist->from(ManufacturePartProduct::class, 'exist_product');

            $dbalExist
                ->select('exist_part.number')
                ->join(
                    'exist_product',
                    ManufacturePart::class,
                    'exist_part',
                    'exist_part.event = exist_product.event'
                );

            $dbalExist->andWhere('exist_product.product = order_product.product');
            $dbalExist->andWhere('(order_product.offer IS NULL OR exist_product.offer = order_product.offer)');
            $dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.variation = order_product.variation)');


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
                        ManufacturePartStatusClosed::STATUS/*,
                        ManufacturePartStatusCompleted::STATUS*/
                    ],
                    ArrayParameterType::STRING
                )
                ->setParameter('complete', $part, ManufacturePartComplete::TYPE);


            //$dbalExist->andWhere('exist_product_event.status != :status_closed');
            //$dbalExist->andWhere('exist_product_event.status != :status_completed');
            //$dbal->setParameter('status_closed', ManufacturePartStatusClosed::STATUS);
            //$dbal->setParameter('status_completed', ManufacturePartStatusCompleted::STATUS);


            //$dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.modification = order_product.modification) ');

            $dbalExist->setMaxResults(1);

            $dbal->addSelect('(SELECT ('.$dbalExist->getSQL().')) AS exist_manufacture');

        }
        else
        {
            $dbal->addSelect('FALSE AS exist_manufacture');
        }


        if($this->search && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                ->addSearchLike('product_info.article');
        }

        //dd($dbal->fetchAllAssociative());

        return $this->paginator->fetchAllAssociative($dbal);
    }


    /**
     * Метод возвращает сгруппированные заказы по артикулам
     */
    public function fetchAllWbOrdersGroupAssociative(
        SearchDTO $search,
        UserProfileUid $profile,
        WbOrdersProductFilterInterface $filter,
        ?ManufacturePartComplete $complete = null
    ): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        /**
         * Wildberries заказ
         */

        //$dbal->select('wb_order.ord as wb_order_id');
        $dbal->from(WbOrders::class, 'wb_order');

        $dbal->addSelect('MIN(wb_order_event.created) AS wb_order_date');
        $dbal->addSelect('wb_order_event.barcode AS wb_order_barcode');
        $dbal->addSelect('wb_order_event.status AS wb_order_status');
        $dbal->addSelect('wb_order_event.wildberries AS wb_order_wildberries');

        $dbal->join('wb_order',
            WbOrdersEvent::class,
            'wb_order_event',
            'wb_order_event.id = wb_order.event'
        );

        $dbal->andWhere('wb_order_event.profile = :profile');
        $dbal->setParameter('profile', $profile, UserProfileUid::TYPE);


        $dbal->andWhere('wb_order_event.status = :status');
        $dbal->setParameter('status', WbOrderStatusNew::STATUS, WbOrderStatus::TYPE);


        //        $dbal->addSelect('wb_order_sticker.sticker AS wb_order_sticker');
        //        $dbal->leftJoin('wb_order',
        //            WbOrdersSticker::class,
        //            'wb_order_sticker',
        //            'wb_order_sticker.event = wb_order.event'
        //        );


        /**
         * Системный заказ
         */

        $dbal->leftJoin('wb_order',
            Order::class,
            'ord',
            'ord.id = wb_order.id'
        );


        /** Только заказы, которых нет на производстве */

        //        $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        //        $dbalExist->select(1);
        //        $dbalExist->from(ManufacturePartProduct::class, 'exist_product');
        //        $dbalExist->join('exist_product',
        //            ManufacturePartEvent::class,
        //            'exist_product_event',
        //            '
        //
        //                exist_product_event.status = :open
        //            '
        //        );
        //        //OR exist_product_event.marketplace = :package
        //        //  exist_product_event.marketplace = :marketplace AND
        //
        //        //$dbal->setParameter('marketplace', ManufacturePartMarketplaceWildberries::MARKETPLACE);
        //        $dbal->setParameter('open', ManufacturePartStatusOpen::STATUS);
        //        //$dbal->setParameter('package', ManufacturePartStatusPackage::STATUS);
        //
        //        //ManufacturePartStatus
        //
        //        $dbalExist->where('exist_product.product = order_product.product ');
        //        $dbalExist->andWhere('order_product.offer IS NULL OR exist_product.offer = order_product.offer ');
        //        $dbalExist->andWhere('exist_product.variation = order_product.variation ');
        //        $dbalExist->andWhere('exist_product.modification = order_product.modification ');




        $dbal->addSelect('order_product.product AS wb_product_event');
        $dbal->addSelect('order_product.offer AS wb_product_offer');
        $dbal->addSelect('order_product.variation AS wb_product_variation');
        $dbal->addSelect('order_product.modification AS wb_product_modification');

        $dbal->join('ord',
            OrderProduct::class,
            'order_product',
            'order_product.event = ord.event' //.($complete ? ' AND NOT EXISTS('.$dbalExist->getSQL().')' : '')
        );


        //$dbal->addSelect('order_price.price AS order_price');
        $dbal->addSelect('SUM(order_price.total) AS order_total');
        $dbal->leftJoin('order_product',
            OrderPrice::class,
            'order_price',
            'order_price.product = order_product.id'
        );


        /**
         * Продукт
         */

        $dbal->leftJoin('order_product',
            ProductEvent::class,
            'product_event',
            'product_event.id = order_product.product'
        );

        $dbal->leftJoin('order_product',
            ProductInfo::class,
            'product_info',
            'product_info.product = product_event.main'
        );

        if($filter->getCategory())
        {

            $dbal->join('order_product',
                ProductCategory::class,
                'product_category',
                'product_category.event = product_event.id AND product_category.category = :category AND product_category.root = true'
            );

            $dbal->setParameter('category', $filter->getCategory(), CategoryProductUid::TYPE);
        }


        $dbal->addSelect('product_trans.name AS product_name');
        $dbal->leftJoin('order_product',
            ProductTrans::class,
            'product_trans',
            'product_trans.event = order_product.product AND product_trans.local = :local'
        );

        $dbal->bindLocal();


        /*
         * Торговое предложение
         */

        $dbal->addSelect('product_offer.value as product_offer_value');
        $dbal->addSelect('product_offer.postfix as product_offer_postfix');

        $dbal->leftJoin('order_product',
            ProductOffer::class,
            'product_offer',
            'product_offer.id = order_product.offer'
        );


        if(!$search->getQuery() && $filter->getOffer())
        {

            $dbal->andWhere('product_offer.value = :offer');
            $dbal->setParameter('offer', $filter->getOffer());
        }

        $dbal->addSelect('category_offer.reference as product_offer_reference');
        $dbal->leftJoin(
            'product_offer',
            CategoryProductOffers::class,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );


        /*
        * Множественный вариант
        */

        $dbal->addSelect('product_variation.value as product_variation_value');
        $dbal->addSelect('product_variation.postfix as product_variation_postfix');

        $dbal->leftJoin('order_product',
            ProductVariation::class,
            'product_variation',
            'product_variation.id = order_product.variation'
        );

        if(!$search->getQuery() && $filter->getVariation())
        {
            $dbal->andWhere('product_variation.value = :variation');
            $dbal->setParameter('variation', $filter->getVariation());
        }


        /* Тип множественного варианта торгового предложения */
        $dbal->addSelect('category_variation.reference as product_variation_reference');
        $dbal->leftJoin(
            'product_variation',
            CategoryProductVariation::class,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );


        /** Артикул продукта */

        $dbal->addSelect("
					CASE
					   WHEN product_variation.article IS NOT NULL THEN product_variation.article
					   WHEN product_offer.article IS NOT NULL THEN product_offer.article
					   WHEN product_info.article IS NOT NULL THEN product_info.article
					   ELSE NULL
					END AS product_article
				"
        );


        /** Фото продукта */

        $dbal->leftJoin(
            'order_product',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = order_product.product AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = order_product.offer AND product_offer_images.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = order_product.variation AND product_variation_image.root = true'
        );


        $dbal->addSelect("
			CASE
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/',  product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
			CASE
			   WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.ext
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.ext
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.ext
			   ELSE NULL
			END AS product_image_ext
		");

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
			CASE
			   WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.cdn
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.cdn
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.cdn
			   ELSE NULL
			END AS product_image_cdn
		");


        /* Карточка Wildberries */


        //        $dbal->leftJoin('wb_order',
        //            WbProductCardVariation::class,
        //            'wb_card_variation',
        //            'wb_card_variation.barcode = wb_order_event.barcode'
        //        );

        //    $dbal->addSelect('NULL AS wb_order_nomenclature');

        //        $dbal->leftJoin('wb_card_variation',
        //            WbProductCardOffer::class,
        //            'wb_card_offer',
        //            'wb_card_offer.card = wb_card_variation.card AND wb_card_offer.offer =  product_offer.const'
        //        );


        //        $dbal->addSelect('wb_order_stats.analog AS wb_order_analog');
        //        $dbal->addSelect('wb_order_stats.alarm AS wb_order_alarm');
        //        $dbal->addSelect('wb_order_stats.old AS wb_order_old');
        //
        //        $dbal->leftJoin('product_event',
        //            WbOrdersStatistics::class,
        //            'wb_order_stats',
        //            'wb_order_stats.product = product_event.main'
        //        );


        /** ******************** */

        if($complete)
        {


            /** Только товары, которых нет в производстве */

            $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);
            //$dbalExist->select(1);
            $dbalExist->select('exist_part.number');
            $dbalExist->from(ManufacturePartProduct::class, 'exist_product');

            $dbalExist->join('exist_product',
                ManufacturePart::class,
                'exist_part',
                '
                exist_part.event = exist_product.event
            '
            );

            $dbalExist->join('exist_part',
                ManufacturePartEvent::class,
                'exist_product_event',
                '
                exist_product_event.id = exist_part.event AND
                exist_product_event.complete = :complete
            '
            );

            /** Только продукция на указанный завершающий этап */
            $dbal->setParameter('complete', $complete, ManufacturePartComplete::TYPE);

            $dbalExist->andWhere('exist_product_event.status != :status_closed');
            $dbalExist->andWhere('exist_product_event.status != :status_completed');

            /** Только продукция в процессе производства */
            $dbal->setParameter('status_closed', ManufacturePartStatusClosed::STATUS);
            $dbal->setParameter('status_completed', ManufacturePartStatusCompleted::STATUS);

            $dbalExist->andWhere('exist_product.product = order_product.product');
            $dbalExist->andWhere('(order_product.offer IS NULL OR exist_product.offer = order_product.offer)');
            $dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.variation = order_product.variation)');
            //$dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.modification = order_product.modification) ');

            $dbalExist->setMaxResults(1);

            $dbal->addSelect('(SELECT ('.$dbalExist->getSQL().')) AS exist_manufacture');
        }
        else
        {
            $dbal->addSelect('FALSE AS exist_manufacture');
        }

        /* Поиск */
        if($search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($search)
                //->addSearchEqualUid('wb_order.id')
                ->addSearchEqualUid('order_product.product')
                ->addSearchEqualUid('order_product.offer')
                ->addSearchEqualUid('order_product.variation')
                ->addSearchLike('product_trans.name')
                //->addSearchLike('wb_order_event.barcode')
                ->addSearchLike('product_variation.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_info.article');
        }

        //$dbal->orderBy('wb_order_event.created', 'ASC');
        $dbal->orderBy('wb_order_date', 'ASC');

        $dbal->allGroupByExclude('exist_part');

        return $this->paginator->fetchAllAssociative($dbal, 'manufacture-part');

    }


}
