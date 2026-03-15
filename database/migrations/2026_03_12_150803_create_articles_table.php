<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->index();
            $table->string('source', 20)->index(); // 'newsapi', 'nytimes', 'guardian'
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('url')->unique();
            $table->string('image_url')->nullable();
            $table->string('author')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->string('source_name')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamps();
            $table->unique(['source', 'external_id'], 'articles_source_external_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
