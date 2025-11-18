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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersGroup;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

/** @see AllWbOrdersManufactureResult */
final class AllWbOrdersManufactureResult
{

    private array|null|false $product_statistics_decode = null;

    public function __construct(
        private readonly string $order_data, // 2025-11-16 04:04:04" Дата и время последнего заказа
        private readonly string $product, // 0198375b-a011-766f-b3da-cf297816b563"
        private readonly string $product_name, // Душнила"


        private readonly string $order_total, //  72

        private readonly ?string $card_article, // FSWHITE-0236"
        private readonly string $product_article, // FSWHITE-0236-02-M"


        private readonly ?string $offer, // 0198375b-a015-742a-a59a-7f66cb7970e3"
        private readonly ?string $product_offer_const, // 000000"
        private readonly ?string $product_offer_value, // 000000"
        private readonly ?string $product_offer_postfix, //  null
        private readonly ?string $product_offer_reference, // color_type"

        private readonly ?string $variation, // 0198375b-a01e-777e-bcdf-79f4a7932f73"
        private readonly ?string $product_variation_const, // M"
        private readonly ?string $product_variation_value, // M"
        private readonly ?string $product_variation_postfix, //  null
        private readonly ?string $product_variation_reference, // size_clothing_type"

        private readonly ?string $modification, // , //  null
        private readonly ?string $product_modification_const, //  null
        private readonly ?string $product_modification_value, //  null
        private readonly ?string $product_modification_postfix, //  null
        private readonly ?string $product_modification_reference, //  null

        private readonly ?string $product_image, // /upload/product_offer_images/b8e15fb0b5dfdb0843ed1a5d6b2a871a"
        private readonly ?string $product_image_ext, // webp"
        private readonly ?bool $product_image_cdn, //  true


        private readonly ?int $stock_available, //  null /** Сверх наличие на складе */

        private readonly ?int $analog, //  7
        private readonly ?int $orders_alarm, //  3
        private readonly ?string $product_statistics,
        // [{"offer": "0194ce79-4c3e-719e-acef-1cfbf7db6628", "value": 3, "variation": "0194ce79-4cd0-743d-9d6b-b5853c6c7af9", "invariable": "0194ce79-50d7-7d12-8df6-e9937c586e90", "modification": null}] ◀"
        private readonly ?string $number, //  номер партии

        private readonly ?string $product_invariable, //  номер партии

    ) {}

    public function getOrderData(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->order_data);
    }

    public function getProductEvent(): ProductEventUid
    {
        return new ProductEventUid($this->product);
    }

    public function getProductName(): string
    {
        return $this->product_name;
    }


    public function getAnalog(): ?int
    {
        return $this->analog;
    }

    public function getAlarm(): ?int
    {
        return $this->alarm;
    }

    public function getCardArticle(): ?string
    {
        return $this->card_article;
    }

    public function getProductArticle(): string
    {
        return $this->product_article;
    }

    /**
     * ProductOffer
     */


    public function getProductOfferId(): ProductOfferUid|false
    {
        return $this->offer ? new ProductOfferUid($this->offer) : false;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }

    public function getOfferArticle(): string
    {
        if(empty($this->offer))
        {
            return $this->product_article;
        }

        $parts = explode('-', $this->getVariationArticle());

        array_pop($parts);

        return implode('-', $parts);
    }

    /**
     * ProductVariation
     */

    public function getProductVariationId(): ProductVariationUid|false
    {
        return $this->variation ? new ProductVariationUid($this->variation) : false;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }

    public function getVariationArticle(): string
    {
        if(empty($this->modification))
        {
            return $this->product_article;
        }

        $parts = explode('-', $this->getModificationArticle());

        array_pop($parts);

        return implode('-', $parts); // Вывод: FSWOMEN-0983-02
    }


    /**
     * ProductModification
     */

    public function getProductModificationId(): ProductModificationUid|false
    {
        return $this->modification ? new ProductModificationUid($this->modification) : false;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }

    public function getModificationArticle(): string
    {
        if(empty($this->modification))
        {
            return $this->product_article;
        }

        $parts = explode('-', $this->product_article);

        array_pop($parts);

        return implode('-', $parts); // Вывод: FSWOMEN-0983-02
    }


    /**
     * ProductImage
     */
    public function getProductImage(): ?string
    {
        return $this->product_image;
    }

    public function getProductImageExt(): ?string
    {
        return $this->product_image_ext;
    }

    public function getProductImageCdn(): bool
    {
        return $this->product_image_cdn === true;
    }

    public function getStockAvailable(): ?int
    {
        return $this->stock_available;
    }

    public function getOrdersAlarm(): ?int
    {
        $stats = $this->getProductStatistics();

        if(false === $stats)
        {
            return 0;
        }

        $filter = array_filter(
            $stats,
            function($element) {
                return $element->invariable === $this->product_invariable;
            });

        if(empty($filter))
        {
            return 0;
        }

        return array_sum(array_column($filter, 'value'));
    }

    public function getProductStatistics(): array|false
    {
        if(is_null($this->product_statistics_decode))
        {
            if(empty($this->product_statistics))
            {
                $this->product_statistics_decode = false;
                return false;
            }

            if(false === json_validate($this->product_statistics))
            {
                $this->product_statistics_decode = false;
                return false;
            }

            $decode = json_decode($this->product_statistics, false, 512, JSON_THROW_ON_ERROR);
            $this->product_statistics_decode = empty($decode) ? false : $decode;

        }

        return $this->product_statistics_decode;

    }


    public function getOfferStatistic(): int
    {
        $stats = $this->getProductStatistics();

        if(false === $stats)
        {
            return 0;
        }

        $filter = array_filter(
            $stats,
            function($element) {
                return $element->offer === $this->product_offer_const;
            });

        if(empty($filter))
        {
            return 0;
        }

        return array_sum(array_column($filter, 'value'));
    }

    public function getVariationStatistic(): int
    {
        $stats = $this->getProductStatistics();

        if(false === $stats)
        {
            return 0;
        }

        $filter = array_filter(
            $stats,
            function($element) {
                return $element->variation === $this->product_variation_const;
            });

        if(empty($filter))
        {
            return 0;
        }

        return array_sum(array_column($filter, 'value'));
    }

    public function getModificationStatistic(): int
    {
        $stats = $this->getProductStatistics();

        if(false === $stats)
        {
            return 0;
        }

        $filter = array_filter(
            $stats,
            function($element) {
                return $element->modification === $this->product_modification_const;
            });

        if(empty($filter))
        {
            return 0;
        }

        return array_sum(array_column($filter, 'value'));
    }

    public function getOrderTotal(): int
    {
        $decode = json_decode($this->order_total, false, 512, JSON_THROW_ON_ERROR);

        if(empty($decode))
        {
            return 0;
        }

        return array_sum(array_column($decode, 'total'));
    }

    public function getNumber(): string|false
    {
        return $this->number ?: false;
    }

    public function getInvariable(): string
    {
        return $this->product_invariable;
    }
}