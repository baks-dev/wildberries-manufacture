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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class AllWbOrdersAnalyticsResult
{
    public function __construct(
        private string $invariable, // " => "0195f0c4-4f99-713b-8f79-d0101f455c51"
        private int $average, // " => 3
        private int $needed_amount, //" => 42
        private int $quantity, //" => 0

        private string $product_id, //" => "0195f0c4-4f14-70b1-b7e8-0e46bf299b88"
        private string $product_event, //" => "0195f0c4-51ac-749f-a9fa-3aebea522fdb"
        private string $product_event_id, //" => "0195f0c4-51ac-749f-a9fa-3aebea522fdb"


        private string $product_url, //" => "67ebb75fa29d1"
        private string $product_trans_name, //" => "Оверсайз хлопковая с принтом булавка"
        private string $product_article, //" => "FBWOMAN-0179-02-XL"

        private ?string $product_offer_value, //" => "000000"
        private ?string $product_offer_id, //" => "0195f0c4-51ae-7cda-b6eb-507983f63324"

        private ?string $product_variation_value, //" => "XL"
        private ?string $product_variation_id, //" => "0195f0c4-51b0-775d-bf34-6f7f6fed7cb7"

        private ?string $product_modification_value, //" => null
        private ?string $product_modification_id, //" => null

        private ?string $product_image, //" => "/upload/product_offer_images/7b4b268344fc3a8404556e5bdf35f469"
        private ?string $product_image_ext, //" => "webp"
        private ?string $product_image_cdn, //" => false

        private ?string $category_url, //" => "futbolki"
        private ?string $category_name, //" => "Футболки"

        private ?string $order_total, //" => null
        private bool|string $exist_manufacture, //" => false

    ) {}

    /**
     * Invariable
     */
    public function getInvariable(): ProductInvariableUid
    {
        return new ProductInvariableUid($this->invariable);
    }

    public function getAverage(): int
    {
        return $this->average;
    }

    public function getNeededAmount(): int
    {
        return $this->needed_amount;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function recommended(): int
    {
        return $this->average * 14 - $this->quantity;
    }

    public function getCategoryUrl(): ?string
    {
        return $this->category_url;
    }

    public function getCategoryName(): ?string
    {
        return $this->category_name;
    }

    public function getOrderTotal(): ?string
    {
        return $this->order_total;
    }

    public function isExistManufacture(): bool
    {
        return $this->exist_manufacture;
    }


    public function getProductId(): ProductUid
    {
        return new ProductUid($this->product_id);
    }

    public function getProductEvent(): ProductEventUid
    {
        return new ProductEventUid($this->product_event);
    }

    public function getProductUrl(): string
    {
        return $this->product_url;
    }

    public function getProductName(): string
    {
        return $this->product_trans_name;
    }

    public function getProductArticle(): string
    {
        return $this->product_article;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferId(): ?ProductOfferUid
    {
        return $this->product_offer_id ? new ProductOfferUid($this->product_offer_id) : null;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationId(): ?ProductVariationUid
    {
        return $this->product_variation_id ? new ProductVariationUid($this->product_variation_id) : null;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationId(): ?ProductModificationUid
    {
        return $this->product_modification_id ? new ProductModificationUid($this->product_modification_id) : null;
    }


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

}