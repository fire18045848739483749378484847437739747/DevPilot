<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Database\Factories\ServiceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Perfil de serviços que sobem e descem juntos (ex.: app + queue + vite).
 *
 * A ordem de subida é `services.boot_order` crescente; a de parada é a inversa.
 */
#[Fillable(['name', 'slug', 'description', 'color', 'start_delay_seconds'])]
class ServiceGroup extends Model
{
    /** @use HasFactory<ServiceGroupFactory> */
    use HasFactory;

    protected $table = 'service_groups';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_delay_seconds' => 'integer',
        ];
    }

    /**
     * O formulário não expõe o slug; ele sai do nome. Feito no model (e não na
     * página) para valer também nos grupos criados pelos atalhos e pelo campo
     * "criar grupo" do formulário de serviço.
     */
    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            if (blank($group->slug)) {
                $group->updateSlug();
            }
        });
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Membros na ordem de subida.
     *
     * @return HasMany<Service, $this>
     */
    public function orderedServices(): HasMany
    {
        return $this->services()->orderBy('boot_order')->orderBy('name');
    }

    public function runningCount(): int
    {
        return $this->services()->where('status', ServiceStatus::Running)->count();
    }

    public function updateSlug(): void
    {
        $base = Str::slug($this->name);
        $slug = $base;
        $i = 2;

        while (self::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base . '-' . $i++;
        }

        $this->slug = $slug;
    }

    /**
     * Devolve o grupo com este nome, criando-o se ainda não existir. Usado pelos
     * atalhos que criam serviços companheiros de um projeto.
     */
    public static function findOrCreateByName(string $name): self
    {
        $group = self::query()->where('name', $name)->first();

        if ($group) {
            return $group;
        }

        return self::create(['name' => $name]);
    }
}
