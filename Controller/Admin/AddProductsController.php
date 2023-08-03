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

namespace BaksDev\Wildberries\Manufacture\Controller\Admin;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsForm;
use BaksDev\Manufacture\Part\UseCase\Admin\AddProduct\ManufacturePartProductsHandler;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByUidInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Wildberries\Manufacture\Type\Marketplace\ManufacturePartMarketplaceWildberries;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_WB_MANUFACTURE_ADD')]
final class AddProductsController extends AbstractController
{
    #[Route('/admin/wb/manufacture/product/add/{total}', name: 'admin.add', methods: ['GET', 'POST'])]
    public function add(
        Request $request,
        ManufacturePartProductsHandler $ManufacturePartProductHandler,
        ProductDetailByUidInterface $productDetail,
        CentrifugoPublishInterface $CentrifugoPublish,
        ?int $total = null,
        #[ParamConverter(ProductEventUid::class)] $product = null,
        #[ParamConverter(ProductOfferUid::class)] $offer = null,
        #[ParamConverter(ProductVariationUid::class)] $variation = null,
        #[ParamConverter(ProductModificationUid::class)] $modification = null,
    ): Response
    {

        $ManufacturePartProductDTO = new ManufacturePartProductsDTO($this->getProfileUid());

        if($request->isMethod('GET'))
        {
            $ManufacturePartProductDTO
                ->setProduct($product)
                ->setOffer($offer)
                ->setVariation($variation)
                ->setModification($modification)
                ->setTotal($total);
        }

        // Форма
        $form = $this->createForm(ManufacturePartProductsForm::class, $ManufacturePartProductDTO, [
            'action' => $this->generateUrl('WildberriesManufacture:admin.add'),
        ]);

        $form->handleRequest($request);

        $details = $productDetail->fetchProductDetailByEventAssociative(
            $ManufacturePartProductDTO->getProduct(),
            $ManufacturePartProductDTO->getOffer(),
            $ManufacturePartProductDTO->getVariation(),
            $ManufacturePartProductDTO->getModification()
        );

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_products'))
        {
            $HandlerResult = $ManufacturePartProductHandler
                ->handle($ManufacturePartProductDTO,
                    new ManufacturePartMarketplaceWildberries()
                );

            /** Если была открыта новая партия - делаем редирект */
            if($HandlerResult instanceof ManufacturePart)
            {
                $this->addFlash(
                    'admin.page.new',
                    'admin.success.new',
                    'admin.manufacture.part'
                );

                return $this->redirectToRoute('WildberriesManufacture:admin.index');
            }

            /** Если был добавлен продукт в открытую партию отправляем сокет */
            if($HandlerResult instanceof ManufacturePartProduct)
            {
                $details['product_total'] = $ManufacturePartProductDTO->getTotal();

                $CentrifugoPublish
                    // HTML продукта
                    ->addData(['product' => $this->render(['product' => $details,],
                        file: 'centrifugo.html.twig')->getContent()])
                    // количество для суммы всех товаров
                    ->addData(['total' => $ManufacturePartProductDTO->getTotal()])
                    ->addData(['identifier' => $ManufacturePartProductDTO->getIdentifier()])
                    ->send((string) $HandlerResult->getEvent());

                $return = $this->addFlash(
                    type: 'admin.page.add',
                    message: 'admin.success.add',
                    domain: 'admin.manufacture.part',
                    status: $request->isXmlHttpRequest() ? 200 : 302 // не делаем редирект в случае AJAX
                );

                return $request->isXmlHttpRequest() ? $return : $this->redirectToRoute('WildberriesManufacture:admin.index');
            }

            $this->addFlash(
                'admin.page.add',
                'admin.danger.add',
                'admin.wb.manufacture',
                $HandlerResult);

            return $this->redirectToRoute('WildberriesManufacture:admin.index');
        }

        return $this->render([
            'form' => $form->createView(),
            'product' => $details
        ]);
    }
}