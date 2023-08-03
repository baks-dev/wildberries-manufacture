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

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ExistManufacturePart\ExistManufacturePartByActionInterface;
use BaksDev\Manufacture\Part\Type\Marketplace\ManufacturePartMarketplace;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartForm;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use BaksDev\Wildberries\Manufacture\Type\Marketplace\ManufacturePartMarketplaceWildberries;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_WB_MANUFACTURE_NEW')]
final class NewController extends AbstractController
{
    #[Route('/admin/wb/manufacture/new', name: 'admin.newedit.new', methods: ['GET', 'POST'])]
    public function news(
        Request $request,
        ManufacturePartHandler $ManufacturePartHandler,
        ExistManufacturePartByActionInterface $existManufacturePartByAction
    ): Response
    {
        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartDTO
            ->setProfile($this->getProfileUid())
            ->setMarketplace(new ManufacturePartMarketplace(ManufacturePartMarketplaceWildberries::class));


        // Форма
        $form = $this->createForm(ManufacturePartForm::class, $ManufacturePartDTO, [
            'action' => $this->generateUrl('WildberriesManufacture:admin.newedit.new'),
        ]);
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part'))
        {

            /**
             * Проверяем, имеется ли открытый новый процесс на указанную категорию
             */
            if(
                $existManufacturePartByAction->existByProfileAction(
                    $ManufacturePartDTO->getProfile(),
                    $ManufacturePartDTO->getAction(),
                    $ManufacturePartDTO->getMarketplace()
                )
            )
            {

                $this->addFlash
                (
                    'admin.page.new',
                    'admin.danger.exist',
                    'admin.wb.manufacture'
                );

                return $this->redirectToReferer();
            }

            $ManufacturePart = $ManufacturePartHandler->handle($ManufacturePartDTO);

            if($ManufacturePart instanceof ManufacturePart)
            {
                $this->addFlash(
                    'admin.page.new',
                    'admin.success.new',
                    'admin.wb.manufacture'
                );

                return $this->redirectToRoute('WildberriesManufacture:admin.index');
            }

            $this->addFlash
            (
                'admin.page.new',
                'admin.danger.new',
                'admin.wb.manufacture',
                $ManufacturePart
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}