<?php

declare(strict_types=1);

namespace App\Services;

use App\Accounts\AccountServices;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ApprovalInbox
{
    /** @param list<AccountServices> $accounts
     * @return array{rows: list<array<string, mixed>>, errors: array<string, string>}
     */
    public function fetch(array $accounts): array
    {
        $errors = [];
        $responses = Http::pool(function (Pool $pool) use ($accounts, &$errors): void {
            foreach ($accounts as $services) {
                try {
                    $services->api->queuePending($pool);
                } catch (Throwable $e) {
                    $errors[$services->account->id] = $this->message($e);
                }
            }
        }, concurrency: 4);
        $rows = [];
        foreach ($accounts as $services) {
            if (isset($errors[$services->account->id])) {
                continue;
            }
            try {
                $response = $responses[$services->account->id] ?? null;
                if (! $response instanceof Response) {
                    throw new ApiFailure(0);
                }
                foreach ($services->api->pendingFromResponse($response) as $approval) {
                    $id = $approval['id'] ?? null;
                    if (is_string($id) && $id !== '') {
                        $rows[] = array_merge($approval, [
                            'account_id' => $services->account->id,
                            'account_label' => $services->account->label,
                            'row_key' => $services->account->id.':'.$id,
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $errors[$services->account->id] = $this->message($e);
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function message(Throwable $error): string
    {
        return $error instanceof ApiFailure ? $error->getMessage()
            : 'Could not reach your Cbox ID host. Check your connection and try again.';
    }
}
