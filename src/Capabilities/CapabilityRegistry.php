<?php

declare(strict_types=1);

namespace Nbcom\Bitrix24\Capabilities;

use Illuminate\Contracts\Config\Repository;

final class CapabilityRegistry
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return array<string, list<string>>
     */
    public function featureMethods(): array
    {
        $features = $this->config->get('bitrix24.features', []);
        $features = is_array($features) ? $features : [];
        $methods = [];

        if ((bool) ($features['users'] ?? true)) {
            $methods['users.get'] = ['user.get'];
            $methods['users.search'] = ['im.search.user.list', 'user.search'];
            $methods['users.current'] = ['user.current'];
        }

        if ((bool) ($features['departments'] ?? true)) {
            $methods['departments.get'] = ['department.get'];
            $methods['departments.search'] = ['im.search.department.list', 'department.get'];
            $methods['departments.employees'] = ['im.department.employees.get', 'user.get'];
        }

        $notifications = $features['notifications'] ?? [];

        if (is_array($notifications)) {
            if ((bool) ($notifications['system'] ?? true)) {
                $methods['notifications.system'] = ['im.notify.system.add', 'im.notify'];
            }

            if ((bool) ($notifications['personal'] ?? true)) {
                $methods['notifications.personal'] = ['im.notify.personal.add', 'im.notify'];
            }

            if ((bool) ($notifications['chat'] ?? true)) {
                $methods['notifications.chat'] = ['im.message.add'];
            }
        }

        if ((bool) ($features['live_feed'] ?? false)) {
            $methods['live_feed.post'] = ['log.blogpost.add'];
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        $features = $this->config->get('bitrix24.features', []);
        $features = is_array($features) ? $features : [];
        $scopes = [];

        if ((bool) ($features['users'] ?? true)) {
            $scopes[] = 'user';
        }

        if ((bool) ($features['departments'] ?? true)) {
            $scopes[] = 'department';
        }

        if ((bool) ($features['users'] ?? true) || (bool) ($features['departments'] ?? true)) {
            $scopes[] = 'im';
        }

        $notifications = $features['notifications'] ?? [];

        if (is_array($notifications) && (
            (bool) ($notifications['system'] ?? true)
            || (bool) ($notifications['personal'] ?? true)
            || (bool) ($notifications['chat'] ?? true)
        )) {
            $scopes[] = 'im';
        }

        if ((bool) ($features['live_feed'] ?? false)) {
            $scopes[] = 'log';
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @return list<string>
     */
    public function allMethods(): array
    {
        $methods = [];

        foreach ($this->featureMethods() as $alternatives) {
            foreach ($alternatives as $method) {
                $methods[] = $method;
            }
        }

        return array_values(array_unique($methods));
    }

    public function primaryMethod(string $feature): string
    {
        $methods = $this->featureMethods()[$feature] ?? null;

        if ($methods === null || $methods === []) {
            throw new \InvalidArgumentException("Unknown Bitrix24 feature [{$feature}].");
        }

        return $methods[0];
    }
}
