<?php

namespace QueueMaster\Services;

class UserAccessControlService
{
    public function evaluateEnvironmentAccess(string $email): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $domain = $this->extractDomain($normalizedEmail);

        $blockedEmails = $this->parseEnvList('AUTH_BLOCKED_EMAILS');
        $allowedEmails = $this->parseEnvList('AUTH_ALLOWED_EMAILS');
        $blockedDomains = $this->parseEnvList('AUTH_BLOCKED_EMAIL_DOMAINS');
        $allowedDomains = $this->parseEnvList('AUTH_ALLOWED_EMAIL_DOMAINS');

        $hasAllowRules = !empty($allowedEmails) || !empty($allowedDomains);

        if ($normalizedEmail === '') {
            return [
                'allowed' => false,
                'reason' => 'Não foi possível identificar o e-mail retornado pelo Google.',
                'matched_rule' => 'missing_email',
            ];
        }

        if (in_array($normalizedEmail, $blockedEmails, true)) {
            return [
                'allowed' => false,
                'reason' => 'Este e-mail está bloqueado para este ambiente.',
                'matched_rule' => 'blocked_email',
            ];
        }

        if (in_array($normalizedEmail, $allowedEmails, true)) {
            return [
                'allowed' => true,
                'reason' => null,
                'matched_rule' => 'allowed_email',
            ];
        }

        if ($domain !== '' && in_array($domain, $blockedDomains, true)) {
            return [
                'allowed' => false,
                'reason' => 'O domínio deste e-mail está bloqueado para este ambiente.',
                'matched_rule' => 'blocked_domain',
            ];
        }

        if ($domain !== '' && in_array($domain, $allowedDomains, true)) {
            return [
                'allowed' => true,
                'reason' => null,
                'matched_rule' => 'allowed_domain',
            ];
        }

        if ($hasAllowRules) {
            return [
                'allowed' => false,
                'reason' => 'Este e-mail não faz parte da lista liberada para este ambiente.',
                'matched_rule' => 'missing_allow_match',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'matched_rule' => 'no_restriction',
        ];
    }

    public function evaluateSystemAccess(?array $user): array
    {
        if (!$user) {
            return [
                'allowed' => false,
                'reason' => 'Usuário não encontrado.',
                'matched_rule' => 'missing_user',
            ];
        }

        if ($this->isUserBlocked($user)) {
            $reason = trim((string)($user['login_block_reason'] ?? ''));

            return [
                'allowed' => false,
                'reason' => $reason !== ''
                    ? "Seu acesso foi bloqueado por um administrador. Motivo: {$reason}"
                    : 'Seu acesso foi bloqueado por um administrador.',
                'matched_rule' => 'blocked_in_system',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'matched_rule' => 'active_user',
        ];
    }

    public function buildUserAccessSnapshot(?array $user): array
    {
        $email = $this->normalizeEmail((string)($user['email'] ?? ''));
        $environment = $this->evaluateEnvironmentAccess($email);
        $system = $this->evaluateSystemAccess($user);

        return [
            'can_authenticate' => $environment['allowed'] && $system['allowed'],
            'environment' => array_merge($environment, [
                'label' => $environment['allowed'] ? 'Permitido no ambiente' : 'Bloqueado no ambiente',
            ]),
            'system' => array_merge($system, [
                'label' => $system['allowed'] ? 'Acesso interno liberado' : 'Bloqueado no sistema',
                'blocked_at' => $user['login_blocked_at'] ?? null,
                'blocked_reason' => $user['login_block_reason'] ?? null,
                'blocked_by_user_id' => $user['login_blocked_by_user_id'] ?? null,
            ]),
        ];
    }

    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isUserBlocked(?array $user): bool
    {
        if (!$user) {
            return true;
        }

        $blockedAt = trim((string)($user['login_blocked_at'] ?? ''));
        $blockedReason = trim((string)($user['login_block_reason'] ?? ''));
        $blockedByUserId = (int)($user['login_blocked_by_user_id'] ?? 0);

        if ($blockedAt !== '' || $blockedReason !== '' || $blockedByUserId > 0) {
            return true;
        }

        return false;
    }

    private function extractDomain(string $email): string
    {
        $domain = strrchr($email, '@');
        return $domain === false ? '' : ltrim(strtolower($domain), '@');
    }

    private function parseEnvList(string $key): array
    {
        $rawValue = trim((string)($_ENV[$key] ?? ''));
        if ($rawValue === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($item) => strtolower(trim($item)),
            explode(',', $rawValue)
        ))));
    }
}
