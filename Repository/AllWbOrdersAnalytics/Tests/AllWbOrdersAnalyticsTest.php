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

namespace BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\AllWbOrdersAnalyticsInterface;

/**
 * @group wildberries-manufacture
 * @group wildberries-manufacture-rep
 */
final class AllWbOrdersAnalyticsTest extends KernelTestCase
{
    public function testRepository()
    {
        /** @var AllWbOrdersAnalyticsInterface $AllWbOrdersAnaltics */
        $AllWbOrdersAnaltics = self::getContainer()->get(AllWbOrdersAnalyticsInterface::class);
        $paginator = $AllWbOrdersAnaltics->findPaginator();

        $data = $paginator->getData();

        if(empty($data))
        {
            self::assertTrue(true);
            return;
        }

        $array_keys = [
            "invariable",
            "average",
            "product_trans_name",
            "product_offer_value",
            "product_variation_value",
            "product_modification_value",
            "product_image",
            "product_image_ext",
            "product_article",
            "quantity",
            "product_image_cdn",
            "product_url",
            "category_url"
        ];

        $current = current($data);

        foreach($current as $key => $value)
        {
            self::assertTrue(in_array($key, $array_keys), sprintf('Появился новый ключ %s', $key));
        }

        foreach($array_keys as $key)
        {
            self::assertTrue(array_key_exists($key, $current));
        }
    }
}