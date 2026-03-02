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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class AllWbStocksBarcodesResult
{
    public function __construct(
        private ?string $barcode,
        private ?string $barcodes,
        private int $quantity,
    ) {}

    public function getBarcode(): ?string
    {
        return true === empty($this->barcode) ? null : $this->barcode;
    }

    /**
     * @return array<int, string>|null
     */
    public function getBarcodes(): array|null
    {
        if(is_null($this->barcodes))
        {
            return null;
        }

        if(false === json_validate($this->barcodes))
        {
            return null;
        }

        $barcodes = json_decode($this->barcodes, true, 512, JSON_THROW_ON_ERROR);

        if(true === empty(current($barcodes)))
        {
            return null;
        }

        return $barcodes;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}