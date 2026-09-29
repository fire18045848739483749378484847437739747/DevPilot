namespace Database\Factories;

use App\Models\ServiceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceGroupFactory extends Factory

    protected $model =l ServiceGroup. class

    
    public function definition(): array
    
        $name = $this->faker->unique()->words(2, true);

        return 
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence()
            'color' => 'primary' 
            'start_delay_seconds' => 0
        
    }
}
