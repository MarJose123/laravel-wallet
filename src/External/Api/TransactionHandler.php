<?php

declare(strict_types=1);

namespace Bavix\Wallet\External\Api;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Bavix\Wallet\Services\AssistantServiceInterface;
use Bavix\Wallet\Services\AtomicServiceInterface;
use Bavix\Wallet\Services\PrepareServiceInterface;
use Bavix\Wallet\Services\TransactionServiceInterface;

/**
 * @internal
 */
final class TransactionHandler implements TransactionHandlerInterface
{
    public function __construct(
        private TransactionServiceInterface $transactionService,
        private AssistantServiceInterface $assistantService,
        private PrepareServiceInterface $prepareService,
        private AtomicServiceInterface $atomicService
    ) {
    }

    public function apply(array $objects): array
    {
        $wallets = $this->assistantService->getUniqueWallets(
            array_map(static fn (array $object): Wallet => $object['wallet'], $objects),
        );

        $values = array_map(
            fn (array $object) => match ($object['type']) {
                Transaction::TYPE_DEPOSIT => $this->prepareService->deposit(
                    $object['wallet'],
                    $object['amount'],
                    $object['meta'] ?? null,
                    $object['confirmed'] ?? true,
                ),
                Transaction::TYPE_WITHDRAW => $this->prepareService->withdraw(
                    $object['wallet'],
                    $object['amount'],
                    $object['meta'] ?? null,
                    $object['confirmed'] ?? true,
                )
            },
            $objects
        );

        return $this->atomicService->blocks(
            $wallets,
            fn () => $this->transactionService->apply($wallets, $values),
        );
    }
}
