<?php

namespace App\Services;

use App\Enums\RestartPolicy;
use App\Models\Service;
use App\Models\ServiceGroup;

/**
 * Cria os serviços de apoio de um projeto Laravel (`queue:work`,
 * `schedule:work`, `pail`) a partir de um serviço já cadastrado.
 *
 * O diretório, o ambiente e as variáveis vêm do serviço de origem — é sempre o
 * mesmo projeto — e a ordem de subida deixa os companheiros depois dele.
 */
class CompanionServiceFactory
{
    public function __construct(protected ProcessManager $manager)
    {
    }

    /**
     * @param  list<string>  $keys  Chaves de DirectoryBrowser::laravelCompanions().
     * @return array{created: int, skipped: int, message: string}
     */
    public function create(Service $source, array $keys, bool $groupTogether = true, bool $startNow = false): array
    {
        $available = DirectoryBrowser::laravelCompanions();
        $keys = array_values(array_intersect($keys, array_keys($available)));

        if ($keys === []) {
            return ['created' => 0, 'skipped' => 0, 'message' => __('services.companions.none_selected')];
        }

        $group = $groupTogether ? $this->resolveGroup($source) : null;
        $created = 0;
        $skipped = 0;
        $order = $this->nextBootOrder($source, $group);

        foreach ($keys as $key) {
            $companion = $available[$key];

            if ($this->exists($source, $companion['command'])) {
                $skipped++;

                continue;
            }

            $service = new Service([
                'name' => $source->name . ' — ' . $companion['label'],
                'description' => __('services.companions.created_from', ['name' => $source->name]),
                'command' => $companion['command'],
                'working_directory' => $source->working_directory,
                'environment' => $source->environment,
                'env_vars' => $source->env_vars,
                'service_group_id' => $group?->getKey(),
                'boot_order' => $order++,
                'auto_start_on_boot' => $source->auto_start_on_boot,
                'auto_restart' => $companion['auto_restart'],
                'restart_policy' => RestartPolicy::OnFailure,
                'max_restarts' => 5,
                'stop_timeout' => 10,
                'alerts_enabled' => true,
            ]);

            $service->updateSlug();
            $service->save();

            $created++;

            if ($startNow) {
                $this->manager->start($service);
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'message' => __('services.companions.result', ['created' => $created, 'skipped' => $skipped]),
        ];
    }

    /**
     * Usa o grupo do serviço de origem; se ele não tiver, cria um com o nome do
     * projeto e coloca a origem lá dentro — o stack só faz sentido junto.
     */
    protected function resolveGroup(Service $source): ServiceGroup
    {
        if ($source->group) {
            return $source->group;
        }

        $group = ServiceGroup::findOrCreateByName($source->name);

        $source->update(['service_group_id' => $group->getKey(), 'boot_order' => 0]);

        return $group;
    }

    protected function nextBootOrder(Service $source, ?ServiceGroup $group): int
    {
        if (! $group) {
            return (int) $source->boot_order + 1;
        }

        return ((int) $group->services()->max('boot_order')) + 1;
    }

    /**
     * Evita duplicar: mesmo comando na mesma pasta já é o mesmo serviço.
     */
    protected function exists(Service $source, string $command): bool
    {
        return Service::query()
            ->where('working_directory', $source->working_directory)
            ->where('command', $command)
            ->exists();
    }
}
