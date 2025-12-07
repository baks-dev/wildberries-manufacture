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

namespace BaksDev\Wildberries\Manufacture\Commands;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Manufacture\Messenger\Schedules\GetWbOrders\GetWbOrdersMessage;
use BaksDev\Wildberries\Repository\AllProfileToken\AllProfileWildberriesTokenInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Добавляет данные о последних заказах на WB
 */
#[AsCommand(
    name: 'baks:wildberries-manufacture:orders-last',
    description: 'Получает данные о последних заказах на WB'
)]
final class GetWbOrdersLastCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MessageDispatchInterface $messageDispatch,
        private readonly AllProfileWildberriesTokenInterface $allWbTokens,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        /** Идентификаторы профилей пользователей, у которых есть активный токен WB */
        $profiles = $this->allWbTokens
            ->onlyActiveToken()
            ->findAll();

        $profiles = iterator_to_array($profiles);

        $helper = $this->getHelper('question');

        /**
         * Интерактивная форма списка профилей
         */

        $questions[] = 'Все';

        foreach($profiles as $quest)
        {
            $questions[] = $quest->getAttr();
        }

        $questions['+'] = 'Выполнить все асинхронно';
        $questions['-'] = 'Выйти';

        $question = new ChoiceQuestion(
            'Профиль пользователя (Ctrl+C чтобы выйти)',
            $questions,
            '0'
        );

        $key = $helper->ask($input, $output, $question);

        /**
         *  Выходим без выполненного запроса
         */

        if($key === '-' || $key === 'Выйти')
        {
            return Command::SUCCESS;
        }

        $question = new Question('За сколько дней необходимо обновить информацию? ');

        // Устанавливаем валидатор для проверки, что введено число
        $question->setValidator(function ($answer) {
            if ($answer !== (string)(int)$answer || $answer <= 0) {
                throw new RuntimeException('Пожалуйста, введите корректное целое число.');
            }
            return (int)$answer;
        });

        $days = $helper->ask($input, $output, $question);

        if($key === '+' || $key === '0' || $key === 'Все')
        {
            /** @var UserProfileUid $profile */
            foreach($profiles as $profile)
            {
                $this->update($profile, $days, $key === '+');
            }

            $this->io->success('Данные о заказах успешно обновлены');
            return Command::SUCCESS;
        }

        $UserProfileUid = null;

        foreach($profiles as $profile)
        {
            if($profile->getAttr() === $questions[$key])
            {
                /* Присваиваем профиль пользователя */
                $UserProfileUid = $profile;
                break;
            }
        }

        if($UserProfileUid)
        {
            $this->update($UserProfileUid, $days);

            $this->io->success('Данные о заказах успешно обновлены');
            return Command::SUCCESS;
        }

        $this->io->success('Профиль пользователя не найден');
        return Command::SUCCESS;
    }

    private function update(UserProfileUid|string $profile, int $days, bool $async = false): void
    {
        $this->io->note(sprintf('Обновляем профиль %s', $profile->getAttr()));
        $this->messageDispatch->dispatch(
            message: new GetWbOrdersMessage($profile, $days),
            transport: $async === true ? $profile.'-low' : null
        );
    }
}