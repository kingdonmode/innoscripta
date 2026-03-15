<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id'  => md5($this->faker->unique()->uuid()),
            'source'       => $this->faker->randomElement(['newsapi', 'guardian', 'nytimes']),
            'title'        => $this->faker->sentence(6),
            'description'  => $this->faker->paragraph(),
            'content'      => $this->faker->paragraphs(3, true),
            'url'          => $this->faker->unique()->url(),
            'image_url'    => null,
            'author'       => $this->faker->name(),
            'category'     => $this->faker->randomElement(['technology', 'world', 'sports', 'science', 'business']),
            'source_name'  => $this->faker->randomElement(['NewsAPI', 'The Guardian', 'The New York Times']),
            'published_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
