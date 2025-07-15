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

namespace BaksDev\Wildberries\Manufacture\Controller\Admin;

use BaksDev\Centrifugo\Services\Token\TokenUserGenerator;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Repository\OpenManufacturePart\OpenManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\OpenManufacturePart\OpenManufacturePartResult;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterForm;
use BaksDev\Wildberries\Manufacture\Repository\AllWbOrdersAnalytics\AllWbOrdersAnalyticsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_WB_MANUFACTURE_FBO')]
final class FboController extends AbstractController
{
    /**
     * Список товаров на складах Wildberries, требующих новых поставок на склад
     */
    #[Route('/admin/wb/fbo/manufacture/{page<\d+>}', name: 'admin.fbo', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AllWbOrdersAnalyticsInterface $allWbOrdersAnalyticsRepository,
        OpenManufacturePartInterface $openManufacturePart,
        TokenUserGenerator $tokenUserGenerator,
        int $page = 0,
        #[MapQueryParameter] int $days = 30,
    ): Response
    {
        $search = new SearchDTO();

        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $search,
                options: ['action' => $this->generateUrl('wildberries-manufacture:admin.fbo')]
            )
            ->handleRequest($request);

        /**
         * Получаем активную открытую поставку ответственного (Независимо от авторизации)
         */
        $opens = $openManufacturePart
            ->forFixed($this->getCurrentProfileUid())
            ->find();

        /**
         * Фильтр продукции
         */
        $filter = new ProductFilterDTO()->hiddenMaterials();

        if($opens instanceof OpenManufacturePartResult)
        {
            /* Если открыт производственный процесс - жестко указываем категорию и скрываем выбор */
            $filter->setCategory(new CategoryProductUid($opens->getCategoryId(), $opens->getCategoryName()));
            $filter->categoryInvisible();
        }

        $filterForm = $this
            ->createForm(
                type: ProductFilterForm::class,
                data: $filter,
                options: ['action' => $this->generateUrl('wildberries-manufacture:admin.fbo')]
            )
            ->handleRequest($request);

        /**
         * Получаем данные о товарах: количество на складе WB, среднее количество заказов в день и количество дней,
         * и др., сортируя в порядке убывания количества продукта, необходимого для пополнения
         */
        $WbOrdersAnalytics = $allWbOrdersAnalyticsRepository
            ->days($days)
            ->search($search)
            ->filter($filter)
            ->findPaginator($opens ? $opens->getComplete() : false);

        return $this->render([
            'opens' => $opens,
            "query" => $WbOrdersAnalytics,
            'search' => $searchForm->createView(),
            'filter' => $filterForm->createView(),
            'token' => $tokenUserGenerator->generate($this->getUsr()),
            'current_profile' => $this->getCurrentProfileUid(),
        ]);
    }
}
