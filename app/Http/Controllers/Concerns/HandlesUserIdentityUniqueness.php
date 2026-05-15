<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

trait HandlesUserIdentityUniqueness
{
    protected function duplicateUserMessage(): string
    {
        return 'هذا المستخدم مسجل من قبل';
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, string>
     */
    protected function duplicateUserValidationMessages(array $fields = ['email', 'mobile']): array
    {
        $messages = [];

        foreach ($fields as $field) {
            $messages[$field . '.unique'] = $this->duplicateUserMessage();
        }

        return $messages;
    }

    protected function rethrowAsDuplicateUserValidation(QueryException $exception, array $fields = ['email', 'mobile']): void
    {
        if (!$this->isUniqueConstraintException($exception)) {
            return;
        }

        $error = strtolower($exception->getMessage());
        $messages = [];

        if (in_array('email', $fields, true) && (str_contains($error, 'users.email') || str_contains($error, 'email'))) {
            $messages['email'] = $this->duplicateUserMessage();
        }

        if (in_array('mobile', $fields, true) && (str_contains($error, 'users.mobile') || str_contains($error, 'mobile'))) {
            $messages['mobile'] = $this->duplicateUserMessage();
        }

        if (count($messages) === 0) {
            foreach ($fields as $field) {
                $messages[$field] = $this->duplicateUserMessage();
            }
        }

        throw ValidationException::withMessages($messages);
    }

    private function isUniqueConstraintException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19', '2067'], true);
    }
}
